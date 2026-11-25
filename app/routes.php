<?php
declare(strict_types=1);

/**
 * Table des routes. $router est fourni par index.php.
 * Les nouveaux modules ajoutent leurs routes ici, phase par phase.
 *
 * @var App\Core\Router $router
 */

use App\Controllers\AuthController;
use App\Controllers\SystemController;

// --- Authentification ---------------------------------------------------
$router->get('login',  [AuthController::class, 'showLogin'], ['guest']);
$router->post('login', [AuthController::class, 'login'],     ['guest']);
$router->post('logout', [AuthController::class, 'logout'],   ['auth']);

// --- Système ------------------------------------------------------------
// Page d'accueil provisoire : remplacée par le tableau de bord en Phase 2.
$router->get('',              [SystemController::class, 'home'],   ['auth']);
$router->get('system/status', [SystemController::class, 'status'], ['auth', 'can:settings.manage']);
