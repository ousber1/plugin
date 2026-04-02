@extends('layouts.app')
@section('title', $product->name)

@php
    $L = \App\Helpers\Lang::class;
    $isFr = $L::locale() === 'fr';
    $cur = \App\Models\Setting::get('currency') ?? 'DH';
@endphp

@section('content')
<div class="max-w-4xl space-y-6">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="{{ route('products.index') }}" class="text-slate-400 hover:text-slate-600"><i class="fas fa-arrow-left"></i></a>
            <h2 class="text-xl font-bold">{{ $product->name }}</h2>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('products.edit', $product) }}" class="px-4 py-2 bg-slate-200 dark:bg-slate-700 rounded-lg text-sm font-medium hover:bg-slate-300">
                <i class="fas fa-edit mr-1"></i> {{ $L::t('common.edit') }}
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            {{-- Product Details --}}
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6">
                @if($product->image)
                <div class="mb-4">
                    <img src="{{ asset('storage/' . $product->image) }}" alt="{{ $product->name }}" class="w-32 h-32 object-cover rounded-xl border border-slate-200 dark:border-slate-600">
                </div>
                @endif
                <div class="grid grid-cols-2 md:grid-cols-3 gap-4 text-sm">
                    <div><p class="text-xs text-slate-500">{{ $L::t('products.sku') }}</p><p class="font-mono font-medium">{{ $product->sku }}</p></div>
                    <div><p class="text-xs text-slate-500">{{ $L::t('products.barcode') }}</p><p class="font-medium">{{ $product->barcode ?? '-' }}</p></div>
                    <div><p class="text-xs text-slate-500">{{ $L::t('products.category') }}</p><p class="font-medium">{{ $product->category ?? '-' }}</p></div>
                    <div><p class="text-xs text-slate-500">{{ $L::t('products.cost_price') }}</p><p class="font-medium">{{ number_format($product->cost_price, 2) }} {{ $cur }}</p></div>
                    <div><p class="text-xs text-slate-500">{{ $L::t('products.selling_price') }}</p><p class="font-bold text-primary-600">{{ number_format($product->selling_price, 2) }} {{ $cur }}</p></div>
                    <div><p class="text-xs text-slate-500">{{ $isFr ? 'Marge' : 'Margin' }}</p><p class="font-medium text-emerald-600">{{ $product->cost_price > 0 ? round((($product->selling_price - $product->cost_price) / $product->cost_price) * 100, 1) : 0 }}%</p></div>
                </div>

                {{-- Sales stats --}}
                @if(isset($salesCount))
                <div class="mt-4 pt-4 border-t border-slate-200 dark:border-slate-700 grid grid-cols-2 gap-4">
                    <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-3 text-center">
                        <p class="text-xs text-blue-600">{{ $isFr ? 'Quantité vendue' : 'Units Sold' }}</p>
                        <p class="text-xl font-bold text-blue-700">{{ $salesCount ?? 0 }}</p>
                    </div>
                    <div class="bg-emerald-50 dark:bg-emerald-900/20 rounded-lg p-3 text-center">
                        <p class="text-xs text-emerald-600">{{ $isFr ? 'Revenu total' : 'Total Revenue' }}</p>
                        <p class="text-xl font-bold text-emerald-700">{{ number_format($salesRevenue ?? 0, 2) }} {{ $cur }}</p>
                    </div>
                </div>
                @endif

                @if($product->description)
                <div class="mt-4 pt-4 border-t border-slate-200 dark:border-slate-700">
                    <p class="text-xs text-slate-500 mb-1">{{ $L::t('products.description') }}</p>
                    <p class="text-sm">{{ $product->description }}</p>
                </div>
                @endif
            </div>

            {{-- Stock Movement History --}}
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-sm font-semibold"><i class="fas fa-history mr-1 text-primary-500"></i>{{ $isFr ? 'Historique des mouvements de stock' : 'Stock Movement History' }}</h3>
                </div>
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-700/30 text-xs text-slate-500">
                        <tr>
                            <th class="px-6 py-2 text-left">{{ $isFr ? 'Date' : 'Date' }}</th>
                            <th class="px-6 py-2 text-center">{{ $isFr ? 'Type' : 'Type' }}</th>
                            <th class="px-6 py-2 text-right">{{ $isFr ? 'Quantité' : 'Quantity' }}</th>
                            <th class="px-6 py-2 text-left">{{ $isFr ? 'Référence' : 'Reference' }}</th>
                            <th class="px-6 py-2 text-left">{{ $isFr ? 'Utilisateur' : 'User' }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                        @forelse($product->stockMovements()->with('user:id,name')->latest()->limit(20)->get() as $movement)
                        <tr>
                            <td class="px-6 py-2 text-xs">{{ $movement->created_at->format('d/m/Y H:i') }}</td>
                            <td class="px-6 py-2 text-center">
                                @php
                                    $typeLabels = ['in' => ($isFr ? 'ENTRÉE' : 'IN'), 'out' => ($isFr ? 'SORTIE' : 'OUT'), 'adjustment' => ($isFr ? 'AJUST.' : 'ADJ.')];
                                @endphp
                                <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full {{ $movement->type === 'in' ? 'bg-emerald-100 text-emerald-700' : ($movement->type === 'out' ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700') }}">{{ $typeLabels[$movement->type] ?? strtoupper($movement->type) }}</span>
                            </td>
                            <td class="px-6 py-2 text-right font-medium {{ $movement->type === 'out' ? 'text-red-600' : 'text-emerald-600' }}">
                                {{ $movement->type === 'out' ? '-' : '+' }}{{ $movement->quantity }}
                            </td>
                            <td class="px-6 py-2 text-xs text-slate-400">{{ $movement->reference ?? '-' }}</td>
                            <td class="px-6 py-2 text-xs">{{ $movement->user->name ?? '-' }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="px-6 py-4 text-center text-slate-400">{{ $isFr ? 'Aucun mouvement de stock' : 'No stock movements' }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Sidebar --}}
        <div class="space-y-6">
            {{-- Stock Level --}}
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6 text-center">
                <h3 class="text-sm font-semibold mb-3">{{ $isFr ? 'Niveau de stock' : 'Stock Level' }}</h3>
                @php $stockColor = $product->stock_quantity <= 0 ? 'red' : ($product->isLowStock() ? 'amber' : 'emerald'); @endphp
                <div class="w-20 h-20 rounded-full bg-{{ $stockColor }}-100 dark:bg-{{ $stockColor }}-900/30 flex items-center justify-center mx-auto mb-2">
                    <span class="text-2xl font-bold text-{{ $stockColor }}-600">{{ $product->stock_quantity }}</span>
                </div>
                <p class="text-xs text-slate-400">{{ $isFr ? 'Seuil minimum' : 'Threshold' }}: {{ $product->low_stock_threshold }}</p>
                @if($product->stock_quantity <= 0)
                <p class="text-xs font-bold text-red-500 mt-1">{{ $isFr ? 'RUPTURE DE STOCK' : 'OUT OF STOCK' }}</p>
                @elseif($product->isLowStock())
                <p class="text-xs font-bold text-amber-500 mt-1">{{ $isFr ? 'STOCK FAIBLE' : 'LOW STOCK' }}</p>
                @endif
            </div>

            {{-- Stock Adjustment --}}
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6" x-data="stockAdjust()">
                <h3 class="text-sm font-semibold mb-3">{{ $isFr ? 'Ajuster le stock' : 'Adjust Stock' }}</h3>
                <div class="space-y-3">
                    <select x-model="type" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
                        <option value="in">{{ $isFr ? 'Entrée de stock (IN)' : 'Add Stock (IN)' }}</option>
                        <option value="out">{{ $isFr ? 'Sortie de stock (OUT)' : 'Remove Stock (OUT)' }}</option>
                    </select>
                    <input type="number" x-model.number="quantity" min="1" required placeholder="{{ $isFr ? 'Quantité' : 'Quantity' }}" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
                    <input type="text" x-model="reference" placeholder="{{ $isFr ? 'Référence / Raison' : 'Reference / Reason' }}" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
                    <div x-show="message" x-text="message" class="text-xs p-2 rounded-lg" :class="success ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'" x-cloak></div>
                    <button @click="adjust()" :disabled="adjusting || !quantity" class="w-full py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700 disabled:opacity-50">
                        <span x-text="adjusting ? '{{ $isFr ? 'En cours...' : 'Processing...' }}' : '{{ $isFr ? 'Ajuster le stock' : 'Adjust Stock' }}'"></span>
                    </button>
                </div>
            </div>

            {{-- Quick Generate Invoice --}}
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6">
                <h3 class="text-sm font-semibold mb-3">{{ $isFr ? 'Valeur du stock' : 'Stock Value' }}</h3>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-slate-500">{{ $isFr ? 'Coût total' : 'Total Cost' }}</span>
                        <span class="font-bold">{{ number_format($product->cost_price * $product->stock_quantity, 2) }} {{ $cur }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">{{ $isFr ? 'Valeur de vente' : 'Selling Value' }}</span>
                        <span class="font-bold text-primary-600">{{ number_format($product->selling_price * $product->stock_quantity, 2) }} {{ $cur }}</span>
                    </div>
                    <div class="flex justify-between border-t border-slate-200 dark:border-slate-700 pt-2">
                        <span class="text-slate-500">{{ $isFr ? 'Bénéfice potentiel' : 'Potential Profit' }}</span>
                        <span class="font-bold text-emerald-600">{{ number_format(($product->selling_price - $product->cost_price) * $product->stock_quantity, 2) }} {{ $cur }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function stockAdjust() {
    return {
        type: 'in',
        quantity: null,
        reference: '',
        adjusting: false,
        message: '',
        success: false,
        async adjust() {
            if (!this.quantity || this.quantity < 1) return;
            this.adjusting = true;
            this.message = '';
            try {
                const res = await fetch('{{ route("products.adjust-stock", $product->id) }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify({ type: this.type, quantity: this.quantity, notes: this.reference })
                });
                const data = await res.json();
                this.success = data.success;
                this.message = data.message;
                if (data.success) {
                    this.quantity = null;
                    this.reference = '';
                    setTimeout(() => location.reload(), 1000);
                }
            } catch (e) { this.message = 'Error'; this.success = false; }
            this.adjusting = false;
        }
    };
}
</script>
@endpush
