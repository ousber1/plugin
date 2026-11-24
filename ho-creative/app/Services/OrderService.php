<?php

namespace App\Services;

use App\Models\ArtworkFile;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductionJob;
use App\Models\ShippingZone;
use App\Services\Pricing\PriceResult;
use App\Services\Pricing\Totals;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Single order-creation path shared by storefront checkout, staff orders
 * (walk-in / phone / WhatsApp / B2B) and quote conversion.
 */
class OrderService
{
    public function __construct(
        protected Totals $totals,
        protected NumberGenerator $numbers,
        protected DeliveryEstimator $estimator,
        protected ProductionService $production,
    ) {}

    /** Normalised order line from a server-side price calculation. */
    public static function lineFromPrice(Product $product, PriceResult $price, array $extra = []): array
    {
        return array_merge([
            'product_id' => $product->id,
            'rule_set_id' => $price->ruleSetId,
            'rule_version' => $price->ruleVersion,
            'description' => $product->t('name', 'fr').($price->describe() ? ' – '.$price->describe() : ''),
            'selection' => $price->selection,
            'options_snapshot' => ['size' => $price->sizeLabel, 'options' => $price->options],
            'breakdown' => $price->snapshot(),
            'quantity' => $price->quantity,
            'width_mm' => $price->widthMm,
            'height_mm' => $price->heightMm,
            'unit_price' => $price->unitPrice,
            'subtotal' => $price->total,
            'manual_discount' => '0.00',
            'manual_discount_reason' => null,
            'artwork_mode' => $price->artworkMode ?: 'later',
            'requires_proof' => (bool) $product->requires_proof,
            'production_days' => $price->productionDays,
            'is_custom' => false,
        ], $extra);
    }

    /** Free-form staff line (custom job). */
    public static function customLine(string $description, int $qty, string $unitPrice, array $extra = []): array
    {
        $total = Money::str(Money::of($unitPrice)->multipliedBy($qty));

        return array_merge([
            'product_id' => null, 'rule_set_id' => null, 'rule_version' => null,
            'description' => $description, 'selection' => null, 'options_snapshot' => null,
            'breakdown' => ['components' => [['key' => 'custom', 'label' => $description, 'amount' => $total]], 'total' => $total, 'custom' => true],
            'quantity' => $qty, 'width_mm' => null, 'height_mm' => null,
            'unit_price' => Money::str($unitPrice, 4), 'subtotal' => $total,
            'manual_discount' => '0.00', 'manual_discount_reason' => null,
            'artwork_mode' => 'later', 'requires_proof' => false, 'production_days' => 3, 'is_custom' => true,
        ], $extra);
    }

