<?php

namespace App\Http\Controllers;

use App\Support\Locales;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function switch(Request $request, string $locale)
    {
        abort_unless(Locales::isSupported($locale), 404);
        $request->session()->put('locale', $locale);
        if ($request->user()) {
            $request->user()->forceFill(['locale' => $locale])->save();
        }
        $back = url()->previous();
        $target = str_starts_with($back, url('/')) ? $back : route('home');

        return redirect($target)->withCookie(cookie()->forever('locale', $locale));
    }
}
