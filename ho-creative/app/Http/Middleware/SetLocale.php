<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        $available = array_keys(config('hocreative.locales'));
        if (in_array($request->query('lang'), $available, true)) {
            $request->session()->put('locale', $request->query('lang'));
        }
        $locale = $request->session()->get('locale')
            ?? $request->user()?->locale
            ?? $request->cookie('hoc_locale')
            ?? 'fr';
        if (! in_array($locale, $available, true)) {
            $locale = 'fr';
        }
        app()->setLocale($locale);
        \Carbon\Carbon::setLocale($locale);

        return $next($request);
    }
}
