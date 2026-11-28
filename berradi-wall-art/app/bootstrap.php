<?php

declare(strict_types=1);

/*
 * Application bootstrap: autoloading + kernel creation.
 * Works with Composer's autoloader (vendor/) and falls back to a built-in PSR-4 loader
 * so the installer can still run if vendor/ was not uploaded yet.
 */

$basePath = dirname(__DIR__);

if (is_file($basePath . '/vendor/autoload.php')) {
    require $basePath . '/vendor/autoload.php';
}

spl_autoload_register(static function (string $class) use ($basePath): void {
    if (str_starts_with($class, 'App\\')) {
        $file = $basePath . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

if (!function_exists('app')) {
    require $basePath . '/app/Helpers/functions.php';
}

return static function (?string $publicPath = null) use ($basePath): \App\Core\App {
    return (new \App\Core\App($basePath, $publicPath))->boot();
};
