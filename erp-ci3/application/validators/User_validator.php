<?php
class User_validator extends Base_validator
{
    public function validate(array $d, bool $isNew): void
    {
        $this->required($d, 'username', 'Username');
        $this->pattern($d, 'username', '/^[a-zA-Z0-9._-]{3,60}$/', 'Username 3-60 karakter, hanya huruf, angka, titik, garis');
        $this->required($d, 'email', 'Email');
        $this->email($d, 'email', 'Email');
        $this->required($d, 'full_name', 'Nama lengkap');
        $this->maxLen($d, 'full_name', 150, 'Nama lengkap');
        if ($isNew) {
            $this->required($d, 'password', 'Password');
        }
        if (!empty($d['password']) && ($d['password'] !== ($d['password_confirm'] ?? null))) {
            $this->addError('password_confirm', 'Konfirmasi password tidak sama');
        }
        if (empty($d['role_ids']) || !is_array($d['role_ids'])) {
            $this->addError('role_ids', 'Minimal satu role harus dipilih');
        }
        $this->throwIfErrors();
    }

    public function validateLogin(array $d): void
    {
        $this->required($d, 'identifier', 'Username/Email');
        $this->required($d, 'password', 'Password');
        $this->throwIfErrors();
    }

    public function validatePasswordChange(array $d): void
    {
        $this->required($d, 'current_password', 'Password saat ini');
        $this->required($d, 'password', 'Password baru');
        if (($d['password'] ?? '') !== ($d['password_confirm'] ?? null)) {
            $this->addError('password_confirm', 'Konfirmasi password tidak sama');
        }
        $this->throwIfErrors();
    }
}
