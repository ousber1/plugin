<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Admin dashboard access: administrators and instructors. */
class EnsureStaff
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->user()?->isStaff(), 403);

        return $next($request);
    }
}
