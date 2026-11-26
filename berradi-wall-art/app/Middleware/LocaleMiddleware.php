<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Translator;
use App\Core\View;

/**
 * Resolves the active language: ?lang= > session > cookie > settings default > Accept-Language.
 */
final class LocaleMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next, string ...$params): Response
    {
        $locale = null;
        $q = $request->query('lang');
        if (is_string($q) && in_array($q, Translator::SUPPORTED, true)) {
            $locale = $q;
            Session::put('locale', $q);
        }
        $locale ??= Session::get('locale');
        $locale ??= $request->cookie('bwa_locale');
        if (!is_string($locale) || !in_array($locale, Translator::SUPPORTED, true)) {
            $locale = null;
        }
        if ($locale === null) {
            $locale = default_locale();
            $accept = strtolower((string) $request->header('Accept-Language', ''));
            if (str_starts_with($accept, 'ar')) {
                $locale = 'ar';
            }
        }
        Translator::setLocale($locale);
        View::share('locale', Translator::locale());
        View::share('dir', Translator::dir());
        return $next($request);
    }
}
