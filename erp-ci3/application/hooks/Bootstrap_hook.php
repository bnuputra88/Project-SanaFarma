<?php
defined('BASEPATH') or exit('No direct script access allowed');

/** pre_system: register autoloader for non-CI layers and timezone. */
class Bootstrap_hook
{
    public function run(): void
    {
        require_once APPPATH . 'support/Autoloader.php';
        Autoloader::register(APPPATH);
        date_default_timezone_set(Env::get('APP_TIMEZONE', 'Asia/Jakarta'));
    }
}
