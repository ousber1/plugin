<?php
declare(strict_types=1);

/**
 * Table des routes. $router est fourni par index.php.
 * Les nouveaux modules ajoutent leurs routes ici, phase par phase.
 *
 * @var App\Core\Router $router
 */

use App\Controllers\ActivityLogController;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\NotificationController;
use App\Controllers\PasswordResetController;
use App\Controllers\ProfileController;
use App\Controllers\RoleController;
use App\Controllers\SettingsController;
use App\Controllers\SystemController;
use App\Controllers\UserController;

// --- Authentification ---------------------------------------------------
$router->get('login',  [AuthController::class, 'showLogin'], ['guest']);
$router->post('login', [AuthController::class, 'login'],     ['guest']);
$router->post('logout', [AuthController::class, 'logout'],   ['auth']);
$router->get('password/forgot',  [PasswordResetController::class, 'showForgot'], ['guest']);
$router->post('password/forgot', [PasswordResetController::class, 'sendLink'],   ['guest']);
$router->get('password/reset',   [PasswordResetController::class, 'showReset'],  ['guest']);
$router->post('password/reset',  [PasswordResetController::class, 'reset'],      ['guest']);

// --- Tableau de bord ----------------------------------------------------
$router->get('', [DashboardController::class, 'index'], ['auth']);

// --- Profil -------------------------------------------------------------
$router->get('profile',           [ProfileController::class, 'show'],     ['auth']);
$router->post('profile',          [ProfileController::class, 'update'],   ['auth']);
$router->post('profile/password', [ProfileController::class, 'password'], ['auth']);
$router->post('profile/theme',    [ProfileController::class, 'theme'],    ['auth']);

// --- Notifications ------------------------------------------------------
$router->get('notifications',              [NotificationController::class, 'index'],   ['auth']);
$router->get('notifications/feed',         [NotificationController::class, 'feed'],    ['auth']);
$router->post('notifications/read-all',    [NotificationController::class, 'readAll'], ['auth']);
$router->post('notifications/{id}/read',   [NotificationController::class, 'read'],    ['auth']);

// --- Utilisateurs & rôles -----------------------------------------------
$router->get('users',                 [UserController::class, 'index'],    ['auth', 'can:users.view']);
$router->get('users/create',          [UserController::class, 'create'],   ['auth', 'can:users.manage']);
$router->post('users',                [UserController::class, 'store'],    ['auth', 'can:users.manage']);
$router->get('users/{id}/edit',       [UserController::class, 'edit'],     ['auth', 'can:users.manage']);
$router->post('users/{id}',           [UserController::class, 'update'],   ['auth', 'can:users.manage']);
$router->post('users/{id}/toggle',    [UserController::class, 'toggle'],   ['auth', 'can:users.manage']);
$router->post('users/{id}/unlock',    [UserController::class, 'unlock'],   ['auth', 'can:users.manage']);
$router->post('users/{id}/password',  [UserController::class, 'password'], ['auth', 'can:users.manage']);
$router->post('users/{id}/delete',    [UserController::class, 'delete'],   ['auth', 'can:users.manage']);
$router->get('roles',                 [RoleController::class, 'index'],    ['auth', 'can:users.view']);
$router->get('roles/create',          [RoleController::class, 'create'],   ['auth', 'can:users.manage']);
$router->post('roles',                [RoleController::class, 'store'],    ['auth', 'can:users.manage']);
$router->get('roles/{id}/edit',       [RoleController::class, 'edit'],     ['auth', 'can:users.manage']);
$router->post('roles/{id}',           [RoleController::class, 'update'],   ['auth', 'can:users.manage']);
$router->post('roles/{id}/delete',    [RoleController::class, 'delete'],   ['auth', 'can:users.manage']);

// --- Paramètres ---------------------------------------------------------
$router->get('settings',               [SettingsController::class, 'index'],       ['auth', 'can:settings.manage']);
$router->post('settings/taxes',        [SettingsController::class, 'saveTax'],     ['auth', 'can:settings.manage']);
$router->post('settings/mail-test',    [SettingsController::class, 'testMail'],    ['auth', 'can:settings.manage']);
$router->post('settings/image-remove', [SettingsController::class, 'removeImage'], ['auth', 'can:settings.manage']);
$router->get('settings/{slug}',        [SettingsController::class, 'show'],        ['auth', 'can:settings.manage']);
$router->post('settings/{slug}',       [SettingsController::class, 'save'],        ['auth', 'can:settings.manage']);

// --- Journal & système --------------------------------------------------
$router->get('logs',          [ActivityLogController::class, 'index'], ['auth', 'can:logs.view']);
$router->get('system/status', [SystemController::class, 'status'],     ['auth', 'can:settings.manage']);
