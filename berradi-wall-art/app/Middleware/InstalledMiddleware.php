<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\App;
use App\Core\Request;
use App\Core\Response;

/**
 * Sends every request to the installation wizard until the application is installed.
 */
final class InstalledMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next, string ...$params): Response
    {
        $path = $request->path();
        $isInstaller = $path === '/install' || str_starts_with($path, '/install/');
        if (!App::instance()->isInstalled() && !$isInstaller) {
            return Response::redirect(url('/install'));
        }
        return $next($request);
    }
}
