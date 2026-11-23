<?php

namespace App\Http\Middleware;

use App\Support\Settings;
use Closure;
use Illuminate\Http\Request;

/** Hides disabled modules (blog, library, tools, store, verification). */
class EnsureFeature
{
    public function handle(Request $request, Closure $next, string $feature)
    {
        abort_unless(Settings::feature($feature), 404);

        return $next($request);
    }
}
