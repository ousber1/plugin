@extends('layouts.app')
@section('title', 'Order #' . $order->invoice_number)

@section('content')
<div class="max-w-4xl space-y-6">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="{{ route('orders.index') }}" class="text-slate-400 hover:text-slate-600"><i class="fas fa-arrow-left"></i></a>
            <h2 class="text-xl font-bold">Order {{ $order->invoice_number }}</h2>
            @php $sc = ['pending' => 'yellow', 'confirmed' => 'blue', 'shipped' => 'purple', 'delivered' => 'emerald', 'cancelled' => 'red'][$order->status] ?? 'gray'; @endphp
            <span class="inline-flex px-3 py-1 text-xs font-medium rounded-full bg-{{ $sc }}-100 text-{{ $sc }}-700">{{ ucfirst($order->status) }}</span>
        </div>
        <a href="{{ route('orders.edit', $order) }}" class="px-4 py-2 bg-slate-200 dark:bg-slate-700 rounded-lg text-sm font-medium hover:bg-slate-300">
            <i class="fas fa-edit mr-1"></i> Edit
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            {{-- Order Info --}}
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                    <div><p class="text-slate-500 text-xs">Date</p><p class="font-medium">{{ $order->created_at->format('M d, Y H:i') }}</p></div>
                    <div><p class="text-slate-500 text-xs">Channel</p><p class="font-medium">{{ strtoupper($order->channel) }}</p></div>
                    <div><p class="text-slate-500 text-xs">Source</p><p class="font-medium">{{ ucfirst($order->source ?? 'N/A') }}</p></div>
                    <div><p class="text-slate-500 text-xs">Customer</p><p class="font-medium">{{ $order->customer->name ?? 'Walk-in' }}</p></div>
                </div>
            </div>

            {{-- Items --}}
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700"><h3 class="text-sm font-semibold">Items</h3></div>
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-700/30 text-xs text-slate-500"><tr><th class="px-6 py-2 text-left">Product</th><th class="px-6 py-2 text-center">Qty</th><th class="px-6 py-2 text-right">Price</th><th class="px-6 py-2 text-right">Total</th></tr></thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                        @foreach($order->items as $item)
                        <tr><td class="px-6 py-3">{{ $item->product->name ?? 'Deleted' }}</td><td class="px-6 py-3 text-center">{{ $item->quantity }}</td><td class="px-6 py-3 text-right">${{ number_format($item->unit_price, 2) }}</td><td class="px-6 py-3 text-right font-semibold">${{ number_format($item->total, 2) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-700 space-y-1 text-sm text-right">
                    <div>Subtotal: <span class="font-medium">${{ number_format($order->subtotal, 2) }}</span></div>
                    @if($order->discount > 0)<div>Discount: <span class="text-red-500">-${{ number_format($order->discount, 2) }}</span></div>@endif
                    <div class="text-lg font-bold">Total: ${{ number_format($order->total, 2) }}</div>
                </div>
            </div>

            {{-- Payment History --}}
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700"><h3 class="text-sm font-semibold">Payments</h3></div>
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-700/30 text-xs text-slate-500"><tr><th class="px-6 py-2 text-left">Date</th><th class="px-6 py-2 text-left">Method</th><th class="px-6 py-2 text-right">Amount</th><th class="px-6 py-2 text-left">Reference</th></tr></thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                        @forelse($order->payments as $payment)
                        <tr><td class="px-6 py-3">{{ $payment->created_at->format('M d, H:i') }}</td><td class="px-6 py-3">{{ ucfirst($payment->method) }}</td><td class="px-6 py-3 text-right font-semibold">${{ number_format($payment->amount, 2) }}</td><td class="px-6 py-3 text-slate-400">{{ $payment->reference ?? '-' }}</td></tr>
                        @empty
                        <tr><td colspan="4" class="px-6 py-4 text-center text-slate-400">No payments recorded</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Sidebar --}}
        <div class="space-y-6">
            {{-- Status Update --}}
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6 space-y-3">
                <h3 class="text-sm font-semibold">Update Status</h3>
                <form method="POST" action="{{ route('orders.status', $order->id) }}">
                    @csrf @method('PATCH')
                    <select name="status" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent mb-3">
                        @foreach(['pending','confirmed','shipped','delivered','cancelled'] as $s)
                        <option value="{{ $s }}" {{ $order->status === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="w-full py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700">Update</button>
                </form>
            </div>

            {{-- Notes --}}
            @if($order->notes)
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6">
                <h3 class="text-sm font-semibold mb-2">Notes</h3>
                <p class="text-sm text-slate-500">{{ $order->notes }}</p>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
