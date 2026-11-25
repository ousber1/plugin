<?php
declare(strict_types=1);

/**
 * HO CREATIVE — POS & ERP
 * Contrôleur frontal : toutes les requêtes passent par ce fichier.
 */

define('BASE_PATH', __DIR__);

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
