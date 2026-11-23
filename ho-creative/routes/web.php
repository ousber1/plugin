<?php

use App\Http\Controllers\Account;
use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\Store;
use Illuminate\Support\Facades\Route;

/* ----------------------------------------------------------------------------
 | Storefront
 * --------------------------------------------------------------------------*/
Route::get('/', [Store\HomeController::class, 'index'])->name('home');
Route::get('/langue/{locale}', [Store\HomeController::class, 'locale'])->name('locale');
Route::get('/recherche', [Store\CatalogController::class, 'search'])->name('search');
Route::get('/categorie/{slug}', [Store\CatalogController::class, 'category'])->name('category');
Route::get('/produit/{slug}', [Store\CatalogController::class, 'product'])->name('product');
Route::post('/api/prix/{product}', [Store\CatalogController::class, 'price'])->middleware('throttle:pricing')->name('price');
Route::get('/modeles', [Store\CatalogController::class, 'templates'])->name('templates');
Route::get('/page/{slug}', [Store\HomeController::class, 'page'])->name('page');
Route::get('/sitemap.xml', [Store\HomeController::class, 'sitemap'])->name('sitemap');
Route::post('/produit/{product}/avis', [Store\CatalogController::class, 'review'])->middleware(['auth', 'throttle:forms'])->name('review.store');

Route::get('/editeur', [Store\EditorController::class, 'show'])->name('editor');
Route::post('/editeur/enregistrer', [Store\EditorController::class, 'save'])->middleware('throttle:uploads')->name('editor.save');

Route::get('/panier', [Store\CartController::class, 'index'])->name('cart');
Route::post('/panier/ajouter', [Store\CartController::class, 'add'])->name('cart.add');
Route::patch('/panier/{item}', [Store\CartController::class, 'update'])->name('cart.update');
Route::delete('/panier/{item}', [Store\CartController::class, 'remove'])->name('cart.remove');
Route::post('/panier/coupon', [Store\CartController::class, 'coupon'])->middleware('throttle:forms')->name('cart.coupon');
Route::post('/panier/{item}/fichier', [Store\CartController::class, 'upload'])->middleware('throttle:uploads')->name('cart.upload');

Route::get('/commande', [Store\CheckoutController::class, 'show'])->name('checkout');
Route::post('/commande', [Store\CheckoutController::class, 'place'])->middleware('throttle:forms')->name('checkout.place');
Route::get('/commande/merci/{order:number}', [Store\CheckoutController::class, 'thanks'])->name('checkout.thanks');

Route::get('/suivi/{token}', [Store\GuestOrderController::class, 'show'])->name('guest.order');
Route::post('/suivi/{token}/article/{item}/fichier', [Store\GuestOrderController::class, 'upload'])->middleware('throttle:uploads')->name('guest.upload');
Route::post('/suivi/{token}/bat/{proof}', [Store\GuestOrderController::class, 'proof'])->middleware('throttle:forms')->name('guest.proof');

Route::get('/devis', [Store\QuoteRequestController::class, 'create'])->name('quote.request');
Route::post('/devis', [Store\QuoteRequestController::class, 'store'])->middleware('throttle:forms')->name('quote.request.store');
Route::get('/devis/{token}', [Store\PublicQuoteController::class, 'show'])->name('quote.public');
Route::post('/devis/{token}/reponse', [Store\PublicQuoteController::class, 'respond'])->middleware('throttle:forms')->name('quote.public.respond');
Route::get('/devis/{token}/pdf', [Store\PublicQuoteController::class, 'pdf'])->name('quote.public.pdf');

Route::get('/paiement/{order:number}/payer', [Store\PaymentController::class, 'start'])->name('payment.start');
Route::post('/paiement/callback/{provider}', [Store\PaymentController::class, 'callback'])->name('payment.callback');
Route::get('/paiement/retour', [Store\PaymentController::class, 'return'])->name('payment.return');
Route::get('/paiement/test/{order:number}', [Store\PaymentController::class, 'sandbox'])->name('payment.sandbox');
Route::post('/paiement/test/{order:number}', [Store\PaymentController::class, 'sandboxPay'])->name('payment.sandbox.pay');

