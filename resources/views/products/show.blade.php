@extends('layouts.app')
@section('title', $product->name)

@section('content')
<div class="max-w-4xl space-y-6">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="{{ route('products.index') }}" class="text-slate-400 hover:text-slate-600"><i class="fas fa-arrow-left"></i></a>
            <h2 class="text-xl font-bold">{{ $product->name }}</h2>
        </div>
        <a href="{{ route('products.edit', $product) }}" class="px-4 py-2 bg-slate-200 dark:bg-slate-700 rounded-lg text-sm font-medium hover:bg-slate-300">
            <i class="fas fa-edit mr-1"></i> Edit
        </a>
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
                    <div><p class="text-xs text-slate-500">SKU</p><p class="font-mono font-medium">{{ $product->sku }}</p></div>
                    <div><p class="text-xs text-slate-500">Barcode</p><p class="font-medium">{{ $product->barcode ?? '-' }}</p></div>
                    <div><p class="text-xs text-slate-500">Category</p><p class="font-medium">{{ $product->category ?? '-' }}</p></div>
                    <div><p class="text-xs text-slate-500">Cost Price</p><p class="font-medium">${{ number_format($product->cost_price, 2) }}</p></div>
                    <div><p class="text-xs text-slate-500">Selling Price</p><p class="font-bold text-primary-600">${{ number_format($product->selling_price, 2) }}</p></div>
                    <div><p class="text-xs text-slate-500">Margin</p><p class="font-medium text-emerald-600">{{ $product->cost_price > 0 ? round((($product->selling_price - $product->cost_price) / $product->cost_price) * 100, 1) : 0 }}%</p></div>
                </div>
                @if($product->description)
                <div class="mt-4 pt-4 border-t border-slate-200 dark:border-slate-700">
                    <p class="text-xs text-slate-500 mb-1">Description</p>
                    <p class="text-sm">{{ $product->description }}</p>
                </div>
                @endif
            </div>

            {{-- Stock Movement --}}
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700"><h3 class="text-sm font-semibold">Stock Movement History</h3></div>
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-700/30 text-xs text-slate-500"><tr><th class="px-6 py-2 text-left">Date</th><th class="px-6 py-2 text-center">Type</th><th class="px-6 py-2 text-right">Quantity</th><th class="px-6 py-2 text-left">Reference</th><th class="px-6 py-2 text-left">User</th></tr></thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                        @forelse($product->stockMovements()->latest()->limit(20)->get() as $movement)
                        <tr>
                            <td class="px-6 py-2 text-xs">{{ $movement->created_at->format('M d, H:i') }}</td>
                            <td class="px-6 py-2 text-center">
                                <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full {{ $movement->type === 'in' ? 'bg-emerald-100 text-emerald-700' : ($movement->type === 'out' ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700') }}">{{ strtoupper($movement->type) }}</span>
                            </td>
                            <td class="px-6 py-2 text-right font-medium {{ $movement->type === 'out' ? 'text-red-600' : 'text-emerald-600' }}">
                                {{ $movement->type === 'out' ? '-' : '+' }}{{ $movement->quantity }}
                            </td>
                            <td class="px-6 py-2 text-xs text-slate-400">{{ $movement->reference ?? '-' }}</td>
                            <td class="px-6 py-2 text-xs">{{ $movement->user->name ?? '-' }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="px-6 py-4 text-center text-slate-400">No stock movements</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Sidebar --}}
        <div class="space-y-6">
            {{-- Stock Level --}}
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6 text-center">
                <h3 class="text-sm font-semibold mb-3">Stock Level</h3>
                @php $stockColor = $product->stock_quantity <= 0 ? 'red' : ($product->isLowStock() ? 'amber' : 'emerald'); @endphp
                <div class="w-20 h-20 rounded-full bg-{{ $stockColor }}-100 dark:bg-{{ $stockColor }}-900/30 flex items-center justify-center mx-auto mb-2">
                    <span class="text-2xl font-bold text-{{ $stockColor }}-600">{{ $product->stock_quantity }}</span>
                </div>
                <p class="text-xs text-slate-400">Threshold: {{ $product->low_stock_threshold }}</p>
            </div>

            {{-- Stock Adjustment --}}
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6">
                <h3 class="text-sm font-semibold mb-3">Adjust Stock</h3>
                <form method="POST" action="{{ route('products.adjust-stock', $product->id) }}" class="space-y-3">
                    @csrf
                    <select name="type" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
                        <option value="in">Add Stock (IN)</option>
                        <option value="out">Remove Stock (OUT)</option>
                        <option value="adjustment">Adjustment</option>
                    </select>
                    <input type="number" name="quantity" min="1" required placeholder="Quantity" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
                    <input type="text" name="reference" placeholder="Reference/Reason" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
                    <button type="submit" class="w-full py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700">Adjust Stock</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
