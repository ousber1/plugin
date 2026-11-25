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
use App\Controllers\CategoryController;
use App\Controllers\CustomerController;
use App\Controllers\MaterialController;
use App\Controllers\PackController;
use App\Controllers\ProductController;
use App\Controllers\SearchController;
use App\Controllers\StockController;
use App\Controllers\SupplierController;

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

/* ======================================================================
   Phase 3 — Gestion commerciale
   ====================================================================== */

// --- Recherche globale --------------------------------------------------
$router->get('search', [SearchController::class, 'index'], ['auth']);

// --- Clients ------------------------------------------------------------
$router->get('customers',                         [CustomerController::class, 'index'],        ['auth', 'can:customers.view']);
$router->get('customers/lookup',                  [CustomerController::class, 'lookup'],       ['auth', 'can:customers.view']);
$router->get('customers/export',                  [CustomerController::class, 'export'],       ['auth', 'can:customers.export']);
$router->get('customers/import',                  [CustomerController::class, 'importForm'],   ['auth', 'can:customers.import']);
$router->post('customers/import',                 [CustomerController::class, 'import'],       ['auth', 'can:customers.import']);
$router->get('customers/import-template',         [CustomerController::class, 'template'],     ['auth', 'can:customers.import']);
$router->get('customers/groups',                  [CustomerController::class, 'groupsIndex'],  ['auth', 'can:customers.view']);
$router->post('customers/groups',                 [CustomerController::class, 'saveGroup'],    ['auth', 'can:customers.edit']);
$router->post('customers/groups/{id}/delete',     [CustomerController::class, 'deleteGroup'],  ['auth', 'can:customers.delete']);
$router->get('customers/create',                  [CustomerController::class, 'create'],       ['auth', 'can:customers.create']);
$router->post('customers',                        [CustomerController::class, 'store'],        ['auth', 'can:customers.create']);
$router->get('customers/{id}',                    [CustomerController::class, 'show'],         ['auth', 'can:customers.view']);
$router->get('customers/{id}/edit',               [CustomerController::class, 'edit'],         ['auth', 'can:customers.edit']);
$router->post('customers/{id}',                   [CustomerController::class, 'update'],       ['auth', 'can:customers.edit']);
$router->post('customers/{id}/delete',            [CustomerController::class, 'delete'],       ['auth', 'can:customers.delete']);
$router->get('customers/{id}/statement',          [CustomerController::class, 'statement'],    ['auth', 'can:customers.view']);
$router->post('customers/{id}/files',             [CustomerController::class, 'uploadFile'],   ['auth', 'can:customers.edit']);
$router->get('customers/{id}/files/{id2}',        [CustomerController::class, 'downloadFile'], ['auth', 'can:customers.view']);
$router->post('customers/{id}/files/{id2}/delete',[CustomerController::class, 'deleteFile'],   ['auth', 'can:customers.edit']);
$router->post('customers/{id}/loyalty',           [CustomerController::class, 'loyalty'],      ['auth', 'can:customers.edit']);
$router->post('customers/{id}/prices',            [CustomerController::class, 'savePrice'],    ['auth', 'can:customers.edit']);
$router->post('customers/{id}/prices/{id2}/delete', [CustomerController::class, 'deletePrice'], ['auth', 'can:customers.edit']);

// --- Fournisseurs -------------------------------------------------------
$router->get('suppliers',                          [SupplierController::class, 'index'],       ['auth', 'can:suppliers.view']);
$router->get('suppliers/lookup',                   [SupplierController::class, 'lookup'],      ['auth', 'can:suppliers.view']);
$router->get('suppliers/export',                   [SupplierController::class, 'export'],      ['auth', 'can:suppliers.view']);
$router->get('suppliers/create',                   [SupplierController::class, 'create'],      ['auth', 'can:suppliers.create']);
$router->post('suppliers',                         [SupplierController::class, 'store'],       ['auth', 'can:suppliers.create']);
$router->get('suppliers/{id}',                     [SupplierController::class, 'show'],        ['auth', 'can:suppliers.view']);
$router->get('suppliers/{id}/edit',                [SupplierController::class, 'edit'],        ['auth', 'can:suppliers.edit']);
$router->post('suppliers/{id}',                    [SupplierController::class, 'update'],      ['auth', 'can:suppliers.edit']);
$router->post('suppliers/{id}/delete',             [SupplierController::class, 'delete'],      ['auth', 'can:suppliers.delete']);
$router->post('suppliers/{id}/prices',             [SupplierController::class, 'savePrice'],   ['auth', 'can:suppliers.edit']);
$router->post('suppliers/{id}/prices/{id2}/delete',[SupplierController::class, 'deletePrice'], ['auth', 'can:suppliers.edit']);

