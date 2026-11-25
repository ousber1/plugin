<?php
declare(strict_types=1);

/**
 * HO CREATIVE — POS & ERP
 * Contrôleur frontal : toutes les requêtes passent par ce fichier.
 */

define('BASE_PATH', __DIR__);

/*
 * Sonde de réécriture d'URL : cette adresse « propre » ne peut atteindre PHP
 * que si le serveur applique la réécriture (.htaccess / mod_rewrite, LiteSpeed,
 * try_files Nginx). Dans ce cas on mémorise que les adresses simplifiées
 * fonctionnent ; sinon l'application continue d'utiliser index.php?route=…
 */
$probePath = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
if (str_ends_with($probePath, '/__hoc-rewrite-test') && !str_contains($probePath, 'index.php')) {
    $flag = BASE_PATH . '/storage/cache/rewrite.ok';
    if (!is_file($flag) && is_dir(dirname($flag)) && is_writable(dirname($flag))) {
        @file_put_contents($flag, date('c'));
    }
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    echo 'HOC-REWRITE-OK';
    exit;
}

$installed = require BASE_PATH . '/app/bootstrap.php';

if ($installed !== true) {
    // Première utilisation : redirection vers l'assistant d'installation
    $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    header('Location: ' . $scriptDir . '/install/');
    exit;
}

$router = new App\Core\Router();
require BASE_PATH . '/app/routes.php';
$router->dispatch(App\Core\Request::method(), App\Core\Request::route());
