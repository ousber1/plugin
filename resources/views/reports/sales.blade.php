@extends('layouts.app')
@section('title', 'Sales Report')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="{{ route('reports.index') }}" class="text-slate-400 hover:text-slate-600"><i class="fas fa-arrow-left"></i></a>
            <h2 class="text-xl font-bold">Sales Report</h2>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('reports.export-excel', ['type' => 'sales', 'start_date' => $startDate, 'end_date' => $endDate]) }}" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700"><i class="fas fa-file-csv mr-1"></i> Export CSV</a>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" class="flex flex-wrap gap-3 bg-white dark:bg-slate-800 rounded-xl p-4 border border-slate-200 dark:border-slate-700">
        <input type="date" name="start_date" value="{{ $startDate }}" class="border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
        <input type="date" name="end_date" value="{{ $endDate }}" class="border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
        <select name="channel" class="border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
            <option value="">All Channels</option>
            <option value="pos" {{ $channel === 'pos' ? 'selected' : '' }}>POS</option>
            <option value="online" {{ $channel === 'online' ? 'selected' : '' }}>Online</option>
        </select>
        <select name="group_by" class="border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
            <option value="day" {{ $groupBy === 'day' ? 'selected' : '' }}>Daily</option>
            <option value="week" {{ $groupBy === 'week' ? 'selected' : '' }}>Weekly</option>
            <option value="month" {{ $groupBy === 'month' ? 'selected' : '' }}>Monthly</option>
        </select>
        <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700"><i class="fas fa-filter mr-1"></i> Apply</button>
    </form>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
        <div class="stat-card text-center"><p class="text-xs text-slate-500">Total Revenue</p><p class="text-xl font-bold text-emerald-600">${{ number_format($totalRevenue, 2) }}</p></div>
        <div class="stat-card text-center"><p class="text-xs text-slate-500">Orders</p><p class="text-xl font-bold">{{ number_format($totalOrders) }}</p></div>
        <div class="stat-card text-center"><p class="text-xs text-slate-500">Avg Order Value</p><p class="text-xl font-bold">${{ number_format($avgOrderValue, 2) }}</p></div>
        <div class="stat-card text-center"><p class="text-xs text-slate-500">POS Sales</p><p class="text-xl font-bold text-blue-600">${{ number_format($posSales, 2) }}</p></div>
        <div class="stat-card text-center"><p class="text-xs text-slate-500">Online Sales</p><p class="text-xl font-bold text-purple-600">${{ number_format($onlineSales, 2) }}</p></div>
    </div>

    {{-- Chart --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6">
        <h3 class="text-sm font-semibold mb-4">Revenue by {{ ucfirst($groupBy) }}</h3>
        <canvas id="salesReportChart" height="80"></canvas>
    </div>

    {{-- Sales Table --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 dark:bg-slate-700/30 text-xs text-slate-500">
                <tr><th class="px-6 py-3 text-left">Invoice</th><th class="px-6 py-3 text-left">Customer</th><th class="px-6 py-3 text-center">Channel</th><th class="px-6 py-3 text-right">Total</th><th class="px-6 py-3 text-center">Status</th><th class="px-6 py-3 text-left">Date</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                @foreach($sales as $sale)
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/20">
                    <td class="px-6 py-3 font-mono text-xs">{{ $sale->invoice_number }}</td>
                    <td class="px-6 py-3">{{ $sale->customer->name ?? 'Walk-in' }}</td>
                    <td class="px-6 py-3 text-center"><span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full {{ $sale->channel === 'pos' ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700' }}">{{ strtoupper($sale->channel) }}</span></td>
                    <td class="px-6 py-3 text-right font-semibold">${{ number_format($sale->total, 2) }}</td>
                    <td class="px-6 py-3 text-center"><span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full bg-emerald-100 text-emerald-700">{{ ucfirst($sale->status) }}</span></td>
                    <td class="px-6 py-3 text-xs text-slate-400">{{ $sale->created_at->format('M d, Y H:i') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    {{ $sales->withQueryString()->links() }}
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const data = @json($salesByPeriod);
    new Chart(document.getElementById('salesReportChart'), {
        type: 'bar',
        data: {
            labels: data.map(d => d.period),
            datasets: [{ label: 'Revenue', data: data.map(d => d.revenue), backgroundColor: 'rgba(99,102,241,0.7)', borderRadius: 6 }]
        },
        options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { ticks: { callback: v => '$' + v } }, x: { grid: { display: false } } } }
    });
});
</script>
@endpush
