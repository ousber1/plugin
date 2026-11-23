<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\Template;
use App\Services\Pricing\PricingEngine;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function category(Request $request, string $slug)
    {
        $category = Category::published()->where('slug', $slug)->firstOrFail();
        $ids = $category->children()->published()->pluck('id')->push($category->id);
        $products = $this->filtered(Product::visible()->whereIn('category_id', $ids), $request)->paginate(24)->withQueryString();
        $children = $category->children()->published()->orderBy('sort')->get();

        return view('store.category', compact('category', 'products', 'children'));
    }

    public function search(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $query = Product::visible()->whereHas('category', fn ($c) => $c->where('is_published', true));
        if ($q !== '') {
            foreach (preg_split('/\s+/u', mb_strtolower($q)) as $word) {
                $query->where('search_text', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $word).'%');
            }
        }
        $products = $this->filtered($query, $request)->paginate(24)->withQueryString();

        return view('store.search', compact('products', 'q'));
    }

    protected function filtered($query, Request $request)
    {
        if ($request->query('mode') === 'instant') {
            $query->where('sale_mode', 'instant');
        } elseif ($request->query('mode') === 'quote') {
            $query->where('sale_mode', 'quote');
        }
        if ($max = $request->query('max')) {
            $query->whereNotNull('from_price')->where('from_price', '<=', (float) $max);
        }
        return match ($request->query('sort')) {
            'price_asc' => $query->orderByRaw('from_price IS NULL')->orderBy('from_price'),
            'price_desc' => $query->orderByDesc('from_price'),
            'name' => $query->orderBy('slug'),
            'new' => $query->latest(),
            default => $query->orderByDesc('is_popular')->orderBy('sort'),
        };
    }

    public function product(string $slug, PricingEngine $engine)
    {
        $product = Product::visible()->where('slug', $slug)->with(['category.parent', 'publishedRuleSet', 'templates' => fn ($q) => $q->where('is_published', true)])->firstOrFail();
        abort_unless($product->category->is_published, 404);
        $initial = $product->publishedRuleSet ? $engine->forProduct($product, []) : null;
        $reviews = $product->approvedReviews()->latest()->paginate(6);
        $rating = $product->ratingSummary();
        $canReview = auth()->check() && Order::where('user_id', auth()->id())->whereHas('items', fn ($q) => $q->where('product_id', $product->id))->exists()
            && ! Review::where('user_id', auth()->id())->where('product_id', $product->id)->exists();
        $related = Product::visible()->where('category_id', $product->category_id)->whereKeyNot($product->id)->limit(4)->get();

        return view('store.product', compact('product', 'initial', 'reviews', 'rating', 'canReview', 'related'));
    }

    /** Authoritative pricing endpoint used by the configurator (JSON). */
    public function price(Request $request, Product $product, PricingEngine $engine)
    {
        abort_unless(in_array($product->sale_mode, ['instant', 'quote']) || ($request->user()?->is_staff), 404);
        $input = $request->validate([
            'quantity' => 'nullable|integer|min:1|max:10000000', 'size' => 'nullable|string|max:60',
            'width' => 'nullable|numeric|min:0|max:100000', 'height' => 'nullable|numeric|min:0|max:100000',
            'unit' => 'nullable|in:mm,cm', 'options' => 'nullable|array', 'options.*' => 'nullable|string|max:60',
            'artwork_mode' => 'nullable|in:'.implode(',', Product::ARTWORK_MODES),
        ]);
        $result = $engine->forProduct($product, $input);

        return response()->json($result->toArray() + ['description' => $result->describe()]);
    }

    public function templates(Request $request)
    {
        $templates = Template::where('is_published', true)->with('product')
            ->when($request->query('produit'), fn ($q, $p) => $q->whereHas('product', fn ($w) => $w->where('slug', $p)))
            ->orderBy('sort')->paginate(24)->withQueryString();

        return view('store.templates', compact('templates'));
    }

    public function review(Request $request, Product $product)
    {
        $data = $request->validate(['rating' => 'required|integer|between:1,5', 'title' => 'nullable|string|max:120', 'body' => 'required|string|min:10|max:2000']);
        $order = Order::where('user_id', $request->user()->id)->whereHas('items', fn ($q) => $q->where('product_id', $product->id))->latest()->first();
        abort_unless($order, 403, __('Seuls les clients ayant commandé ce produit peuvent laisser un avis.'));
        Review::firstOrCreate(['user_id' => $request->user()->id, 'product_id' => $product->id], $data + [
            'order_id' => $order->id, 'author_name' => $request->user()->name, 'status' => 'pending',
        ]);

        return back()->with('ok', __('Merci ! Votre avis sera publié après modération.'));
    }
}
