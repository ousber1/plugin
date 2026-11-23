<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\Template;
use App\Services\ArtworkService;
use App\Services\CartService;
use App\Services\Pricing\PricingEngine;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CartController extends Controller
{
    public function __construct(protected CartService $cart) {}

    public function index()
    {
        $cart = $this->cart->current();
        $priced = $this->cart->priced($cart);

        return view('store.cart', compact('cart', 'priced'));
    }

    public function add(Request $request, PricingEngine $engine, ArtworkService $artwork)
    {
        $product = Product::findOrFail($request->input('product_id'));
        abort_unless($product->isPurchasable(), 404);
        $data = $request->validate([
            'quantity' => 'required|integer|min:1', 'size' => 'nullable|string|max:60', 'width' => 'nullable|numeric', 'height' => 'nullable|numeric',
            'unit' => 'nullable|in:mm,cm', 'options' => 'nullable|array', 'options.*' => 'nullable|string|max:60',
            'artwork_mode' => 'nullable|in:'.implode(',', $product->artworkModes() ?: ['later']),
            'template_id' => 'nullable|integer', 'notes' => 'nullable|string|max:3000',
            'artwork_front' => ['nullable', ...array_slice($artwork->rules(), 1)],
            'artwork_back' => ['nullable', ...array_slice($artwork->rules(), 1)],
            'reference' => ['nullable', ...array_slice($artwork->rules(), 1)],
        ]);
        $mode = $data['artwork_mode'] ?? ($product->artworkModes()[0] ?? 'later');
        $input = collect($data)->only(['quantity', 'size', 'width', 'height', 'unit', 'options'])->all();
        $price = $engine->forProduct($product, $input + ['artwork_mode' => $mode]);
        if (! $price->valid) {
            throw ValidationException::withMessages(['options' => $price->errors]);
        }
        if ($mode === 'upload' && ! $request->hasFile('artwork_front')) {
            throw ValidationException::withMessages(['artwork_front' => __('Veuillez sélectionner votre fichier ou choisir « envoyer plus tard ».')]);
        }
        $template = null;
        if ($mode === 'template') {
            $template = Template::where('is_published', true)->where('product_id', $product->id)->find($data['template_id'] ?? 0);
            if (! $template) {
                throw ValidationException::withMessages(['template_id' => __('Veuillez choisir un modèle.')]);
            }
        }
        // Store the normalised selection (server-side), never prices.
        $item = $this->cart->add($product->id, $price->selection, $mode, ['template_id' => $template?->id, 'notes' => $data['notes'] ?? null]);
        foreach (['artwork_front' => 'front', 'artwork_back' => 'back', 'reference' => 'reference'] as $field => $side) {
            if ($request->hasFile($field)) {
                $artwork->store($request->file($field), $item, $side);
            }
        }
        $redirect = in_array($mode, ['template', 'editor']) ? route('editor', ['item' => $item->id]) : route('cart');
        if ($request->expectsJson()) {
            return response()->json(['redirect' => $redirect]);
        }

        return redirect($redirect)->with('ok', __('Produit ajouté au panier.'));
    }

    protected function ownItem(CartItem $item): CartItem
    {
        $cart = $this->cart->current();
        abort_unless($cart && $item->cart_id === $cart->id, 404);

        return $item;
    }

    public function update(Request $request, CartItem $item, PricingEngine $engine)
    {
        $this->ownItem($item);
        $qty = (int) $request->validate(['quantity' => 'required|integer|min:1'])['quantity'];
        $sel = $item->selection;
        $sel['quantity'] = $qty;
        $price = $engine->forProduct($item->product, $sel + ['artwork_mode' => $item->artwork_mode]);
        if (! $price->valid) {
            return back()->with('err', implode(' ', $price->errors));
        }
        $item->update(['selection' => $price->selection]);

        return back()->with('ok', __('Panier mis à jour.'));
    }

    public function remove(CartItem $item)
    {
        $this->ownItem($item)->delete();

        return back()->with('ok', __('Article retiré.'));
    }

    public function coupon(Request $request)
    {
        $code = strtoupper(trim((string) $request->input('code')));
        $cart = $this->cart->current(true);
        if ($code === '') {
            $cart->update(['coupon_code' => null]);

            return back();
        }
        $coupon = Coupon::where('code', $code)->first();
        if (! $coupon || ! $coupon->isUsable()) {
            return back()->with('err', __('Code promo invalide ou expiré.'));
        }
        $cart->update(['coupon_code' => $coupon->code]);

        return back()->with('ok', __('Code promo appliqué.'));
    }

    public function upload(Request $request, CartItem $item, ArtworkService $artwork)
    {
        $this->ownItem($item);
        $request->validate(['file' => $artwork->rules(), 'side' => 'required|in:front,back']);
        $artwork->store($request->file('file'), $item, $request->input('side'));
        if ($item->artwork_mode === 'later') {
            $item->update(['artwork_mode' => 'upload']);
        }

        return $request->expectsJson() ? response()->json(['ok' => true]) : back()->with('ok', __('Fichier ajouté.'));
    }
}
