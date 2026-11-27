<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Per-request additions to the Content-Security-Policy and Permissions-Policy.
 *
 * The default policy only allows this site. A page that really needs an outside host (the CMI payment form,
 * the analytics / advertising tags the owner enabled) adds exactly that host here; the security headers
 * middleware merges the additions when the response is sent.
 */
final class Csp
{
    /** @var array<string,list<string>> directive => extra sources */
    private static array $extra = [];
    /** @var array<string,string> feature => allowlist, e.g. camera => self */
    private static array $features = [];

    public static function allow(string $directive, string ...$sources): void
    {
        foreach ($sources as $s) {
            // only scheme+host sources are accepted (no keywords such as 'unsafe-eval' can be smuggled in)
            if (!preg_match('#^https://[a-z0-9.*-]+(:\d+)?(/[A-Za-z0-9._/-]*)?$#', $s)) {
                throw new \InvalidArgumentException('Invalid CSP source: ' . $s);
            }
            if (!in_array($s, self::$extra[$directive] ?? [], true)) {
                self::$extra[$directive][] = $s;
            }
        }
    }

    /** Let this page use a browser feature (camera…) for its own origin only. */
    public static function allowFeature(string $feature): void
    {
        if (preg_match('/^[a-z-]+$/', $feature)) {
            self::$features[$feature] = 'self';
        }
    }

    public static function policy(): string
    {
        $base = [
            'default-src' => ["'self'"],
            'script-src' => ["'self'"],
            'style-src' => ["'self'", "'unsafe-inline'", 'https://fonts.googleapis.com'],
            'font-src' => ["'self'", 'data:', 'https://fonts.gstatic.com'],
            'img-src' => ["'self'", 'data:', 'blob:'],
            'media-src' => ["'self'", 'blob:'],
            'connect-src' => ["'self'"],
            'frame-src' => ['https://www.google.com', 'https://maps.google.com'],
            'frame-ancestors' => ["'self'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
            'object-src' => ["'none'"],
        ];
        foreach (self::$extra as $directive => $sources) {
            $base[$directive] = array_values(array_unique(array_merge($base[$directive] ?? ["'self'"], $sources)));
        }
        $parts = [];
        foreach ($base as $directive => $sources) {
            $parts[] = $directive . ' ' . implode(' ', $sources);
        }
        return implode('; ', $parts);
    }

    public static function permissions(): string
    {
        $features = ['camera' => '', 'microphone' => '', 'geolocation' => '', 'payment' => ''];
        foreach (self::$features as $f => $v) {
            $features[$f] = $v;
        }
        $out = [];
        foreach ($features as $f => $v) {
            $out[] = $f . '=(' . $v . ')';
        }
        return implode(', ', $out);
    }

    /** Tests only. */
    public static function reset(): void
    {
        self::$extra = [];
        self::$features = [];
    }
}
