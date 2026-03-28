@extends('layouts.app')
@section('title', $customer->name)

@section('content')
<div class="max-w-4xl space-y-6">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="{{ route('customers.index') }}" class="text-slate-400 hover:text-slate-600"><i class="fas fa-arrow-left"></i></a>
            <h2 class="text-xl font-bold">{{ $customer->name }}</h2>
            @foreach(($customer->tags ?? []) as $tag)
            <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400">{{ $tag }}</span>
            @endforeach
        </div>
        <a href="{{ route('customers.edit', $customer) }}" class="px-4 py-2 bg-slate-200 dark:bg-slate-700 rounded-lg text-sm font-medium hover:bg-slate-300"><i class="fas fa-edit mr-1"></i> Edit</a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            {{-- Customer Info --}}
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6">
                <div class="grid grid-cols-2 md:grid-cols-3 gap-4 text-sm">
                    <div><p class="text-xs text-slate-500">Email</p><p class="font-medium">{{ $customer->email ?? '-' }}</p></div>
                    <div><p class="text-xs text-slate-500">Phone</p><p class="font-medium">{{ $customer->phone ?? '-' }}</p></div>
                    <div><p class="text-xs text-slate-500">City</p><p class="font-medium">{{ $customer->city ?? '-' }}</p></div>
                </div>
                @if($customer->address)
                <div class="mt-3 pt-3 border-t border-slate-100 dark:border-slate-700"><p class="text-xs text-slate-500">Address</p><p class="text-sm">{{ $customer->address }}</p></div>
                @endif
                @if($customer->notes)
                <div class="mt-3 pt-3 border-t border-slate-100 dark:border-slate-700"><p class="text-xs text-slate-500">Notes</p><p class="text-sm">{{ $customer->notes }}</p></div>
                @endif
            </div>

            {{-- Purchase History --}}
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700"><h3 class="text-sm font-semibold">Purchase History</h3></div>
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-700/30 text-xs text-slate-500"><tr><th class="px-6 py-2 text-left">Invoice</th><th class="px-6 py-2 text-left">Date</th><th class="px-6 py-2 text-center">Channel</th><th class="px-6 py-2 text-right">Total</th><th class="px-6 py-2 text-center">Status</th></tr></thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                        @forelse($customer->sales()->latest()->limit(20)->get() as $sale)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/20">
                            <td class="px-6 py-3"><a href="{{ route('orders.show', $sale) }}" class="text-primary-600 hover:underline font-mono text-xs">{{ $sale->invoice_number }}</a></td>
                            <td class="px-6 py-3 text-xs">{{ $sale->created_at->format('M d, Y') }}</td>
                            <td class="px-6 py-3 text-center"><span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">{{ strtoupper($sale->channel) }}</span></td>
                            <td class="px-6 py-3 text-right font-semibold">${{ number_format($sale->total, 2) }}</td>
                            <td class="px-6 py-3 text-center"><span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full bg-emerald-100 text-emerald-700">{{ ucfirst($sale->status) }}</span></td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="px-6 py-8 text-center text-slate-400">No purchases yet</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Stats Sidebar --}}
        <div class="space-y-6">
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6 space-y-4">
                <h3 class="text-sm font-semibold">Customer Stats</h3>
                <div class="text-center">
                    <p class="text-3xl font-bold text-primary-600">${{ number_format($customer->total_spent, 2) }}</p>
                    <p class="text-xs text-slate-400">Total Spent</p>
                </div>
                <div class="grid grid-cols-2 gap-3 text-center">
                    <div class="bg-slate-50 dark:bg-slate-700/30 rounded-lg p-3">
                        <p class="text-lg font-bold">{{ $customer->total_purchases }}</p>
                        <p class="text-xs text-slate-400">Orders</p>
                    </div>
                    <div class="bg-slate-50 dark:bg-slate-700/30 rounded-lg p-3">
                        <p class="text-lg font-bold">${{ $customer->total_purchases > 0 ? number_format($customer->total_spent / $customer->total_purchases, 2) : '0.00' }}</p>
                        <p class="text-xs text-slate-400">Avg Order</p>
                    </div>
                </div>
            </div>

            @if($customer->whatsappContact)
            <a href="{{ route('whatsapp.index') }}" class="block bg-emerald-50 dark:bg-emerald-900/20 rounded-xl border border-emerald-200 dark:border-emerald-800 p-4 hover:bg-emerald-100 transition">
                <div class="flex items-center gap-3">
                    <i class="fab fa-whatsapp text-2xl text-emerald-600"></i>
                    <div><p class="text-sm font-medium text-emerald-700">WhatsApp Connected</p><p class="text-xs text-emerald-500">{{ $customer->whatsappContact->phone }}</p></div>
                </div>
            </a>
            @endif
        </div>
    </div>
</div>
@endsection
