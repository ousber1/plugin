<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\Quote;
use App\Models\QuoteRevision;
use App\Models\Shipment;
use App\Support\Money;
use App\Support\Settings;
use Brick\Math\RoundingMode;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Issues financial / operational documents. The full content is frozen in
 * `snapshot` at issuance; PDFs are always rendered from that snapshot, so
 * later catalog, price or settings changes never alter an issued document.
 * Issued invoices are never edited: corrections go through credit notes.
 */
class DocumentService
{
    public function __construct(protected Settings $settings, protected NumberGenerator $numbers) {}

    protected function company(): array
    {
        $s = $this->settings;

        return [
            'name' => $s->get('company.legal_name'), 'brand' => $s->get('brand.name'), 'address' => $s->get('company.address'),
            'city' => $s->get('company.city'), 'phone' => $s->get('company.phone'), 'email' => $s->get('company.email'),
            'ice' => $s->get('company.ice'), 'rc' => $s->get('company.rc'), 'if' => $s->get('company.if'),
            'patente' => $s->get('company.patente'), 'cnss' => $s->get('company.cnss'),
            'bank' => $s->get('company.bank_name'), 'rib' => $s->get('company.rib'),
            'footer' => $s->get('company.document_footer'), 'logo' => $s->get('brand.logo'),
            'color' => $s->get('brand.primary_color'), 'accent' => $s->get('brand.accent_color'),
        ];
    }

    protected function customerBlock($customer, ?array $address = null): array
    {
        return [
            'name' => $customer?->name, 'company' => $customer?->company_name, 'ice' => $customer?->ice,
            'email' => $customer?->email, 'phone' => $customer?->phone, 'address' => $address,
        ];
    }

    protected function issue(string $type, ?string $uniq, array $attrs, array $snapshot): Document
    {
        if ($uniq && ($d = Document::where('uniq_key', $uniq)->first())) {
            return $d;
        }
        try {
            return DB::transaction(function () use ($type, $uniq, $attrs, $snapshot) {
                $number = $this->numbers->next($type);
                $snapshot['number'] = $number;
                $snapshot['type'] = $type;
                $snapshot['issued_at'] = now()->toDateString();
                $doc = Document::create($attrs + [
                    'type' => $type, 'number' => $number, 'uniq_key' => $uniq, 'snapshot' => $snapshot,
                    'issued_by' => auth()->id(), 'issued_at' => now(),
                    'subtotal' => $snapshot['totals']['subtotal'] ?? 0, 'tax_total' => $snapshot['totals']['tax'] ?? 0,
                    'total' => $snapshot['totals']['total'] ?? 0,
                ]);
                AuditLogger::log('document.issued', $doc, null, ['type' => $type, 'number' => $number, 'total' => $doc->total]);

                return $doc;
            });
        } catch (UniqueConstraintViolationException) {
            return Document::where('uniq_key', $uniq)->firstOrFail();
        }
    }

    protected function orderLines(Order $order, bool $prices = true): array
    {
        return $order->items->map(fn ($i) => [
            'description' => $i->product?->t('name', $order->locale) ?: $i->description,
            'details' => $this->details($i->options_snapshot, $i->is_custom ? null : $i->description),
            'qty' => $i->quantity,
            'unit_price' => $prices ? Money::str(Money::of($i->total)->dividedBy(max(1, $i->quantity), 4, RoundingMode::HalfUp), 4) : null,
            'total' => $prices ? $i->total : null,
            'discount_note' => Money::of($i->manual_discount)->isPositive() ? 'Remise '.Money::format($i->manual_discount) : null,
        ])->all();
    }

    protected function details(?array $snap, ?string $fallback): string
    {
        if (! $snap) {
            return '';
        }
        $parts = [];
        if (! empty($snap['size'])) {
            $parts[] = $snap['size'];
        }
        foreach ($snap['options'] ?? [] as $o) {
            $parts[] = $o['label'].' : '.$o['value_label'];
        }

        return implode(' · ', $parts);
    }

    protected function orderTotals(Order $order): array
    {
        return [
            'subtotal' => $order->subtotal, 'discount' => $order->discount_total, 'discount_reason' => $order->discount_reason,
            'shipping' => $order->shipping_total, 'tax_rate' => $order->tax_rate, 'tax' => $order->tax_total,
            'ht' => Money::str(Money::of($order->subtotal)->minus($order->discount_total)->plus($order->shipping_total)),
            'total' => $order->total, 'paid' => $order->netPaid(), 'balance' => $order->balance(),
        ];
    }

