<?php

/*
 * Installation wizard routes. Locked forever once storage/installed.lock exists.
 *
 * @var \App\Core\Router $router
 */

use App\Controllers\Installer\InstallController;

$router->group(['prefix' => '/install', 'middleware' => ['installer']], function ($router): void {
    $router->get('/', [InstallController::class, 'index'])->name('installer.index');
    $router->get('/{step}', [InstallController::class, 'show'])->name('installer.step');
    $router->post('/{step}', [InstallController::class, 'store'])->middleware('throttle:30,1')->name('installer.store');
});