    /**
     * @param  array  $lines  normalised lines (see lineFromPrice/customLine), optionally with
     *                        'cart_item_id', 'design_id', 'template_id', 'notes'.
     * @param  array  $meta  channel, user_id, created_by, shipping_method, shipping_zone_id, shipping_address,
     *                       billing_address, contact_*, notes, payment_method, coupon (Coupon), manual_discount,
     *                       discount_reason, checkout_key, quote_id, quote_revision, locale, status, deposit_required
     */
    public function create(Customer $customer, array $lines, array $meta = []): Order
    {
        if (! empty($meta['checkout_key']) && ($existing = Order::where('checkout_key', $meta['checkout_key'])->first())) {
            return $existing; // idempotent resubmission
        }

        return DB::transaction(function () use ($customer, $lines, $meta) {
            foreach ($lines as &$l) {
                $l['total'] = Money::str(Money::of($l['subtotal'])->minus(Money::of($l['manual_discount'] ?? 0)));
                if (Money::of($l['total'])->isNegative()) {
                    throw new \InvalidArgumentException('Remise de ligne supérieure au montant de la ligne.');
                }
            }
            unset($l);

            $zone = ! empty($meta['shipping_zone_id']) ? ShippingZone::find($meta['shipping_zone_id']) : null;
            $method = $meta['shipping_method'] ?? 'pickup';
            /** @var Coupon|null $coupon */
            $coupon = $meta['coupon'] ?? null;
            $t = $this->totals->compute($lines, $coupon, $zone, $method, $meta['manual_discount'] ?? 0);
            if ($t['coupon_error']) {
                $coupon = null;
                $t = $this->totals->compute($lines, null, $zone, $method, $meta['manual_discount'] ?? 0);
            }

            $maxDays = (int) collect($lines)->max('production_days');
            $est = $this->estimator->estimate($maxDays, $zone, $method);
            $needsProof = collect($lines)->contains(fn ($l) => $l['requires_proof']);

            $order = Order::create([
                'number' => $this->numbers->next('order'),
                'customer_id' => $customer->id,
                'user_id' => $meta['user_id'] ?? null,
                'created_by' => $meta['created_by'] ?? null,
                'quote_id' => $meta['quote_id'] ?? null,
                'quote_revision' => $meta['quote_revision'] ?? null,
                'channel' => $meta['channel'] ?? 'web',
                'status' => $meta['status'] ?? 'pending',
                'payment_method' => $meta['payment_method'] ?? null,
                'subtotal' => $t['subtotal'],
                'discount_total' => $t['discount_total'],
                'discount_reason' => $meta['discount_reason'] ?? ($coupon ? 'Code promo '.$coupon->code : null),
                'coupon_code' => $coupon?->code,
                'shipping_total' => $t['shipping_total'],
                'tax_rate' => $t['tax_rate'],
                'tax_total' => $t['tax_total'],
                'total' => $t['total'],
                'deposit_required' => $meta['deposit_required'] ?? '0.00',
                'shipping_method' => $method,
                'shipping_zone_id' => $zone?->id,
                'shipping_address' => $meta['shipping_address'] ?? null,
                'billing_address' => $meta['billing_address'] ?? null,
                'contact_name' => $meta['contact_name'] ?? $customer->name,
                'contact_email' => $meta['contact_email'] ?? $customer->email,
                'contact_phone' => $meta['contact_phone'] ?? $customer->phone,
                'notes' => $meta['notes'] ?? null,
                'internal_notes' => $meta['internal_notes'] ?? null,
                'guest_token' => empty($meta['user_id']) ? Str::random(48) : null,
                'locale' => $meta['locale'] ?? app()->getLocale(),
                'estimated_ready_at' => $est['ready']->toDateString(),
                'estimated_delivery_at' => $est['delivery']->toDateString(),
                'estimate_depends_on_proof' => $needsProof,
                'checkout_key' => $meta['checkout_key'] ?? null,
                'placed_at' => now(),
                'is_demo' => $meta['is_demo'] ?? false,
            ]);

            foreach (array_values($lines) as $i => $l) {
                $item = $order->items()->create([
                    'product_id' => $l['product_id'], 'rule_set_id' => $l['rule_set_id'], 'rule_version' => $l['rule_version'],
                    'description' => mb_substr($l['description'], 0, 500), 'selection' => $l['selection'],
                    'options_snapshot' => $l['options_snapshot'], 'breakdown' => $l['breakdown'],
                    'quantity' => $l['quantity'], 'width_mm' => $l['width_mm'], 'height_mm' => $l['height_mm'],
                    'unit_price' => $l['unit_price'], 'subtotal' => $l['subtotal'],
                    'manual_discount' => $l['manual_discount'] ?? '0.00', 'manual_discount_reason' => $l['manual_discount_reason'] ?? null,
                    'total' => $l['total'], 'artwork_mode' => $l['artwork_mode'],
                    'design_id' => $l['design_id'] ?? null, 'template_id' => $l['template_id'] ?? null,
                    'requires_proof' => $l['requires_proof'], 'production_days' => $l['production_days'],
                    'is_custom' => $l['is_custom'], 'notes' => $l['notes'] ?? null, 'sort' => $i,
                    'remake_of_id' => $l['remake_of_id'] ?? null, 'remake_reason' => $l['remake_reason'] ?? null,
                ]);
                if (! empty($l['cart_item_id'])) {
                    ArtworkFile::where('cart_item_id', $l['cart_item_id'])->whereNull('order_item_id')
                        ->update(['order_item_id' => $item->id, 'cart_item_id' => null]);
                }
                if (! empty($l['manual_discount']) && Money::of($l['manual_discount'])->isPositive()) {
                    AuditLogger::log('order.line_discount', $item, null, ['amount' => $l['manual_discount']], $l['manual_discount_reason'] ?? null);
                }
                $this->refreshArtworkStatus($item);
                $this->production->createJobFor($item, $est['ready']);
            }

            if ($coupon) {
                Coupon::whereKey($coupon->id)->increment('used_count');
            }
            if (Money::of($t['manual_discount'])->isPositive()) {
                AuditLogger::log('order.manual_discount', $order, null, ['amount' => $t['manual_discount']], $meta['discount_reason'] ?? null);
            }
            AuditLogger::log('order.created', $order, null, ['channel' => $order->channel, 'total' => $order->total]);

            return $order->fresh(['items']);
        });
    }

