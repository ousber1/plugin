@extends('layouts.app')
@section('title', 'Orders')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h2 class="text-xl font-bold">Orders</h2>
        <a href="{{ route('orders.create') }}" class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700">
            <i class="fas fa-plus mr-1"></i> New Order
        </a>
    </div>

    {{-- Filters --}}
    <form method="GET" class="flex flex-wrap gap-3 bg-white dark:bg-slate-800 rounded-xl p-4 border border-slate-200 dark:border-slate-700">
        <select name="status" class="border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
            <option value="">All Statuses</option>
            @foreach(['pending','confirmed','shipped','delivered','cancelled'] as $s)
            <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
            @endforeach
        </select>
        <select name="channel" class="border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
            <option value="">All Channels</option>
            <option value="pos" {{ request('channel') === 'pos' ? 'selected' : '' }}>POS</option>
            <option value="online" {{ request('channel') === 'online' ? 'selected' : '' }}>Online</option>
        </select>
        <input type="date" name="start_date" value="{{ request('start_date') }}" class="border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
        <input type="date" name="end_date" value="{{ request('end_date') }}" class="border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search invoice, customer..." class="border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent flex-1 min-w-[200px]">
        <button type="submit" class="px-4 py-2 bg-slate-200 dark:bg-slate-700 rounded-lg text-sm font-medium hover:bg-slate-300">
            <i class="fas fa-filter mr-1"></i> Filter
        </button>
    </form>

    {{-- Table --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 dark:bg-slate-700/30 text-xs text-slate-500 dark:text-slate-400">
                <tr>
                    <th class="px-6 py-3 text-left">Invoice</th>
                    <th class="px-6 py-3 text-left">Customer</th>
                    <th class="px-6 py-3 text-center">Channel</th>
                    <th class="px-6 py-3 text-center">Items</th>
                    <th class="px-6 py-3 text-right">Total</th>
                    <th class="px-6 py-3 text-center">Payment</th>
                    <th class="px-6 py-3 text-center">Status</th>
                    <th class="px-6 py-3 text-left">Date</th>
                    <th class="px-6 py-3 text-center">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                @forelse($orders as $order)
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/20">
                    <td class="px-6 py-3 font-mono text-xs">{{ $order->invoice_number }}</td>
                    <td class="px-6 py-3">{{ $order->customer->name ?? 'Walk-in' }}</td>
                    <td class="px-6 py-3 text-center">
                        <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full {{ $order->channel === 'pos' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' : 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400' }}">
                            {{ strtoupper($order->channel) }}
                        </span>
                    </td>
                    <td class="px-6 py-3 text-center">{{ $order->items_count ?? $order->items->count() }}</td>
                    <td class="px-6 py-3 text-right font-semibold">${{ number_format($order->total, 2) }}</td>
                    <td class="px-6 py-3 text-center">
                        @php $pc = ['unpaid' => 'red', 'partial' => 'amber', 'paid' => 'emerald'][$order->payment_status] ?? 'gray'; @endphp
                        <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full bg-{{ $pc }}-100 text-{{ $pc }}-700 dark:bg-{{ $pc }}-900/30 dark:text-{{ $pc }}-400">{{ ucfirst($order->payment_status) }}</span>
                    </td>
                    <td class="px-6 py-3 text-center">
                        @php $sc = ['pending' => 'yellow', 'confirmed' => 'blue', 'shipped' => 'purple', 'delivered' => 'emerald', 'cancelled' => 'red'][$order->status] ?? 'gray'; @endphp
                        <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full bg-{{ $sc }}-100 text-{{ $sc }}-700 dark:bg-{{ $sc }}-900/30 dark:text-{{ $sc }}-400">{{ ucfirst($order->status) }}</span>
                    </td>
                    <td class="px-6 py-3 text-xs text-slate-400">{{ $order->created_at->format('M d, Y') }}</td>
                    <td class="px-6 py-3 text-center">
                        <div class="flex items-center justify-center gap-2">
                            <a href="{{ route('orders.show', $order) }}" class="text-primary-600 hover:text-primary-800"><i class="fas fa-eye"></i></a>
                            <a href="{{ route('orders.edit', $order) }}" class="text-slate-400 hover:text-slate-600"><i class="fas fa-edit"></i></a>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="9" class="px-6 py-8 text-center text-slate-400">No orders found</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $orders->withQueryString()->links() }}
</div>
@endsection
