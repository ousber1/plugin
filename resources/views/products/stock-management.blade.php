@extends('layouts.app')
@section('title', \App\Helpers\Lang::locale() === 'fr' ? 'Gestion de Stock' : 'Stock Management')

@php
    $L = \App\Helpers\Lang::class;
    $isFr = $L::locale() === 'fr';
    $cur = $currency ?? 'DH';
@endphp

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h2 class="text-xl font-bold"><i class="fas fa-warehouse mr-2 text-primary-500"></i>{{ $isFr ? 'Gestion de Stock' : 'Stock Management' }}</h2>
        <div class="flex gap-2">
            <a href="{{ route('products.index') }}" class="px-4 py-2 bg-slate-200 dark:bg-slate-700 rounded-lg text-sm font-medium hover:bg-slate-300">
                <i class="fas fa-boxes mr-1"></i> {{ $isFr ? 'Tous les produits' : 'All Products' }}
            </a>
        </div>
    </div>

    {{-- Overview Stats --}}
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4 text-center">
            <div class="w-10 h-10 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center mx-auto mb-2">
                <i class="fas fa-box text-blue-600"></i>
            </div>
            <p class="text-2xl font-bold">{{ $totalProducts }}</p>
            <p class="text-xs text-slate-500">{{ $isFr ? 'Produits actifs' : 'Active Products' }}</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4 text-center">
            <div class="w-10 h-10 rounded-full bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center mx-auto mb-2">
                <i class="fas fa-cubes text-emerald-600"></i>
            </div>
            <p class="text-2xl font-bold">{{ number_format($totalUnits) }}</p>
            <p class="text-xs text-slate-500">{{ $isFr ? 'Unités en stock' : 'Units in Stock' }}</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-amber-300 dark:border-amber-700 p-4 text-center">
            <div class="w-10 h-10 rounded-full bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center mx-auto mb-2">
                <i class="fas fa-exclamation-triangle text-amber-600"></i>
            </div>
            <p class="text-2xl font-bold text-amber-600">{{ $lowStockCount }}</p>
            <p class="text-xs text-slate-500">{{ $isFr ? 'Stock faible' : 'Low Stock' }}</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-red-300 dark:border-red-700 p-4 text-center">
            <div class="w-10 h-10 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center mx-auto mb-2">
                <i class="fas fa-times-circle text-red-600"></i>
            </div>
            <p class="text-2xl font-bold text-red-600">{{ $outOfStockCount }}</p>
            <p class="text-xs text-slate-500">{{ $isFr ? 'Rupture de stock' : 'Out of Stock' }}</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4 text-center">
            <div class="w-10 h-10 rounded-full bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center mx-auto mb-2">
                <i class="fas fa-coins text-purple-600"></i>
            </div>
            <p class="text-lg font-bold">{{ number_format($costValue, 2) }}</p>
            <p class="text-xs text-slate-500">{{ $isFr ? 'Valeur coût' : 'Cost Value' }} ({{ $cur }})</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4 text-center">
            <div class="w-10 h-10 rounded-full bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center mx-auto mb-2">
                <i class="fas fa-dollar-sign text-primary-600"></i>
            </div>
            <p class="text-lg font-bold text-primary-600">{{ number_format($sellValue, 2) }}</p>
            <p class="text-xs text-slate-500">{{ $isFr ? 'Valeur vente' : 'Selling Value' }} ({{ $cur }})</p>
        </div>
    </div>

    {{-- Profit Potential Banner --}}
    @php $potentialProfit = $sellValue - $costValue; @endphp
    <div class="bg-gradient-to-r from-emerald-500 to-teal-600 rounded-xl p-4 text-white flex items-center justify-between">
        <div>
            <p class="text-sm opacity-90">{{ $isFr ? 'Bénéfice potentiel total du stock' : 'Total Stock Potential Profit' }}</p>
            <p class="text-2xl font-bold">{{ number_format($potentialProfit, 2) }} {{ $cur }}</p>
        </div>
        <div class="text-right">
            <p class="text-sm opacity-90">{{ $isFr ? 'Marge moyenne' : 'Average Margin' }}</p>
            <p class="text-2xl font-bold">{{ $costValue > 0 ? round(($potentialProfit / $costValue) * 100, 1) : 0 }}%</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Left Column --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- Stock Movement Chart --}}
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                    <h3 class="text-sm font-semibold"><i class="fas fa-chart-bar mr-1 text-primary-500"></i>{{ $isFr ? 'Mouvements de stock (30 jours)' : 'Stock Movements (30 Days)' }}</h3>
                </div>
                <div class="p-6">
                    <canvas id="movementChart" height="200"></canvas>
                </div>
            </div>

            {{-- Low Stock Alerts --}}
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-sm font-semibold"><i class="fas fa-exclamation-triangle mr-1 text-amber-500"></i>{{ $isFr ? 'Alertes de stock faible' : 'Low Stock Alerts' }} ({{ $lowStockProducts->count() }})</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 dark:bg-slate-700/30 text-xs text-slate-500">
                            <tr>
                                <th class="px-4 py-2 text-left">{{ $isFr ? 'Produit' : 'Product' }}</th>
                                <th class="px-4 py-2 text-left">SKU</th>
                                <th class="px-4 py-2 text-center">{{ $isFr ? 'Stock' : 'Stock' }}</th>
                                <th class="px-4 py-2 text-center">{{ $isFr ? 'Seuil' : 'Threshold' }}</th>
                                <th class="px-4 py-2 text-center">{{ $isFr ? 'Statut' : 'Status' }}</th>
                                <th class="px-4 py-2 text-right">{{ $isFr ? 'Action' : 'Action' }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                            @forelse($lowStockProducts as $p)
                            <tr class="{{ $p->stock_quantity <= 0 ? 'bg-red-50 dark:bg-red-900/10' : 'bg-amber-50 dark:bg-amber-900/10' }}">
                                <td class="px-4 py-2 font-medium">{{ $p->name }}</td>
                                <td class="px-4 py-2 font-mono text-xs text-slate-500">{{ $p->sku }}</td>
                                <td class="px-4 py-2 text-center font-bold {{ $p->stock_quantity <= 0 ? 'text-red-600' : 'text-amber-600' }}">{{ $p->stock_quantity }}</td>
                                <td class="px-4 py-2 text-center text-slate-400">{{ $p->low_stock_threshold }}</td>
                                <td class="px-4 py-2 text-center">
                                    @if($p->stock_quantity <= 0)
                                    <span class="inline-flex px-2 py-0.5 text-xs font-bold rounded-full bg-red-100 text-red-700">{{ $isFr ? 'RUPTURE' : 'OUT' }}</span>
                                    @else
                                    <span class="inline-flex px-2 py-0.5 text-xs font-bold rounded-full bg-amber-100 text-amber-700">{{ $isFr ? 'FAIBLE' : 'LOW' }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-right">
                                    <a href="{{ route('products.show', $p->id) }}" class="text-primary-600 hover:text-primary-700 text-xs font-medium">
                                        <i class="fas fa-plus-circle mr-1"></i>{{ $isFr ? 'Réapprovisionner' : 'Restock' }}
                                    </a>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="6" class="px-4 py-6 text-center text-slate-400"><i class="fas fa-check-circle text-emerald-500 mr-1"></i>{{ $isFr ? 'Aucune alerte de stock' : 'No stock alerts' }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Recent Stock Movements --}}
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-sm font-semibold"><i class="fas fa-history mr-1 text-primary-500"></i>{{ $isFr ? 'Mouvements récents' : 'Recent Movements' }}</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 dark:bg-slate-700/30 text-xs text-slate-500">
                            <tr>
                                <th class="px-4 py-2 text-left">{{ $isFr ? 'Date' : 'Date' }}</th>
                                <th class="px-4 py-2 text-left">{{ $isFr ? 'Produit' : 'Product' }}</th>
                                <th class="px-4 py-2 text-center">{{ $isFr ? 'Type' : 'Type' }}</th>
                                <th class="px-4 py-2 text-right">{{ $isFr ? 'Quantité' : 'Qty' }}</th>
                                <th class="px-4 py-2 text-left">{{ $isFr ? 'Référence' : 'Reference' }}</th>
                                <th class="px-4 py-2 text-left">{{ $isFr ? 'Utilisateur' : 'User' }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                            @forelse($recentMovements as $m)
                            <tr>
                                <td class="px-4 py-2 text-xs text-slate-400">{{ $m->created_at->format('d/m/Y H:i') }}</td>
                                <td class="px-4 py-2 font-medium">
                                    @if($m->product)
                                    <a href="{{ route('products.show', $m->product_id) }}" class="text-primary-600 hover:underline">{{ $m->product->name }}</a>
                                    @else
                                    <span class="text-slate-400">-</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-center">
                                    <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full {{ $m->type === 'in' ? 'bg-emerald-100 text-emerald-700' : ($m->type === 'out' ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700') }}">
                                        {{ $m->type === 'in' ? ($isFr ? 'ENTRÉE' : 'IN') : ($m->type === 'out' ? ($isFr ? 'SORTIE' : 'OUT') : ($isFr ? 'AJUST.' : 'ADJ.')) }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-right font-bold {{ $m->type === 'out' ? 'text-red-600' : 'text-emerald-600' }}">
                                    {{ $m->type === 'out' ? '-' : '+' }}{{ $m->quantity }}
                                </td>
                                <td class="px-4 py-2 text-xs text-slate-400">{{ $m->reference ?? '-' }}</td>
                                <td class="px-4 py-2 text-xs">{{ $m->user->name ?? '-' }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="6" class="px-4 py-6 text-center text-slate-400">{{ $isFr ? 'Aucun mouvement' : 'No movements' }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Right Column --}}
        <div class="space-y-6">

            {{-- Stock by Category --}}
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-sm font-semibold"><i class="fas fa-tags mr-1 text-purple-500"></i>{{ $isFr ? 'Stock par catégorie' : 'Stock by Category' }}</h3>
                </div>
                <div class="divide-y divide-slate-100 dark:divide-slate-700">
                    @forelse($stockByCategory as $cat)
                    <div class="px-6 py-3">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-sm font-medium">{{ $cat->category }}</span>
                            <span class="text-xs text-slate-500">{{ $cat->count }} {{ $isFr ? 'produits' : 'products' }}</span>
                        </div>
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-slate-400">{{ number_format($cat->total_qty) }} {{ $isFr ? 'unités' : 'units' }}</span>
                            <span class="font-bold text-primary-600">{{ number_format($cat->total_value, 2) }} {{ $cur }}</span>
                        </div>
                        <div class="mt-1 w-full bg-slate-200 dark:bg-slate-700 rounded-full h-1.5">
                            <div class="bg-primary-500 h-1.5 rounded-full" style="width: {{ $totalUnits > 0 ? min(100, ($cat->total_qty / $totalUnits) * 100) : 0 }}%"></div>
                        </div>
                    </div>
                    @empty
                    <div class="px-6 py-4 text-center text-slate-400 text-sm">{{ $isFr ? 'Aucune catégorie' : 'No categories' }}</div>
                    @endforelse
                </div>
            </div>

            {{-- Top Moving Products --}}
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-sm font-semibold"><i class="fas fa-fire mr-1 text-orange-500"></i>{{ $isFr ? 'Plus vendus (30j)' : 'Top Moving (30d)' }}</h3>
                </div>
                <div class="divide-y divide-slate-100 dark:divide-slate-700">
                    @forelse($topMoving as $i => $tm)
                    <div class="px-6 py-3 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <span class="w-6 h-6 rounded-full bg-orange-100 dark:bg-orange-900/30 flex items-center justify-center text-xs font-bold text-orange-600">{{ $i + 1 }}</span>
                            <div>
                                @if($tm->product)
                                <a href="{{ route('products.show', $tm->product_id) }}" class="text-sm font-medium text-primary-600 hover:underline">{{ $tm->product->name }}</a>
                                <p class="text-xs text-slate-400">{{ $isFr ? 'Stock restant' : 'Remaining' }}: {{ $tm->product->stock_quantity }}</p>
                                @else
                                <span class="text-sm text-slate-400">-</span>
                                @endif
                            </div>
                        </div>
                        <span class="text-sm font-bold text-red-600">-{{ $tm->total_out }}</span>
                    </div>
                    @empty
                    <div class="px-6 py-4 text-center text-slate-400 text-sm">{{ $isFr ? 'Aucune donnée' : 'No data' }}</div>
                    @endforelse
                </div>
            </div>

            {{-- Slow Moving Products --}}
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-sm font-semibold"><i class="fas fa-snowflake mr-1 text-blue-500"></i>{{ $isFr ? 'Stock dormant (30j)' : 'Slow Moving (30d)' }}</h3>
                </div>
                <div class="divide-y divide-slate-100 dark:divide-slate-700">
                    @forelse($slowMoving as $sm)
                    <div class="px-6 py-3 flex items-center justify-between">
                        <div>
                            <a href="{{ route('products.show', $sm->id) }}" class="text-sm font-medium text-primary-600 hover:underline">{{ $sm->name }}</a>
                            <p class="text-xs text-slate-400">{{ $sm->category ?? ($isFr ? 'Sans catégorie' : 'Uncategorized') }}</p>
                        </div>
                        <div class="text-right">
                            <span class="text-sm font-bold">{{ $sm->stock_quantity }}</span>
                            <p class="text-xs text-slate-400">{{ number_format($sm->cost_price * $sm->stock_quantity, 2) }} {{ $cur }}</p>
                        </div>
                    </div>
                    @empty
                    <div class="px-6 py-4 text-center text-slate-400 text-sm">{{ $isFr ? 'Aucun stock dormant' : 'No slow moving items' }}</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const chartData = @json($movementChart);
    const dates = [];
    const inData = [];
    const outData = [];

    // Build 30 days of labels
    for (let i = 29; i >= 0; i--) {
        const d = new Date();
        d.setDate(d.getDate() - i);
        const key = d.toISOString().split('T')[0];
        dates.push(d.toLocaleDateString('{{ $isFr ? "fr-FR" : "en-US" }}', { day: '2-digit', month: 'short' }));

        const dayData = chartData[key] || [];
        let inVal = 0, outVal = 0;
        if (Array.isArray(dayData)) {
            dayData.forEach(function(item) {
                if (item.type === 'in') inVal += parseInt(item.total);
                if (item.type === 'out') outVal += parseInt(item.total);
            });
        }
        inData.push(inVal);
        outData.push(outVal);
    }

    new Chart(document.getElementById('movementChart'), {
        type: 'bar',
        data: {
            labels: dates,
            datasets: [
                {
                    label: '{{ $isFr ? "Entrées" : "Stock In" }}',
                    data: inData,
                    backgroundColor: 'rgba(16, 185, 129, 0.7)',
                    borderRadius: 4
                },
                {
                    label: '{{ $isFr ? "Sorties" : "Stock Out" }}',
                    data: outData,
                    backgroundColor: 'rgba(239, 68, 68, 0.7)',
                    borderRadius: 4
                }
            ]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'top' } },
            scales: {
                x: { grid: { display: false }, ticks: { maxRotation: 45, font: { size: 10 } } },
                y: { beginAtZero: true, ticks: { stepSize: 1 } }
            }
        }
    });
});
</script>
@endpush
