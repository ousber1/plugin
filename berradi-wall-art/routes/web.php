<?php

/*
 * Storefront & customer account routes.
 *
 * @var \App\Core\Router $router
 */

use App\Controllers\Customer\AccountController;
use App\Controllers\Customer\AddressController;
use App\Controllers\Customer\OrderController as CustomerOrderController;
use App\Controllers\Customer\AuthController as CustomerAuth;
use App\Controllers\Customer\DesignController;
use App\Controllers\Storefront\BuilderController;
use App\Controllers\Storefront\CartController;
use App\Controllers\Storefront\CheckoutController;
use App\Controllers\Storefront\PaymentWebhookController;
use App\Controllers\Storefront\TrackingController;
use App\Controllers\Storefront\HomeController;
use App\Controllers\Storefront\CollectionController;
use App\Controllers\Storefront\LocaleController;
use App\Controllers\Storefront\NewsletterController;
use App\Controllers\Storefront\PageController;
use App\Controllers\Storefront\ProductController;
use App\Controllers\Storefront\ShopController;

$router->get('/', [HomeController::class, 'index'])->name('home');
$router->get('/lang/{locale}', [LocaleController::class, 'switch'])->name('locale.switch');

// catalogue
$router->get('/shop', [ShopController::class, 'index'])->name('shop.index');
$router->get('/shop/category/{slug}', [ShopController::class, 'category'])->name('shop.category');
$router->get('/best-sellers', [ShopController::class, 'bestSellers'])->name('shop.best_sellers');
$router->get('/new-arrivals', [ShopController::class, 'newArrivals'])->name('shop.new');
$router->get('/offers', [ShopController::class, 'offers'])->name('shop.offers');
$router->get('/collections', [CollectionController::class, 'index'])->name('collections.index');
$router->get('/collections/{slug}', [CollectionController::class, 'show'])->name('collections.show');
$router->get('/product/{slug}', [ProductController::class, 'show'])->name('product.show');

// custom wall art builder (photos are private: served only after an ownership check)
$router->get('/custom-wall-art', [BuilderController::class, 'index'])->name('builder.index');
$router->post('/builder/upload', [BuilderController::class, 'upload'])->middleware('throttle:40,10')->name('builder.upload');
$router->get('/builder/image/{uuid}/{variant}', [BuilderController::class, 'image'])->name('builder.image');
$router->post('/builder/image/{uuid}/delete', [BuilderController::class, 'deleteImage'])->name('builder.image.delete');
$router->post('/builder/price', [BuilderController::class, 'price'])->middleware('throttle:240,1,session')->name('builder.price');
$router->post('/builder/design', [BuilderController::class, 'saveDesign'])->middleware('throttle:30,10')->name('builder.design.save');
$router->get('/builder/design/{uuid}', [BuilderController::class, 'showDesign'])->name('builder.design.show');
$router->get('/builder/design/{uuid}/preview', [BuilderController::class, 'designPreview'])->name('builder.design.preview');
$router->post('/builder/design/{uuid}/delete', [BuilderController::class, 'deleteDesign'])->name('builder.design.delete');

// cart & checkout
$router->get('/cart', [CartController::class, 'index'])->name('cart.index');
$router->post('/cart/add', [CartController::class, 'add'])->middleware('throttle:60,1')->name('cart.add');
$router->post('/cart/item/{id}', [CartController::class, 'update'])->name('cart.update');
$router->post('/cart/item/{id}/remove', [CartController::class, 'remove'])->name('cart.remove');
$router->post('/cart/coupon', [CartController::class, 'coupon'])->middleware('throttle:15,10')->name('cart.coupon');
$router->post('/cart/shipping', [CartController::class, 'shipping'])->middleware('throttle:120,1,session')->name('cart.shipping');
$router->get('/checkout', [CheckoutController::class, 'show'])->name('checkout.index');
$router->post('/checkout', [CheckoutController::class, 'place'])->middleware('throttle:10,10')->name('checkout.place');
$router->get('/order/confirmation/{number}', [CheckoutController::class, 'confirmation'])->name('checkout.confirmation');
$router->get('/track', [TrackingController::class, 'form'])->name('order.track');
$router->post('/track', [TrackingController::class, 'lookup'])->middleware('throttle:20,10')->name('order.track.lookup');
$router->get('/artwork/{token}', [\App\Controllers\Storefront\ArtworkProofController::class, 'show'])->middleware('throttle:60,10')->name('artwork.proof');
$router->get('/artwork/{token}/preview', [\App\Controllers\Storefront\ArtworkProofController::class, 'preview'])->name('artwork.proof.preview');
$router->post('/artwork/{token}', [\App\Controllers\Storefront\ArtworkProofController::class, 'respond'])->middleware('throttle:10,10')->name('artwork.proof.respond');
$router->get('/track/{number}/{token}', [TrackingController::class, 'token'])->name('order.track.token');
// online payment providers (inactive until a gateway is implemented & configured; CSRF-exempt, signature-verified)
$router->post('/webhooks/payments/{provider}', [PaymentWebhookController::class, 'handle'])->name('payments.webhook');

