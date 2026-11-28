<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\App;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\Auth;

/**
 * When "maintenance_mode" is enabled, the storefront returns 503 to visitors.
 * The admin area, the installer and logged-in staff are never blocked.
 */
final class MaintenanceMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next, string ...$params): Response
    {
        if (!App::instance()->isInstalled() || !setting('maintenance_mode', false)) {
            return $next($request);
        }
        $admin = '/' . trim((string) config('app.admin_path', 'admin'), '/');
        $path = $request->path();
        if ($path === $admin || str_starts_with($path, $admin . '/') || str_starts_with($path, '/lang/') || Auth::user() !== null) {
            return $next($request);
        }
        throw new HttpException(503, 'Maintenance');
    }
}
