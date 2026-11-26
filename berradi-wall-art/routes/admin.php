<?php

/*
 * Administration routes (prefix configurable with ADMIN_PATH in .env).
 *
 * @var \App\Core\Router $router
 */

use App\Controllers\Admin\ActivityLogController;
use App\Controllers\Admin\AuthController;
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
    });
});
