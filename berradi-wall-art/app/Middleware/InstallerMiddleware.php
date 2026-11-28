<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\App;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;

/**
 * Permanently locks the installer once storage/installed.lock exists.
 */
final class InstallerMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next, string ...$params): Response
    {
        if (is_file(App::instance()->storagePath('installed.lock'))) {
            throw new HttpException(404, 'Installer is locked');
        }
        return $next($request);
    }
}
