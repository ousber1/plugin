<?php

namespace App\Http\Controllers;

use App\Models\Page;

class PageController extends Controller
{
    public function show(Page $page)
    {
        abort_unless($page->status === 'published' || auth()->user()?->isAdmin(), 404);

        return view('pages.show', compact('page'));
    }
}
