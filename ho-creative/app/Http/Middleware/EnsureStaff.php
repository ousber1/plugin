<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureStaff
{
    public function handle(Request $request, Closure $next)
    {
        $u = $request->user();
        if (! $u || ! $u->is_staff || ! $u->is_active) {
            abort(403);
        }

        return $next($request);
    }
}
