<?php

/*
 * Storefront & customer account routes.
 *
 * @var \App\Core\Router $router
 */

use App\Controllers\Customer\AccountController;
use App\Controllers\Customer\AuthController as CustomerAuth;
use App\Controllers\Storefront\HomeController;
use App\Controllers\Storefront\LocaleController;

$router->get('/', [HomeController::class, 'index'])->name('home');
$router->get('/lang/{locale}', [LocaleController::class, 'switch'])->name('locale.switch');

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
