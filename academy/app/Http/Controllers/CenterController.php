<?php

namespace App\Http\Controllers;

use App\Models\Center;
use Illuminate\Http\Request;

class CenterController extends Controller
{
    public function index(Request $request)
    {
        $centers = Center::active()->withCount(['courses' => fn ($q) => $q->published()])->get();
        $cities = $centers->map(fn ($c) => $c->tr('city'))->filter()->unique()->sort()->values();
        $city = (string) $request->query('city', '');
        $shown = $city !== '' ? $centers->filter(fn ($c) => $c->tr('city') === $city)->values() : $centers;

        return view('centers.index', compact('centers', 'cities', 'city', 'shown'));
    }

    public function show(Center $center)
    {
        abort_unless($center->is_active || auth()->user()?->isAdmin(), 404);
        $courses = $center->courses()->published()->withCardData()->latest('published_at')->get();
        $others = Center::active()->whereKeyNot($center->id)->limit(3)->get();

        return view('centers.show', compact('center', 'courses', 'others'));
    }
}
