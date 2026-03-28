@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')
<div class="space-y-6">
    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="stat-card">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Total Revenue</p>
                    <p class="text-2xl font-bold mt-1">${{ number_format($totalRevenue, 2) }}</p>
                </div>
                <div class="w-12 h-12 bg-emerald-100 dark:bg-emerald-900/30 rounded-xl flex items-center justify-center">
                    <i class="fas fa-dollar-sign text-emerald-600 dark:text-emerald-400 text-xl"></i>
                </div>
            </div>
            <p class="text-xs text-slate-400 mt-3"><i class="fas fa-calendar mr-1"></i>All time</p>
        </div>

        <div class="stat-card">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Today's Sales</p>
                    <p class="text-2xl font-bold mt-1">${{ number_format($todayRevenue, 2) }}</p>
                </div>
                <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900/30 rounded-xl flex items-center justify-center">
                    <i class="fas fa-chart-line text-blue-600 dark:text-blue-400 text-xl"></i>
                </div>
            </div>
            <p class="text-xs text-slate-400 mt-3"><i class="fas fa-clock mr-1"></i>Last 24 hours</p>
        </div>

        <div class="stat-card">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Total Orders</p>
                    <p class="text-2xl font-bold mt-1">{{ number_format($totalOrders) }}</p>
                </div>
                <div class="w-12 h-12 bg-purple-100 dark:bg-purple-900/30 rounded-xl flex items-center justify-center">
                    <i class="fas fa-shopping-cart text-purple-600 dark:text-purple-400 text-xl"></i>
                </div>
            </div>
            <p class="text-xs text-slate-400 mt-3"><i class="fas fa-arrow-up mr-1 text-emerald-500"></i>All channels</p>
        </div>

        <div class="stat-card">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Customers</p>
                    <p class="text-2xl font-bold mt-1">{{ number_format($totalCustomers) }}</p>
                </div>
                <div class="w-12 h-12 bg-amber-100 dark:bg-amber-900/30 rounded-xl flex items-center justify-center">
                    <i class="fas fa-users text-amber-600 dark:text-amber-400 text-xl"></i>
                </div>
            </div>
            <p class="text-xs text-slate-400 mt-3"><i class="fas fa-user-plus mr-1"></i>Total registered</p>
        </div>
    </div>

    {{-- Charts Row --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <h3 class="text-sm font-semibold mb-4">Sales Trend (Last 30 Days)</h3>
            <canvas id="salesChart" height="100"></canvas>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <h3 class="text-sm font-semibold mb-4">Sales by Channel</h3>
            <canvas id="channelChart" height="200"></canvas>
            <div class="flex justify-center gap-6 mt-4 text-sm">
                <span class="flex items-center gap-2"><span class="w-3 h-3 bg-indigo-500 rounded-full"></span>POS</span>
                <span class="flex items-center gap-2"><span class="w-3 h-3 bg-emerald-500 rounded-full"></span>Online</span>
            </div>
        </div>
    </div>

    {{-- Recent Sales & Alerts --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Recent Sales --}}
        <div class="lg:col-span-2 bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                <h3 class="text-sm font-semibold">Recent Sales</h3>
                <a href="{{ route('orders.index') }}" class="text-xs text-primary-500 hover:underline">View all</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-xs text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-700/30">
                        <tr>
                            <th class="px-6 py-3 text-left">Invoice</th>
                            <th class="px-6 py-3 text-left">Customer</th>
                            <th class="px-6 py-3 text-left">Channel</th>
                            <th class="px-6 py-3 text-right">Total</th>
                            <th class="px-6 py-3 text-left">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                        @forelse($recentSales as $sale)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/20">
                            <td class="px-6 py-3 font-mono text-xs">{{ $sale->invoice_number }}</td>
                            <td class="px-6 py-3">{{ $sale->customer->name ?? 'Walk-in' }}</td>
                            <td class="px-6 py-3">
                                <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full {{ $sale->channel === 'pos' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' : 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400' }}">
                                    {{ strtoupper($sale->channel) }}
                                </span>
                            </td>
                            <td class="px-6 py-3 text-right font-semibold">${{ number_format($sale->total, 2) }}</td>
                            <td class="px-6 py-3">
                                @php
                                    $colors = ['pending' => 'yellow', 'confirmed' => 'blue', 'shipped' => 'purple', 'delivered' => 'emerald', 'cancelled' => 'red'];
                                    $c = $colors[$sale->status] ?? 'gray';
                                @endphp
                                <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full bg-{{ $c }}-100 text-{{ $c }}-700 dark:bg-{{ $c }}-900/30 dark:text-{{ $c }}-400">
                                    {{ ucfirst($sale->status) }}
                                </span>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="px-6 py-8 text-center text-slate-400">No sales yet</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Sidebar Panels --}}
        <div class="space-y-6">
            {{-- Low Stock Alerts --}}
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-sm font-semibold flex items-center gap-2">
                        <i class="fas fa-exclamation-triangle text-amber-500"></i>Low Stock Alerts
                    </h3>
                </div>
                <div class="p-4 space-y-2 max-h-48 overflow-y-auto">
                    @forelse($lowStockProducts as $product)
                    <div class="flex items-center justify-between py-1.5 px-2 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700/20">
                        <span class="text-sm truncate">{{ $product->name }}</span>
                        <span class="text-xs font-bold {{ $product->stock_quantity <= 0 ? 'text-red-500' : 'text-amber-500' }}">
                            {{ $product->stock_quantity }} left
                        </span>
                    </div>
                    @empty
                    <p class="text-sm text-slate-400 text-center py-2">All stock levels OK</p>
                    @endforelse
                </div>
            </div>

            {{-- Top Products --}}
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-sm font-semibold">Top Products</h3>
                </div>
                <div class="p-4 space-y-2 max-h-48 overflow-y-auto">
                    @forelse($topProducts as $item)
                    <div class="flex items-center justify-between py-1.5 px-2 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700/20">
                        <span class="text-sm truncate">{{ $item->product->name ?? 'N/A' }}</span>
                        <span class="text-xs font-semibold text-primary-600 dark:text-primary-400">{{ $item->total_qty }} sold</span>
                    </div>
                    @empty
                    <p class="text-sm text-slate-400 text-center py-2">No sales data</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const salesData = @json($salesChartData ?? []);
    const channelData = @json($channelData ?? ['pos' => 0, 'online' => 0]);
    const isDark = document.documentElement.classList.contains('dark');
    const gridColor = isDark ? 'rgba(148,163,184,0.1)' : 'rgba(148,163,184,0.2)';

    if (document.getElementById('salesChart')) {
        new Chart(document.getElementById('salesChart'), {
            type: 'line',
            data: {
                labels: salesData.map(d => d.date),
                datasets: [{
                    label: 'Revenue',
                    data: salesData.map(d => d.total),
                    borderColor: '#6366f1',
                    backgroundColor: 'rgba(99,102,241,0.1)',
                    fill: true,
                    tension: 0.4,
                    pointRadius: 2,
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    y: { grid: { color: gridColor }, ticks: { callback: v => '$' + v } },
                    x: { grid: { display: false }, ticks: { maxTicksLimit: 10 } }
                }
            }
        });
    }

    if (document.getElementById('channelChart')) {
        new Chart(document.getElementById('channelChart'), {
            type: 'doughnut',
            data: {
                labels: ['POS', 'Online'],
                datasets: [{ data: [channelData.pos, channelData.online], backgroundColor: ['#6366f1', '#10b981'], borderWidth: 0 }]
            },
            options: { responsive: true, plugins: { legend: { display: false } }, cutout: '70%' }
        });
    }
});
</script>
@endpush