/* Protected files: every request is authorised against the owning record. */
Route::get('/fichiers/artwork/{file}', [FileController::class, 'artwork'])->name('files.artwork');
Route::get('/fichiers/bat/{proof}', [FileController::class, 'proof'])->name('files.proof');
Route::get('/fichiers/document/{document}', [FileController::class, 'document'])->name('files.document');
Route::get('/fichiers/design/{design:uuid}/{side}', [FileController::class, 'design'])->name('files.design');
Route::get('/fichiers/piece-jointe/{attachment}', [FileController::class, 'attachment'])->name('files.attachment');

/* ----------------------------------------------------------------------------
 | Authentication
 * --------------------------------------------------------------------------*/
Route::middleware('guest')->group(function () {
    Route::get('/connexion', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/connexion', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::get('/admin/connexion', [AuthController::class, 'showLogin'])->name('admin.login');
    Route::get('/inscription', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/inscription', [AuthController::class, 'register'])->middleware('throttle:forms');
    Route::get('/mot-de-passe/oublie', [AuthController::class, 'showForgot'])->name('password.request');
    Route::post('/mot-de-passe/oublie', [AuthController::class, 'sendReset'])->middleware('throttle:forms')->name('password.email');
    Route::get('/mot-de-passe/reinitialiser/{token}', [AuthController::class, 'showReset'])->name('password.reset');
    Route::post('/mot-de-passe/reinitialiser', [AuthController::class, 'reset'])->middleware('throttle:forms')->name('password.update');
});
Route::post('/deconnexion', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

/* ----------------------------------------------------------------------------
 | Customer area
 * --------------------------------------------------------------------------*/
Route::middleware('auth')->prefix('compte')->name('account.')->group(function () {
    Route::get('/', [Account\AccountController::class, 'dashboard'])->name('dashboard');
    Route::get('/profil', [Account\AccountController::class, 'profile'])->name('profile');
    Route::put('/profil', [Account\AccountController::class, 'updateProfile'])->name('profile.update');
    Route::get('/commandes', [Account\OrderController::class, 'index'])->name('orders');
    Route::get('/commandes/{order:number}', [Account\OrderController::class, 'show'])->name('orders.show');
    Route::post('/commandes/{order:number}/recommander', [Account\OrderController::class, 'reorder'])->name('orders.reorder');
    Route::post('/commandes/{order:number}/article/{item}/fichier', [Account\OrderController::class, 'upload'])->middleware('throttle:uploads')->name('orders.upload');
    Route::post('/bat/{proof}', [Account\OrderController::class, 'proof'])->name('proof.respond');
    Route::get('/devis', [Account\AccountController::class, 'quotes'])->name('quotes');
    Route::get('/factures', [Account\AccountController::class, 'invoices'])->name('invoices');
    Route::get('/creations', [Account\AccountController::class, 'designs'])->name('designs');
    Route::resource('adresses', Account\AddressController::class)->except(['show'])->parameters(['adresses' => 'address'])->names('addresses');
});

/* ----------------------------------------------------------------------------
 | Back office
 * --------------------------------------------------------------------------*/
Route::middleware(['auth', 'staff'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');

    // Generic resources (categories, pages, coupons, zones, materials, suppliers, expenses …)
    Route::get('/r/{resource}', [Admin\ResourceController::class, 'index'])->name('r.index');
    Route::get('/r/{resource}/nouveau', [Admin\ResourceController::class, 'create'])->name('r.create');
    Route::post('/r/{resource}', [Admin\ResourceController::class, 'store'])->name('r.store');
    Route::get('/r/{resource}/{id}/modifier', [Admin\ResourceController::class, 'edit'])->name('r.edit');
    Route::put('/r/{resource}/{id}', [Admin\ResourceController::class, 'update'])->name('r.update');
    Route::delete('/r/{resource}/{id}', [Admin\ResourceController::class, 'destroy'])->name('r.destroy');
    Route::get('/r/{resource}/export.csv', [Admin\ResourceController::class, 'export'])->name('r.export');

    Route::resource('products', Admin\ProductController::class)->except(['show', 'destroy']);
    Route::get('products/{product}/pricing', [Admin\PricingController::class, 'edit'])->name('pricing.edit');
    Route::post('products/{product}/pricing', [Admin\PricingController::class, 'saveDraft'])->name('pricing.save');
    Route::post('products/{product}/pricing/preview', [Admin\PricingController::class, 'preview'])->name('pricing.preview');
    Route::post('products/{product}/pricing/publish', [Admin\PricingController::class, 'publish'])->name('pricing.publish');

    Route::get('templates', [Admin\TemplateController::class, 'index'])->name('templates.index');
    Route::get('templates/create', [Admin\TemplateController::class, 'create'])->name('templates.create');
    Route::post('templates', [Admin\TemplateController::class, 'store'])->name('templates.store');
    Route::get('templates/{template}/edit', [Admin\TemplateController::class, 'edit'])->name('templates.edit');
    Route::match(['put', 'post'], 'templates/{template}', [Admin\TemplateController::class, 'update'])->name('templates.update');

    Route::get('reviews', [Admin\ReviewController::class, 'index'])->name('reviews.index');
    Route::post('reviews/{review}', [Admin\ReviewController::class, 'moderate'])->name('reviews.moderate');

    Route::get('customers', [Admin\CustomerController::class, 'index'])->name('customers.index');
    Route::get('customers/create', [Admin\CustomerController::class, 'create'])->name('customers.create');
    Route::post('customers', [Admin\CustomerController::class, 'store'])->name('customers.store');
    Route::get('customers/{customer}', [Admin\CustomerController::class, 'show'])->name('customers.show');
    Route::get('customers/{customer}/edit', [Admin\CustomerController::class, 'edit'])->name('customers.edit');
    Route::put('customers/{customer}', [Admin\CustomerController::class, 'update'])->name('customers.update');
    Route::post('customers/{customer}/contacts', [Admin\CustomerController::class, 'addContact'])->name('customers.contacts');
    Route::post('customers/{customer}/notes', [Admin\CustomerController::class, 'addNote'])->name('customers.notes');
    Route::post('customers/{customer}/addresses', [Admin\CustomerController::class, 'addAddress'])->name('customers.addresses');

    Route::get('quote-requests', [Admin\QuoteController::class, 'requests'])->name('quote-requests.index');
    Route::get('quote-requests/{quoteRequest}', [Admin\QuoteController::class, 'request'])->name('quote-requests.show');
    Route::get('quotes', [Admin\QuoteController::class, 'index'])->name('quotes.index');
    Route::get('quotes/create', [Admin\QuoteController::class, 'create'])->name('quotes.create');
    Route::post('quotes', [Admin\QuoteController::class, 'store'])->name('quotes.store');
    Route::get('quotes/{quote}', [Admin\QuoteController::class, 'show'])->name('quotes.show');
    Route::get('quotes/{quote}/revise', [Admin\QuoteController::class, 'revise'])->name('quotes.revise');
    Route::post('quotes/{quote}/revise', [Admin\QuoteController::class, 'storeRevision'])->name('quotes.revision');
    Route::post('quotes/{quote}/send', [Admin\QuoteController::class, 'send'])->name('quotes.send');
    Route::post('quotes/{quote}/respond', [Admin\QuoteController::class, 'respond'])->name('quotes.respond');
    Route::post('quotes/{quote}/convert', [Admin\QuoteController::class, 'convert'])->name('quotes.convert');
    Route::post('quotes/line-price', [Admin\QuoteController::class, 'linePrice'])->name('quotes.line-price');

    Route::get('orders', [Admin\OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/create', [Admin\OrderController::class, 'create'])->name('orders.create');
    Route::post('orders', [Admin\OrderController::class, 'store'])->name('orders.store');
    Route::get('orders/{order}', [Admin\OrderController::class, 'show'])->name('orders.show');
    Route::post('orders/{order}/confirm', [Admin\OrderController::class, 'confirm'])->name('orders.confirm');
    Route::post('orders/{order}/cancel', [Admin\OrderController::class, 'cancel'])->name('orders.cancel');
    Route::post('orders/{order}/notes', [Admin\OrderController::class, 'notes'])->name('orders.notes');
    Route::post('orders/{order}/payments', [Admin\OrderController::class, 'payment'])->name('orders.payment');
    Route::post('orders/{order}/documents', [Admin\OrderController::class, 'document'])->name('orders.document');
    Route::post('orders/{order}/shipments', [Admin\OrderController::class, 'shipment'])->name('orders.shipment');
    Route::put('shipments/{shipment}', [Admin\OrderController::class, 'updateShipment'])->name('shipments.update');
    Route::post('order-items/{item}/artwork', [Admin\OrderController::class, 'artwork'])->name('items.artwork');
    Route::post('artwork/{file}/review', [Admin\OrderController::class, 'reviewArtwork'])->name('artwork.review');
    Route::post('order-items/{item}/proof', [Admin\OrderController::class, 'proof'])->name('items.proof');
    Route::post('proofs/{proof}/record', [Admin\OrderController::class, 'recordProofResponse'])->name('proofs.record');
    Route::post('order-items/{item}/remake', [Admin\OrderController::class, 'remake'])->name('items.remake');

    Route::get('production', [Admin\ProductionController::class, 'board'])->name('production.board');
    Route::get('production/list', [Admin\ProductionController::class, 'list'])->name('production.list');
    Route::get('production/{job}', [Admin\ProductionController::class, 'show'])->name('production.show');
    Route::get('production/{job}/sheet', [Admin\ProductionController::class, 'sheet'])->name('production.sheet');
    Route::post('production/{job}/transition', [Admin\ProductionController::class, 'transition'])->name('production.transition');
    Route::put('production/{job}', [Admin\ProductionController::class, 'update'])->name('production.update');
    Route::post('production/{job}/costs', [Admin\ProductionController::class, 'cost'])->name('production.cost');
    Route::post('production/{job}/materials', [Admin\ProductionController::class, 'material'])->name('production.material');

    Route::get('materials/{material}', [Admin\StockController::class, 'show'])->name('materials.show');
    Route::post('materials/{material}/adjust', [Admin\StockController::class, 'adjust'])->name('materials.adjust');
    Route::get('stock', [Admin\StockController::class, 'index'])->name('stock.index');

    Route::get('purchase-orders', [Admin\PurchaseController::class, 'index'])->name('po.index');
    Route::get('purchase-orders/create', [Admin\PurchaseController::class, 'create'])->name('po.create');
    Route::post('purchase-orders', [Admin\PurchaseController::class, 'store'])->name('po.store');
    Route::get('purchase-orders/{po}', [Admin\PurchaseController::class, 'show'])->name('po.show');
    Route::post('purchase-orders/{po}/status', [Admin\PurchaseController::class, 'status'])->name('po.status');
    Route::post('purchase-orders/{po}/receive', [Admin\PurchaseController::class, 'receive'])->name('po.receive');
    Route::post('purchase-orders/{po}/document', [Admin\PurchaseController::class, 'document'])->name('po.document');

    Route::get('deliveries', [Admin\OrderController::class, 'deliveries'])->name('deliveries.index');
    Route::get('payments', [Admin\FinanceController::class, 'payments'])->name('payments.index');
    Route::get('documents', [Admin\FinanceController::class, 'documents'])->name('documents.index');
    Route::post('documents/{document}/credit-note', [Admin\FinanceController::class, 'creditNote'])->name('documents.credit');

    Route::get('reports', [Admin\ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/{report}.csv', [Admin\ReportController::class, 'csv'])->name('reports.csv');

    Route::get('staff', [Admin\StaffController::class, 'index'])->name('staff.index');
    Route::get('staff/create', [Admin\StaffController::class, 'create'])->name('staff.create');
    Route::post('staff', [Admin\StaffController::class, 'store'])->name('staff.store');
    Route::get('staff/{user}/edit', [Admin\StaffController::class, 'edit'])->name('staff.edit');
    Route::put('staff/{user}', [Admin\StaffController::class, 'update'])->name('staff.update');

    Route::get('settings', [Admin\SettingsController::class, 'edit'])->name('settings.edit');
    Route::post('settings', [Admin\SettingsController::class, 'update'])->name('settings.update');
    Route::get('audit', [Admin\SettingsController::class, 'audit'])->name('audit.index');
});

Route::get('/robots.txt', function () {
    $lines = ['User-agent: *'];
    foreach (['/admin', '/compte', '/panier', '/commande', '/suivi', '/fichiers', '/editeur', '/install', '/paiement', '/api'] as $p) {
        $lines[] = 'Disallow: '.$p;
    }
    $lines[] = 'Sitemap: '.route('sitemap');

    return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain']);
});
