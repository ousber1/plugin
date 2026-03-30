<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InvoiceController extends Controller
{
    /**
     * List all invoices with filters.
     */
    public function index(Request $request)
    {
        $query = Sale::with(['items', 'customer', 'user', 'payments']);

        // Filter by invoice type
        if ($request->filled('type')) {
            $query->where('invoice_type', $request->input('type'));
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        // Search by invoice number or customer name
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($cq) use ($search) {
                      $cq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $invoices = $query->latest()->paginate(20)->withQueryString();

        $types = ['facture', 'proforma', 'bon_livraison', 'devis'];

        return view('invoices.index', compact('invoices', 'types'));
    }

    /**
     * Show form to create a new invoice (optionally pre-filled from an existing sale).
     */
    public function create(Request $request)
    {
        $sale = null;

        if ($request->filled('sale_id')) {
            $sale = Sale::with(['items.product', 'customer', 'payments'])
                ->findOrFail($request->input('sale_id'));
        }

        $customers = Customer::orderBy('name')->get();
        $products = Product::orderBy('name')->get();

        return view('invoices.create', compact('sale', 'customers', 'products'));
    }

    /**
     * Generate and display an invoice document for an existing sale.
     */
    public function generate(Request $request, int $saleId)
    {
        $sale = Sale::with(['items.product', 'customer', 'user', 'payments'])
            ->findOrFail($saleId);

        // Determine invoice type and language
        $type = $request->input('type', 'facture');
        $lang = $request->input('lang', session('locale', 'fr'));

        // Validate allowed types
        if (! in_array($type, ['facture', 'proforma', 'bon_livraison', 'devis'])) {
            $type = 'facture';
        }

        // Validate allowed languages
        if (! in_array($lang, ['fr', 'en'])) {
            $lang = 'fr';
        }

        // Load all store and Moroccan fiscal settings
        $settingKeys = [
            'store_name',
            'store_phone',
            'store_email',
            'store_address',
            'currency',
            'tax_rate',
            // Moroccan fiscal identifiers
            'ice',
            'if_number',
            'rc',
            'cnss',
            'patente',
            // Banking details
            'bank_name',
            'bank_rib',
            // Invoice presentation
            'invoice_conditions',
            'invoice_footer',
            'receipt_logo',
            'receipt_show_logo',
        ];

        $settings = [];
        foreach ($settingKeys as $key) {
            $settings[$key] = Setting::get($key);
        }

        // Calculate invoice totals
        $subtotal = $sale->items->sum('total');
        $taxRate = floatval($settings['tax_rate'] ?? 20);
        $tvaAmount = round($subtotal * ($taxRate / 100), 2);
        $totalTtc = round($subtotal + $tvaAmount, 2);

        return view('invoices.document', compact(
            'sale',
            'settings',
            'type',
            'lang',
            'subtotal',
            'tvaAmount',
            'totalTtc',
            'taxRate',
        ));
    }

    /**
     * Download the invoice as a PDF (placeholder - redirects to generate for now).
     */
    public function downloadPdf(Request $request, int $saleId)
    {
        return redirect()->route('invoices.generate', [
            'saleId' => $saleId,
            'type'   => $request->input('type', 'facture'),
            'lang'   => $request->input('lang', 'fr'),
        ]);
    }
}
