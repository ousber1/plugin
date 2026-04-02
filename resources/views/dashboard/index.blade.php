@extends('layouts.app')
@section('title', \App\Helpers\Lang::t('dashboard.title'))

@php $L = \App\Helpers\Lang::class; $cur = $currency ?? 'MAD'; @endphp

@section('content')
<div class="space-y-6">
    {{-- Stats Cards Row 1 --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="stat-card">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ $L::t('dashboard.total_revenue') }}</p>
                    <p class="text-2xl font-bold mt-1">{{ number_format($totalRevenue, 2) }} {{ $cur }}</p>
                </div>
                <div class="w-12 h-12 bg-emerald-100 dark:bg-emerald-900/30 rounded-xl flex items-center justify-center">
                    <i class="fas fa-coins text-emerald-600 dark:text-emerald-400 text-xl"></i>
                </div>
            </div>
            <p class="text-xs text-slate-400 mt-3"><i class="fas fa-calendar mr-1"></i>{{ $L::locale() === 'fr' ? 'Depuis le début' : 'All time' }}</p>
        </div>

        <div class="stat-card">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ $L::t('dashboard.today_revenue') }}</p>
                    <p class="text-2xl font-bold mt-1">{{ number_format($todayRevenue, 2) }} {{ $cur }}</p>
                </div>
                <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900/30 rounded-xl flex items-center justify-center">
                    <i class="fas fa-chart-line text-blue-600 dark:text-blue-400 text-xl"></i>
                </div>
            </div>
            <p class="text-xs mt-3 {{ ($revenueChange ?? 0) >= 0 ? 'text-emerald-500' : 'text-red-500' }}">
                <i class="fas fa-arrow-{{ ($revenueChange ?? 0) >= 0 ? 'up' : 'down' }} mr-1"></i>
                {{ abs($revenueChange ?? 0) }}% {{ $L::locale() === 'fr' ? 'vs hier' : 'vs yesterday' }}
            </p>
        </div>

        <div class="stat-card">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ $L::t('dashboard.monthly_revenue') }}</p>
                    <p class="text-2xl font-bold mt-1">{{ number_format($monthlyRevenue, 2) }} {{ $cur }}</p>
                </div>
                <div class="w-12 h-12 bg-purple-100 dark:bg-purple-900/30 rounded-xl flex items-center justify-center">
                    <i class="fas fa-calendar-alt text-purple-600 dark:text-purple-400 text-xl"></i>
                </div>
            </div>
            <p class="text-xs text-slate-400 mt-3"><i class="fas fa-clock mr-1"></i>{{ now()->format('F Y') }}</p>
        </div>

        <div class="stat-card">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ $L::t('dashboard.total_orders') }}</p>
                    <p class="text-2xl font-bold mt-1">{{ number_format($totalOrders) }}</p>
                </div>
                <div class="w-12 h-12 bg-amber-100 dark:bg-amber-900/30 rounded-xl flex items-center justify-center">
                    <i class="fas fa-shopping-cart text-amber-600 dark:text-amber-400 text-xl"></i>
                </div>
            </div>
            <p class="text-xs text-slate-400 mt-3"><i class="fas fa-plus-circle mr-1 text-blue-400"></i>{{ $todayOrders ?? 0 }} {{ $L::locale() === 'fr' ? "aujourd'hui" : 'today' }}</p>
        </div>
    </div>

    {{-- Stats Cards Row 2 --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="stat-card">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ $L::t('dashboard.total_customers') }}</p>
                    <p class="text-2xl font-bold mt-1">{{ number_format($totalCustomers) }}</p>
                </div>
                <div class="w-12 h-12 bg-indigo-100 dark:bg-indigo-900/30 rounded-xl flex items-center justify-center">
                    <i class="fas fa-users text-indigo-600 dark:text-indigo-400 text-xl"></i>
                </div>
            </div>
            <p class="text-xs text-emerald-500 mt-3"><i class="fas fa-user-plus mr-1"></i>+{{ $newCustomersThisMonth ?? 0 }} {{ $L::locale() === 'fr' ? 'ce mois' : 'this month' }}</p>
        </div>

        <div class="stat-card">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ $L::locale() === 'fr' ? 'Produits actifs' : 'Active Products' }}</p>
                    <p class="text-2xl font-bold mt-1">{{ number_format($totalProducts ?? 0) }}</p>
                </div>
                <div class="w-12 h-12 bg-teal-100 dark:bg-teal-900/30 rounded-xl flex items-center justify-center">
                    <i class="fas fa-box-open text-teal-600 dark:text-teal-400 text-xl"></i>
                </div>
            </div>
            <p class="text-xs text-amber-500 mt-3"><i class="fas fa-exclamation-triangle mr-1"></i>{{ $lowStockProducts->count() }} {{ $L::locale() === 'fr' ? 'stock faible' : 'low stock' }}</p>
        </div>

        <div class="stat-card">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ $L::locale() === 'fr' ? 'Bénéfice estimé' : 'Estimated Profit' }}</p>
                    <p class="text-2xl font-bold mt-1 text-emerald-600">{{ number_format($totalProfit ?? 0, 2) }} {{ $cur }}</p>
                </div>
                <div class="w-12 h-12 bg-emerald-100 dark:bg-emerald-900/30 rounded-xl flex items-center justify-center">
                    <i class="fas fa-hand-holding-usd text-emerald-600 dark:text-emerald-400 text-xl"></i>
                </div>
            </div>
            <p class="text-xs text-slate-400 mt-3"><i class="fas fa-percentage mr-1"></i>{{ $totalRevenue > 0 ? round(($totalProfit / $totalRevenue) * 100, 1) : 0 }}% {{ $L::locale() === 'fr' ? 'marge' : 'margin' }}</p>
        </div>

        <div class="stat-card">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ $L::locale() === 'fr' ? 'Dépenses Ads' : 'Ads Spend' }}</p>
                    <p class="text-2xl font-bold mt-1 text-red-500">{{ number_format($adsMetrics['total_spend'] ?? 0, 2) }} {{ $cur }}</p>
                </div>
                <div class="w-12 h-12 bg-red-100 dark:bg-red-900/30 rounded-xl flex items-center justify-center">
                    <i class="fas fa-bullhorn text-red-600 dark:text-red-400 text-xl"></i>
                </div>
            </div>
            <p class="text-xs {{ ($adsMetrics['avg_roi'] ?? 0) > 0 ? 'text-emerald-500' : 'text-red-500' }} mt-3"><i class="fas fa-chart-pie mr-1"></i>ROI: {{ number_format($adsMetrics['avg_roi'] ?? 0, 1) }}%</p>
        </div>
    </div>

    {{-- Charts Row --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-semibold">{{ $L::locale() === 'fr' ? 'Tendance des ventes (30 jours)' : 'Sales Trend (Last 30 Days)' }}</h3>
            </div>
            <canvas id="salesChart" height="100"></canvas>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <h3 class="text-sm font-semibold mb-4">{{ $L::locale() === 'fr' ? 'Ventes par canal' : 'Sales by Channel' }}</h3>
            <canvas id="channelChart" height="200"></canvas>
            <div class="flex justify-center gap-6 mt-4 text-sm">
                <span class="flex items-center gap-2"><span class="w-3 h-3 bg-indigo-500 rounded-full"></span>POS</span>
                <span class="flex items-center gap-2"><span class="w-3 h-3 bg-emerald-500 rounded-full"></span>Online</span>
            </div>
        </div>
    </div>

    {{-- Monthly Revenue Chart --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
        <h3 class="text-sm font-semibold mb-4">{{ $L::locale() === 'fr' ? 'Revenus mensuels (12 mois)' : 'Monthly Revenue (12 Months)' }}</h3>
        <canvas id="monthlyChart" height="60"></canvas>
    </div>

    {{-- Payment Methods Breakdown --}}
    @if(isset($paymentMethods) && $paymentMethods->count() > 0)
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        @php
            $methodLabels = ['cash' => ['fr' => 'Espèces', 'en' => 'Cash', 'icon' => 'fa-money-bill-wave', 'color' => 'emerald'],
                            'card' => ['fr' => 'Carte bancaire', 'en' => 'Card', 'icon' => 'fa-credit-card', 'color' => 'blue'],
                            'bank_transfer' => ['fr' => 'Virement', 'en' => 'Transfer', 'icon' => 'fa-university', 'color' => 'purple']];
        @endphp
        @foreach($methodLabels as $method => $info)
        <div class="stat-card">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-slate-500">{{ $info[$L::locale()] ?? ucfirst($method) }}</p>
                    <p class="text-xl font-bold mt-1">{{ number_format(($paymentMethods[$method]->total ?? 0), 2) }} {{ $cur }}</p>
                </div>
                <div class="w-10 h-10 bg-{{ $info['color'] }}-100 dark:bg-{{ $info['color'] }}-900/30 rounded-xl flex items-center justify-center">
                    <i class="fas {{ $info['icon'] }} text-{{ $info['color'] }}-600"></i>
                </div>
            </div>
            <p class="text-xs text-slate-400 mt-2">{{ $paymentMethods[$method]->count ?? 0 }} {{ $L::locale() === 'fr' ? 'transactions' : 'transactions' }}</p>
        </div>
        @endforeach
    </div>
    @endif

    {{-- Recent Sales & Alerts --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Recent Sales --}}
        <div class="lg:col-span-2 bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                <h3 class="text-sm font-semibold">{{ $L::t('dashboard.recent_sales') }}</h3>
                <a href="{{ route('orders.index') }}" class="text-xs text-primary-500 hover:underline">{{ $L::t('common.view') }} {{ $L::locale() === 'fr' ? 'tout' : 'all' }}</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-xs text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-700/30">
                        <tr>
                            <th class="px-6 py-3 text-left">{{ $L::t('invoice.number') }}</th>
                            <th class="px-6 py-3 text-left">{{ $L::t('invoice.client') }}</th>
                            <th class="px-6 py-3 text-left">{{ $L::t('orders.channel') }}</th>
                            <th class="px-6 py-3 text-right">{{ $L::t('orders.total') }}</th>
                            <th class="px-6 py-3 text-left">{{ $L::t('orders.status') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                        @forelse($recentSales as $sale)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/20">
                            <td class="px-6 py-3 font-mono text-xs">{{ $sale->invoice_number }}</td>
                            <td class="px-6 py-3">{{ $sale->customer->name ?? ($L::locale() === 'fr' ? 'Client de passage' : 'Walk-in') }}</td>
                            <td class="px-6 py-3">
                                <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full {{ $sale->channel === 'pos' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' : 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400' }}">
                                    {{ strtoupper($sale->channel) }}
                                </span>
                            </td>
                            <td class="px-6 py-3 text-right font-semibold">{{ number_format($sale->total, 2) }} {{ $cur }}</td>
                            <td class="px-6 py-3">
                                @php
                                    $statusLabels = ['pending' => $L::t('orders.pending'), 'confirmed' => $L::t('orders.confirmed'), 'shipped' => $L::t('orders.shipped'), 'delivered' => $L::t('orders.delivered'), 'cancelled' => $L::t('orders.cancelled')];
                                    $colors = ['pending' => 'yellow', 'confirmed' => 'blue', 'shipped' => 'purple', 'delivered' => 'emerald', 'cancelled' => 'red'];
                                    $c = $colors[$sale->status] ?? 'gray';
                                @endphp
                                <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full bg-{{ $c }}-100 text-{{ $c }}-700 dark:bg-{{ $c }}-900/30 dark:text-{{ $c }}-400">
                                    {{ $statusLabels[$sale->status] ?? ucfirst($sale->status) }}
                                </span>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="px-6 py-8 text-center text-slate-400">{{ $L::locale() === 'fr' ? 'Aucune vente pour le moment' : 'No sales yet' }}</td></tr>
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
                        <i class="fas fa-exclamation-triangle text-amber-500"></i>{{ $L::t('dashboard.low_stock') }}
                    </h3>
                </div>
                <div class="p-4 space-y-2 max-h-48 overflow-y-auto">
                    @forelse($lowStockProducts as $product)
                    <div class="flex items-center justify-between py-1.5 px-2 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700/20">
                        <span class="text-sm truncate">{{ $product->name }}</span>
                        <span class="text-xs font-bold {{ $product->stock_quantity <= 0 ? 'text-red-500' : 'text-amber-500' }}">
                            {{ $product->stock_quantity }} {{ $L::locale() === 'fr' ? 'restant' : 'left' }}
                        </span>
                    </div>
                    @empty
                    <p class="text-sm text-slate-400 text-center py-2">{{ $L::locale() === 'fr' ? 'Stock OK' : 'All stock levels OK' }}</p>
                    @endforelse
                </div>
            </div>

            {{-- Top Products --}}
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-sm font-semibold">{{ $L::t('dashboard.top_products') }}</h3>
                </div>
                <div class="p-4 space-y-2 max-h-48 overflow-y-auto">
                    @forelse($topProducts as $item)
                    <div class="flex items-center justify-between py-1.5 px-2 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700/20">
                        <span class="text-sm truncate">{{ $item->product->name ?? 'N/A' }}</span>
                        <div class="text-right">
                            <span class="text-xs font-semibold text-primary-600 dark:text-primary-400">{{ $item->total_qty }} {{ $L::locale() === 'fr' ? 'vendus' : 'sold' }}</span>
                            <p class="text-[10px] text-slate-400">{{ number_format($item->total_revenue, 2) }} {{ $cur }}</p>
                        </div>
                    </div>
                    @empty
                    <p class="text-sm text-slate-400 text-center py-2">{{ $L::locale() === 'fr' ? 'Pas de données' : 'No sales data' }}</p>
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
    const monthlyData = @json($monthlyChartData ?? []);
    const isDark = document.documentElement.classList.contains('dark');
    const gridColor = isDark ? 'rgba(148,163,184,0.1)' : 'rgba(148,163,184,0.2)';
    const cur = @json($cur);

    if (document.getElementById('salesChart')) {
        new Chart(document.getElementById('salesChart'), {
            type: 'line',
            data: {
                labels: salesData.map(d => d.date),
                datasets: [{
                    label: '{{ $L::locale() === "fr" ? "Revenus" : "Revenue" }}',
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
                    y: { grid: { color: gridColor }, ticks: { callback: v => v.toLocaleString() + ' ' + cur } },
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

    if (document.getElementById('monthlyChart') && monthlyData.length > 0) {
        new Chart(document.getElementById('monthlyChart'), {
            type: 'bar',
            data: {
                labels: monthlyData.map(d => d.month),
                datasets: [{
                    label: '{{ $L::locale() === "fr" ? "Revenus mensuels" : "Monthly Revenue" }}',
                    data: monthlyData.map(d => d.total),
                    backgroundColor: 'rgba(99,102,241,0.7)',
                    borderRadius: 6,
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    y: { grid: { color: gridColor }, ticks: { callback: v => v.toLocaleString() + ' ' + cur } },
                    x: { grid: { display: false } }
                }
            }
        });
    }
});
</script>
@endpush
