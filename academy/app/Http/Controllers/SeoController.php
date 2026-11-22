<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use App\Models\Tool;
use App\Support\Settings;

class SeoController extends Controller
{
    public function robots()
    {
        $lines = [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /dashboard',
            'Disallow: /learn/',
            'Disallow: /media/',
            'Disallow: /profile',
            'Disallow: /orders/',
            'Sitemap: '.route('sitemap'),
        ];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /** Only published public content is listed. */
    public function sitemap()
    {
        $urls = [
            ['loc' => route('home'), 'lastmod' => null],
            ['loc' => route('courses.index'), 'lastmod' => null],
            ['loc' => route('contact'), 'lastmod' => null],
        ];
        foreach (Course::published()->get(['slug', 'updated_at']) as $c) {
            $urls[] = ['loc' => route('courses.show', $c), 'lastmod' => $c->updated_at];
        }
        foreach (Page::where('status', 'published')->get(['slug', 'updated_at']) as $p) {
            $urls[] = ['loc' => route('pages.show', $p), 'lastmod' => $p->updated_at];
        }
        if (Settings::feature('blog')) {
            $urls[] = ['loc' => route('blog.index'), 'lastmod' => null];
            foreach (Post::published()->get(['slug', 'updated_at']) as $p) {
                $urls[] = ['loc' => route('blog.show', $p), 'lastmod' => $p->updated_at];
            }
        }
        if (Settings::feature('library')) {
            $urls[] = ['loc' => route('library.index'), 'lastmod' => null];
        }
        if (Settings::feature('tools')) {
            $urls[] = ['loc' => route('tools.index'), 'lastmod' => null];
            foreach (Tool::where('is_enabled', true)->get() as $t) {
                $urls[] = ['loc' => route('tools.show', $t), 'lastmod' => null];
            }
        }
        if (Settings::feature('verification')) {
            $urls[] = ['loc' => route('verify.index'), 'lastmod' => null];
        }
        if (Settings::feature('store')) {
            $urls[] = ['loc' => route('store.index'), 'lastmod' => null];
            foreach (Product::published()->get(['slug', 'updated_at']) as $p) {
                $urls[] = ['loc' => route('store.show', $p), 'lastmod' => $p->updated_at];
            }
        }

        return response()->view('sitemap', ['urls' => $urls])->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
