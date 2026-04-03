<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\WhatsappController;
use App\Http\Controllers\AdsController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\AuthController;

// Auth routes
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Language switcher
Route::get('/lang/{locale}', function ($locale) {
    if (in_array($locale, ['en', 'fr'])) {
        session(['locale' => $locale]);
    }
    return back();
})->name('lang.switch');

// All authenticated routes
Route::middleware(['auth'])->group(function () {
    // Dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // POS
    Route::prefix('pos')->name('pos.')->group(function () {
        Route::get('/', [PosController::class, 'index'])->name('index');
        Route::get('/products', [PosController::class, 'getProducts'])->name('products');
        Route::post('/sale', [PosController::class, 'createSale'])->name('sale');
        Route::get('/receipt/{id}', [PosController::class, 'receipt'])->name('receipt');
        Route::get('/sessions', [PosController::class, 'sessions'])->name('sessions');
        Route::post('/sessions/open', [PosController::class, 'openSession'])->name('sessions.open');
        Route::post('/sessions/{id}/close', [PosController::class, 'closeSession'])->name('sessions.close');
    });

    // Orders
    Route::resource('orders', OrderController::class);
    Route::patch('/orders/{id}/status', [OrderController::class, 'updateStatus'])->name('orders.status');

    // Products
    Route::get('/stock-management', [ProductController::class, 'stockManagement'])->name('products.stock-management');
    Route::resource('products', ProductController::class);
    Route::post('/products/{id}/adjust-stock', [ProductController::class, 'adjustStock'])->name('products.adjust-stock');
    Route::post('/products/import', [ProductController::class, 'import'])->name('products.import');

    // Customers
    Route::resource('customers', CustomerController::class);

    // WhatsApp
    Route::prefix('whatsapp')->name('whatsapp.')->group(function () {
        Route::get('/', [WhatsappController::class, 'index'])->name('index');
        Route::get('/conversations', [WhatsappController::class, 'getConversations'])->name('conversations');
        Route::get('/messages/{conversationId}', [WhatsappController::class, 'getMessages'])->name('messages');
        Route::post('/send', [WhatsappController::class, 'sendMessage'])->name('send');
        Route::get('/contacts', [WhatsappController::class, 'contacts'])->name('contacts');
        Route::post('/broadcast', [WhatsappController::class, 'broadcast'])->name('broadcast');
        Route::get('/automation', [WhatsappController::class, 'automationRules'])->name('automation');
        Route::post('/automation', [WhatsappController::class, 'storeAutomationRule'])->name('automation.store');
        Route::delete('/automation/{id}', [WhatsappController::class, 'deleteAutomationRule'])->name('automation.delete');
    });

    // Ads
    Route::prefix('ads')->name('ads.')->group(function () {
        Route::get('/', [AdsController::class, 'index'])->name('index');
        Route::get('/campaigns', [AdsController::class, 'campaigns'])->name('campaigns');
        Route::post('/campaigns', [AdsController::class, 'storeCampaign'])->name('campaigns.store');
        Route::put('/campaigns/{id}', [AdsController::class, 'updateCampaign'])->name('campaigns.update');
        Route::get('/ad-sets/{campaignId}', [AdsController::class, 'adSets'])->name('ad-sets');
        Route::post('/ad-sets', [AdsController::class, 'storeAdSet'])->name('ad-sets.store');
        Route::get('/ads/{adSetId}', [AdsController::class, 'ads'])->name('ads');
        Route::post('/ads', [AdsController::class, 'storeAd'])->name('ads.store');
        Route::put('/ads/{id}', [AdsController::class, 'updateAd'])->name('ads.update');
        Route::post('/metrics', [AdsController::class, 'storeMetrics'])->name('metrics.store');
        Route::post('/analyze/{adId}', [AdsController::class, 'analyze'])->name('analyze');
        Route::post('/analyze-all', [AdsController::class, 'analyzeAll'])->name('analyze-all');
        Route::post('/ai-suggestions/{adId}', [AdsController::class, 'aiSuggestions'])->name('ai-suggestions');
        Route::get('/report', [AdsController::class, 'report'])->name('report');
        Route::post('/sync-meta', [AdsController::class, 'syncMeta'])->name('sync-meta');
        Route::post('/sync-google', [AdsController::class, 'syncGoogle'])->name('sync-google');
    });

    // Invoices / Factures
    Route::prefix('invoices')->name('invoices.')->group(function () {
        Route::get('/', [InvoiceController::class, 'index'])->name('index');
        Route::get('/create', [InvoiceController::class, 'create'])->name('create');
        Route::get('/{saleId}/generate', [InvoiceController::class, 'generate'])->name('generate');
        Route::get('/{saleId}/pdf', [InvoiceController::class, 'downloadPdf'])->name('pdf');
    });

    // Reports
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::get('/sales', [ReportController::class, 'sales'])->name('sales');
        Route::get('/products', [ReportController::class, 'products'])->name('products');
        Route::get('/customers', [ReportController::class, 'customers'])->name('customers');
        Route::get('/profit', [ReportController::class, 'profit'])->name('profit');
        Route::get('/export-pdf', [ReportController::class, 'exportPdf'])->name('export-pdf');
        Route::get('/export-excel', [ReportController::class, 'exportExcel'])->name('export-excel');
    });

    // Settings
    Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
    Route::post('/settings', [SettingController::class, 'update'])->name('settings.update');
    Route::get('/profile', [SettingController::class, 'profile'])->name('profile');
    Route::post('/profile', [SettingController::class, 'updateProfile'])->name('profile.update');

    // Notifications
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::post('/{id}/read', [NotificationController::class, 'markAsRead'])->name('read');
        Route::post('/read-all', [NotificationController::class, 'markAllAsRead'])->name('read-all');
        Route::get('/unread-count', [NotificationController::class, 'getUnreadCount'])->name('unread-count');
    });

    // Admin only routes
    Route::middleware(['admin'])->prefix('admin')->name('admin.')->group(function () {
        Route::get('/users', [SettingController::class, 'users'])->name('users');
        Route::post('/users', [SettingController::class, 'storeUser'])->name('users.store');
        Route::put('/users/{id}', [SettingController::class, 'updateUser'])->name('users.update');
        Route::delete('/users/{id}', [SettingController::class, 'deleteUser'])->name('users.delete');
    });
});

// WhatsApp Webhook (no auth)
Route::get('/webhook/whatsapp', [WhatsappController::class, 'webhookVerify']);
Route::post('/webhook/whatsapp', [WhatsappController::class, 'webhook']);
