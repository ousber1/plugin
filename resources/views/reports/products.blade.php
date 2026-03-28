@extends('layouts.app')
@section('title', 'Product Performance')

@section('content')
<div class="space-y-6">
    <div class="flex items-center gap-3">
        <a href="{{ route('reports.index') }}" class="text-slate-400 hover:text-slate-600"><i class="fas fa-arrow-left"></i></a>
        <h2 class="text-xl font-bold">Product Performance</h2>
    </div>

    <form method="GET" class="flex gap-3 bg-white dark:bg-slate-800 rounded-xl p-4 border border-slate-200 dark:border-slate-700">
        <input type="date" name="start_date" value="{{ $startDate }}" class="border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
        <input type="date" name="end_date" value="{{ $endDate }}" class="border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
        <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium">Apply</button>
    </form>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Top Sellers --}}
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700"><h3 class="text-sm font-semibold text-emerald-600"><i class="fas fa-trophy mr-1"></i> Top Sellers</h3></div>
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-700/30 text-xs text-slate-500"><tr><th class="px-6 py-2 text-left">Product</th><th class="px-6 py-2 text-right">Qty</th><th class="px-6 py-2 text-right">Revenue</th></tr></thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @foreach($topProducts as $item)
                    <tr><td class="px-6 py-2">{{ $item->product->name ?? 'N/A' }}</td><td class="px-6 py-2 text-right">{{ $item->total_qty }}</td><td class="px-6 py-2 text-right font-semibold">${{ number_format($item->total_revenue, 2) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Slow Movers --}}
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700"><h3 class="text-sm font-semibold text-red-500"><i class="fas fa-exclamation-circle mr-1"></i> Slow Movers (No sales in 30 days)</h3></div>
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-700/30 text-xs text-slate-500"><tr><th class="px-6 py-2 text-left">Product</th><th class="px-6 py-2 text-right">Stock</th><th class="px-6 py-2 text-right">Price</th></tr></thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @forelse($slowProducts as $product)
                    <tr><td class="px-6 py-2">{{ $product->name }}</td><td class="px-6 py-2 text-right">{{ $product->stock_quantity }}</td><td class="px-6 py-2 text-right">${{ number_format($product->selling_price, 2) }}</td></tr>
                    @empty
                    <tr><td colspan="3" class="px-6 py-4 text-center text-slate-400">All products selling well</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Margins --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700"><h3 class="text-sm font-semibold">Profit Margins by Product</h3></div>
        <table class="w-full text-sm">
            <thead class="bg-slate-50 dark:bg-slate-700/30 text-xs text-slate-500"><tr><th class="px-6 py-2 text-left">Product</th><th class="px-6 py-2 text-right">Qty Sold</th><th class="px-6 py-2 text-right">Revenue</th><th class="px-6 py-2 text-right">Cost</th><th class="px-6 py-2 text-right">Margin</th><th class="px-6 py-2 text-right">Margin %</th></tr></thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                @foreach($productMargins->take(20) as $item)
                <tr>
                    <td class="px-6 py-2">{{ $item['product']->name ?? 'N/A' }}</td>
                    <td class="px-6 py-2 text-right">{{ $item['qty'] }}</td>
                    <td class="px-6 py-2 text-right">${{ number_format($item['revenue'], 2) }}</td>
                    <td class="px-6 py-2 text-right">${{ number_format($item['cost'], 2) }}</td>
                    <td class="px-6 py-2 text-right font-semibold {{ $item['margin'] >= 0 ? 'text-emerald-600' : 'text-red-500' }}">${{ number_format($item['margin'], 2) }}</td>
                    <td class="px-6 py-2 text-right">{{ number_format($item['margin_pct'], 1) }}%</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
