<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;

/**
 * Adds defensive HTTP headers to every response.
 */
final class SecurityHeadersMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next, string ...$params): Response
    {
        $response = $next($request);
        $response->header('X-Content-Type-Options', 'nosniff');
        $response->header('X-Frame-Options', 'SAMEORIGIN');
        $response->header('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->header('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        $response->header('Cross-Origin-Opener-Policy', 'same-origin');
        $response->header(
            'Content-Security-Policy',
            "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
            . "font-src 'self' data: https://fonts.gstatic.com; img-src 'self' data: blob:; connect-src 'self'; "
            . "frame-src https://www.google.com https://maps.google.com; frame-ancestors 'self'; base-uri 'self'; "
            . "form-action 'self'; object-src 'none'"
        );
        if ($request->isSecure()) {
            $response->header('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }
        // private areas and secret-link pages are never indexed, whatever the page itself says
        $path = $request->path();
        $admin = '/' . trim((string) config('app.admin_path', 'admin'), '/');
        foreach ([$admin, '/install', '/account', '/cart', '/checkout', '/order/', '/track/', '/builder/', '/artwork/', '/quote/', '/invoice/', '/newsletter/'] as $prefix) {
            if ($path === rtrim($prefix, '/') || str_starts_with($path, rtrim($prefix, '/') . '/')) {
                $response->header('X-Robots-Tag', 'noindex, nofollow');
                break;
            }
        }
        $headers = $response->headers();
        if (!isset($headers['Cache-Control']) && !isset($headers['Location'])) {
            $response->header('Cache-Control', 'no-store, private');
        }
        return $response;
    }
}
