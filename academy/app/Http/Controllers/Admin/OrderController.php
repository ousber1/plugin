<?php

namespace App\Http\Controllers\Admin;

use App\Models\Order;
use App\Support\Audit;
use App\Support\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class OrderController extends AdminController
{
    public function index(Request $request)
    {
        $request->validate(['status' => ['nullable', 'in:'.implode(',', Order::STATUSES)], 'q' => ['nullable', 'string', 'max:120'], 'method' => ['nullable', 'string', 'max:20']]);
        $orders = Order::with(['user:id,name,email', 'items', 'product:id,title,slug'])
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->input('method'), fn ($q, $m) => $q->where('payment_method', $m))
            ->when($request->input('q'), fn ($q, $t) => $q->where(fn ($w) => $w->where('reference', 'like', "%$t%")->orWhere('name', 'like', "%$t%")->orWhere('phone', 'like', "%$t%")->orWhere('email', 'like', "%$t%")))
            ->latest()->paginate(25)->withQueryString();
        $counts = Order::selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');
        $revenue = [
            'MAD' => (int) Order::where('status', 'paid')->where('currency', 'MAD')->sum('amount_cents'),
            'USD' => (int) Order::where('status', 'paid')->where('currency', 'USD')->sum('amount_cents'),
            'month' => (int) Order::where('status', 'paid')->where('currency', 'MAD')->where('paid_at', '>=', now()->startOfMonth())->sum('amount_cents'),
        ];

        return view('admin.orders.index', compact('orders', 'counts', 'revenue'));
    }

    public function show(Order $order)
    {
        $order->load(['user', 'items.product', 'product']);
        $wa = $order->phone ? 'https://wa.me/'.ltrim(preg_replace('/\D+/', '', preg_replace('/^0/', '212', trim($order->phone))), '0') : null;

        return view('admin.orders.show', compact('order', 'wa'));
    }

    public function receipt(Order $order)
    {
        abort_unless($order->receipt_path && Storage::disk('local')->exists($order->receipt_path), 404);

        return Storage::disk('local')->response($order->receipt_path, null, ['X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'"]);
    }

    public function update(Request $request, Order $order)
    {
        $data = $request->validate(['status' => ['required', 'in:'.implode(',', Order::STATUSES)], 'transfer_reference' => ['nullable', 'string', 'max:120'], 'admin_note' => ['nullable', 'string', 'max:1000']]);
        $old = $order->status;
        $order->status = $data['status'];
        $order->transfer_reference = $data['transfer_reference'] ?? null;
        $order->admin_note = $data['admin_note'] ?? null;
        $order->paid_at = $data['status'] === 'paid' ? ($order->paid_at ?? now()) : null;
        $order->save();
        if ($order->status === 'paid') {
            Shop::fulfil($order);
        }
        if ($old !== $order->status) {
            Audit::log('order.status_changed', $order, ['from' => $old, 'to' => $order->status]);
        }

        return back()->with('success', 'تم تحديث الطلب '.$order->reference.'.');
    }
}
