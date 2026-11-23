<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteRequest;
use App\Models\ShippingZone;
use App\Services\DocumentService;
use App\Services\PaymentService;
use App\Services\Pricing\PricingEngine;
use App\Services\QuoteService;
use Illuminate\Http\Request;

class QuoteController extends Controller
{
    public function __construct(protected QuoteService $quotes) {}

    public function requests(Request $request)
    {
        $this->authorize('quotes.manage');
        $reqs = QuoteRequest::latest()->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))->paginate(40)->withQueryString();

        return view('admin.quotes.requests', compact('reqs'));
    }

    public function request(QuoteRequest $quoteRequest)
    {
        $this->authorize('quotes.manage');
        $quoteRequest->load('attachments', 'quote', 'product');
        if ($quoteRequest->status === 'new') {
            $quoteRequest->update(['status' => 'in_progress']);
        }

        return view('admin.quotes.request', ['r' => $quoteRequest]);
    }

    public function index(Request $request)
    {
        $this->authorize('quotes.manage');
        $quotes = Quote::with('customer', 'currentRevision')
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('q'), fn ($q, $s) => $q->where('number', 'like', "%$s%")->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%$s%")->orWhere('company_name', 'like', "%$s%")))
            ->latest()->paginate(40)->withQueryString();

        return view('admin.quotes.index', compact('quotes'));
    }

    protected function formData(): array
    {
        return ['products' => Product::whereIn('sale_mode', ['instant', 'quote'])->whereHas('publishedRuleSet')->orderBy('slug')->get(),
            'zones' => ShippingZone::where('is_active', true)->orderBy('sort')->get(), 'customers' => Customer::orderBy('name')->limit(500)->get()];
    }

    public function create(Request $request)
    {
        $this->authorize('quotes.manage');
        $prefill = null;
        $customer = $request->query('customer') ? Customer::find($request->query('customer')) : null;
        if ($rid = $request->query('request')) {
            $prefill = QuoteRequest::findOrFail($rid);
            $customer ??= $prefill->customer ?? Customer::where('email', $prefill->email)->first();
        }

        return view('admin.quotes.form', $this->formData() + ['quote' => null, 'rev' => null, 'customer' => $customer, 'prefill' => $prefill]);
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'customer_id' => 'required|exists:customers,id', 'channel' => 'required|in:'.implode(',', config('hocreative.order_channels')),
            'expires_at' => 'nullable|date', 'shipping_method' => 'required|in:pickup,delivery', 'shipping_zone_id' => 'nullable|exists:shipping_zones,id',
            'manual_discount' => 'nullable|numeric|min:0', 'discount_reason' => 'nullable|string|max:190', 'notes' => 'nullable|string|max:3000',
            'quote_request_id' => 'nullable|exists:quote_requests,id', 'locale' => 'required|in:fr,ar',
            'lines' => 'required|array|min:1', 'lines.*.product_id' => 'nullable|exists:products,id', 'lines.*.selection' => 'nullable|array',
            'lines.*.description' => 'nullable|string|max:500', 'lines.*.quantity' => 'nullable|integer|min:1', 'lines.*.unit_price' => 'nullable|numeric|min:0',
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('quotes.manage');
        $v = $this->validated($request);
        if (! empty($v['manual_discount']) && ! $request->user()->can('orders.discount')) {
            abort(403, 'Remise non autorisée pour votre rôle.');
        }
        $lines = $this->quotes->priceLines($v['lines']);
        $quote = $this->quotes->create(Customer::findOrFail($v['customer_id']), $lines, $v);
        if (! empty($v['quote_request_id'])) {
            QuoteRequest::whereKey($v['quote_request_id'])->update(['status' => 'quoted', 'quote_id' => $quote->id]);
        }

        return redirect()->route('admin.quotes.show', $quote)->with('ok', 'Devis créé.');
    }

    public function show(Quote $quote)
    {
        $this->authorize('quotes.manage');
        $quote->load('customer', 'revisions.items.product', 'order', 'auditLogs.user');

        return view('admin.quotes.show', ['quote' => $quote, 'rev' => $quote->revisionNo($quote->current_revision)]);
    }

    public function revise(Quote $quote)
    {
        $this->authorize('quotes.manage');
        abort_if(in_array($quote->status, ['accepted', 'converted']), 403);

        return view('admin.quotes.form', $this->formData() + ['quote' => $quote, 'rev' => $quote->revisionNo($quote->current_revision)->load('items'), 'customer' => $quote->customer, 'prefill' => null]);
    }

    public function storeRevision(Request $request, Quote $quote)
    {
        $this->authorize('quotes.manage');
        $v = $this->validated($request);
        if (! empty($v['manual_discount']) && ! $request->user()->can('orders.discount')) {
            abort(403);
        }
        $quote->update(['expires_at' => $v['expires_at'] ?? $quote->expires_at, 'locale' => $v['locale']]);
        $this->quotes->addRevision($quote, $this->quotes->priceLines($v['lines']), $v);

        return redirect()->route('admin.quotes.show', $quote)->with('ok', 'Nouvelle révision créée. Renvoyez le devis au client.');
    }

    public function send(Quote $quote, DocumentService $docs)
    {
        $this->authorize('quotes.manage');
        $this->quotes->send($quote);
        $docs->quote($quote, $quote->revisionNo($quote->current_revision));

        return back()->with('ok', 'Devis envoyé. Lien client : '.route('quote.public', $quote->token));
    }

    /** Staff records the decision received by WhatsApp, phone or in person. */
    public function respond(Request $request, Quote $quote)
    {
        $this->authorize('quotes.manage');
        $v = $request->validate(['decision' => 'required|in:accept,reject', 'name' => 'required|max:120', 'note' => 'required|max:1000']);
        $this->quotes->respond($quote, $v['decision'], $v['name'], $v['note'], true);

        return back()->with('ok', 'Réponse du client enregistrée.');
    }

    public function convert(Request $request, Quote $quote, PaymentService $payments)
    {
        $this->authorize('quotes.manage');
        $v = $request->validate(['deposit' => 'nullable|numeric|min:0', 'deposit_method' => 'nullable|required_with:deposit|in:'.implode(',', config('hocreative.payment_methods')), 'key' => 'required|uuid']);
        $order = $this->quotes->convert($quote, ['deposit_required' => $v['deposit'] ?? '0.00', 'payment_method' => $v['deposit_method'] ?? null]);
        if (! empty($v['deposit']) && (float) $v['deposit'] > 0) {
            abort_unless($request->user()->can('payments.record'), 403);
            $payments->record($order, 'payment', $v['deposit_method'], (string) $v['deposit'], 'deposit:'.$v['key'], ['notes' => 'Acompte à la conversion du devis '.$quote->number]);
        }

        return redirect()->route('admin.orders.show', $order)->with('ok', 'Commande '.$order->number.' créée depuis le devis.');
    }

    public function linePrice(Request $request, PricingEngine $engine)
    {
        $this->authorize('quotes.manage');
        $p = Product::findOrFail($request->input('product_id'));
        $r = $engine->forProduct($p, (array) $request->input('selection', []));

        return response()->json($r->toArray() + ['description' => $r->describe()]);
    }
}
