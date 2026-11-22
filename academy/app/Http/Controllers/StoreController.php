<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Services\MediaService;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Optional digital store — separate from free enrollment and certificates. */
class StoreController extends Controller
{
    public function index()
    {
        return view('store.index', ['products' => Product::published()->latest()->paginate(12)]);
    }

    public function show(Product $product)
    {
        abort_unless($product->status === 'published', 404);

        return view('store.show', compact('product'));
    }

    public function checkout(Request $request, Product $product)
    {
        abort_unless($product->status === 'published', 404);
        $request->validate(['accept' => ['accepted']]);

        $order = Order::where('user_id', $request->user()->id)->where('product_id', $product->id)->where('status', 'pending')->first();
        if (! $order) {
            $order = new Order([
                'reference' => 'ORD-'.Str::upper(Str::random(8)),
                'user_id' => $request->user()->id,
                'product_id' => $product->id,
                'amount_cents' => $product->price_cents,
                'currency' => 'MAD',
                'status' => 'pending',
            ]);
            $order->save();
        }

        return redirect()->route('store.order', $order);
    }

    public function order(Request $request, Order $order)
    {
        abort_unless($order->user_id === $request->user()->id || $request->user()->isAdmin(), 404);
        $bank = [
            'name' => Settings::get('bank_name'),
            'holder' => Settings::get('bank_account_holder'),
            'rib' => Settings::get('bank_rib'),
            'instructions' => Settings::get('bank_instructions'),
        ];

        return view('store.order', ['order' => $order->load('product'), 'bank' => $bank]);
    }

    public function download(Request $request, Order $order, MediaService $media)
    {
        abort_unless($order->user_id === $request->user()->id && $order->status === 'paid', 403);
        $product = $order->product;
        abort_unless($product->file_path, 404);

        return $media->respond($product->disk, $product->file_path, $product->original_name);
    }
}
