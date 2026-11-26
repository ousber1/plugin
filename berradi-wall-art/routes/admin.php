<?php

/*
 * Administration routes (prefix configurable with ADMIN_PATH in .env).
 *
 * @var \App\Core\Router $router
 */

use App\Controllers\Admin\ActivityLogController;
use App\Controllers\Admin\ArtworkController;
use App\Controllers\Admin\InventoryController;
use App\Controllers\Admin\OrderController;
use App\Controllers\Admin\ProductionController;
use App\Controllers\Admin\PurchaseController;
use App\Controllers\Admin\SupplierController;
use App\Controllers\Admin\AuthController;
use App\Controllers\Admin\CategoryController;
use App\Controllers\Admin\CollectionController;
use App\Controllers\Admin\CustomerController;
use App\Controllers\Admin\MaintenanceController;
use App\Controllers\Admin\OptionController;
use App\Controllers\Admin\ProductController;
use App\Controllers\Admin\DashboardController;
use App\Controllers\Admin\ProfileController;
use App\Controllers\Admin\RoleController;
use App\Controllers\Admin\SettingsController;
use App\Controllers\Admin\UserController;

$adminPath = '/' . trim((string) config('app.admin_path', 'admin'), '/');

$router->group(['prefix' => $adminPath, 'name' => 'admin.'], function ($router): void {
    $router->group(['middleware' => ['guest:admin']], function ($router): void {
        $router->get('/login', [AuthController::class, 'showLogin'])->name('login');
        $router->post('/login', [AuthController::class, 'login'])->middleware('throttle:20,1')->name('login.post');
        $router->get('/forgot-password', [AuthController::class, 'showForgot'])->name('password.request');
        $router->post('/forgot-password', [AuthController::class, 'sendReset'])->middleware('throttle:5,10')->name('password.email');
        $router->get('/reset-password/{token}', [AuthController::class, 'showReset'])->name('password.reset');
        $router->post('/reset-password', [AuthController::class, 'reset'])->middleware('throttle:10,10')->name('password.update');
    });

    $router->group(['middleware' => ['auth.admin']], function ($router): void {
        $router->post('/logout', [AuthController::class, 'logout'])->name('logout');
        $router->get('/', [DashboardController::class, 'index'])->name('dashboard');

        $router->get('/profile', [ProfileController::class, 'edit'])->name('profile');
        $router->post('/profile', [ProfileController::class, 'update'])->name('profile.update');
        $router->post('/profile/password', [ProfileController::class, 'password'])->name('profile.password');

        $router->group(['middleware' => ['can:settings.manage']], function ($router): void {
            $router->get('/settings/{tab?}', [SettingsController::class, 'edit'])->name('settings');
            $router->post('/settings/{tab}', [SettingsController::class, 'update'])->name('settings.update');
            $router->post('/settings-test-mail', [SettingsController::class, 'testMail'])->middleware('throttle:5,1')->name('settings.test_mail');
        });

        $router->group(['middleware' => ['can:users.manage']], function ($router): void {
            $router->get('/users', [UserController::class, 'index'])->name('users.index');
            $router->get('/users/create', [UserController::class, 'create'])->name('users.create');
            $router->post('/users', [UserController::class, 'store'])->name('users.store');
            $router->get('/users/{id}/edit', [UserController::class, 'edit'])->name('users.edit');
            $router->post('/users/{id}', [UserController::class, 'update'])->name('users.update');
            $router->post('/users/{id}/toggle', [UserController::class, 'toggle'])->name('users.toggle');
        });

        $router->group(['middleware' => ['can:roles.manage']], function ($router): void {
            $router->get('/roles', [RoleController::class, 'index'])->name('roles.index');
            $router->get('/roles/create', [RoleController::class, 'create'])->name('roles.create');
            $router->post('/roles', [RoleController::class, 'store'])->name('roles.store');
            $router->get('/roles/{id}/edit', [RoleController::class, 'edit'])->name('roles.edit');
            $router->post('/roles/{id}', [RoleController::class, 'update'])->name('roles.update');
            $router->post('/roles/{id}/delete', [RoleController::class, 'destroy'])->name('roles.destroy');
        });

        $router->get('/activity-logs', [ActivityLogController::class, 'index'])->middleware('can:logs.view')->name('logs.index');

        // ---------------------------------------------------------------- catalogue
        $router->group(['middleware' => ['can:products.view,products.manage']], function ($router): void {
            $router->get('/products', [ProductController::class, 'index'])->name('products.index');
        });
        $router->group(['middleware' => ['can:products.manage']], function ($router): void {
            $router->get('/products/create', [ProductController::class, 'create'])->name('products.create');
            $router->post('/products', [ProductController::class, 'store'])->name('products.store');
            $router->post('/products/bulk', [ProductController::class, 'bulk'])->name('products.bulk');
            $router->get('/products/{id}/edit', [ProductController::class, 'edit'])->name('products.edit');
            $router->post('/products/{id}', [ProductController::class, 'update'])->name('products.update');
            $router->post('/products/{id}/duplicate', [ProductController::class, 'duplicate'])->name('products.duplicate');
            $router->post('/products/{id}/delete', [ProductController::class, 'destroy'])->name('products.destroy');
            $router->post('/products/{id}/images', [ProductController::class, 'uploadImages'])->name('products.images');
            $router->post('/products/{id}/images/{imageId}', [ProductController::class, 'imageAction'])->name('products.image');
            $router->post('/products/{id}/variants', [ProductController::class, 'saveVariants'])->name('products.variants');
            $router->post('/products/{id}/variants/add', [ProductController::class, 'addVariant'])->name('products.variants.add');
            $router->post('/products/{id}/variants/generate', [ProductController::class, 'generateVariants'])->name('products.variants.generate');
            $router->get('/print-options', [OptionController::class, 'index'])->name('options.index');
            $router->post('/print-options/materials', [OptionController::class, 'saveMaterial'])->name('options.materials.store');
            $router->post('/print-options/materials/{id}', [OptionController::class, 'saveMaterial'])->name('options.materials.update');
            $router->post('/print-options/frames', [OptionController::class, 'saveFrame'])->name('options.frames.store');
            $router->post('/print-options/frames/{id}', [OptionController::class, 'saveFrame'])->name('options.frames.update');
            $router->post('/print-options/options', [OptionController::class, 'saveOption'])->name('options.options.store');
            $router->post('/print-options/options/{id}', [OptionController::class, 'saveOption'])->name('options.options.update');
        });
        foreach (['categories' => CategoryController::class, 'collections' => CollectionController::class] as $name => $controller) {
            $router->group(['middleware' => ['can:' . $name . '.manage']], function ($router) use ($name, $controller): void {
                $router->get('/' . $name, [$controller, 'index'])->name($name . '.index');
                $router->get('/' . $name . '/create', [$controller, 'create'])->name($name . '.create');
                $router->post('/' . $name, [$controller, 'store'])->name($name . '.store');
                $router->post('/' . $name . '/reorder', [$controller, 'reorder'])->name($name . '.reorder');
                $router->get('/' . $name . '/{id}/edit', [$controller, 'edit'])->name($name . '.edit');
                $router->post('/' . $name . '/{id}', [$controller, 'update'])->name($name . '.update');
                $router->post('/' . $name . '/{id}/delete', [$controller, 'destroy'])->name($name . '.destroy');
            });
        }

        // ---------------------------------------------------------------- customers
        $router->group(['middleware' => ['can:customers.view,customers.manage']], function ($router): void {
            $router->get('/customers', [CustomerController::class, 'index'])->name('customers.index');
        });
        $router->group(['middleware' => ['can:customers.manage']], function ($router): void {
            $router->get('/customers/create', [CustomerController::class, 'create'])->name('customers.create');
            $router->post('/customers', [CustomerController::class, 'store'])->name('customers.store');
            $router->get('/customers/{id}/edit', [CustomerController::class, 'edit'])->name('customers.edit');
            $router->post('/customers/{id}', [CustomerController::class, 'update'])->name('customers.update');
            $router->post('/customers/{id}/anonymize', [CustomerController::class, 'anonymize'])->name('customers.anonymize');
        });
        $router->get('/customers/{id}', [CustomerController::class, 'show'])->middleware('can:customers.view,customers.manage')->name('customers.show');

        // ---------------------------------------------------------------- maintenance
        $router->group(['middleware' => ['can:settings.manage']], function ($router): void {
            $router->get('/maintenance', [MaintenanceController::class, 'index'])->name('maintenance');
            $router->post('/maintenance/update', [MaintenanceController::class, 'update'])->name('maintenance.update');
            $router->post('/maintenance/demo', [MaintenanceController::class, 'demo'])->name('maintenance.demo');
        });

        // ---------------------------------------------------------------- orders
        $router->get('/orders/create', [OrderController::class, 'create'])->middleware('can:orders.create')->name('orders.create');
        $router->post('/orders', [OrderController::class, 'store'])->middleware('can:orders.create')->name('orders.store');
        $router->group(['middleware' => ['can:orders.view,orders.manage']], function ($router): void {
            $router->get('/orders', [OrderController::class, 'index'])->name('orders.index');
            $router->get('/orders/{id}', [OrderController::class, 'show'])->name('orders.show');
            $router->get('/orders/{id}/print', [OrderController::class, 'printable'])->name('orders.print');
            $router->get('/orders/{id}/items/{itemId}/preview', [OrderController::class, 'itemPreview'])->name('orders.item_preview');
        });
        $router->post('/orders/{id}/status', [OrderController::class, 'status'])->middleware('can:orders.status,orders.manage')->name('orders.status');
        $router->post('/orders/{id}/details', [OrderController::class, 'update'])->middleware('can:orders.manage')->name('orders.update');
        $router->post('/orders/{id}/payments', [OrderController::class, 'payment'])->middleware('can:payments.manage')->name('orders.payment');
        $router->group(['middleware' => ['can:shipments.manage']], function ($router): void {
            $router->post('/orders/{id}/shipments', [OrderController::class, 'shipment'])->name('orders.shipment');
            $router->post('/orders/{id}/shipments/{shipmentId}', [OrderController::class, 'shipmentStatus'])->name('orders.shipment.status');
        });

        // ---------------------------------------------------------------- artwork
        $router->group(['middleware' => ['can:artwork.view']], function ($router): void {
            $router->get('/artwork', [ArtworkController::class, 'index'])->name('artwork.index');
            $router->get('/artwork/{itemId}', [ArtworkController::class, 'show'])->name('artwork.show');
            $router->get('/artwork/{itemId}/original/{cid}', [ArtworkController::class, 'original'])->name('artwork.original');
            $router->get('/artwork/{itemId}/print-file/{cid}', [ArtworkController::class, 'downloadPrintFile'])->name('artwork.print_file.download');
        });
        $router->group(['middleware' => ['can:artwork.approve']], function ($router): void {
            $router->post('/artwork/{itemId}/approve', [ArtworkController::class, 'approve'])->name('artwork.approve');
            $router->post('/artwork/{itemId}/changes', [ArtworkController::class, 'changes'])->name('artwork.changes');
            $router->post('/artwork/{itemId}/proof', [ArtworkController::class, 'proof'])->middleware('throttle:20,10')->name('artwork.proof');
            $router->post('/artwork/{itemId}/print-file/{cid}', [ArtworkController::class, 'printFile'])->name('artwork.print_file');
        });
        $router->post('/artwork/{itemId}/purge/{cid}', [ArtworkController::class, 'purge'])->middleware('can:artwork.delete')->name('artwork.purge');

        // ---------------------------------------------------------------- production
        $router->group(['middleware' => ['can:production.view,production.manage']], function ($router): void {
            $router->get('/production', [ProductionController::class, 'index'])->name('production.index');
            $router->get('/production/jobs/{id}', [ProductionController::class, 'show'])->name('production.show');
        });
        $router->group(['middleware' => ['can:production.manage']], function ($router): void {
            $router->post('/production/jobs/{id}/tasks/{taskId}', [ProductionController::class, 'task'])->name('production.task');
            $router->post('/production/jobs/{id}', [ProductionController::class, 'update'])->name('production.update');
            $router->post('/production/orders/{orderId}/jobs', [ProductionController::class, 'createJobs'])->name('production.create_jobs');
            $router->get('/machines', [ProductionController::class, 'machines'])->name('machines.index');
            $router->post('/machines', [ProductionController::class, 'saveMachine'])->name('machines.store');
            $router->post('/machines/{id}', [ProductionController::class, 'saveMachine'])->name('machines.update');
        });

        // ---------------------------------------------------------------- inventory
        $router->group(['middleware' => ['can:inventory.view,inventory.manage']], function ($router): void {
            $router->get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
        });
        $router->group(['middleware' => ['can:inventory.manage']], function ($router): void {
            $router->get('/inventory/create', [InventoryController::class, 'create'])->name('inventory.create');
            $router->post('/inventory', [InventoryController::class, 'save'])->name('inventory.store');
            $router->get('/inventory/{id}/edit', [InventoryController::class, 'edit'])->name('inventory.edit');
            $router->post('/inventory/{id}', [InventoryController::class, 'save'])->name('inventory.update');
            $router->post('/inventory/{id}/movements', [InventoryController::class, 'movement'])->name('inventory.movement');
        });
        $router->get('/inventory/{id}', [InventoryController::class, 'show'])->middleware('can:inventory.view,inventory.manage')->name('inventory.show');
        $router->group(['middleware' => ['can:suppliers.manage']], function ($router): void {
            $router->get('/suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
            $router->post('/suppliers', [SupplierController::class, 'save'])->name('suppliers.store');
            $router->post('/suppliers/{id}', [SupplierController::class, 'save'])->name('suppliers.update');
        });
        $router->group(['middleware' => ['can:purchases.manage']], function ($router): void {
            $router->get('/purchases', [PurchaseController::class, 'index'])->name('purchases.index');
            $router->get('/purchases/create', [PurchaseController::class, 'create'])->name('purchases.create');
            $router->post('/purchases', [PurchaseController::class, 'save'])->name('purchases.store');
            $router->get('/purchases/{id}', [PurchaseController::class, 'show'])->name('purchases.show');
            $router->get('/purchases/{id}/edit', [PurchaseController::class, 'edit'])->name('purchases.edit');
            $router->post('/purchases/{id}', [PurchaseController::class, 'save'])->name('purchases.update');
            $router->post('/purchases/{id}/receive', [PurchaseController::class, 'receive'])->name('purchases.receive');
            $router->post('/purchases/{id}/cancel', [PurchaseController::class, 'cancel'])->name('purchases.cancel');
            $router->post('/purchases/{id}/pay', [PurchaseController::class, 'pay'])->name('purchases.pay');
        });
    });
});
