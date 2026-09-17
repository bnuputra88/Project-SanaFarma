<?php
/**
 * Immutable-ish per-request context: who is acting, from where, for which branch.
 * Injected into services so audit trail and authorization never depend on globals.
 */
final class Request_context
{
    public $user_id;
    public $username;
    public $company_id;
    public $branch_id;
    public $ip;
    public $user_agent;
    public $channel; // web|api|cli
    public $permissions = [];
    public $is_superadmin = false;
    public $request_id;

    public function __construct()
    {
        $this->request_id = bin2hex(random_bytes(8));
        $this->ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
        $this->user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'cli', 0, 255);
        $this->channel = PHP_SAPI === 'cli' ? 'cli' : 'web';
    }

    public function isAuthenticated(): bool
    {
        return $this->user_id !== null;
    }

    public function can(string $permission): bool
    {
        return $this->is_superadmin || in_array($permission, $this->permissions, true);
    }
}
