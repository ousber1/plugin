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

// cPanel layouts: tell artisan / cron (storage:link, PDFs, asset versions) where the web files are.
//  - public_html/hocreative  (everything extracted into public_html)
//  - ~/hocreative next to ~/public_html
if (! is_file($app->basePath('public/index.php'))) {
    foreach ([dirname($app->basePath()), dirname($app->basePath()).'/public_html'] as $web) {
        if (is_file($web.'/index.php') && is_file($web.'/.htaccess')) {
            $app->usePublicPath($web);
            break;
        }
    }
}

return $app;
