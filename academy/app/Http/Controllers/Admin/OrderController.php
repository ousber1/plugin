<?php

namespace App\Http\Controllers\Admin;

use App\Models\Order;
use App\Support\Audit;
use Illuminate\Http\Request;

class OrderController extends AdminController
{
    public function index(Request $request)
    {
        $orders = Order::with(['user:id,name,email', 'product:id,title,slug'])
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->input('q'), fn ($q, $t) => $q->where('reference', 'like', "%$t%"))
            ->latest()->paginate(25)->withQueryString();

        return view('admin.orders.index', compact('orders'));
    }

    public function update(Request $request, Order $order)
    {
        $data = $request->validate(['status' => ['required', 'in:pending,paid,cancelled'], 'transfer_reference' => ['nullable', 'string', 'max:120'], 'admin_note' => ['nullable', 'string', 'max:1000']]);
        $old = $order->status;
        $order->status = $data['status'];
        $order->transfer_reference = $data['transfer_reference'] ?? null;
        $order->admin_note = $data['admin_note'] ?? null;
        $order->paid_at = $data['status'] === 'paid' ? ($order->paid_at ?? now()) : null;
        $order->save();
        if ($old !== $order->status) {
            Audit::log('order.status_changed', $order, ['from' => $old, 'to' => $order->status]);
        }

        return back()->with('success', 'تم تحديث الطلب '.$order->reference.'.');
    }
}
