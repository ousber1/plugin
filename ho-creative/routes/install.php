<?php

use App\Http\Controllers\InstallController;
use Illuminate\Support\Facades\Route;

// Stateless one-time installer. Returns 404 as soon as storage/app/installed.lock exists.
Route::get('/install', [InstallController::class, 'show'])->name('install');
Route::post('/install', [InstallController::class, 'run'])->name('install.run');
