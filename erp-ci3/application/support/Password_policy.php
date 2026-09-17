<?php
/**
 * Configurable password policy (values come from system_settings, not hard-coded).
 */
final class Password_policy
{
    private $rules;

    public function __construct(array $rules = [])
    {
        $this->rules = array_merge([
            'min_length' => 10,
            'require_upper' => true,
            'require_lower' => true,
            'require_digit' => true,
            'require_symbol' => true,
            'max_age_days' => 90,
        ], $rules);
    }

    /** @return string[] list of violation messages (empty = valid) */
    public function validate(string $password): array
    {
        $errors = [];
        if (mb_strlen($password) < (int) $this->rules['min_length']) {
            $errors[] = sprintf('Password minimal %d karakter', $this->rules['min_length']);
        }
        if ($this->rules['require_upper'] && !preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password harus mengandung huruf besar';
        }
        if ($this->rules['require_lower'] && !preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password harus mengandung huruf kecil';
        }
        if ($this->rules['require_digit'] && !preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password harus mengandung angka';
        }
        if ($this->rules['require_symbol'] && !preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = 'Password harus mengandung simbol';
        }
        return $errors;
    }

    public function isExpired(?string $changedAt): bool
    {
        $maxAge = (int) $this->rules['max_age_days'];
        if ($maxAge <= 0 || $changedAt === null) {
            return false;
        }
        return strtotime($changedAt) < strtotime("-{$maxAge} days");
    }

    public static function hash(string $password): string
    {
        if (defined('PASSWORD_ARGON2ID')) {
            return password_hash($password, PASSWORD_ARGON2ID);
        }
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    public static function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }
}