    public function invoice(Order $order): Document
    {
        if ($order->status === 'cancelled') {
            throw ValidationException::withMessages(['order' => 'Impossible de facturer une commande annulée.']);
        }
        $order->loadMissing('items.product', 'customer');

        return $this->issue('invoice', 'invoice:order:'.$order->id, ['customer_id' => $order->customer_id, 'order_id' => $order->id, 'locale' => $order->locale], [
            'company' => $this->company(), 'customer' => $this->customerBlock($order->customer, $order->billing_address ?? $order->shipping_address),
            'reference' => $order->number, 'lines' => $this->orderLines($order), 'totals' => $this->orderTotals($order),
            'payment_terms' => (float) $order->balance() > 0 ? __('Solde dû : :a', ['a' => Money::format($order->balance(), $order->locale)], $order->locale) : __('Facture acquittée', [], $order->locale),
            'notes' => null,
        ]);
    }

    public function proforma(Order $order): Document
    {
        $order->loadMissing('items.product', 'customer');

        return $this->issue('proforma', null, ['customer_id' => $order->customer_id, 'order_id' => $order->id, 'locale' => $order->locale], [
            'company' => $this->company(), 'customer' => $this->customerBlock($order->customer, $order->billing_address),
            'reference' => $order->number, 'lines' => $this->orderLines($order), 'totals' => $this->orderTotals($order),
            'payment_terms' => null, 'notes' => null,
        ]);
    }

    /** Credit note against an issued invoice: full reversal or a partial TTC amount. */
    public function creditNote(Document $invoice, string $amountTtc, string $reason): Document
    {
        if ($invoice->type !== 'invoice') {
            throw ValidationException::withMessages(['document' => 'Un avoir doit se rapporter à une facture.']);
        }
        $credited = Money::sum(Document::where('related_document_id', $invoice->id)->where('type', 'credit_note')->pluck('total'));
        $remaining = Money::of($invoice->total)->minus($credited);
        $amt = Money::round($amountTtc);
        if (! $amt->isPositive() || $amt->isGreaterThan($remaining)) {
            throw ValidationException::withMessages(['amount' => 'Montant d\'avoir invalide (maximum '.Money::format($remaining).').']);
        }
        $rate = Money::of($invoice->snapshot['totals']['tax_rate'] ?? 0);
        $ht = $amt->multipliedBy(100)->dividedBy($rate->plus(100), 2, RoundingMode::HalfUp);
        $tax = $amt->minus($ht);

        return $this->issue('credit_note', null, ['customer_id' => $invoice->customer_id, 'order_id' => $invoice->order_id, 'related_document_id' => $invoice->id, 'reason' => $reason, 'locale' => $invoice->locale], [
            'company' => $this->company(), 'customer' => $invoice->snapshot['customer'] ?? [],
            'reference' => 'Facture '.$invoice->number,
            'lines' => [['description' => 'Avoir sur facture '.$invoice->number, 'details' => $reason, 'qty' => 1, 'unit_price' => Money::str($ht, 4), 'total' => Money::str($ht)]],
            'totals' => ['subtotal' => Money::str($ht), 'discount' => '0.00', 'shipping' => '0.00', 'tax_rate' => (string) $rate, 'tax' => Money::str($tax), 'ht' => Money::str($ht), 'total' => Money::str($amt)],
            'payment_terms' => null, 'notes' => $reason,
        ]);
    }

    public function quote(Quote $quote, QuoteRevision $rev): Document
    {
        $rev->loadMissing('items.product');
        $lines = $rev->items->sortBy('sort')->map(fn ($i) => [
            'description' => $i->product?->t('name', $quote->locale) ?: $i->description,
            'details' => $this->details($i->options_snapshot, null) ?: ($i->product ? '' : ''),
            'qty' => $i->quantity, 'unit_price' => Money::str($i->unit_price, 4), 'total' => $i->total,
        ])->values()->all();

        return $this->issue('quote', 'quote:'.$quote->id.':rev:'.$rev->revision, ['customer_id' => $quote->customer_id, 'quote_id' => $quote->id, 'locale' => $quote->locale], [
            'company' => $this->company(), 'customer' => $this->customerBlock($quote->customer),
            'reference' => $quote->number.' – révision '.$rev->revision,
            'lines' => $lines,
            'totals' => ['subtotal' => $rev->subtotal, 'discount' => $rev->discount_total, 'discount_reason' => $rev->discount_reason, 'shipping' => $rev->shipping_total, 'tax_rate' => $rev->tax_rate, 'tax' => $rev->tax_total,
                'ht' => Money::str(Money::of($rev->subtotal)->minus($rev->discount_total)->plus($rev->shipping_total)), 'total' => $rev->total],
            'valid_until' => $quote->expires_at?->toDateString(), 'payment_terms' => null, 'notes' => $rev->notes,
        ]);
    }

