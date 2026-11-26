<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Auth;

/**
 * Requires an authenticated customer account.
 */
final class CustomerAuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next, string ...$params): Response
    {
        if (Auth::customer() === null) {
            if ($request->isAjax()) {
                return Response::json(['ok' => false, 'message' => 'Unauthenticated'], 401);
            }
            if ($request->method() === 'GET') {
                Session::put('url.intended_customer', $request->path());
            }
            return Response::redirect(route('customer.login'));
        }
        return $next($request);
    }
}
