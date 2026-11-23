<?php

namespace App\Http\Middleware;

use App\Support\Settings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Adds lazy-loading to images and (optionally) trims whitespace from public HTML pages. */
class OptimizeResponse
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        if (! $response instanceof Response || ! $request->isMethod('GET') || $request->is('admin', 'admin/*')
            || ! str_contains((string) $response->headers->get('Content-Type'), 'text/html') || $response->getStatusCode() !== 200) {
            return $response;
        }
        $html = $response->getContent();
        if (! is_string($html) || $html === '') {
            return $response;
        }
        $lazy = Settings::get('lazy_images');
        $minify = Settings::get('html_minify');
        if (! $lazy && ! $minify) {
            return $response;
        }
        if ($lazy) {
            // Images without an explicit loading attribute load lazily (the first two stay eager for LCP).
            $n = 0;
            $html = preg_replace_callback('/<img\b(?![^>]*\bloading=)([^>]*)>/i', function ($m) use (&$n) {
                return ++$n <= 2 ? $m[0] : '<img loading="lazy" decoding="async"'.$m[1].'>';
            }, $html) ?? $html;
        }
        if ($minify) {
            // Protect blocks where whitespace matters.
            $keep = [];
            $html = preg_replace_callback('#<(pre|textarea|script|style|code)\b[^>]*>.*?</\1>#is', function ($m) use (&$keep) {
                $keep[] = $m[0];

                return '@@KEEP'.(count($keep) - 1).'@@';
            }, $html) ?? $html;
            $html = preg_replace(['/<!--(?!\[if).*?-->/s', '/>\s+</', '/\s{2,}/'], ['', '> <', ' '], $html) ?? $html;
            $html = preg_replace_callback('/@@KEEP(\d+)@@/', fn ($m) => $keep[(int) $m[1]], $html) ?? $html;
        }
        $response->setContent($html);

        return $response;
    }
}
