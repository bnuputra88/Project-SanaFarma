<?php
defined('BASEPATH') or exit('No direct script access allowed');

/** post_controller_constructor: baseline security headers for every response. */
class Security_headers_hook
{
    public function run(): void
    {
        if (PHP_SAPI === 'cli' || headers_sent()) {
            return;
        }
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('X-XSS-Protection: 0');
        header('Permissions-Policy: camera=(self), microphone=(), geolocation=()');
        header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.jsdelivr.net; style-src 'self' https://cdn.jsdelivr.net https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net; img-src 'self' data:; frame-ancestors 'self'");
        if (ENVIRONMENT === 'production') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }
}
