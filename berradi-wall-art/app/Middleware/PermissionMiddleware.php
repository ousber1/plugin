<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\Auth;

/**
 * Usage: "can:products.manage" or "can:orders.view,orders.manage" (any of).
 */
final class PermissionMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next, string ...$params): Response
    {
        foreach ($params as $permission) {
            if (Auth::can($permission)) {
                return $next($request);
            }
        }
        throw new HttpException(403, __('admin.forbidden'));
    }
}
