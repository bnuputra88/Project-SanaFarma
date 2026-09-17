<?php
/**
 * Authentication: web session + API JWT, brute-force lockout, login history, password reset, concurrent-session control.
 */
class Auth_service
{
    private $users;
    private $auth;
    private $settings;
    private $audit;
    private $ctx;
    private $db;

    public function __construct(User_repository $users, Auth_repository $auth, Setting_service $settings, Audit_service $audit, Request_context $ctx, Db $db)
    {
        $this->users = $users;
        $this->auth = $auth;
        $this->settings = $settings;
        $this->audit = $audit;
        $this->ctx = $ctx;
        $this->db = $db;
    }

    /** @return array user row (without hash). @throws Authentication_exception */
    public function authenticate(string $identifier, string $password, string $channel = 'web'): array
    {
        $identifier = trim($identifier);
        $bucket = $this->ctx->ip . ':' . strtolower($identifier);
        $maxAttempts = (int) Env::get('LOGIN_MAX_ATTEMPTS', 5);
        $lockMinutes = (int) Env::get('LOGIN_LOCKOUT_MINUTES', 15);

        $attempt = $this->auth->getAttempt($bucket);
        if ($attempt && $attempt['locked_until'] && strtotime($attempt['locked_until']) > time()) {
            $this->history(null, $identifier, 'LOCKED', $channel);
            throw new Authentication_exception(sprintf(lang('login_locked'), max(1, (int) ceil((strtotime($attempt['locked_until']) - time()) / 60))));
        }

        $user = $this->users->findForLogin($identifier);
        if (!$user || !Password_policy::verify($password, $user['password_hash'])) {
            $this->auth->registerFailure($bucket, $maxAttempts, $lockMinutes);
            $this->history($user['id'] ?? null, $identifier, 'FAILED', $channel);
            throw new Authentication_exception(lang('login_failed'));
        }
        if (!$user['is_active']) {
            $this->history((int) $user['id'], $identifier, 'INACTIVE', $channel);
            throw new Authentication_exception(lang('user_inactive'));
        }
        if (password_needs_rehash($user['password_hash'], defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT)) {
            $this->auth->updateLoginMeta((int) $user['id'], ['password_hash' => Password_policy::hash($password)]);
        }
        $this->auth->clearAttempts($bucket);
        unset($user['password_hash']);
        return $user;
    }

    /** Web login: regenerate session (fixation protection), bind single session, log history. */
    public function loginWeb(CI_Session $session, string $identifier, string $password): array
    {
        $user = $this->authenticate($identifier, $password, 'web');
        $session->sess_regenerate(true);
        $sid = session_id();
        $session->set_userdata(['user_id' => (int) $user['id'], 'login_at' => time(), 'last_activity' => time()]);
        $this->auth->updateLoginMeta((int) $user['id'], ['last_login_at' => date('Y-m-d H:i:s'), 'last_login_ip' => $this->ctx->ip, 'current_session_id' => $sid]);
        $this->history((int) $user['id'], $identifier, 'SUCCESS', 'web', $sid);
        $this->fillContext($user);
        $this->audit->log('auth', 'login', 'users', $user['id']);
        return $user;
    }

    public function logoutWeb(CI_Session $session): void
    {
        if ($this->ctx->user_id) {
            $this->history($this->ctx->user_id, $this->ctx->username, 'LOGOUT', 'web', session_id());
            $this->audit->log('auth', 'logout', 'users', $this->ctx->user_id);
            $this->auth->updateLoginMeta($this->ctx->user_id, ['current_session_id' => null]);
        }
        $session->sess_destroy();
    }

    /** Validates session (timeout, concurrent-session) and fills context. */
    public function hydrateContextFromSession(CI_Session $session, Request_context $ctx): bool
    {
        $userId = (int) $session->userdata('user_id');
        if (!$userId) {
            return false;
        }
        $timeout = (int) Env::get('SESSION_TIMEOUT', 1800);
        if ($timeout > 0 && time() - (int) $session->userdata('last_activity') > $timeout) {
            $session->sess_destroy();
            return false;
        }
        $user = $this->users->find($userId);
        if (!$user || !$user['is_active']) {
            $session->sess_destroy();
            return false;
        }
        if (Env::get('SESSION_SINGLE_DEVICE', true) && $user['current_session_id'] && $user['current_session_id'] !== session_id()) {
            $session->sess_destroy();
            return false;
        }
        $session->set_userdata('last_activity', time());
        $this->fillContext($user);
        return true;
    }

    public function issueTokens(array $user): array
    {
        $jwt = new Jwt((string) Env::required('JWT_SECRET'));
        $accessTtl = (int) Env::get('JWT_ACCESS_TTL', 900);
        $refreshTtl = (int) Env::get('JWT_REFRESH_TTL', 604800);
        $jti = bin2hex(random_bytes(8));
        $this->auth->storeRefreshToken((int) $user['id'], $jti, time() + $refreshTtl);
        return [
            'access_token' => $jwt->encode(['sub' => (int) $user['id'], 'typ' => 'access', 'cid' => (int) $user['company_id']], $accessTtl),
            'refresh_token' => $jwt->encode(['sub' => (int) $user['id'], 'typ' => 'refresh', 'jti' => $jti], $refreshTtl),
            'token_type' => 'Bearer', 'expires_in' => $accessTtl,
        ];
    }