    public function receipt(Payment $payment): Document
    {
        $order = $payment->order;

        return $this->issue('receipt', 'receipt:payment:'.$payment->id, ['customer_id' => $order->customer_id, 'order_id' => $order->id, 'payment_id' => $payment->id, 'locale' => $order->locale], [
            'company' => $this->company(), 'customer' => $this->customerBlock($order->customer),
            'reference' => $order->number,
            'lines' => [['description' => ($payment->type === 'refund' ? 'Remboursement' : 'Paiement reçu').' – commande '.$order->number, 'details' => __('payment.'.$payment->method).($payment->reference ? ' – réf. '.$payment->reference : ''), 'qty' => 1, 'unit_price' => $payment->amount, 'total' => $payment->amount]],
            'totals' => ['total' => $payment->amount, 'order_total' => $order->total, 'balance' => $order->balance()],
            'no_tax' => true, 'payment_terms' => null, 'notes' => null,
        ]);
    }

    public function deliveryNote(Shipment $shipment): Document
    {
        $order = $shipment->order->loadMissing('items.product');

        return $this->issue('delivery_note', 'delivery_note:shipment:'.$shipment->id, ['customer_id' => $order->customer_id, 'order_id' => $order->id, 'locale' => $order->locale], [
            'company' => $this->company(), 'customer' => $this->customerBlock($order->customer, $order->shipping_address),
            'reference' => $order->number.($shipment->tracking_number ? ' – suivi '.$shipment->tracking_number : ''),
            'lines' => $this->orderLines($order, false), 'totals' => [], 'no_prices' => true,
            'carrier' => $shipment->carrier, 'method' => $shipment->method, 'payment_terms' => null, 'notes' => $shipment->notes,
        ]);
    }

    public function purchaseOrder(PurchaseOrder $po): Document
    {
        $po->loadMissing('items', 'supplier');
        $lines = $po->items->map(fn ($i) => ['description' => $i->description, 'details' => $i->material?->sku ?? '', 'qty' => (float) $i->quantity, 'unit_price' => Money::str($i->unit_cost, 4), 'total' => Money::str(Money::of($i->quantity)->multipliedBy($i->unit_cost))])->all();

        return $this->issue('purchase_order', 'po:'.$po->id.':'.$po->updated_at?->timestamp, ['purchase_order_id' => $po->id, 'locale' => 'fr'], [
            'company' => $this->company(),
            'customer' => ['name' => $po->supplier->name, 'company' => $po->supplier->contact_name, 'ice' => $po->supplier->ice, 'email' => $po->supplier->email, 'phone' => $po->supplier->phone],
            'reference' => $po->number, 'lines' => $lines,
            'totals' => ['subtotal' => $po->total, 'ht' => $po->total, 'total' => $po->total], 'no_tax' => true,
            'payment_terms' => $po->expected_at ? 'Livraison souhaitée : '.$po->expected_at->format('d/m/Y') : null, 'notes' => $po->notes,
        ]);
    }

    public function renderPdf(Document $doc): string
    {
        $locale = $doc->locale ?: 'fr';
        $previous = app()->getLocale();
        app()->setLocale($locale);
        try {
            $tmp = storage_path('app/mpdf-tmp');
            @mkdir($tmp, 0775, true);
            $mpdf = new \Mpdf\Mpdf([
                'tempDir' => $tmp, 'format' => 'A4', 'margin_left' => 14, 'margin_right' => 14, 'margin_top' => 14, 'margin_bottom' => 18,
                'default_font' => $locale === 'ar' ? 'xbriyaz' : 'dejavusans', 'autoScriptToLang' => true, 'autoLangToFont' => true,
            ]);
            if ($locale === 'ar') {
                $mpdf->SetDirectionality('rtl');
            }
            $mpdf->SetTitle($doc->number);
            $mpdf->WriteHTML(view('pdf.document', ['doc' => $doc, 's' => $doc->snapshot, 'rtl' => $locale === 'ar'])->render());

            return $mpdf->Output('', 'S');
        } finally {
            app()->setLocale($previous);
        }
    }
}
