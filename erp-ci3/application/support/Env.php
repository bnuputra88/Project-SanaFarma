<?php
/**
 * Minimal .env loader (no external dependency). Values never leave process env.
 */
final class Env
{
    public static function load(string $path): void
    {
        if (!is_readable($path)) {
            return;
        }
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if (strlen($value) > 1 && ($value[0] === '"' || $value[0] === "'") && substr($value, -1) === $value[0]) {
                $value = substr($value, 1, -1);
            }
            if (getenv($key) === false) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
            }
        }
    }

    public static function get(string $key, $default = null)
    {
        $v = getenv($key);
        if ($v === false || $v === '') {
            return $default;
        }
        if (in_array(strtolower($v), ['true', 'false'], true)) {
            return strtolower($v) === 'true';
        }
        return $v;
    }

    public static function required(string $key)
    {
        $v = self::get($key);
        if ($v === null) {
            throw new RuntimeException("Missing required environment variable: $key");
        }
        return $v;
    }
}
