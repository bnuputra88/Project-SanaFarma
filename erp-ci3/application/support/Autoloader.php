<?php
/**
 * PSR-0 style autoloader for non-CI layers (repositories, services, validators, exceptions, support).
 * Class name == file name, e.g. Inventory_service -> application/services/Inventory_service.php
 */
final class Autoloader
{
    private static $dirs = ['exceptions', 'repositories', 'services', 'validators', 'support', 'dto'];

    public static function register(string $appPath): void
    {
        spl_autoload_register(function (string $class) use ($appPath) {
            if (strpos($class, '\\') !== false) {
                return;
            }
            foreach (self::$dirs as $dir) {
                $file = $appPath . $dir . DIRECTORY_SEPARATOR . $class . '.php';
                if (is_file($file)) {
                    require_once $file;
                    return;
                }
            }
        });
    }
}