// --- Catégories ---------------------------------------------------------
$router->get('categories',              [CategoryController::class, 'index'],  ['auth', 'can:products.view']);
$router->post('categories',             [CategoryController::class, 'save'],   ['auth', 'can:products.edit']);
$router->post('categories/{id}/delete', [CategoryController::class, 'delete'], ['auth', 'can:products.delete']);

// --- Produits & services ------------------------------------------------
$router->get('products',                 [ProductController::class, 'index'],      ['auth', 'can:products.view']);
$router->get('products/lookup',          [ProductController::class, 'lookup'],     ['auth', 'can:products.view']);
$router->get('products/export',          [ProductController::class, 'export'],     ['auth', 'can:products.export']);
$router->get('products/import',          [ProductController::class, 'importForm'], ['auth', 'can:products.import']);
$router->post('products/import',         [ProductController::class, 'import'],     ['auth', 'can:products.import']);
$router->get('products/import-template', [ProductController::class, 'template'],   ['auth', 'can:products.import']);
$router->get('products/create',          [ProductController::class, 'create'],     ['auth', 'can:products.create']);
$router->post('products',                [ProductController::class, 'store'],      ['auth', 'can:products.create']);
$router->get('products/{id}',            [ProductController::class, 'show'],       ['auth', 'can:products.view']);
$router->get('products/{id}/edit',       [ProductController::class, 'edit'],       ['auth', 'can:products.edit']);
$router->post('products/{id}',           [ProductController::class, 'update'],     ['auth', 'can:products.edit']);
$router->post('products/{id}/delete',    [ProductController::class, 'delete'],     ['auth', 'can:products.delete']);

// --- Packs --------------------------------------------------------------
$router->get('packs',              [PackController::class, 'index'],  ['auth', 'can:packs.view']);
$router->get('packs/lookup',       [PackController::class, 'lookup'], ['auth', 'can:packs.view']);
$router->get('packs/create',       [PackController::class, 'create'], ['auth', 'can:packs.manage']);
$router->post('packs',             [PackController::class, 'store'],  ['auth', 'can:packs.manage']);
$router->get('packs/{id}',         [PackController::class, 'show'],   ['auth', 'can:packs.view']);
$router->get('packs/{id}/edit',    [PackController::class, 'edit'],   ['auth', 'can:packs.manage']);
$router->post('packs/{id}',        [PackController::class, 'update'], ['auth', 'can:packs.manage']);
$router->post('packs/{id}/delete', [PackController::class, 'delete'], ['auth', 'can:packs.manage']);

// --- Matières -----------------------------------------------------------
$router->get('materials',              [MaterialController::class, 'index'],  ['auth', 'can:stock.view']);
$router->get('materials/lookup',       [MaterialController::class, 'lookup'], ['auth', 'can:stock.view']);
$router->get('materials/export',       [MaterialController::class, 'export'], ['auth', 'can:stock.view']);
$router->get('materials/create',       [MaterialController::class, 'create'], ['auth', 'can:stock.materials']);
$router->post('materials',             [MaterialController::class, 'store'],  ['auth', 'can:stock.materials']);
$router->get('materials/{id}',         [MaterialController::class, 'show'],   ['auth', 'can:stock.view']);
$router->get('materials/{id}/edit',    [MaterialController::class, 'edit'],   ['auth', 'can:stock.materials']);
$router->post('materials/{id}',        [MaterialController::class, 'update'], ['auth', 'can:stock.materials']);
$router->post('materials/{id}/delete', [MaterialController::class, 'delete'], ['auth', 'can:stock.materials']);

// --- Stock --------------------------------------------------------------
$router->get('stock',                          [StockController::class, 'index'],           ['auth', 'can:stock.view']);
$router->get('stock/movements',                [StockController::class, 'movements'],       ['auth', 'can:stock.view']);
$router->get('stock/movements/export',         [StockController::class, 'exportMovements'], ['auth', 'can:stock.view']);
$router->get('stock/movements/create',         [StockController::class, 'createMovement'],  ['auth', 'can:stock.movements']);
$router->post('stock/movements',               [StockController::class, 'storeMovement'],   ['auth', 'can:stock.movements']);
$router->get('stock/inventories',              [StockController::class, 'inventories'],     ['auth', 'can:stock.view']);
$router->post('stock/inventories',             [StockController::class, 'startInventory'],  ['auth', 'can:stock.inventory']);
$router->get('stock/inventories/{id}',         [StockController::class, 'showInventory'],   ['auth', 'can:stock.view']);
$router->post('stock/inventories/{id}',        [StockController::class, 'saveInventory'],   ['auth', 'can:stock.movements']);
$router->post('stock/inventories/{id}/cancel', [StockController::class, 'cancelInventory'], ['auth', 'can:stock.inventory']);
