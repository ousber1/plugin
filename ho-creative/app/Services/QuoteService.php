<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteRevision;
use App\Models\ShippingZone;
use App\Notifications\SimpleNotification;
use App\Services\Pricing\PricingEngine;
use App\Services\Pricing\Totals;
use App\Support\Money;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class QuoteService
{
    public function __construct(
        protected PricingEngine $engine, protected Totals $totals, protected NumberGenerator $numbers,
        protected Settings $settings, protected OrderService $orders,
    ) {}

    /**
     * Each line: ['product_id' => id, 'selection' => [...]] for catalog lines (priced on the server),
     * or ['description' => ..., 'quantity' => n, 'unit_price' => '12.50'] for custom lines.
     *
     * @return array<int, array>
     */
    public function priceLines(array $input): array
    {
        $lines = [];
        foreach ($input as $i => $row) {
            if (! empty($row['product_id'])) {
                $product = Product::findOrFail($row['product_id']);
                $sel = (array) ($row['selection'] ?? []);
                $price = $this->engine->forProduct($product, $sel, 'fr');
                if (! $price->valid) {
                    throw ValidationException::withMessages(["lines.$i" => $product->t('name').' : '.implode(' ', $price->errors)]);
                }
                $lines[] = OrderService::lineFromPrice($product, $price, ['artwork_mode' => $sel['artwork_mode'] ?? 'later']);
            } elseif (! empty($row['description'])) {
                $lines[] = OrderService::customLine($row['description'], max(1, (int) ($row['quantity'] ?? 1)), Money::str($row['unit_price'] ?? 0, 4));
            }
        }
        if (! $lines) {
            throw ValidationException::withMessages(['lines' => 'Le devis doit contenir au moins une ligne.']);
        }

        return $lines;
    }

    public function create(Customer $customer, array $lines, array $meta): Quote
    {
        return DB::transaction(function () use ($customer, $lines, $meta) {
            $quote = Quote::create([
                'number' => $this->numbers->next('quote'), 'customer_id' => $customer->id, 'created_by' => auth()->id(),
                'quote_request_id' => $meta['quote_request_id'] ?? null, 'channel' => $meta['channel'] ?? 'web',
                'status' => 'draft', 'current_revision' => 0, 'token' => Str::random(48),
                'expires_at' => $meta['expires_at'] ?? now()->addDays((int) $this->settings->get('quotes.validity_days', 30))->toDateString(),
                'locale' => $meta['locale'] ?? 'fr', 'is_demo' => $meta['is_demo'] ?? false,
            ]);
            $this->addRevision($quote, $lines, $meta);
            AuditLogger::log('quote.created', $quote, null, ['channel' => $quote->channel]);

            return $quote->fresh();
        });
    }

    public function addRevision(Quote $quote, array $lines, array $meta): QuoteRevision
    {
        if (in_array($quote->status, ['converted', 'accepted'])) {
            throw ValidationException::withMessages(['quote' => 'Un devis accepté ou converti ne peut plus être révisé.']);
        }

        $lines = array_map(fn ($l) => $l + ['total' => $l['subtotal']], $lines);

        return DB::transaction(function () use ($quote, $lines, $meta) {
            $zone = ! empty($meta['shipping_zone_id']) ? ShippingZone::find($meta['shipping_zone_id']) : null;
            $method = $meta['shipping_method'] ?? 'pickup';
            $t = $this->totals->compute($lines, null, $zone, $method, $meta['manual_discount'] ?? 0);
            if (Money::of($t['manual_discount'])->isPositive() && empty($meta['discount_reason'])) {
                throw ValidationException::withMessages(['discount_reason' => 'Le motif de la remise est obligatoire.']);
            }
            $n = $quote->current_revision + 1;
            $rev = $quote->revisions()->create([
                'revision' => $n, 'subtotal' => $t['subtotal'], 'discount_total' => $t['discount_total'],
                'discount_reason' => $meta['discount_reason'] ?? null, 'shipping_total' => $t['shipping_total'],
                'tax_rate' => $t['tax_rate'], 'tax_total' => $t['tax_total'], 'total' => $t['total'],
                'shipping_method' => $method, 'shipping_zone_id' => $zone?->id, 'notes' => $meta['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);
            foreach ($lines as $i => $l) {
                $rev->items()->create([
                    'product_id' => $l['product_id'], 'rule_set_id' => $l['rule_set_id'], 'rule_version' => $l['rule_version'],
                    'description' => mb_substr($l['description'], 0, 500), 'selection' => $l['selection'], 'options_snapshot' => $l['options_snapshot'],
                    'breakdown' => $l['breakdown'], 'quantity' => $l['quantity'], 'width_mm' => $l['width_mm'], 'height_mm' => $l['height_mm'],
                    'unit_price' => $l['unit_price'], 'total' => $l['subtotal'], 'artwork_mode' => $l['artwork_mode'], 'is_custom' => $l['is_custom'], 'sort' => $i,
                ]);
            }
            $quote->update(['current_revision' => $n, 'status' => 'draft']);
            if ($n > 1) {
                AuditLogger::log('quote.revised', $quote, null, ['revision' => $n, 'total' => $rev->total]);
            }
            if (Money::of($t['manual_discount'])->isPositive()) {
                AuditLogger::log('quote.manual_discount', $quote, null, ['revision' => $n, 'amount' => $t['manual_discount']], $meta['discount_reason'] ?? null);
            }

            return $rev;
        });
    }

    public function send(Quote $quote): void
    {
        $rev = $quote->revisionNo($quote->current_revision);
        $rev->update(['sent_at' => now()]);
        $quote->update(['status' => 'sent']);
        AuditLogger::log('quote.sent', $quote, null, ['revision' => $rev->revision]);
        if ($quote->customer->email) {
            try {
                Notification::route('mail', $quote->customer->email)->notify(new SimpleNotification('quote_sent', [
                    'quote' => $quote->number, 'total' => Money::format($rev->total, $quote->locale), 'url' => route('quote.public', $quote->token),
                    'expires' => $quote->expires_at?->format('d/m/Y'),
                ], $quote->locale));
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    /** Customer (secure link) or staff (on their behalf, e.g. WhatsApp) records the decision. */
    public function respond(Quote $quote, string $decision, ?string $name, ?string $note, bool $byStaff = false): Quote
    {
        return DB::transaction(function () use ($quote, $decision, $name, $note, $byStaff) {
            $quote = Quote::whereKey($quote->id)->lockForUpdate()->first();
            if (! in_array($quote->status, ['sent', 'draft']) || ($quote->status === 'draft' && ! $byStaff)) {
                throw ValidationException::withMessages(['quote' => __('Ce devis ne peut plus recevoir de réponse.')]);
            }
            if ($quote->isExpired() && ! $byStaff) {
                $quote->update(['status' => 'expired']);
                throw ValidationException::withMessages(['quote' => __('Ce devis a expiré. Contactez-nous pour une mise à jour.')]);
            }
            $status = $decision === 'accept' ? 'accepted' : 'rejected';
            $quote->update(['status' => $status, 'accepted_revision' => $status === 'accepted' ? $quote->current_revision : null,
                'responded_at' => now(), 'response_name' => $name, 'response_note' => $note]);
            AuditLogger::log('quote.'.$status, $quote, null, ['revision' => $quote->current_revision, 'by_staff' => $byStaff, 'name' => $name], $note);

            return $quote;
        });
    }

    /** Converts the accepted revision into exactly one order (unique quote_id on orders). */
    public function convert(Quote $quote, array $meta = []): \App\Models\Order
    {
        return DB::transaction(function () use ($quote, $meta) {
            $quote = Quote::whereKey($quote->id)->lockForUpdate()->first();
            if ($existing = $quote->order) {
                return $existing;
            }
            if ($quote->status !== 'accepted' || ! $quote->accepted_revision) {
                throw ValidationException::withMessages(['quote' => 'Seul un devis accepté peut être converti en commande.']);
            }
            $rev = $quote->revisionNo($quote->accepted_revision);
            $lines = $rev->items->sortBy('sort')->map(fn ($i) => [
                'product_id' => $i->product_id, 'rule_set_id' => $i->rule_set_id, 'rule_version' => $i->rule_version,
                'description' => $i->description, 'selection' => $i->selection, 'options_snapshot' => $i->options_snapshot,
                'breakdown' => $i->breakdown, 'quantity' => $i->quantity, 'width_mm' => $i->width_mm, 'height_mm' => $i->height_mm,
                'unit_price' => $i->unit_price, 'subtotal' => $i->total, 'manual_discount' => '0.00', 'artwork_mode' => $i->artwork_mode,
                'requires_proof' => $i->product ? (bool) $i->product->requires_proof : false,
                'production_days' => (int) ($i->breakdown['production_days'] ?? 3), 'is_custom' => $i->is_custom,
            ])->values()->all();
            $order = $this->orders->create($quote->customer, $lines, $meta + [
                'channel' => $quote->channel, 'quote_id' => $quote->id, 'quote_revision' => $rev->revision,
                'created_by' => auth()->id(), 'user_id' => $quote->customer->users()->value('id'),
                'shipping_method' => $rev->shipping_method, 'shipping_zone_id' => $rev->shipping_zone_id,
                'manual_discount' => Money::str(Money::of($rev->discount_total)), 'discount_reason' => $rev->discount_reason,
                'locale' => $quote->locale, 'status' => 'confirmed',
            ]);
            // The order must reproduce the accepted amounts exactly.
            if ($order->total !== $rev->total) {
                $order->update(['subtotal' => $rev->subtotal, 'discount_total' => $rev->discount_total, 'shipping_total' => $rev->shipping_total,
                    'tax_rate' => $rev->tax_rate, 'tax_total' => $rev->tax_total, 'total' => $rev->total]);
            }
            $quote->update(['status' => 'converted']);
            AuditLogger::log('quote.converted', $quote, null, ['order' => $order->number]);

            return $order;
        });
    }
}
