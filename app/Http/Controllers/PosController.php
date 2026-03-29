<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Product;
use App\Models\RegisterSession;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Setting;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PosController extends Controller
{

    /**
     * POS terminal interface page.
     */
    public function index()
    {
        $products = Product::where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'barcode', 'selling_price', 'stock_quantity', 'image', 'category']);

        $categories = Product::where('is_active', true)
            ->whereNull('deleted_at')
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category');

        $openSession = RegisterSession::where('user_id', Auth::id())
            ->where('status', 'open')
            ->first();

        return view('pos.index', compact('products', 'categories', 'openSession'));
    }

    /**
     * AJAX endpoint returning products with search/barcode filter.
     */
    public function getProducts(Request $request): JsonResponse
    {
        try {
            $query = Product::where('is_active', true)->whereNull('deleted_at');

            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('sku', 'like', "%{$search}%")
                      ->orWhere('barcode', $search);
                });
            }

            if ($request->filled('category')) {
                $query->where('category', $request->input('category'));
            }

            $products = $query->orderBy('name')
                ->get(['id', 'name', 'sku', 'barcode', 'selling_price', 'stock_quantity', 'image', 'category']);

            return response()->json(['success' => true, 'data' => $products]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to load products.'], 500);
        }
    }

    /**
     * Process a POS sale.
     */
    public function createSale(Request $request): JsonResponse
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'payment_method' => 'required|in:cash,card,bank_transfer,other',
            'payment_amount' => 'required|numeric|min:0',
            'customer_id' => 'nullable|exists:customers,id',
            'discount' => 'nullable|numeric|min:0',
            'tax' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        DB::beginTransaction();

        try {
            $subtotal = 0;
            $itemsData = [];

            // Validate stock availability and calculate subtotal
            foreach ($request->input('items') as $item) {
                $product = Product::lockForUpdate()->findOrFail($item['product_id']);

                if ($product->stock_quantity < $item['quantity']) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => "Insufficient stock for {$product->name}. Available: {$product->stock_quantity}",
                    ], 422);
                }

                $lineTotal = $item['quantity'] * $item['unit_price'];
                $subtotal += $lineTotal;

                $itemsData[] = [
                    'product' => $product,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total' => $lineTotal,
                ];
            }

            $discount = $request->input('discount', 0);
            $tax = $request->input('tax', 0);
            $total = $subtotal - $discount + $tax;

            // Create the sale
            $sale = Sale::create([
                'invoice_number' => 'POS-' . strtoupper(Str::random(8)),
                'customer_id' => $request->input('customer_id'),
                'user_id' => Auth::id(),
                'channel' => 'pos',
                'status' => 'delivered',
                'subtotal' => $subtotal,
                'discount' => $discount,
                'tax' => $tax,
                'total' => $total,
                'payment_status' => $request->input('payment_amount') >= $total ? 'paid' : 'partial',
                'notes' => $request->input('notes'),
            ]);

            // Create sale items and deduct stock
            foreach ($itemsData as $itemData) {
                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $itemData['product']->id,
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'total' => $itemData['total'],
                ]);

                // Deduct stock
                $itemData['product']->decrement('stock_quantity', $itemData['quantity']);

                // Record stock movement
                StockMovement::create([
                    'product_id' => $itemData['product']->id,
                    'type' => 'out',
                    'quantity' => $itemData['quantity'],
                    'reference' => $sale->invoice_number,
                    'notes' => 'POS sale',
                    'user_id' => Auth::id(),
                ]);
            }

            // Create payment record
            Payment::create([
                'sale_id' => $sale->id,
                'amount' => $request->input('payment_amount'),
                'method' => $request->input('payment_method'),
                'reference' => $request->input('payment_reference'),
                'user_id' => Auth::id(),
            ]);

            DB::commit();

            $sale->load(['items.product', 'customer', 'payments']);

            return response()->json([
                'success' => true,
                'message' => 'Sale completed successfully.',
                'data' => $sale,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to process sale: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate receipt view for a sale.
     */
    public function receipt(int $id)
    {
        $sale = Sale::with(['items.product', 'customer', 'payments', 'user'])
            ->findOrFail($id);

        $settings = [
            'store_name' => Setting::get('store_name', 'OmniChannel Store'),
            'store_phone' => Setting::get('store_phone', ''),
            'store_email' => Setting::get('store_email', ''),
            'store_address' => Setting::get('store_address', ''),
            'currency' => Setting::get('currency', '$'),
            'receipt_logo' => Setting::get('receipt_logo', ''),
            'receipt_header' => Setting::get('receipt_header', ''),
            'receipt_footer' => Setting::get('receipt_footer', 'Thank you for your purchase!'),
            'receipt_width' => Setting::get('receipt_width', '80mm'),
            'receipt_show_logo' => Setting::get('receipt_show_logo', '1'),
        ];

        return view('pos.receipt', compact('sale', 'settings'));
    }

    /**
     * Register sessions list.
     */
    public function sessions()
    {
        $sessions = RegisterSession::with('user:id,name')
            ->orderByDesc('opened_at')
            ->paginate(20);

        return view('pos.sessions', compact('sessions'));
    }

    /**
     * Open a new register session.
     */
    public function openSession(Request $request): JsonResponse
    {
        $request->validate([
            'opening_amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            // Check if user already has an open session
            $existingSession = RegisterSession::where('user_id', Auth::id())
                ->where('status', 'open')
                ->first();

            if ($existingSession) {
                return response()->json([
                    'success' => false,
                    'message' => 'You already have an open register session.',
                ], 422);
            }

            $session = RegisterSession::create([
                'user_id' => Auth::id(),
                'opening_amount' => $request->input('opening_amount'),
                'status' => 'open',
                'opened_at' => now(),
                'notes' => $request->input('notes'),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Register session opened.',
                'data' => $session,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to open session: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Close a register session with closing amount.
     */
    public function closeSession(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'closing_amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $session = RegisterSession::where('user_id', Auth::id())
                ->where('status', 'open')
                ->findOrFail($id);

            // Calculate expected amount from cash payments during this session
            $cashPayments = Payment::where('method', 'cash')
                ->where('user_id', Auth::id())
                ->whereBetween('created_at', [$session->opened_at, now()])
                ->sum('amount');

            $expectedAmount = $session->opening_amount + $cashPayments;

            $session->update([
                'closing_amount' => $request->input('closing_amount'),
                'expected_amount' => $expectedAmount,
                'difference' => $request->input('closing_amount') - $expectedAmount,
                'status' => 'closed',
                'closed_at' => now(),
                'notes' => $request->input('notes') ?? $session->notes,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Register session closed.',
                'data' => $session->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to close session: ' . $e->getMessage(),
            ], 500);
        }
    }
}
