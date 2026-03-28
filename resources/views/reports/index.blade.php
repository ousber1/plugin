@extends('layouts.app')
@section('title', 'Reports')

@section('content')
<div class="space-y-6">
    <h2 class="text-xl font-bold">Reports</h2>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <a href="{{ route('reports.sales') }}" class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6 hover:shadow-md transition group">
            <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900/30 rounded-xl flex items-center justify-center mb-4 group-hover:scale-110 transition">
                <i class="fas fa-chart-line text-blue-600 text-xl"></i>
            </div>
            <h3 class="font-semibold">Sales Report</h3>
            <p class="text-sm text-slate-400 mt-1">Revenue, orders, and channel breakdown</p>
        </a>

        <a href="{{ route('reports.products') }}" class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6 hover:shadow-md transition group">
            <div class="w-12 h-12 bg-emerald-100 dark:bg-emerald-900/30 rounded-xl flex items-center justify-center mb-4 group-hover:scale-110 transition">
                <i class="fas fa-box-open text-emerald-600 text-xl"></i>
            </div>
            <h3 class="font-semibold">Product Performance</h3>
            <p class="text-sm text-slate-400 mt-1">Top sellers, margins, slow movers</p>
        </a>

        <a href="{{ route('reports.customers') }}" class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6 hover:shadow-md transition group">
            <div class="w-12 h-12 bg-purple-100 dark:bg-purple-900/30 rounded-xl flex items-center justify-center mb-4 group-hover:scale-110 transition">
                <i class="fas fa-users text-purple-600 text-xl"></i>
            </div>
            <h3 class="font-semibold">Customer Insights</h3>
            <p class="text-sm text-slate-400 mt-1">Top customers, retention, growth</p>
        </a>

        <a href="{{ route('reports.profit') }}" class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6 hover:shadow-md transition group">
            <div class="w-12 h-12 bg-amber-100 dark:bg-amber-900/30 rounded-xl flex items-center justify-center mb-4 group-hover:scale-110 transition">
                <i class="fas fa-calculator text-amber-600 text-xl"></i>
            </div>
            <h3 class="font-semibold">Profit Report</h3>
            <p class="text-sm text-slate-400 mt-1">Revenue, costs, margins, ad spend</p>
        </a>
    </div>
</div>
@endsection
