<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use App\Services\MediaService;
use App\Support\Audit;
use App\Support\Settings;
use App\Support\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Digital store — separate from free courses and certificates. */
class StoreController extends Controller
{
    private function cardQuery()
    {
        return Product::published()->with('category')
            ->withAvg(['approvedReviews' => fn ($q) => $q], 'rating')->withCount('approvedReviews')
            ->withCount(['orderItems as sales_count' => fn ($q) => $q->whereHas('order', fn ($o) => $o->where('status', 'paid'))]);
    }

    public function index(Request $request)
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:120'], 'category' => ['nullable', 'string', 'max:120'],
            'sort' => ['nullable', 'in:newest,popular,price_asc,price_desc,title'],
        ]);
        $categories = Category::ofType('product')->where('is_active', true)
            ->withCount(['products' => fn ($q) => $q->published()])->get()->where('products_count', '>', 0)->values();
        $active = isset($data['category']) ? $categories->firstWhere('slug', $data['category']) : null;
        $sort = $data['sort'] ?? 'newest';

        $query = $this->cardQuery()
            ->searchTranslated(['title', 'subtitle', 'description'], $data['q'] ?? null)
            ->when($active, fn ($q) => $q->where('category_id', $active->id))
            ->when(isset($data['category']) && ! $active, fn ($q) => $q->whereRaw('1 = 0'));
        match ($sort) {
            'popular' => $query->orderByDesc('sales_count'),
            'price_asc' => $query->orderBy('price_cents'),
            'price_desc' => $query->orderByDesc('price_cents'),
            'title' => $query->orderBy('slug'),
            default => $query->orderByDesc('is_featured')->orderBy('sort_order')->latest(),
        };

        $all = Product::published()->get(['id', 'price_cents', 'compare_price_cents', 'price_usd_cents', 'compare_price_usd_cents']);

        return view('store.index', [
            'products' => $query->paginate(12)->withQueryString(),
            'categories' => $categories, 'active' => $active, 'sort' => $sort, 'q' => $data['q'] ?? '',
            'stats' => [
                'count' => $all->count(),
                'min' => $all->min(fn ($p) => $p->price()),
                'discount' => $all->map(fn ($p) => $p->discountPercent())->filter()->max(),
            ],
        ]);
    }

    public function show(Product $product)
    {
        abort_unless($product->status === 'published' || auth()->user()?->isAdmin(), 404);
        $product->loadAvg('approvedReviews', 'rating')->loadCount('approvedReviews');
        $owned = auth()->check() ? OrderItem::where('product_id', $product->id)
            ->whereHas('order', fn ($q) => $q->where('user_id', auth()->id())->where('status', 'paid'))->latest('id')->first() : null;
        $related = $this->cardQuery()->whereKeyNot($product->id)
            ->orderByRaw('CASE WHEN category_id = ? THEN 0 ELSE 1 END', [$product->category_id ?? 0])->latest()->limit(4)->get();

        return view('store.show', [
            'product' => $product, 'owned' => $owned, 'related' => $related,
            'reviews' => $product->approvedReviews()->with('user:id,name')->latest()->limit(20)->get(),
            'myReview' => auth()->check() ? ProductReview::where('product_id', $product->id)->where('user_id', auth()->id())->first() : null,
            'inCart' => in_array($product->id, Shop::cartIds(), true),
        ]);
    }

    public function currency(string $currency)
    {
        abort_unless(in_array($currency, Shop::CURRENCIES, true), 404);
        session(['currency' => $currency]);

        return back();
    }

    public function cart()
    {
        return view('store.cart', Shop::totals());
    }

    public function add(Request $request, Product $product)
    {
        abort_unless($product->status === 'published', 404);
        Shop::addToCart($product);
        if ($request->boolean('buy_now')) {
            return redirect()->route('store.checkout');
        }

        return back()->with('success', __('أُضيف «:title» إلى السلة.', ['title' => $product->tr('title')]));
    }

    public function remove(Product $product)
    {
        Shop::removeFromCart($product->id);

        return back()->with('info', __('حُذف المنتج من السلة.'));
    }

    public function coupon(Request $request)
    {
        if ($request->boolean('remove')) {
            session()->forget('coupon');

            return back();
        }
        $request->validate(['code' => ['required', 'string', 'max:40']]);
        if (! Coupon::findValid($request->input('code'))) {
            return back()->withErrors(['code' => __('رمز الخصم غير صالح أو منتهي.')]);
        }
        session(['coupon' => strtoupper(trim($request->input('code')))]);

        return back()->with('success', __('تم تطبيق الخصم.'));
    }

    public function checkout()
    {
        $totals = Shop::totals();
        if ($totals['items']->isEmpty()) {
            return redirect()->route('store.cart');
        }

        return view('store.checkout', $totals + ['methods' => Shop::methods($totals['currency']), 'user' => auth()->user()]);
    }

    public function place(Request $request)
    {
        $totals = Shop::totals();
        if ($totals['items']->isEmpty()) {
            return redirect()->route('store.cart');
        }
        $methods = Shop::methods($totals['currency']);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:40', 'regex:/^\+?[0-9 ]{8,20}$/'],
            'email' => ['required', 'email', 'max:190'],
            'payment_method' => ['required', 'in:'.implode(',', array_keys($methods) ?: ['none'])],
            'customer_note' => ['nullable', 'string', 'max:1000'],
            'accept' => ['accepted'],
        ], ['phone.regex' => __('أدخل رقم هاتف صحيحًا، مثل 0612345678.')]);

        $order = DB::transaction(function () use ($data, $totals, $request) {
            $order = Order::create([
                'reference' => Shop::newReference(), 'user_id' => $request->user()->id,
                'name' => $data['name'], 'phone' => $data['phone'], 'email' => mb_strtolower($data['email']),
                'subtotal_cents' => $totals['subtotal'], 'discount_cents' => $totals['discount'], 'amount_cents' => $totals['total'],
                'coupon_code' => $totals['coupon']?->code, 'currency' => $totals['currency'], 'payment_method' => $data['payment_method'],
                'status' => 'pending', 'customer_note' => $data['customer_note'] ?? null,
            ]);
            foreach ($totals['items'] as $p) {
                $order->items()->create(['product_id' => $p->id, 'title' => $p->tr('title'), 'price_cents' => $p->price($totals['currency']), 'billing' => $p->billing]);
            }
            $totals['coupon']?->increment('used');

            return $order;
        });
        if (! $request->user()->phone) {
            $request->user()->forceFill(['phone' => $data['phone']])->save();
        }
        Shop::clearCart();
        Audit::log('order.created', $order, ['reference' => $order->reference, 'total' => $order->amount_cents, 'currency' => $order->currency]);

        return redirect()->route('store.order', $order)->with('success', __('تم تسجيل طلبك :ref.', ['ref' => $order->reference]));
    }

    public function order(Request $request, Order $order)
    {
        abort_unless($order->user_id === $request->user()->id || $request->user()->isAdmin(), 404);
        $order->load('items.product', 'product');
        $wa = Settings::whatsappLink(__('السلام عليكم، أريد إتمام دفع طلبي :ref بمبلغ :total.', ['ref' => $order->reference, 'total' => $order->totalLabel()]));

        return view('store.order', [
            'order' => $order, 'banks' => Shop::bankAccounts(), 'whatsapp' => $wa,
            'agency' => ['names' => Settings::get('agency_names'), 'receiver' => Settings::get('agency_receiver'), 'city' => Settings::get('agency_city'), 'phone' => Settings::get('agency_phone')],
            'paypal' => Settings::get('paypal_link'), 'instructions' => Settings::get('bank_instructions'),
        ]);
    }

    public function receipt(Request $request, Order $order)
    {
        abort_unless($order->user_id === $request->user()->id, 404);
        abort_unless(in_array($order->status, ['pending', 'review'], true), 403);
        $request->validate([
            'receipt' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'transfer_reference' => ['nullable', 'string', 'max:120'],
        ]);
        if ($order->receipt_path) {
            Storage::disk('local')->delete($order->receipt_path);
        }
        $order->receipt_path = $request->file('receipt')->store('receipts', 'local');
        $order->transfer_reference = $request->input('transfer_reference') ?: $order->transfer_reference;
        $order->status = 'review';
        $order->save();
        Audit::log('order.receipt_uploaded', $order, ['reference' => $order->reference]);

        return back()->with('success', __('استلمنا وصل الدفع. سنراجعه ونفعّل طلبك في أقرب وقت.'));
    }

    public function account(Request $request)
    {
        $orders = Order::where('user_id', $request->user()->id)->with('items.product')->latest()->get();
        $licenses = $orders->where('status', 'paid')->flatMap->items->filter(fn ($i) => $i->license_key)
            ->groupBy('license_key')->map(fn ($g) => $g->sortByDesc('expires_at')->first())->values();

        return view('store.account', compact('orders', 'licenses'));
    }

    public function download(Request $request, Order $order, MediaService $media, ?OrderItem $item = null)
    {
        abort_unless($order->user_id === $request->user()->id && $order->status === 'paid', 403);
        $item ??= $order->items()->first();
        abort_unless($item && $item->order_id === $order->id, 404);
        $product = $item->product ?? $order->product;
        abort_unless($product && $product->file_path, 404);
        // Subscriptions: downloads (updates) stay available while any licence for this product is active.
        $active = OrderItem::where('product_id', $product->id)->where('license_key', $item->license_key)
            ->whereHas('order', fn ($q) => $q->where('user_id', $order->user_id)->where('status', 'paid'))->get()->contains(fn ($i) => $i->isActive());
        if ($item->license_key && ! $active) {
            return redirect()->route('store.show', $product)->with('error', __('انتهى اشتراكك في التحديثات. جدّده للتحميل من جديد بنفس المفتاح.'));
        }

        return $media->respond($product->disk, $product->file_path, $product->original_name);
    }

    public function review(Request $request, Product $product)
    {
        $bought = OrderItem::where('product_id', $product->id)->whereHas('order', fn ($q) => $q->where('user_id', $request->user()->id)->where('status', 'paid'))->exists();
        abort_unless($bought, 403);
        $data = $request->validate(['rating' => ['required', 'integer', 'between:1,5'], 'body' => ['nullable', 'string', 'max:2000']]);
        ProductReview::updateOrCreate(['product_id' => $product->id, 'user_id' => $request->user()->id],
            $data + ['is_approved' => ! Settings::get('store_reviews_moderated')]);

        return back()->with('success', Settings::get('store_reviews_moderated') ? __('شكرًا! ستظهر مراجعتك بعد اعتمادها.') : __('شكرًا على مراجعتك!'));
    }
}
