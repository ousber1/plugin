<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * Check if the authenticated user has the admin role.
     * Redirect back with an error message if not.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user() || $request->user()->role !== 'admin') {
            return redirect()
                ->route('dashboard')
                ->with('error', 'You do not have permission to access this area.');
        }

        return $next($request);
    }
}
