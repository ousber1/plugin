<?php

namespace App\Http\Middleware;

use App\Support\Locales;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;

class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        $locale = $request->session()->get('locale')
            ?? $request->user()?->locale
            ?? $request->cookie('locale')
            ?? Locales::DEFAULT;

        // The admin dashboard is Arabic RTL.
        if ($request->is('admin', 'admin/*')) {
            $locale = 'ar';
        }

        app()->setLocale(Locales::isSupported($locale) ? $locale : Locales::DEFAULT);
        Carbon::setLocale(app()->getLocale());

        return $next($request);
    }
}
