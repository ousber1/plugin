<?php

namespace App\Http\Controllers;

use App\Models\Page;

class PageController extends Controller
{
    public function show(Page $page)
    {
        abort_unless($page->status === 'published' || auth()->user()?->isAdmin(), 404);

        if ($page->use_builder && $page->blocks) {
            return view('builder.page', [
                'blocks' => \App\Support\Builder::sanitize($page->blocks),
                'pageTitle' => $page->tr('seo_title') ?: $page->tr('title'),
                'pageDescription' => $page->tr('seo_description'),
                'canonical' => route('pages.show', $page),
            ]);
        }

        return view('pages.show', compact('page'));
    }
}
