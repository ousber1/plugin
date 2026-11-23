<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;

trait RemembersIntendedUrl
{
    /** Accept ?intended= only for URLs on this site (prevents open redirects). */
    protected function rememberIntended(Request $request): void
    {
        $intended = (string) $request->query('intended', '');
        if ($intended !== '' && str_starts_with($intended, url('/').'/')) {
            $request->session()->put('url.intended', $intended);
        }
    }
}
