<?php
/** Base validator: centralized, framework-agnostic validation producing Validation_exception. */
abstract class Base_validator
{
    protected $errors = [];

    protected function required(array $d, string $f, string $label): void
    {
        if (!isset($d[$f]) || trim((string) $d[$f]) === '' || (is_array($d[$f]) && !$d[$f])) {
            $this->errors[$f] = "$label wajib diisi";
        }
    }

    protected function maxLen(array $d, string $f, int $max, string $label): void
    {
        if (isset($d[$f]) && mb_strlen((string) $d[$f]) > $max) {
            $this->errors[$f] = "$label maksimal $max karakter";
        }
    }

    protected function numeric(array $d, string $f, string $label, bool $allowNegative = false, bool $allowZero = true): void
    {
        if (!isset($d[$f]) || $d[$f] === '') {
            return;
        }
        if (!is_numeric($d[$f])) {
            $this->errors[$f] = "$label harus berupa angka";
        } elseif (!$allowNegative && (float) $d[$f] < 0) {
            $this->errors[$f] = "$label tidak boleh negatif";
        } elseif (!$allowZero && (float) $d[$f] == 0) {
            $this->errors[$f] = "$label tidak boleh nol";
        }
    }

    protected function inList(array $d, string $f, array $allowed, string $label): void
    {
        if (isset($d[$f]) && $d[$f] !== '' && !in_array($d[$f], $allowed, true)) {
            $this->errors[$f] = "$label tidak valid";
        }
    }

    protected function date(array $d, string $f, string $label): void
    {
        if (!empty($d[$f]) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d[$f])) {
            $this->errors[$f] = "$label format tanggal harus YYYY-MM-DD";
        }
    }

    protected function email(array $d, string $f, string $label): void
    {
        if (!empty($d[$f]) && !filter_var($d[$f], FILTER_VALIDATE_EMAIL)) {
            $this->errors[$f] = "$label tidak valid";
        }
    }

    protected function pattern(array $d, string $f, string $regex, string $msg): void
    {
        if (!empty($d[$f]) && !preg_match($regex, $d[$f])) {
            $this->errors[$f] = $msg;
        }
    }

    protected function addError(string $f, string $msg): void
    {
        $this->errors[$f] = $msg;
    }

    protected function throwIfErrors(): void
    {
        if ($this->errors) {
            $errors = $this->errors;
            $this->errors = [];
            throw new Validation_exception($errors);
        }
    }
}
