<?php

namespace App\Support;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/** Store helpers: currency, cart (session), totals, payment methods and licence fulfilment. */
class Shop
{
    public const CURRENCIES = ['MAD', 'USD'];

    public static function currency(): string
    {
        $c = session('currency', Settings::get('store_default_currency', 'MAD'));

        return $c === 'USD' && Settings::get('store_usd_enabled') ? 'USD' : 'MAD';
    }

    public static function toUsd(int $madCents): int
    {
        $rate = max(0.01, (float) Settings::get('store_usd_rate', 10));

        return (int) (round($madCents / $rate / 100) * 100) ?: 100;
    }

    public static function money(int $cents, ?string $currency = null): string
    {
        $currency ??= self::currency();
        $amount = $cents / 100;
        $n = fmod($amount, 1.0) == 0.0 ? number_format($amount, 0, '.', ' ') : number_format($amount, 2, '.', ' ');
        if ($currency === 'USD') {
            return '$'.$n;
        }

        return app()->getLocale() === 'ar' ? $n.' درهم' : $n.' MAD';
    }

    /** Payment methods enabled for a currency. */
    public static function methods(?string $currency = null): array
    {
        $all = [
            'bank' => ['label' => __('تحويل بنكي'), 'desc' => __('تحويل أو إيداع في حسابنا البنكي'), 'icon' => 'briefcase', 'currencies' => ['MAD'],
                'note' => __('حوّل المبلغ إلى أحد حساباتنا، ثم ارفع وصل التحويل في الصفحة التالية ليتم تفعيل طلبك.')],
            'agency' => ['label' => __('وكالات تحويل الأموال'), 'desc' => Settings::get('agency_names') ?: 'Cash Plus · Wafacash · Barid Cash', 'icon' => 'receipt', 'currencies' => ['MAD'],
                'note' => __('أرسل المبلغ باسم المستفيد الظاهر في الصفحة التالية، ثم ارفع صورة الوصل.')],
            'whatsapp' => ['label' => __('الدفع عبر واتساب'), 'desc' => __('نتواصل معك ونتفق على طريقة الدفع'), 'icon' => 'whatsapp', 'currencies' => ['MAD', 'USD'],
                'note' => __('بعد التأكيد نفتح لك محادثة واتساب برقم طلبك لإتمام الدفع.')],
            'paypal' => ['label' => 'PayPal', 'desc' => __('الدفع بالبطاقة أو حساب PayPal'), 'icon' => 'globe', 'currencies' => ['USD'],
                'note' => __('ستجد رابط الدفع في الصفحة التالية. أضف رقم الطلب في الملاحظة.')],
        ];
        $enabled = array_filter($all, fn ($m, $k) => (bool) Settings::get('pay_'.$k.'_enabled'), ARRAY_FILTER_USE_BOTH);
        if ($currency) {
            $enabled = array_filter($enabled, fn ($m) => in_array($currency, $m['currencies'], true));
        }

        return $enabled;
    }

    public static function bankAccounts(): array
    {
        $list = collect(Settings::get('bank_accounts') ?: [])->filter(fn ($a) => ! empty($a['rib']))->values()->all();
        if (! $list && Settings::get('bank_rib')) {
            $list[] = ['bank' => Settings::get('bank_name'), 'holder' => Settings::get('bank_account_holder'), 'rib' => Settings::get('bank_rib')];
        }

        return $list;
    }

    // ---- Cart (session: list of product ids; digital licences, quantity 1) ----

    public static function cartIds(): array
    {
        return array_values(array_unique(array_map('intval', (array) session('cart', []))));
    }

    public static function cart(): Collection
    {
        $ids = self::cartIds();

        return $ids ? Product::published()->whereIn('id', $ids)->get()->sortBy(fn ($p) => array_search($p->id, $ids))->values() : collect();
    }

    public static function cartCount(): int
    {
        return count(self::cartIds());
    }

    public static function addToCart(Product $product): void
    {
        session(['cart' => array_values(array_unique([...self::cartIds(), $product->id]))]);
    }

    public static function removeFromCart(int $id): void
    {
        session(['cart' => array_values(array_diff(self::cartIds(), [$id]))]);
    }

    public static function clearCart(): void
    {
        session()->forget(['cart', 'coupon']);
    }

    /** @return array{items: Collection, subtotal:int, discount:int, total:int, coupon:?Coupon, currency:string} */
    public static function totals(?Collection $items = null, ?string $currency = null): array
    {
        $items ??= self::cart();
        $currency ??= self::currency();
        $subtotal = (int) $items->sum(fn ($p) => $p->price($currency));
        $coupon = Coupon::findValid(session('coupon'));
        $discount = $coupon ? $coupon->discountFor($subtotal, $currency) : 0;

        return ['items' => $items, 'subtotal' => $subtotal, 'discount' => $discount, 'total' => $subtotal - $discount, 'coupon' => $coupon, 'currency' => $currency];
    }

    public static function newReference(): string
    {
        do {
            $ref = 'ORD-'.now()->format('y').'-'.Str::upper(Str::random(6));
        } while (Order::where('reference', $ref)->exists());

        return $ref;
    }

    /** Called when an order becomes paid: licence keys and subscription periods. */
    public static function fulfil(Order $order): void
    {
        foreach ($order->items()->get() as $item) {
            if ($item->license_key) {
                continue;
            }
            $previous = $item->product_id ? OrderItem::where('product_id', $item->product_id)->whereNotNull('license_key')
                ->whereHas('order', fn ($q) => $q->where('user_id', $order->user_id)->where('status', 'paid')->whereKeyNot($order->id))
                ->orderByDesc('expires_at')->first() : null;
            $key = $previous?->license_key ?? self::licenseKey();
            $expires = null;
            if (in_array($item->billing, ['year', 'month'], true)) {
                $from = $previous?->expires_at && $previous->expires_at->isFuture() ? $previous->expires_at->copy() : now();
                $expires = $item->billing === 'year' ? $from->addYear() : $from->addMonth();
            }
            $item->forceFill(['license_key' => $key, 'expires_at' => $expires])->save();
        }
    }

    public static function licenseKey(): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $parts = [];
        for ($p = 0; $p < 4; $p++) {
            $s = '';
            for ($i = 0; $i < 5; $i++) {
                $s .= $chars[random_int(0, strlen($chars) - 1)];
            }
            $parts[] = $s;
        }

        return implode('-', $parts);
    }
}
