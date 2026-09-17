<?php
/**
 * HS256 JWT encode/decode without external dependency. Constant-time signature comparison.
 */
final class Jwt
{
    private $secret;

    public function __construct(string $secret)
    {
        if (strlen($secret) < 32) {
            throw new RuntimeException('JWT_SECRET must be at least 32 characters');
        }
        $this->secret = $secret;
    }

    public function encode(array $claims, int $ttlSeconds): string
    {
        $now = time();
        $claims += ['iat' => $now, 'nbf' => $now, 'exp' => $now + $ttlSeconds, 'jti' => bin2hex(random_bytes(8))];
        $h = self::b64(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $p = self::b64(json_encode($claims));
        $s = self::b64(hash_hmac('sha256', "$h.$p", $this->secret, true));
        return "$h.$p.$s";
    }

    /** @throws Authentication_exception */
    public function decode(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new Authentication_exception('Token tidak valid');
        }
        [$h, $p, $s] = $parts;
        $expected = self::b64(hash_hmac('sha256', "$h.$p", $this->secret, true));
        if (!hash_equals($expected, $s)) {
            throw new Authentication_exception('Signature token tidak valid');
        }
        $claims = json_decode(self::unb64($p), true);
        if (!is_array($claims)) {
            throw new Authentication_exception('Payload token tidak valid');
        }
        $now = time();
        if (($claims['nbf'] ?? 0) > $now + 30 || ($claims['exp'] ?? 0) < $now) {
            throw new Authentication_exception('Token kedaluwarsa');
        }
        return $claims;
    }

    private static function b64(string $d): string
    {
        return rtrim(strtr(base64_encode($d), '+/', '-_'), '=');
    }

    private static function unb64(string $d): string
    {
        return base64_decode(strtr($d, '-_', '+/'));
    }
}
