<?php

namespace App\Http\Middleware;

use App\Support\Settings;
use Closure;
use Illuminate\Http\Request;

/**
 * Maintenance mode switchable from the dashboard. Staff keep full access;
 * login stays reachable so administrators can sign in.
 */
class MaintenanceMode
{
    public function handle(Request $request, Closure $next)
    {
        if (! Settings::get('maintenance_enabled') || $request->user()?->isStaff()
            || $request->is('login', 'logout', 'admin', 'admin/*', 'css/*', 'js/*', 'fonts/*', 'up', 'storage/*', 'covers/*')) {
            return $next($request);
        }

        return response()->view('errors.maintenance', ['message' => Settings::tr('maintenance_message')], 503)->header('Retry-After', '3600');
    }
}
