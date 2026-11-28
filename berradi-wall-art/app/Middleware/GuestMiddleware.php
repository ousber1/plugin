<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Services\Auth;

/**
 * Redirects already-authenticated users away from login/register pages.
 * Usage: "guest:admin" or "guest:customer".
 */
final class GuestMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next, string ...$params): Response
    {
        $guard = $params[0] ?? 'customer';
        if ($guard === 'admin' && Auth::user() !== null) {
            return Response::redirect(route('admin.dashboard'));
        }
        if ($guard === 'customer' && Auth::customer() !== null) {
            return Response::redirect(route('customer.account'));
        }
        return $next($request);
    }
}