    public function loginApi(string $identifier, string $password): array
    {
        $user = $this->authenticate($identifier, $password, 'api');
        $this->history((int) $user['id'], $identifier, 'SUCCESS', 'api');
        $this->fillContext($user);
        $this->audit->log('auth', 'api_login', 'users', $user['id']);
        return ['user' => $this->publicUser($user)] + $this->issueTokens($user);
    }

    public function refreshApi(string $refreshToken): array
    {
        $claims = (new Jwt((string) Env::required('JWT_SECRET')))->decode($refreshToken);
        if (($claims['typ'] ?? '') !== 'refresh' || !$this->auth->refreshTokenValid($claims['jti'] ?? '')) {
            throw new Authentication_exception('Refresh token tidak valid atau telah dicabut');
        }
        $user = $this->users->find((int) $claims['sub']);
        if (!$user || !$user['is_active']) {
            throw new Authentication_exception(lang('user_inactive'));
        }
        $this->auth->revokeRefreshToken($claims['jti']); // rotation
        return $this->issueTokens($user);
    }

    public function hydrateContextFromBearer(string $header, Request_context $ctx): void
    {
        if (stripos($header, 'Bearer ') !== 0) {
            throw new Authentication_exception('Authorization Bearer token diperlukan');
        }
        $claims = (new Jwt((string) Env::required('JWT_SECRET')))->decode(trim(substr($header, 7)));
        if (($claims['typ'] ?? '') !== 'access') {
            throw new Authentication_exception('Tipe token tidak valid');
        }
        $user = $this->users->find((int) $claims['sub']);
        if (!$user || !$user['is_active']) {
            throw new Authentication_exception(lang('user_inactive'));
        }
        $this->fillContext($user);
    }

    public function changePassword(int $userId, string $current, string $new): void
    {
        $user = $this->users->findWithHash($userId);
        if (!$user || !Password_policy::verify($current, $user['password_hash'])) {
            throw new Validation_exception(['current_password' => 'Password saat ini salah']);
        }
        $this->setPassword($userId, $new, false);
    }

    public function setPassword(int $userId, string $new, bool $mustChange): void
    {
        $errors = $this->settings->passwordPolicy()->validate($new);
        if ($errors) {
            throw new Validation_exception(['password' => implode('; ', $errors)]);
        }
        $this->auth->updateLoginMeta($userId, ['password_hash' => Password_policy::hash($new), 'password_changed_at' => date('Y-m-d H:i:s'), 'must_change_password' => $mustChange ? 1 : 0]);
        $this->audit->log('auth', 'password_change', 'users', $userId);
    }

    /** Returns raw token (to be emailed / displayed by admin). Only hash is stored. */
    public function createResetToken(string $email): ?string
    {
        $user = $this->users->findForLogin($email);
        if (!$user) {
            return null; // do not reveal existence
        }
        $token = bin2hex(random_bytes(32));
        $this->auth->createResetToken((int) $user['id'], hash('sha256', $token), date('Y-m-d H:i:s', time() + 3600));
        $this->audit->log('auth', 'password_reset_request', 'users', $user['id']);
        return $token;
    }

    public function resetPassword(string $token, string $new): void
    {
        $row = $this->auth->findValidResetToken(hash('sha256', $token));
        if (!$row) {
            throw new Validation_exception(['token' => 'Token reset tidak valid atau kedaluwarsa']);
        }
        $this->db->transaction(function () use ($row, $new) {
            $this->setPassword((int) $row['user_id'], $new, false);
            $this->auth->consumeResetToken((int) $row['id']);
            $this->auth->updateLoginMeta((int) $row['user_id'], ['current_session_id' => null]);
        });
    }

    public function publicUser(array $user): array
    {
        return ['id' => (int) $user['id'], 'username' => $user['username'], 'email' => $user['email'], 'full_name' => $user['full_name'],
            'company_id' => (int) $user['company_id'], 'branch_id' => $user['branch_id'] ? (int) $user['branch_id'] : null, 'permissions' => $this->ctx->permissions, 'is_superadmin' => (bool) $user['is_superadmin']];
    }

    private function fillContext(array $user): void
    {
        $this->ctx->user_id = (int) $user['id'];
        $this->ctx->username = $user['username'];
        $this->ctx->company_id = (int) $user['company_id'];
        $this->ctx->branch_id = $user['branch_id'] ? (int) $user['branch_id'] : null;
        $this->ctx->is_superadmin = (bool) $user['is_superadmin'];
        $this->ctx->permissions = $this->users->permissions((int) $user['id']);
    }

    private function history(?int $userId, string $identifier, string $status, string $channel, ?string $sid = null): void
    {
        $this->auth->recordLogin(['user_id' => $userId, 'username_attempted' => mb_substr($identifier, 0, 150), 'status' => $status, 'channel' => $channel,
            'ip_address' => $this->ctx->ip, 'user_agent' => $this->ctx->user_agent, 'session_id' => $sid]);
    }
}