// pages
$router->get('/about', [PageController::class, 'about'])->name('page.about');
$router->get('/contact', [PageController::class, 'contact'])->name('page.contact');
$router->post('/contact', [PageController::class, 'sendContact'])->middleware('throttle:5,10')->name('page.contact.send');
$router->get('/faq', [PageController::class, 'faq'])->name('page.faq');
$router->get('/terms', [PageController::class, 'terms'])->name('page.terms');

// newsletter
$router->post('/newsletter', [NewsletterController::class, 'subscribe'])->middleware('throttle:10,10')->name('newsletter.subscribe');
$router->get('/newsletter/unsubscribe/{token}', [NewsletterController::class, 'unsubscribe'])->name('newsletter.unsubscribe');

$router->group(['prefix' => '/account'], function ($router): void {
    // guests only
    $router->group(['middleware' => ['guest:customer']], function ($router): void {
        $router->get('/login', [CustomerAuth::class, 'showLogin'])->name('customer.login');
        $router->post('/login', [CustomerAuth::class, 'login'])->middleware('throttle:20,1')->name('customer.login.post');
        $router->get('/register', [CustomerAuth::class, 'showRegister'])->name('customer.register');
        $router->post('/register', [CustomerAuth::class, 'register'])->middleware('throttle:10,10')->name('customer.register.post');
        $router->get('/forgot-password', [CustomerAuth::class, 'showForgot'])->name('customer.password.request');
        $router->post('/forgot-password', [CustomerAuth::class, 'sendReset'])->middleware('throttle:5,10')->name('customer.password.email');
        $router->get('/reset-password/{token}', [CustomerAuth::class, 'showReset'])->name('customer.password.reset');
        $router->post('/reset-password', [CustomerAuth::class, 'reset'])->middleware('throttle:10,10')->name('customer.password.update');
    });

    // authenticated customers
    $router->group(['middleware' => ['auth.customer']], function ($router): void {
        $router->get('/', [AccountController::class, 'dashboard'])->name('customer.account');
        $router->get('/profile', [AccountController::class, 'profile'])->name('customer.profile');
        $router->get('/designs', [DesignController::class, 'index'])->name('customer.designs');
        $router->get('/orders', [CustomerOrderController::class, 'index'])->name('customer.orders');
        $router->get('/orders/{number}', [CustomerOrderController::class, 'show'])->name('customer.orders.show');
        $router->post('/orders/{number}/cancel', [CustomerOrderController::class, 'cancel'])->name('customer.orders.cancel');
        $router->get('/addresses', [AddressController::class, 'index'])->name('customer.addresses');
        $router->post('/addresses', [AddressController::class, 'store'])->name('customer.addresses.store');
        $router->post('/addresses/{id}', [AddressController::class, 'update'])->name('customer.addresses.update');
        $router->post('/addresses/{id}/delete', [AddressController::class, 'destroy'])->name('customer.addresses.delete');
        $router->post('/profile', [AccountController::class, 'updateProfile'])->name('customer.profile.update');
        $router->post('/password', [AccountController::class, 'updatePassword'])->name('customer.password.change');
        $router->post('/logout', [CustomerAuth::class, 'logout'])->name('customer.logout');
    });
});
