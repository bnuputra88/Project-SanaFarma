<?php
defined('BASEPATH') or exit('No direct script access allowed');

/** Map uncaught exceptions to clean pages (no stack trace in production). */
class MY_Exceptions extends CI_Exceptions
{
    public function show_exception($exception)
    {
        if (ENVIRONMENT !== 'development' && !($exception instanceof Domain_exception)) {
            log_message('error', $exception->getMessage() . ' @ ' . $exception->getFile() . ':' . $exception->getLine());
            if (PHP_SAPI !== 'cli') {
                set_status_header(500);
                echo '<h1>500</h1><p>Terjadi kesalahan internal. Silakan hubungi administrator.</p>';
                return;
            }
        }
        parent::show_exception($exception);
    }
}
