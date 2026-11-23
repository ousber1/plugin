<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            \Illuminate\Support\Facades\Route::middleware('installer')->group(base_path('routes/install.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Runs before sessions/cookies so an uninstalled site redirects to /install instead of failing.
        $middleware->prepend(\App\Http\Middleware\EnsureInstalled::class);
        $middleware->web(append: [
            \App\Http\Middleware\SetLocale::class,
            \App\Http\Middleware\SecurityHeaders::class,
        ]);
        // Installer runs before APP_KEY exists: no sessions/cookies, guarded by a file token instead.
        $middleware->group('installer', [\App\Http\Middleware\SecurityHeaders::class]);
        $middleware->alias(['staff' => \App\Http\Middleware\EnsureStaff::class]);
        $middleware->validateCsrfTokens(except: ['paiement/callback/*']);
        $middleware->encryptCookies(except: ['hoc_locale']);
        $middleware->trustProxies(at: '*');
        $middleware->redirectGuestsTo(fn (Request $r) => $r->is('admin', 'admin/*') ? route('admin.login') : route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

// cPanel layout: web files live in ~/public_html next to the application folder (~/hocreative).
// Lets artisan / cron (storage:link, PDFs, asset versions) use the right public directory.
if (! is_file($app->basePath('public/index.php')) && is_file(dirname($app->basePath()).'/public_html/index.php')) {
    $app->usePublicPath(dirname($app->basePath()).'/public_html');
}

return $app;
