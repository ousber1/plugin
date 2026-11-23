<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\ShippingZone;
use App\Services\Pricing\PricingEngine;
use App\Services\Pricing\Totals;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * DB-backed cart keyed by a cookie token (guests) and/or user id. Prices are
 * NEVER stored on the cart: every read re-prices lines with the current
 * published rule set on the server.
 */
class CartService
{
    public const COOKIE = 'hoc_cart';

    public function __construct(protected PricingEngine $engine, protected Totals $totals) {}

    public function current(bool $create = false): ?Cart
    {
        $user = auth()->user();
        $token = request()->cookie(self::COOKIE);
        $cart = $token ? Cart::where('token', $token)->first() : null;
        if ($user && ! $user->is_staff) {
            $userCart = Cart::where('user_id', $user->id)->latest('id')->first();
            if ($cart && ! $cart->user_id) {
                if ($userCart && $userCart->id !== $cart->id) {
                    $cart->items()->update(['cart_id' => $userCart->id]);
                    $cart->delete();
                    $cart = $userCart;
                } else {
                    $cart->update(['user_id' => $user->id]);
                }
            } elseif (! $cart || $cart->user_id !== $user->id) {
                $cart = $userCart;
            }
        } elseif ($cart && $cart->user_id) {
            $cart = null; // never expose a user's cart to a logged-out browser
        }
        if (! $cart && $create) {
            $cart = Cart::create(['token' => Str::random(48), 'user_id' => ($user && ! $user->is_staff) ? $user->id : null]);
        }
        if ($cart) {
            Cookie::queue(self::COOKIE, $cart->token, 60 * 24 * 30);
        }

        return $cart;
    }

    public function count(): int
    {
        return (int) $this->current()?->items()->count();
    }

    /** @return array{lines: array, totals: array, errors: array, has_invalid: bool} */
    public function priced(?Cart $cart, ?ShippingZone $zone = null, string $method = 'pickup'): array
    {
        $lines = [];
        $invalid = false;
        foreach ($cart?->items()->with('product.publishedRuleSet', 'artworkFiles', 'design', 'template')->get() ?? [] as $item) {
            $p = $item->product;
            $price = $p && $p->isPurchasable() ? $this->engine->forProduct($p, $item->selection + ['artwork_mode' => $item->artwork_mode]) : null;
            $ok = $price && $price->valid;
            $invalid = $invalid || ! $ok;
            $lines[] = ['item' => $item, 'product' => $p, 'price' => $price, 'valid' => $ok,
                'errors' => $price?->errors ?: ($ok ? [] : [__('Ce produit n\'est plus disponible.')]),
                'total' => $ok ? $price->total : '0.00', 'product_id' => $p?->id];
        }
        $coupon = $cart?->coupon_code ? Coupon::where('code', $cart->coupon_code)->first() : null;
        $totals = $this->totals->compute(array_filter($lines, fn ($l) => $l['valid']), $coupon, $zone, $method);

        return ['lines' => $lines, 'totals' => $totals, 'has_invalid' => $invalid, 'coupon' => $coupon];
    }

    public function add(int $productId, array $selection, string $artworkMode, array $extra = []): CartItem
    {
        $cart = $this->current(true);

        return $cart->items()->create(['product_id' => $productId, 'selection' => $selection, 'artwork_mode' => $artworkMode] + $extra);
    }
}
