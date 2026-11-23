<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Sends every request to the installer until storage/app/installed.lock exists. */
class EnsureInstalled
{
    public function handle(Request $request, Closure $next)
    {
        if (! is_installed() && ! app()->runningUnitTests() && ! app()->runningInConsole() && ! $request->is('install', 'install/*', 'up', 'assets/*', 'favicon.svg')) {
            return redirect('/install');
        }

        return $next($request);
    }
}
