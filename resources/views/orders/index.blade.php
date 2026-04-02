@extends('layouts.app')
@section('title', \App\Helpers\Lang::t('orders.title'))

@php $L = \App\Helpers\Lang::class; $cur = \App\Models\Setting::get('currency') ?? 'DH'; @endphp

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h2 class="text-xl font-bold">{{ $L::t('orders.title') }}</h2>
        <a href="{{ route('orders.create') }}" class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700">
            <i class="fas fa-plus mr-1"></i> {{ $L::t('orders.new_order') }}
        </a>
    </div>

    {{-- Filters --}}
    <form method="GET" class="flex flex-wrap gap-3 bg-white dark:bg-slate-800 rounded-xl p-4 border border-slate-200 dark:border-slate-700">
        <select name="status" class="border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
            <option value="">{{ $L::t('orders.all_statuses') }}</option>
            @php $statuses = ['pending' => $L::t('orders.pending'), 'confirmed' => $L::t('orders.confirmed'), 'shipped' => $L::t('orders.shipped'), 'delivered' => $L::t('orders.delivered'), 'cancelled' => $L::t('orders.cancelled')]; @endphp
            @foreach($statuses as $val => $label)
            <option value="{{ $val }}" {{ request('status') === $val ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
        <select name="channel" class="border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
            <option value="">{{ $L::locale() === 'fr' ? 'Tous les canaux' : 'All Channels' }}</option>
            <option value="pos" {{ request('channel') === 'pos' ? 'selected' : '' }}>POS</option>
            <option value="online" {{ request('channel') === 'online' ? 'selected' : '' }}>Online</option>
        </select>
        <input type="date" name="start_date" value="{{ request('start_date') }}" class="border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
        <input type="date" name="end_date" value="{{ request('end_date') }}" class="border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ $L::t('common.search') }}..." class="border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent flex-1 min-w-[200px]">
        <button type="submit" class="px-4 py-2 bg-slate-200 dark:bg-slate-700 rounded-lg text-sm font-medium hover:bg-slate-300">
            <i class="fas fa-filter mr-1"></i> {{ $L::t('common.filter') }}
        </button>
    </form>

    {{-- Table --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 dark:bg-slate-700/30 text-xs text-slate-500 dark:text-slate-400">
                <tr>
                    <th class="px-6 py-3 text-left">{{ $L::t('invoice.number') }}</th>
                    <th class="px-6 py-3 text-left">{{ $L::t('orders.customer') }}</th>
                    <th class="px-6 py-3 text-center">{{ $L::t('orders.channel') }}</th>
                    <th class="px-6 py-3 text-center">{{ $L::t('orders.items') }}</th>
                    <th class="px-6 py-3 text-right">{{ $L::t('orders.total') }}</th>
                    <th class="px-6 py-3 text-center">{{ $L::t('orders.payment') }}</th>
                    <th class="px-6 py-3 text-center">{{ $L::t('orders.status') }}</th>
                    <th class="px-6 py-3 text-left">{{ $L::t('orders.date') }}</th>
                    <th class="px-6 py-3 text-center">{{ $L::t('orders.actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                @forelse($orders as $order)
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/20">
                    <td class="px-6 py-3 font-mono text-xs">{{ $order->invoice_number }}</td>
                    <td class="px-6 py-3">{{ $order->customer->name ?? $L::t('pos.walk_in') }}</td>
                    <td class="px-6 py-3 text-center">
                        <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full {{ $order->channel === 'pos' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' : 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400' }}">
                            {{ strtoupper($order->channel) }}
                        </span>
                    </td>
                    <td class="px-6 py-3 text-center">{{ $order->items_count ?? 0 }}</td>
                    <td class="px-6 py-3 text-right font-semibold">{{ number_format($order->total, 2) }} {{ $cur }}</td>
                    <td class="px-6 py-3 text-center">
                        @php
                            $payLabels = ['unpaid' => $L::t('invoice.unpaid'), 'partial' => $L::t('invoice.partial'), 'paid' => $L::t('invoice.paid')];
                            $pc = ['unpaid' => 'red', 'partial' => 'amber', 'paid' => 'emerald'][$order->payment_status] ?? 'gray';
                        @endphp
                        <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full bg-{{ $pc }}-100 text-{{ $pc }}-700 dark:bg-{{ $pc }}-900/30 dark:text-{{ $pc }}-400">{{ $payLabels[$order->payment_status] ?? ucfirst($order->payment_status) }}</span>
                    </td>
                    <td class="px-6 py-3 text-center">
                        @php $sc = ['pending' => 'yellow', 'confirmed' => 'blue', 'shipped' => 'purple', 'delivered' => 'emerald', 'cancelled' => 'red'][$order->status] ?? 'gray'; @endphp
                        <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full bg-{{ $sc }}-100 text-{{ $sc }}-700 dark:bg-{{ $sc }}-900/30 dark:text-{{ $sc }}-400">{{ $statuses[$order->status] ?? ucfirst($order->status) }}</span>
                    </td>
                    <td class="px-6 py-3 text-xs text-slate-400">{{ $order->created_at->format('d/m/Y') }}</td>
                    <td class="px-6 py-3 text-center">
                        <div class="flex items-center justify-center gap-2">
                            <a href="{{ route('orders.show', $order) }}" class="text-primary-600 hover:text-primary-800" title="{{ $L::t('common.view') }}"><i class="fas fa-eye"></i></a>
                            <a href="{{ route('orders.edit', $order) }}" class="text-slate-400 hover:text-slate-600" title="{{ $L::t('common.edit') }}"><i class="fas fa-edit"></i></a>
                            <a href="{{ route('invoices.generate', ['saleId' => $order->id, 'type' => 'facture']) }}" class="text-amber-500 hover:text-amber-700" title="{{ $L::t('invoice.facture') }}"><i class="fas fa-file-invoice"></i></a>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="9" class="px-6 py-8 text-center text-slate-400">{{ $L::locale() === 'fr' ? 'Aucune commande trouvée' : 'No orders found' }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $orders->withQueryString()->links() }}
</div>
@endsection