    public function refreshArtworkStatus(OrderItem $item): void
    {
        $has = $item->artworkFiles()->exists() || ($item->design && ! empty($item->design->exports));
        if ($has && $item->artwork_status === 'pending') {
            $item->update(['artwork_status' => 'received']);
        }
        if ($item->artwork_mode === 'design_service' && $item->artwork_status === 'pending') {
            $item->update(['artwork_status' => 'in_review']);
        }
    }

    public function recalculateTotals(Order $order): void
    {
        $lines = $order->items->map(fn ($i) => ['total' => $i->total, 'product_id' => $i->product_id])->all();
        $coupon = $order->coupon_code ? Coupon::where('code', $order->coupon_code)->first() : null;
        $t = $this->totals->compute($lines, null, $order->shippingZone, $order->shipping_method,
            Money::of($order->discount_total));
        $order->update(['subtotal' => $t['subtotal'], 'shipping_total' => $t['shipping_total'], 'tax_total' => $t['tax_total'], 'total' => $t['total'], 'discount_total' => $t['discount_total']]);
    }

    public function cancel(Order $order, string $reason): void
    {
        DB::transaction(function () use ($order, $reason) {
            $order->refresh();
            if ($order->status === 'cancelled') {
                return;
            }
            $old = $order->only(['status']);
            foreach ($order->jobs as $job) {
                $this->production->transition($job, 'cancelled', $reason, force: true);
            }
            $order->update(['status' => 'cancelled', 'cancelled_at' => now()]);
            AuditLogger::log('order.cancelled', $order, $old, ['status' => 'cancelled'], $reason);
        });
    }

    public function confirm(Order $order): void
    {
        if ($order->status === 'pending') {
            $order->update(['status' => 'confirmed']);
            AuditLogger::log('order.confirmed', $order, ['status' => 'pending'], ['status' => 'confirmed']);
        }
    }

    public function syncFulfillment(Order $order): void
    {
        $order->refresh();
        if ($order->status === 'cancelled') {
            return;
        }
        $stages = $order->jobs()->where('stage', '!=', 'cancelled')->pluck('stage');
        if ($stages->isEmpty()) {
            return;
        }
        $ready = ['ready_for_pickup', 'shipped', 'delivered'];
        $printingOrLater = ['printing', 'finishing', 'quality_control', 'packing', 'ready_for_pickup', 'shipped', 'delivered'];
        $updates = [];
        if ($stages->every(fn ($s) => $s === 'delivered')) {
            $updates = ['fulfillment_status' => $order->shipping_method === 'pickup' ? 'picked_up' : 'delivered', 'status' => 'completed'];
        } elseif ($stages->every(fn ($s) => in_array($s, ['shipped', 'delivered']))) {
            $updates = ['fulfillment_status' => 'shipped', 'status' => 'ready'];
        } elseif ($stages->every(fn ($s) => in_array($s, $ready))) {
            $updates = ['fulfillment_status' => 'ready', 'status' => 'ready'];
        } elseif ($stages->contains(fn ($s) => in_array($s, $printingOrLater))) {
            $updates = ['fulfillment_status' => 'in_production', 'status' => in_array($order->status, ['pending', 'confirmed']) ? 'in_production' : $order->status];
        }
        if ($updates) {
            $order->update($updates);
        }
    }

    /**
     * Rebuild lines from an old order with CURRENT prices and current option availability.
     *
     * @return array{lines: array<int, array>, issues: array<int, string>}
     */
    public function reorderLines(Order $order, \App\Services\Pricing\PricingEngine $engine): array
    {
        $lines = [];
        $issues = [];
        foreach ($order->items as $item) {
            $product = $item->product;
            if (! $item->product_id || ! $product || ! $product->isPurchasable()) {
                $issues[] = __('« :item » n\'est plus disponible à la commande en ligne.', ['item' => $item->description]);

                continue;
            }
            $sel = (array) $item->selection;
            $sel['artwork_mode'] = 'later';
            $price = $engine->forProduct($product, $sel);
            if (! $price->valid) {
                $issues[] = __('« :item » : :err', ['item' => $product->t('name'), 'err' => implode(' ', $price->errors)]);

                continue;
            }
            $lines[] = ['product' => $product, 'selection' => $price->selection, 'price' => $price];
        }

        return compact('lines', 'issues');
    }
}
