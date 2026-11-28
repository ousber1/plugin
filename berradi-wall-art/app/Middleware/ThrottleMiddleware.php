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
 * Generic request throttle per route path + client IP. Usage: "throttle:10,1" (10 requests / 1 minute).
 * "throttle:240,1,session" keeps the counter in the session (high-frequency endpoints such as live pricing,
 * so they never write to the database). Only POST requests are counted.
 * Counters are independent per route: heavy use of one endpoint never blocks another one.
 * Before installation (no database yet) the session counter is always used.
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
        $useSession = ($params[2] ?? '') === 'session';
        $key = 'throttle:' . $request->path();

        if ($useSession || !App::instance()->isInstalled()) {
            $now = time();
            $hits = array_values(array_filter((array) Session::get('_throttle_' . $key, []), fn ($ts) => (int) $ts > $now - $minutes * 60));
            if (count($hits) >= $max) {
                throw new HttpException(429, __('common.too_many_requests'));
            }
            $hits[] = $now;
            Session::put('_throttle_' . $key, $hits);
            return $next($request);
        }

        $dbKey = mb_substr($key . '|' . $request->ip(), 0, 191);
        if (RateLimiter::hitsFor($dbKey, $minutes) >= $max) {
            throw new HttpException(429, __('common.too_many_requests'));
        }
        RateLimiter::hit($dbKey, $request->ip());
        return $next($request);
    }
}
