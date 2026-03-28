@extends('layouts.app')
@section('title', 'Customer Insights')

@section('content')
<div class="space-y-6">
    <div class="flex items-center gap-3">
        <a href="{{ route('reports.index') }}" class="text-slate-400 hover:text-slate-600"><i class="fas fa-arrow-left"></i></a>
        <h2 class="text-xl font-bold">Customer Insights</h2>
    </div>

    <form method="GET" class="flex gap-3 bg-white dark:bg-slate-800 rounded-xl p-4 border border-slate-200 dark:border-slate-700">
        <input type="date" name="start_date" value="{{ $startDate }}" class="border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
        <input type="date" name="end_date" value="{{ $endDate }}" class="border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
        <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium">Apply</button>
    </form>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="stat-card text-center"><p class="text-xs text-slate-500">Total Customers</p><p class="text-2xl font-bold">{{ number_format($totalCustomers) }}</p></div>
        <div class="stat-card text-center"><p class="text-xs text-slate-500">New Customers (Period)</p><p class="text-2xl font-bold text-emerald-600">{{ number_format($newCustomers) }}</p></div>
        <div class="stat-card text-center"><p class="text-xs text-slate-500">Repeat Customers</p><p class="text-2xl font-bold text-purple-600">{{ number_format($repeatCustomers) }}</p></div>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700"><h3 class="text-sm font-semibold"><i class="fas fa-crown text-amber-500 mr-1"></i> Top Customers</h3></div>
        <table class="w-full text-sm">
            <thead class="bg-slate-50 dark:bg-slate-700/30 text-xs text-slate-500"><tr><th class="px-6 py-3 text-left">Customer</th><th class="px-6 py-3 text-left">Phone</th><th class="px-6 py-3 text-right">Orders</th><th class="px-6 py-3 text-right">Revenue</th></tr></thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                @forelse($topCustomers as $customer)
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/20">
                    <td class="px-6 py-3 font-medium"><a href="{{ route('customers.show', $customer) }}" class="text-primary-600 hover:underline">{{ $customer->name }}</a></td>
                    <td class="px-6 py-3 text-slate-400">{{ $customer->phone ?? '-' }}</td>
                    <td class="px-6 py-3 text-right">{{ $customer->order_count }}</td>
                    <td class="px-6 py-3 text-right font-semibold">${{ number_format($customer->total_revenue ?? 0, 2) }}</td>
                </tr>
                @empty
                <tr><td colspan="4" class="px-6 py-8 text-center text-slate-400">No customer data</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
