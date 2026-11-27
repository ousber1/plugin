<?php

/*
 * Administration routes (prefix configurable with ADMIN_PATH in .env).
 *
 * @var \App\Core\Router $router
 */

use App\Controllers\Admin\ActivityLogController;
use App\Controllers\Admin\ArtworkController;
use App\Controllers\Admin\BackupController;
use App\Controllers\Admin\ExpenseController;
use App\Controllers\Admin\FinanceController;
use App\Controllers\Admin\InvoiceController;
use App\Controllers\Admin\PaymentController;
use App\Controllers\Admin\QuotationController;
use App\Controllers\Admin\ReportController;
use App\Controllers\Admin\InventoryController;
use App\Controllers\Admin\OrderController;
use App\Controllers\Admin\ProductionController;
use App\Controllers\Admin\PurchaseController;
use App\Controllers\Admin\SupplierController;
use App\Controllers\Admin\AuthController;
use App\Controllers\Admin\CategoryController;
use App\Controllers\Admin\ContentController;
use App\Controllers\Admin\CouponController;
use App\Controllers\Admin\NewsletterController;
use App\Controllers\Admin\ReviewController;
use App\Controllers\Admin\ShippingController;
use App\Controllers\Admin\CollectionController;
use App\Controllers\Admin\CustomerController;
use App\Controllers\Admin\MaintenanceController;
use App\Controllers\Admin\NotificationController;
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
            $router->get('/payment-methods', [\App\Controllers\Admin\PaymentMethodController::class, 'index'])->name('payment_methods.index');
            $router->post('/payment-methods', [\App\Controllers\Admin\PaymentMethodController::class, 'save'])->name('payment_methods.store');
            $router->post('/payment-methods/{id}', [\App\Controllers\Admin\PaymentMethodController::class, 'save'])->name('payment_methods.update');
            $router->post('/payment-methods/{id}/delete', [\App\Controllers\Admin\PaymentMethodController::class, 'destroy'])->name('payment_methods.destroy');
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

        // ---------------------------------------------------------------- quotations
        $router->group(['middleware' => ['can:quotations.view,quotations.manage']], function ($router): void {
            $router->get('/quotations', [QuotationController::class, 'index'])->name('quotations.index');
        });
        $router->group(['middleware' => ['can:quotations.manage']], function ($router): void {
            $router->get('/quotations/create', [QuotationController::class, 'create'])->name('quotations.create');
            $router->post('/quotations', [QuotationController::class, 'save'])->name('quotations.store');
            $router->get('/quotations/{id}/edit', [QuotationController::class, 'edit'])->name('quotations.edit');
            $router->post('/quotations/{id}', [QuotationController::class, 'save'])->name('quotations.update');
            $router->post('/quotations/{id}/send', [QuotationController::class, 'send'])->middleware('throttle:20,10')->name('quotations.send');
            $router->post('/quotations/{id}/status', [QuotationController::class, 'status'])->name('quotations.status');
            $router->post('/quotations/{id}/duplicate', [QuotationController::class, 'duplicate'])->name('quotations.duplicate');
            $router->post('/quotations/{id}/convert', [QuotationController::class, 'convert'])->middleware('can:orders.create')->name('quotations.convert');
        });
        $router->group(['middleware' => ['can:quotations.view,quotations.manage']], function ($router): void {
            $router->get('/quotations/{id}', [QuotationController::class, 'show'])->name('quotations.show');
            $router->get('/quotations/{id}/pdf', [QuotationController::class, 'pdf'])->name('quotations.pdf');
        });

        // ---------------------------------------------------------------- invoices & credit notes
        $router->group(['middleware' => ['can:invoices.view,invoices.manage']], function ($router): void {
            $router->get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
        });
        $router->group(['middleware' => ['can:invoices.manage']], function ($router): void {
            $router->get('/invoices/create', [InvoiceController::class, 'create'])->name('invoices.create');
            $router->post('/invoices', [InvoiceController::class, 'save'])->name('invoices.store');
            $router->post('/invoices/from-order/{orderId}', [InvoiceController::class, 'fromOrder'])->name('invoices.from_order');
            $router->get('/invoices/{id}/edit', [InvoiceController::class, 'edit'])->name('invoices.edit');
            $router->post('/invoices/{id}', [InvoiceController::class, 'save'])->name('invoices.update');
            $router->post('/invoices/{id}/issue', [InvoiceController::class, 'issue'])->name('invoices.issue');
            $router->post('/invoices/{id}/delete', [InvoiceController::class, 'destroy'])->name('invoices.destroy');
            $router->post('/invoices/{id}/credit', [InvoiceController::class, 'credit'])->name('invoices.credit');
            $router->post('/invoices/{id}/send', [InvoiceController::class, 'send'])->middleware('throttle:20,10')->name('invoices.send');
        });
        $router->post('/invoices/{id}/payments', [InvoiceController::class, 'payment'])->middleware('can:payments.manage')->name('invoices.payment');
        $router->group(['middleware' => ['can:invoices.view,invoices.manage']], function ($router): void {
            $router->get('/invoices/{id}', [InvoiceController::class, 'show'])->name('invoices.show');
            $router->get('/invoices/{id}/pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf');
        });

        // ---------------------------------------------------------------- payments, expenses, finance
        $router->get('/payments', [PaymentController::class, 'index'])->middleware('can:payments.view,payments.manage')->name('payments.index');
        $router->post('/orders/{orderId}/refunds', [PaymentController::class, 'refund'])->middleware('can:payments.manage')->name('orders.refund');
        $router->group(['middleware' => ['can:expenses.manage']], function ($router): void {
            $router->get('/expenses', [ExpenseController::class, 'index'])->name('expenses.index');
            $router->post('/expenses', [ExpenseController::class, 'save'])->name('expenses.store');
            $router->post('/expenses/{id}', [ExpenseController::class, 'save'])->name('expenses.update');
            $router->post('/expenses/{id}/delete', [ExpenseController::class, 'destroy'])->name('expenses.destroy');
            $router->get('/expenses/{id}/receipt', [ExpenseController::class, 'receipt'])->name('expenses.receipt');
        });
        $router->get('/finance', [FinanceController::class, 'index'])->middleware('can:finance.view')->name('finance.index');
        $router->get('/reports', [ReportController::class, 'index'])->middleware('can:reports.view')->name('reports.index');

        // ---------------------------------------------------------------- notifications
        $router->get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
        $router->post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read_all');
        $router->group(['middleware' => ['can:logs.view,settings.manage']], function ($router): void {
            $router->get('/notifications/emails', [NotificationController::class, 'emails'])->name('notifications.emails');
            $router->get('/notifications/emails/{id}', [NotificationController::class, 'email'])->name('notifications.email');
            $router->post('/notifications/emails/{id}/retry', [NotificationController::class, 'retry'])->middleware('throttle:20,10')->name('notifications.retry');
        });
        $router->get('/notifications/{id}', [NotificationController::class, 'open'])->name('notifications.open');

        // ---------------------------------------------------------------- backups & retention
        $router->group(['middleware' => ['can:backups.manage']], function ($router): void {
            $router->get('/backups', [BackupController::class, 'index'])->name('backups.index');
            $router->post('/backups', [BackupController::class, 'create'])->middleware('throttle:10,10')->name('backups.create');
            $router->post('/backups/settings', [BackupController::class, 'settings'])->name('backups.settings');
            $router->get('/backups/{name}', [BackupController::class, 'download'])->name('backups.download');
            $router->post('/backups/{name}/delete', [BackupController::class, 'destroy'])->name('backups.destroy');
        });
        $router->get('/customers/{id}/export', [CustomerController::class, 'export'])->middleware('can:customers.manage')->name('customers.export');

        // ---------------------------------------------------------------- shipping & marketing
        $router->group(['middleware' => ['can:shipping.manage']], function ($router): void {
            $router->get('/shipping', [ShippingController::class, 'index'])->name('shipping.index');
            $router->post('/shipping/zones', [ShippingController::class, 'saveZone'])->name('shipping.zones.store');
            $router->post('/shipping/zones/{id}', [ShippingController::class, 'saveZone'])->name('shipping.zones.update');
            $router->post('/shipping/rates', [ShippingController::class, 'saveRate'])->name('shipping.rates.store');
            $router->post('/shipping/rates/{id}', [ShippingController::class, 'saveRate'])->name('shipping.rates.update');
            $router->post('/shipping/rates/{id}/delete', [ShippingController::class, 'deleteRate'])->name('shipping.rates.destroy');
        });
        $router->group(['middleware' => ['can:coupons.manage']], function ($router): void {
            $router->get('/coupons', [CouponController::class, 'index'])->name('coupons.index');
            $router->post('/coupons', [CouponController::class, 'save'])->name('coupons.store');
            $router->post('/coupons/{id}', [CouponController::class, 'save'])->name('coupons.update');
            $router->post('/coupons/{id}/delete', [CouponController::class, 'destroy'])->name('coupons.destroy');
        });
        $router->group(['middleware' => ['can:reviews.manage']], function ($router): void {
            $router->get('/reviews', [ReviewController::class, 'index'])->name('reviews.index');
            $router->post('/reviews/{id}', [ReviewController::class, 'action'])->name('reviews.action');
        });
        $router->group(['middleware' => ['can:marketing.manage']], function ($router): void {
            $router->get('/content', [ContentController::class, 'index'])->name('content.index');
            $router->post('/content/{type}', [ContentController::class, 'save'])->name('content.store');
            $router->post('/content/{type}/{id}', [ContentController::class, 'save'])->name('content.update');
            $router->post('/content/{type}/{id}/delete', [ContentController::class, 'destroy'])->name('content.destroy');
        });
        $router->group(['middleware' => ['can:marketing.manage']], function ($router): void {
            $seo = \App\Controllers\Admin\SeoController::class;
            $router->get('/seo', [$seo, 'index'])->name('seo.index');
            $router->post('/seo/general', [$seo, 'general'])->name('seo.general');
            $router->post('/seo/pages', [$seo, 'savePage'])->name('seo.pages');
            $router->post('/seo/redirects', [$seo, 'saveRedirect'])->name('seo.redirects.store');
            $router->post('/seo/redirects/{id}', [$seo, 'saveRedirect'])->name('seo.redirects.update');
            $router->post('/seo/redirects/{id}/delete', [$seo, 'deleteRedirect'])->name('seo.redirects.destroy');
            $router->post('/seo/not-found/clear', [$seo, 'clearNotFound'])->name('seo.not_found.clear');
            $router->post('/seo/not-found/{id}/delete', [$seo, 'clearNotFound'])->name('seo.not_found.destroy');
            $tr = \App\Controllers\Admin\TrackingController::class;
            $router->get('/tracking', [$tr, 'index'])->name('tracking.index');
            $router->post('/tracking', [$tr, 'save'])->name('tracking.save');
            $router->post('/tracking/test', [$tr, 'test'])->middleware('throttle:5,1')->name('tracking.test');
        });
        $router->group(['middleware' => ['can:newsletter.manage']], function ($router): void {
            $router->get('/newsletter', [NewsletterController::class, 'index'])->name('newsletter.index');
            $router->post('/newsletter/{id}/unsubscribe', [NewsletterController::class, 'unsubscribe'])->name('newsletter.unsubscribe');
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
