<?php
/**
 * Test bootstrap. Unit tests need only the support/exception classes.
 * Integration tests boot CodeIgniter in 'testing' env against DB_NAME from .env.testing (falls back to .env).
 */
define('TEST_ROOT', __DIR__);
define('APP_ROOT', dirname(__DIR__) . '/');
require_once APP_ROOT . 'application/support/Env.php';
Env::load(file_exists(APP_ROOT . '.env.testing') ? APP_ROOT . '.env.testing' : APP_ROOT . '.env');
require_once APP_ROOT . 'application/support/Autoloader.php';
Autoloader::register(APP_ROOT . 'application/');
if (file_exists(APP_ROOT . 'vendor/autoload.php')) {
    require_once APP_ROOT . 'vendor/autoload.php';
}
// Allow CI config files (guarded by BASEPATH) to be required in pure unit tests
defined('BASEPATH') or define('BASEPATH', APP_ROOT . 'vendor/codeigniter/framework/system/');

/** Boots a CI instance once for integration tests (requires DB). Returns CI controller (MY_Controller) with container. */
function ci_boot(): ?MY_Controller
{
    static $ci = null;
    if ($ci !== null) {
        return $ci ?: null;
    }
    if (!getenv('DB_HOST') || !is_dir(APP_ROOT . 'vendor/codeigniter/framework/system')) {
        $ci = false;
        return null;
    }
    $_SERVER['argv'] = ['index.php', 'cli/noop'];
    $_SERVER['argc'] = 2;
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    define('ENVIRONMENT', 'testing');
    define('FCPATH', APP_ROOT);
    define('SELF', 'index.php');
    define('SYSDIR', 'system');
    define('APPPATH', APP_ROOT . 'application/');
    define('VIEWPATH', APPPATH . 'views/');
    ob_start();
    require_once BASEPATH . 'core/CodeIgniter.php'; // boots CI, runs cli/noop, leaves instance available
    ob_end_clean();
    $ci = get_instance();
    return $ci;
}
