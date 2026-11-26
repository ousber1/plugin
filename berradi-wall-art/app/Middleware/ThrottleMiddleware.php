<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\App;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\RateLimiter;

/**
 * Generic request throttle per IP + route path. Usage: "throttle:10,1" (10 requests / 1 minute).
 * Only counts state-changing requests (POST).
 * Before installation (no database yet) a session-based counter is used instead.
 */
final class ThrottleMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next, string ...$params): Response
    {
        if (!$request->isPost()) {
            return $next($request);
        }
        $max = (int) ($params[0] ?? 30);
        $minutes = (int) ($params[1] ?? 1);
        $key = 'throttle:' . $request->path();

        if (!App::instance()->isInstalled()) {
            $now = time();
            $hits = array_values(array_filter((array) Session::get('_throttle_' . $key, []), fn ($ts) => (int) $ts > $now - $minutes * 60));
            if (count($hits) >= $max) {
                throw new HttpException(429, __('common.too_many_requests'));
            }
            $hits[] = $now;
            Session::put('_throttle_' . $key, $hits);
            return $next($request);
        }

        if (RateLimiter::tooManyAttempts($key, $request->ip(), $max, $minutes)) {
            throw new HttpException(429, __('common.too_many_requests'));
        }
        RateLimiter::hit($key, $request->ip());
        return $next($request);
    }
}
