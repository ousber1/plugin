<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\HomeSection;
use App\Models\Page;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index()
    {
        $sections = HomeSection::where('is_active', true)->orderBy('sort')->get();
        $categories = Category::published()->whereNull('parent_id')->orderBy('sort')->get();
        $popular = Product::visible()->where('is_popular', true)->with('category')->orderBy('sort')->limit(8)->get();
        $reviews = Review::where('status', 'approved')->with('product')->latest()->limit(6)->get();

        return view('store.home', compact('sections', 'categories', 'popular', 'reviews'));
    }

    public function locale(Request $request, string $locale)
    {
        abort_unless(array_key_exists($locale, config('hocreative.locales')), 404);
        $request->session()->put('locale', $locale);
        if ($u = $request->user()) {
            $u->forceFill(['locale' => $locale])->save();
        }

        return redirect()->back(fallback: route('home'))->withCookie(cookie('hoc_locale', $locale, 60 * 24 * 365));
    }

    public function page(string $slug)
    {
        $page = Page::where('slug', $slug)->where('is_published', true)->firstOrFail();

        return view('store.page', compact('page'));
    }

    public function sitemap()
    {
        $urls = [['loc' => route('home'), 'lastmod' => now()], ['loc' => route('quote.request'), 'lastmod' => now()], ['loc' => route('templates'), 'lastmod' => now()]];
        foreach (Category::published()->get() as $c) {
            $urls[] = ['loc' => route('category', $c->slug), 'lastmod' => $c->updated_at];
        }
        foreach (Product::visible()->whereHas('category', fn ($q) => $q->where('is_published', true))->get() as $p) {
            $urls[] = ['loc' => route('product', $p->slug), 'lastmod' => $p->updated_at];
        }
        foreach (Page::where('is_published', true)->get() as $p) {
            $urls[] = ['loc' => route('page', $p->slug), 'lastmod' => $p->updated_at];
        }

        return response()->view('store.sitemap', compact('urls'))->header('Content-Type', 'application/xml');
    }
}
