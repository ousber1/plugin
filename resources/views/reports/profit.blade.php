@extends('layouts.app')
@section('title', 'Profit Report')

@section('content')
<div class="space-y-6">
    <div class="flex items-center gap-3">
        <a href="{{ route('reports.index') }}" class="text-slate-400 hover:text-slate-600"><i class="fas fa-arrow-left"></i></a>
        <h2 class="text-xl font-bold">Profit Report</h2>
    </div>

    <form method="GET" class="flex gap-3 bg-white dark:bg-slate-800 rounded-xl p-4 border border-slate-200 dark:border-slate-700">
        <input type="date" name="start_date" value="{{ $startDate }}" class="border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
        <input type="date" name="end_date" value="{{ $endDate }}" class="border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
        <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium">Apply</button>
    </form>

    {{-- Profit Breakdown --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6 space-y-4">
            <h3 class="font-semibold">Profit & Loss Statement</h3>
            <div class="space-y-3 text-sm">
                <div class="flex justify-between py-2 border-b border-slate-100 dark:border-slate-700">
                    <span class="text-slate-500"><i class="fas fa-arrow-up text-emerald-500 mr-2"></i>Sales Revenue</span>
                    <span class="font-bold text-emerald-600">${{ number_format($salesRevenue, 2) }}</span>
                </div>
                <div class="flex justify-between py-2 border-b border-slate-100 dark:border-slate-700">
                    <span class="text-slate-500"><i class="fas fa-arrow-down text-red-500 mr-2"></i>Cost of Goods Sold</span>
                    <span class="font-bold text-red-500">-${{ number_format($costOfGoods, 2) }}</span>
                </div>
                <div class="flex justify-between py-2 border-b-2 border-slate-300 dark:border-slate-600">
                    <span class="font-semibold">Gross Profit</span>
                    <span class="font-bold {{ $grossProfit >= 0 ? 'text-emerald-600' : 'text-red-500' }}">${{ number_format($grossProfit, 2) }}</span>
                </div>
                <div class="flex justify-between py-2 border-b border-slate-100 dark:border-slate-700">
                    <span class="text-slate-500"><i class="fas fa-bullhorn text-amber-500 mr-2"></i>Ad Spend</span>
                    <span class="font-bold text-red-500">-${{ number_format($adSpend, 2) }}</span>
                </div>
                <div class="flex justify-between py-3 bg-slate-50 dark:bg-slate-700/30 rounded-lg px-4 text-lg">
                    <span class="font-bold">Net Profit</span>
                    <span class="font-bold {{ $netProfit >= 0 ? 'text-emerald-600' : 'text-red-500' }}">${{ number_format($netProfit, 2) }}</span>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6 space-y-4">
            <h3 class="font-semibold">Margins</h3>
            <div class="grid grid-cols-2 gap-4">
                <div class="text-center p-6 bg-slate-50 dark:bg-slate-700/30 rounded-xl">
                    <p class="text-3xl font-bold {{ $grossMargin >= 0 ? 'text-emerald-600' : 'text-red-500' }}">{{ number_format($grossMargin, 1) }}%</p>
                    <p class="text-sm text-slate-400 mt-1">Gross Margin</p>
                </div>
                <div class="text-center p-6 bg-slate-50 dark:bg-slate-700/30 rounded-xl">
                    <p class="text-3xl font-bold {{ $netMargin >= 0 ? 'text-emerald-600' : 'text-red-500' }}">{{ number_format($netMargin, 1) }}%</p>
                    <p class="text-sm text-slate-400 mt-1">Net Margin</p>
                </div>
            </div>

            <div class="mt-4">
                <canvas id="profitChart" height="150"></canvas>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    new Chart(document.getElementById('profitChart'), {
        type: 'doughnut',
        data: {
            labels: ['COGS', 'Ad Spend', 'Net Profit'],
            datasets: [{
                data: [{{ $costOfGoods }}, {{ $adSpend }}, {{ max(0, $netProfit) }}],
                backgroundColor: ['#ef4444', '#f59e0b', '#10b981'],
                borderWidth: 0
            }]
        },
        options: { responsive: true, plugins: { legend: { position: 'bottom' } }, cutout: '60%' }
    });
});
</script>
@endpush
