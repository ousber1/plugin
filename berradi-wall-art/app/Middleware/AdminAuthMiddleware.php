<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Auth;

/**
 * Requires an authenticated, active staff user.
 */
final class AdminAuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next, string ...$params): Response
    {
        if (Auth::user() === null) {
            if ($request->isAjax()) {
                return Response::json(['ok' => false, 'message' => 'Unauthenticated'], 401);
            }
            if ($request->method() === 'GET') {
                Session::put('url.intended_admin', $request->path());
            }
            return Response::redirect(route('admin.login'));
        }
        return $next($request);
    }
}
