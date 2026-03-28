<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderController extends Controller
{

    /**
     * List all online orders with filters.
     */
    public function index(Request $request)
    {
        $query = Sale::with(['customer:id,name,phone', 'user:id,name'])
            ->where('channel', 'online');

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
                      $cq->where('name', 'like', "%{$search}%")
                         ->orWhere('phone', 'like', "%{$search}%");
                  });
            });
        }

        $orders = $query->orderByDesc('created_at')->paginate(20)->withQueryString();

        return view('orders.index', compact('orders'));
    }

    /**
     * Show create order form.
     */
    public function create()
    {
        $customers = Customer::orderBy('name')->get(['id', 'name', 'phone', 'email']);
        $products = Product::where('is_active', true)
            ->where('stock_quantity', '>', 0)
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'selling_price', 'stock_quantity']);

        return view('orders.create', compact('customers', 'products'));
    }

    /**
     * Store a new online order.
     */
    public function store(Request $request)
    {
        $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'tax' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
            'source' => 'nullable|string|max:100',
            'payment_method' => 'nullable|in:cash,card,bank_transfer,other',
            'payment_amount' => 'nullable|numeric|min:0',
        ]);

        DB::beginTransaction();

        try {
            $subtotal = 0;
            $itemsData = [];

            foreach ($request->input('items') as $item) {
                $product = Product::lockForUpdate()->findOrFail($item['product_id']);

                if ($product->stock_quantity < $item['quantity']) {
                    DB::rollBack();
                    return back()->withErrors([
                        'items' => "Insufficient stock for {$product->name}. Available: {$product->stock_quantity}",
                    ])->withInput();
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

            $paymentAmount = $request->input('payment_amount', 0);
            if ($paymentAmount >= $total) {
                $paymentStatus = 'paid';
            } elseif ($paymentAmount > 0) {
                $paymentStatus = 'partial';
            } else {
                $paymentStatus = 'unpaid';
            }

            $sale = Sale::create([
                'invoice_number' => 'ONL-' . strtoupper(Str::random(8)),
                'customer_id' => $request->input('customer_id'),
                'user_id' => Auth::id(),
                'channel' => 'online',
                'source' => $request->input('source'),
                'status' => 'pending',
                'subtotal' => $subtotal,
                'discount' => $discount,
                'tax' => $tax,
                'total' => $total,
                'payment_status' => $paymentStatus,
                'notes' => $request->input('notes'),
            ]);

            foreach ($itemsData as $itemData) {
                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $itemData['product']->id,
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'total' => $itemData['total'],
                ]);

                $itemData['product']->decrement('stock_quantity', $itemData['quantity']);

                StockMovement::create([
                    'product_id' => $itemData['product']->id,
                    'type' => 'out',
                    'quantity' => $itemData['quantity'],
                    'reference' => $sale->invoice_number,
                    'notes' => 'Online order',
                    'user_id' => Auth::id(),
                ]);
            }

            // Create payment if amount provided
            if ($paymentAmount > 0 && $request->filled('payment_method')) {
                Payment::create([
                    'sale_id' => $sale->id,
                    'amount' => $paymentAmount,
                    'method' => $request->input('payment_method'),
                    'user_id' => Auth::id(),
                ]);
            }

            DB::commit();

            return redirect()->route('orders.show', $sale->id)
                ->with('success', 'Order created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Failed to create order: ' . $e->getMessage()])->withInput();
        }
    }

    /**
     * Show order details.
     */
    public function show(int $id)
    {
        $order = Sale::with([
            'customer',
            'user:id,name',
            'items.product:id,name,sku,image',
            'payments',
        ])->where('channel', 'online')->findOrFail($id);

        return view('orders.show', compact('order'));
    }

    /**
     * Show edit order form.
     */
    public function edit(int $id)
    {
        $order = Sale::with(['items.product', 'customer', 'payments'])
            ->where('channel', 'online')
            ->findOrFail($id);

        $customers = Customer::orderBy('name')->get(['id', 'name', 'phone', 'email']);
        $products = Product::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'selling_price', 'stock_quantity']);

        return view('orders.edit', compact('order', 'customers', 'products'));
    }

    /**
     * Update order status and items.
     */
    public function update(Request $request, int $id)
    {
        $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
            'status' => 'required|in:pending,confirmed,shipped,delivered,cancelled',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'tax' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
        ]);

        DB::beginTransaction();

        try {
            $order = Sale::with('items')->where('channel', 'online')->findOrFail($id);

            // Restore stock from old items
            foreach ($order->items as $oldItem) {
                Product::where('id', $oldItem->product_id)
                    ->increment('stock_quantity', $oldItem->quantity);
            }

            // Delete old items
            $order->items()->delete();

            $subtotal = 0;
            $itemsData = [];

            foreach ($request->input('items') as $item) {
                $product = Product::lockForUpdate()->findOrFail($item['product_id']);

                if ($product->stock_quantity < $item['quantity']) {
                    DB::rollBack();
                    return back()->withErrors([
                        'items' => "Insufficient stock for {$product->name}. Available: {$product->stock_quantity}",
                    ])->withInput();
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

            $order->update([
                'customer_id' => $request->input('customer_id'),
                'status' => $request->input('status'),
                'subtotal' => $subtotal,
                'discount' => $discount,
                'tax' => $tax,
                'total' => $total,
                'notes' => $request->input('notes'),
            ]);

            foreach ($itemsData as $itemData) {
                SaleItem::create([
                    'sale_id' => $order->id,
                    'product_id' => $itemData['product']->id,
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'total' => $itemData['total'],
                ]);

                $itemData['product']->decrement('stock_quantity', $itemData['quantity']);

                StockMovement::create([
                    'product_id' => $itemData['product']->id,
                    'type' => 'out',
                    'quantity' => $itemData['quantity'],
                    'reference' => $order->invoice_number,
                    'notes' => 'Online order update',
                    'user_id' => Auth::id(),
                ]);
            }

            DB::commit();

            return redirect()->route('orders.show', $order->id)
                ->with('success', 'Order updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Failed to update order: ' . $e->getMessage()])->withInput();
        }
    }

    /**
     * Quick status update via AJAX.
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:pending,confirmed,shipped,delivered,cancelled',
        ]);

        try {
            $order = Sale::where('channel', 'online')->findOrFail($id);

            // If cancelling, restore stock
            if ($request->input('status') === 'cancelled' && $order->status !== 'cancelled') {
                $order->load('items');
                foreach ($order->items as $item) {
                    Product::where('id', $item->product_id)
                        ->increment('stock_quantity', $item->quantity);

                    StockMovement::create([
                        'product_id' => $item->product_id,
                        'type' => 'in',
                        'quantity' => $item->quantity,
                        'reference' => $order->invoice_number,
                        'notes' => 'Order cancelled - stock restored',
                        'user_id' => Auth::id(),
                    ]);
                }
            }

            $order->update(['status' => $request->input('status')]);

            return response()->json([
                'success' => true,
                'message' => 'Order status updated.',
                'data' => $order->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update status: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Soft delete an order.
     */
    public function destroy(int $id)
    {
        try {
            $order = Sale::where('channel', 'online')->findOrFail($id);
            $order->delete();

            return redirect()->route('orders.index')
                ->with('success', 'Order deleted successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to delete order: ' . $e->getMessage()]);
        }
    }
}
