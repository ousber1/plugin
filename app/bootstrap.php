<?php
declare(strict_types=1);

/**
 * Amorçage de l'application HO CREATIVE.
 * Retourne true si l'application est installée et initialisée.
 */

use App\Core\Config;
use App\Core\Logger;
use App\Core\Response;
use App\Core\Session;
use App\Core\Settings;

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}
const APP_VERSION = '1.0.0';
const MIN_PHP_VERSION = '8.2.0';

if (version_compare(PHP_VERSION, MIN_PHP_VERSION, '<')) {
    http_response_code(500);
    exit('HO CREATIVE nécessite PHP ' . MIN_PHP_VERSION . ' ou supérieur (version actuelle : ' . PHP_VERSION . ').');
}

// Autoloader PSR-4 : App\Core\Auth => app/Core/Auth.php
spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }
    $file = BASE_PATH . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require BASE_PATH . '/app/helpers.php';

mb_internal_encoding('UTF-8');

/** L'application est installée si la config ET le verrou existent. */
function app_is_installed(): bool
{
    return is_file(BASE_PATH . '/config/config.php') && is_file(BASE_PATH . '/storage/installed.lock');
}

if (!app_is_installed()) {
    return false;
}

Config::load(BASE_PATH . '/config/config.php');
date_default_timezone_set((string) Config::get('app.timezone', 'Africa/Casablanca'));

// Gestion des erreurs : jamais d'affichage en production
$debug = (bool) Config::get('app.debug', false);
error_reporting(E_ALL);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', BASE_PATH . '/storage/logs/php-errors.log');

set_exception_handler(static function (\Throwable $e) use ($debug): void {
    Logger::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
    if (!headers_sent()) {
        http_response_code(500);
    }
    if (\App\Core\Request::isAjax()) {
        Response::json(['success' => false, 'message' => $debug ? $e->getMessage() : 'Une erreur interne est survenue.'], 500);
    }
    $message = $debug ? $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')' : null;
    try {
        echo \App\Core\View::render('errors/500', ['debugMessage' => $message], 'auth');
    } catch (\Throwable) {
        echo 'Une erreur interne est survenue.';
    }
});

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new \ErrorException($message, 0, $severity, $file, $line);
});

// Mise à jour automatique du schéma après remplacement des fichiers (sans effet si la base est à jour)
\App\Core\Migrator::autoMigrate();

Response::securityHeaders();
Session::start((int) Settings::get('session_lifetime_minutes', 120));
Session::rotateOldInput();

return true;
