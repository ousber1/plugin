<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ArtworkFile;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Proof;
use App\Models\Shipment;
use App\Models\ShippingZone;
use App\Notifications\SimpleNotification;
use App\Services\ArtworkService;
use App\Services\AuditLogger;
use App\Services\DocumentService;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Services\Pricing\PricingEngine;
use App\Services\ProductionService;
use App\Services\ProofService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('orders.view');
        $orders = Order::with('customer')
            ->when($request->query('q'), function ($q, $s) {
                $q->where(fn ($w) => $w->where('number', 'like', "%$s%")->orWhere('contact_phone', 'like', "%$s%")->orWhere('contact_name', 'like', "%$s%")
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%$s%")->orWhere('company_name', 'like', "%$s%")->orWhere('phone', 'like', "%$s%")));
            })
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('channel'), fn ($q, $s) => $q->where('channel', $s))
            ->when($request->query('payment'), fn ($q, $s) => $q->where('payment_status', $s))
            ->latest()->paginate(40)->withQueryString();

        return view('admin.orders.index', compact('orders'));
    }

    public function create(Request $request)
    {
        $this->authorize('orders.manage');

        return view('admin.orders.create', [
            'customer' => $request->query('customer') ? Customer::find($request->query('customer')) : null,
            'customers' => Customer::orderBy('name')->limit(500)->get(),
            'products' => Product::whereIn('sale_mode', ['instant', 'quote'])->whereHas('publishedRuleSet')->orderBy('slug')->get(),
            'zones' => ShippingZone::where('is_active', true)->orderBy('sort')->get(),
        ]);
    }

    /** Staff order (walk-in, phone, WhatsApp, B2B) through the same OrderService as the storefront. */
    public function store(Request $request, OrderService $orders, PricingEngine $engine, PaymentService $payments)
    {
        $this->authorize('orders.manage');
        $v = $request->validate([
            'customer_id' => 'required|exists:customers,id', 'channel' => 'required|in:walk_in,phone,whatsapp,b2b,email,web',
            'shipping_method' => 'required|in:pickup,delivery', 'shipping_zone_id' => 'nullable|required_if:shipping_method,delivery|exists:shipping_zones,id',
            'line1' => 'nullable|string|max:190', 'city' => 'nullable|string|max:80',
            'manual_discount' => 'nullable|numeric|min:0', 'discount_reason' => 'nullable|required_with:manual_discount|string|max:190',
            'notes' => 'nullable|string|max:3000', 'internal_notes' => 'nullable|string|max:3000', 'checkout_key' => 'required|uuid',
            'deposit' => 'nullable|numeric|min:0', 'deposit_method' => 'nullable|in:'.implode(',', config('hocreative.payment_methods')),
            'lines' => 'required|array|min:1', 'lines.*.product_id' => 'nullable|exists:products,id', 'lines.*.selection' => 'nullable|array',
            'lines.*.description' => 'nullable|string|max:500', 'lines.*.quantity' => 'nullable|integer|min:1', 'lines.*.unit_price' => 'nullable|numeric|min:0',
            'lines.*.line_discount' => 'nullable|numeric|min:0', 'lines.*.line_discount_reason' => 'nullable|string|max:190', 'lines.*.requires_proof' => 'nullable|boolean',
        ]);
        $hasDiscount = (float) ($v['manual_discount'] ?? 0) > 0 || collect($v['lines'])->contains(fn ($l) => (float) ($l['line_discount'] ?? 0) > 0);
        if ($hasDiscount && ! $request->user()->can('orders.discount')) {
            abort(403, 'Remise non autorisée pour votre rôle.');
        }
        $lines = [];
        foreach ($v['lines'] as $i => $row) {
            $extra = [];
            if ((float) ($row['line_discount'] ?? 0) > 0) {
                if (empty($row['line_discount_reason'])) {
                    throw ValidationException::withMessages(["lines.$i.line_discount_reason" => 'Motif obligatoire pour une remise de ligne.']);
                }
                $extra = ['manual_discount' => Money::str($row['line_discount']), 'manual_discount_reason' => $row['line_discount_reason']];
            }
            if (! empty($row['product_id'])) {
                $p = Product::findOrFail($row['product_id']);
                $price = $engine->forProduct($p, (array) ($row['selection'] ?? []));
                if (! $price->valid) {
                    throw ValidationException::withMessages(["lines.$i" => $p->t('name').' : '.implode(' ', $price->errors)]);
                }
                $lines[] = OrderService::lineFromPrice($p, $price, $extra);
            } elseif (! empty($row['description'])) {
                $lines[] = OrderService::customLine($row['description'], (int) ($row['quantity'] ?? 1), (string) ($row['unit_price'] ?? 0), $extra + ['requires_proof' => (bool) ($row['requires_proof'] ?? false)]);
            }
        }
        if (! $lines) {
            throw ValidationException::withMessages(['lines' => 'Ajoutez au moins une ligne.']);
        }
        $customer = Customer::findOrFail($v['customer_id']);
        $address = $v['shipping_method'] === 'delivery' ? ['name' => $customer->name, 'line1' => $v['line1'] ?? '', 'city' => $v['city'] ?? '', 'phone' => $customer->phone] : null;
        $order = $orders->create($customer, $lines, [
            'channel' => $v['channel'], 'created_by' => auth()->id(), 'user_id' => $customer->users()->value('id'),
            'shipping_method' => $v['shipping_method'], 'shipping_zone_id' => $v['shipping_zone_id'] ?? null, 'shipping_address' => $address,
            'manual_discount' => $v['manual_discount'] ?? 0, 'discount_reason' => $v['discount_reason'] ?? null,
            'notes' => $v['notes'] ?? null, 'internal_notes' => $v['internal_notes'] ?? null, 'checkout_key' => $v['checkout_key'],
            'payment_method' => $v['deposit_method'] ?? null, 'locale' => 'fr', 'status' => 'pending',
        ]);
        if ((float) ($v['deposit'] ?? 0) > 0) {
            abort_unless($request->user()->can('payments.record'), 403);
            $payments->record($order, 'payment', $v['deposit_method'] ?? 'cash', (string) $v['deposit'], 'deposit:'.$v['checkout_key'], ['notes' => 'Acompte à la commande']);
        }

        return redirect()->route('admin.orders.show', $order)->with('ok', 'Commande '.$order->number.' créée.');
    }

    public function show(Order $order, ProductionService $production)
    {
        $this->authorize('orders.view');
        $order->load('customer', 'items.product', 'items.artworkFiles.uploader', 'items.proofs.responder', 'items.job', 'items.design', 'payments.recorder', 'documents', 'shipments', 'auditLogs.user', 'quote', 'shippingZone');

        return view('admin.orders.show', ['order' => $order, 'production' => $production]);
    }

    public function confirm(Order $order, OrderService $orders)
    {
        $this->authorize('orders.manage');
        $orders->confirm($order);

        return back()->with('ok', 'Commande confirmée : la production peut démarrer (sous réserve des BAT).');
    }

    public function cancel(Request $request, Order $order, OrderService $orders)
    {
        $this->authorize('orders.manage');
        $reason = $request->validate(['reason' => 'required|string|max:500'])['reason'];
        $orders->cancel($order, $reason);

        return back()->with('ok', 'Commande annulée. Les réservations de stock ont été libérées. Pensez à l\'avoir / remboursement si nécessaire.');
    }

    public function notes(Request $request, Order $order)
    {
        $this->authorize('orders.manage');
        $order->update($request->validate(['internal_notes' => 'nullable|string|max:5000']));

        return back()->with('ok', 'Notes enregistrées.');
    }

    public function payment(Request $request, Order $order, PaymentService $payments)
    {
        $v = $request->validate(['type' => 'required|in:payment,refund', 'method' => 'required|in:'.implode(',', config('hocreative.payment_methods')),
            'amount' => 'required|numeric|min:0.01', 'reference' => 'nullable|string|max:190', 'notes' => 'nullable|string|max:500',
            'paid_at' => 'nullable|date', 'key' => 'required|uuid']);
        $this->authorize($v['type'] === 'refund' ? 'payments.refund' : 'payments.record');
        if ($v['type'] === 'refund' && empty($v['notes'])) {
            throw ValidationException::withMessages(['notes' => 'Le motif du remboursement est obligatoire.']);
        }
        $payments->record($order, $v['type'], $v['method'], (string) $v['amount'], 'manual:'.$v['key'], [
            'reference' => $v['reference'] ?? null, 'notes' => $v['notes'] ?? null, 'paid_at' => $v['paid_at'] ?? now(),
        ]);

        return back()->with('ok', $v['type'] === 'refund' ? 'Remboursement enregistré.' : 'Paiement enregistré.');
    }

    public function document(Request $request, Order $order, DocumentService $docs)
    {
        $this->authorize('documents.issue');
        $v = $request->validate(['type' => 'required|in:invoice,proforma,receipt,delivery_note', 'payment_id' => 'nullable|exists:payments,id', 'shipment_id' => 'nullable|exists:shipments,id']);
        $doc = match ($v['type']) {
            'invoice' => $docs->invoice($order),
            'proforma' => $docs->proforma($order),
            'receipt' => $docs->receipt(Payment::where('order_id', $order->id)->findOrFail($v['payment_id'])),
            'delivery_note' => $docs->deliveryNote(Shipment::where('order_id', $order->id)->findOrFail($v['shipment_id'])),
        };

        return back()->with('ok', __('doc.'.$doc->type).' '.$doc->number.' disponible.');
    }

    public function shipment(Request $request, Order $order, OrderService $orders)
    {
        $this->authorize('delivery.manage');
        $v = $request->validate(['method' => 'required|in:pickup,local,carrier', 'carrier' => 'nullable|string|max:120', 'tracking_number' => 'nullable|string|max:120',
            'status' => 'required|in:pending,ready,shipped,delivered,picked_up', 'dispatched_at' => 'nullable|date', 'recipient_name' => 'nullable|string|max:120', 'notes' => 'nullable|string|max:500']);
        $s = $order->shipments()->create($v + ['user_id' => auth()->id(), 'dispatched_at' => $v['dispatched_at'] ?? (in_array($v['status'], ['shipped', 'delivered', 'picked_up']) ? now() : null)]);
        $this->applyShipment($s, $orders);

        return back()->with('ok', 'Expédition / retrait enregistré.');
    }

    public function updateShipment(Request $request, Shipment $shipment, OrderService $orders)
    {
        $this->authorize('delivery.manage');
        $v = $request->validate(['status' => 'required|in:pending,ready,shipped,delivered,picked_up,returned', 'tracking_number' => 'nullable|string|max:120', 'carrier' => 'nullable|string|max:120', 'dispatched_at' => 'nullable|date', 'recipient_name' => 'nullable|string|max:120']);
        $old = $shipment->only(['status', 'tracking_number']);
        if (in_array($v['status'], ['delivered', 'picked_up']) && ! $shipment->delivered_at) {
            $v['delivered_at'] = now();
        }
        if (in_array($v['status'], ['shipped', 'delivered', 'picked_up']) && empty($v['dispatched_at']) && ! $shipment->dispatched_at) {
            $v['dispatched_at'] = now();
        }
        $shipment->update($v);
        AuditLogger::log('shipment.updated', $shipment->order, $old, $shipment->only(['status', 'tracking_number']));
        $this->applyShipment($shipment, $orders);

        return back()->with('ok', 'Livraison mise à jour.');
    }

    /** Moves the order's jobs to the matching dispatch stage and notifies the customer. */
    protected function applyShipment(Shipment $s, OrderService $orders): void
    {
        $stage = match ($s->status) {
            'ready' => 'ready_for_pickup', 'shipped' => 'shipped', 'delivered', 'picked_up' => 'delivered', default => null,
        };
        if (! $stage) {
            return;
        }
        $production = app(ProductionService::class);
        foreach ($s->order->jobs()->where('stage', '!=', 'cancelled')->get() as $job) {
            if (ProductionService::index($job->stage) < ProductionService::index($stage)) {
                $production->transition($job, $stage, 'Expédition '.$s->id.' : '.$s->status);
            }
        }
        if (in_array($s->status, ['delivered', 'picked_up'])) {
            $s->update(['delivered_at' => $s->delivered_at ?? now()]);
        }
        $order = $s->order->fresh();
        if ($order->contact_email) {
            try {
                Notification::route('mail', $order->contact_email)->notify(new SimpleNotification('order_status', [
                    'order' => $order->number, 'status' => __('shipment_status.'.$s->status, [], $order->locale).($s->tracking_number ? ' – '.$s->tracking_number : ''),
                    'url' => $order->user_id ? route('account.orders.show', $order) : route('guest.order', $order->guest_token),
                ], $order->locale));
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    public function artwork(Request $request, OrderItem $item, ArtworkService $artwork)
    {
        $this->authorize('artwork.manage');
        $request->validate(['file' => $artwork->rules(), 'side' => 'required|in:front,back']);
        $artwork->store($request->file('file'), $item, $request->input('side'));

        return back()->with('ok', 'Fichier ajouté.');
    }

    public function reviewArtwork(Request $request, ArtworkFile $file)
    {
        $this->authorize('artwork.manage');
        $v = $request->validate(['review_status' => 'required|in:ok,issues', 'review_note' => 'nullable|required_if:review_status,issues|string|max:1000']);
        $file->update($v);
        $file->orderItem?->update(['artwork_status' => $v['review_status'] === 'ok' ? 'ok' : 'issues']);
        AuditLogger::log('artwork.reviewed', $file->orderItem, null, $v);

        return back()->with('ok', 'Contrôle prépresse enregistré.');
    }

    public function proof(Request $request, OrderItem $item, ProofService $proofs)
    {
        $this->authorize('proofs.manage');
        $request->validate(['file' => 'required|file|mimes:pdf,png,jpg,jpeg|max:51200', 'message' => 'nullable|string|max:2000']);
        $p = $proofs->upload($item, $request->file('file'), $request->input('message'));

        return back()->with('ok', 'BAT v'.$p->version.' envoyé au client. La production est bloquée jusqu\'à son approbation.');
    }

    /** Customer approval received in person / by phone, recorded by staff with a reference. */
    public function recordProofResponse(Request $request, Proof $proof, ProofService $proofs)
    {
        $this->authorize('proofs.manage');
        $v = $request->validate(['decision' => 'required|in:approved,changes_requested', 'comment' => 'required|string|max:2000']);
        $proofs->respond($proof, $v['decision'], $request->user(), '[Saisi par '.$request->user()->name.'] '.$v['comment']);

        return back()->with('ok', 'Réponse au BAT enregistrée.');
    }

    public function remake(Request $request, OrderItem $item, ProductionService $production)
    {
        $this->authorize('production.manage');
        $v = $request->validate(['reason' => 'required|string|max:190', 'charge' => 'boolean']);
        $copy = $production->remake($item, $v['reason'], $request->boolean('charge'));

        return back()->with('ok', 'Reprise créée : '.$copy->job->number);
    }

    public function deliveries(Request $request)
    {
        $this->authorize('delivery.manage');
        $ready = Order::with('customer', 'shipments', 'shippingZone')->where('status', '!=', 'cancelled')
            ->whereIn('fulfillment_status', ['ready', 'in_production', 'shipped'])->orderBy('estimated_delivery_at')->paginate(50);

        return view('admin.orders.deliveries', compact('ready'));
    }
}
