<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Csrf;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;

/**
 * Verifies the CSRF token on every state-changing request (form field "_token" or header X-CSRF-Token).
 */
final class CsrfMiddleware implements MiddlewareInterface
{
    /**
     * Paths exempt from CSRF: payment provider callbacks (authenticated by the provider's signature) and the
     * bank's return to the shop (secret order token in the URL, read-only: it never changes anything).
     */
    private const EXCEPT = ['/webhooks/', '/payment-return/'];

    public function handle(Request $request, callable $next, string ...$params): Response
    {
        if ($request->isPost()) {
            foreach (self::EXCEPT as $prefix) {
                if (str_starts_with($request->path(), $prefix)) {
                    return $next($request);
                }
            }
            $token = $request->input('_token');
            if (!is_string($token) || $token === '') {
                $token = $request->header('X-CSRF-Token');
            }
            if (!Csrf::verify(is_string($token) ? $token : null)) {
                throw new HttpException(419, 'CSRF token mismatch');
            }
        }
        return $next($request);
    }
}
