<?php
/** Auth-related persistence: login attempts, history, reset tokens, refresh tokens, rate limits. */
class Auth_repository extends Base_repository
{
    protected $table = 'login_history';

    public function recordLogin(array $row): void
    {
        $this->db->insert('login_history', $row);
    }

    public function getAttempt(string $identifier): ?array
    {
        $row = $this->db->select('identifier, attempts, locked_until, last_attempt_at')->from('login_attempts')->where('identifier', $identifier)->get()->row_array();
        return $row ?: null;
    }

    public function registerFailure(string $identifier, int $maxAttempts, int $lockMinutes): int
    {
        $now = date('Y-m-d H:i:s');
        $this->db->query("INSERT INTO login_attempts (identifier, attempts, last_attempt_at) VALUES (?, 1, ?)
            ON DUPLICATE KEY UPDATE attempts = attempts + 1, last_attempt_at = VALUES(last_attempt_at),
            locked_until = IF(attempts + 1 >= ?, DATE_ADD(VALUES(last_attempt_at), INTERVAL ? MINUTE), locked_until)", [$identifier, $now, $maxAttempts, $lockMinutes]);
        return (int) ($this->getAttempt($identifier)['attempts'] ?? 0);
    }

    public function clearAttempts(string $identifier): void
    {
        $this->db->where('identifier', $identifier)->delete('login_attempts');
    }

    public function updateLoginMeta(int $userId, array $data): void
    {
        $this->db->where('id', $userId)->update('users', $data);
    }

    public function createResetToken(int $userId, string $tokenHash, string $expiresAt): void
    {
        $this->db->insert('password_reset_tokens', ['user_id' => $userId, 'token_hash' => $tokenHash, 'expires_at' => $expiresAt]);
    }

    public function findValidResetToken(string $tokenHash): ?array
    {
        $row = $this->db->select('id, user_id, expires_at')->from('password_reset_tokens')
            ->where('token_hash', $tokenHash)->where('used_at IS NULL', null, false)->where('expires_at >', date('Y-m-d H:i:s'))->get()->row_array();
        return $row ?: null;
    }

    public function consumeResetToken(int $id): void
    {
        $this->db->where('id', $id)->update('password_reset_tokens', ['used_at' => date('Y-m-d H:i:s')]);
    }

    public function storeRefreshToken(int $userId, string $jti, int $expiresTs): void
    {
        $this->db->insert('api_tokens', ['user_id' => $userId, 'jti' => $jti, 'expires_at' => date('Y-m-d H:i:s', $expiresTs)]);
    }

    public function refreshTokenValid(string $jti): bool
    {
        return $this->db->from('api_tokens')->where('jti', $jti)->where('revoked_at IS NULL', null, false)->where('expires_at >', date('Y-m-d H:i:s'))->count_all_results() === 1;
    }

    public function revokeRefreshToken(string $jti): void
    {
        $this->db->where('jti', $jti)->update('api_tokens', ['revoked_at' => date('Y-m-d H:i:s')]);
    }

    /** Fixed-window counter; returns hits in current window. */
    public function rateHit(string $bucket, int $windowSeconds): int
    {
        $windowStart = date('Y-m-d H:i:s', (int) (floor(time() / $windowSeconds) * $windowSeconds));
        $this->db->query("INSERT INTO rate_limits (bucket, window_start, hits) VALUES (?, ?, 1)
            ON DUPLICATE KEY UPDATE hits = IF(window_start = VALUES(window_start), hits + 1, 1), window_start = VALUES(window_start)", [$bucket, $windowStart]);
        return (int) $this->db->select('hits')->from('rate_limits')->where('bucket', $bucket)->get()->row()->hits;
    }
}
