<?php

/*
 * Storefront & customer account routes.
 *
 * @var \App\Core\Router $router
 */

use App\Controllers\Customer\AccountController;
use App\Controllers\Customer\AuthController as CustomerAuth;
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

// pages
$router->get('/about', [PageController::class, 'about'])->name('page.about');
$router->get('/contact', [PageController::class, 'contact'])->name('page.contact');
$router->post('/contact', [PageController::class, 'sendContact'])->middleware('throttle:5,10')->name('page.contact.send');
$router->get('/faq', [PageController::class, 'faq'])->name('page.faq');

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
        $router->post('/profile', [AccountController::class, 'updateProfile'])->name('customer.profile.update');
        $router->post('/password', [AccountController::class, 'updatePassword'])->name('customer.password.change');
        $router->post('/logout', [CustomerAuth::class, 'logout'])->name('customer.logout');
    });
});
