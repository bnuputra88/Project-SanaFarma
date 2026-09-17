<?php
/**
 * PharmaERP front controller.
 * Loads .env, sets ENVIRONMENT, then bootstraps CodeIgniter 3 from composer vendor.
 */
define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR);

require_once FCPATH . 'application/support/Env.php';
Env::load(FCPATH . '.env');

define('ENVIRONMENT', Env::get('APP_ENV', 'production'));

switch (ENVIRONMENT) {
    case 'development':
        error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED); // CI3 emits deprecations on PHP >= 8.2; target runtime is 7.4
        ini_set('display_errors', 1);
        break;
    case 'testing':
    case 'staging':
    case 'production':
        ini_set('display_errors', 0);
        error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_USER_NOTICE & ~E_USER_DEPRECATED);
        break;
    default:
        header('HTTP/1.1 503 Service Unavailable.', true, 503);
        echo 'The application environment is not set correctly.';
        exit(1);
}

$system_path = FCPATH . 'vendor/codeigniter/framework/system';
$application_folder = FCPATH . 'application';
$view_folder = '';

if (!is_dir($system_path)) {
    header('HTTP/1.1 503 Service Unavailable.', true, 503);
    echo 'CodeIgniter system folder not found. Run: composer install';
    exit(3);
}

define('SELF', pathinfo(__FILE__, PATHINFO_BASENAME));
define('BASEPATH', $system_path . DIRECTORY_SEPARATOR);
define('SYSDIR', basename(BASEPATH));
define('APPPATH', $application_folder . DIRECTORY_SEPARATOR);
define('VIEWPATH', APPPATH . 'views' . DIRECTORY_SEPARATOR);

require_once BASEPATH . 'core/CodeIgniter.php';
