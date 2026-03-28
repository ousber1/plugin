@extends('layouts.app')
@section('title', 'Products')

@section('content')
<div class="space-y-6" x-data="{ showDelete: false, deleteId: null }">
    <div class="flex items-center justify-between">
        <h2 class="text-xl font-bold">Products</h2>
        <div class="flex gap-2">
            <button onclick="document.getElementById('importCsv').click()" class="px-4 py-2 bg-slate-200 dark:bg-slate-700 rounded-lg text-sm font-medium hover:bg-slate-300">
                <i class="fas fa-file-import mr-1"></i> Import CSV
            </button>
            <form method="POST" action="{{ route('products.import') }}" enctype="multipart/form-data" class="hidden">
                @csrf
                <input type="file" id="importCsv" name="csv_file" accept=".csv" onchange="this.form.submit()">
            </form>
            <a href="{{ route('products.create') }}" class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700">
                <i class="fas fa-plus mr-1"></i> Add Product
            </a>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" class="flex flex-wrap gap-3 bg-white dark:bg-slate-800 rounded-xl p-4 border border-slate-200 dark:border-slate-700">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search products..." class="flex-1 min-w-[200px] border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
        <select name="category" class="border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
            <option value="">All Categories</option>
            @foreach($categories as $cat)
            <option value="{{ $cat }}" {{ request('category') === $cat ? 'selected' : '' }}>{{ $cat }}</option>
            @endforeach
        </select>
        <select name="stock" class="border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
            <option value="">All Stock</option>
            <option value="low" {{ request('stock') === 'low' ? 'selected' : '' }}>Low Stock</option>
            <option value="out" {{ request('stock') === 'out' ? 'selected' : '' }}>Out of Stock</option>
        </select>
        <button type="submit" class="px-4 py-2 bg-slate-200 dark:bg-slate-700 rounded-lg text-sm font-medium hover:bg-slate-300"><i class="fas fa-filter mr-1"></i> Filter</button>
    </form>

    {{-- Table --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 dark:bg-slate-700/30 text-xs text-slate-500 dark:text-slate-400">
                <tr>
                    <th class="px-6 py-3 text-left">Product</th>
                    <th class="px-6 py-3 text-left">SKU</th>
                    <th class="px-6 py-3 text-left">Category</th>
                    <th class="px-6 py-3 text-right">Cost</th>
                    <th class="px-6 py-3 text-right">Price</th>
                    <th class="px-6 py-3 text-center">Stock</th>
                    <th class="px-6 py-3 text-center">Status</th>
                    <th class="px-6 py-3 text-center">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                @forelse($products as $product)
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/20 {{ $product->isLowStock() ? 'bg-amber-50/50 dark:bg-amber-900/10' : '' }}">
                    <td class="px-6 py-3">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-slate-100 dark:bg-slate-700 rounded-lg flex items-center justify-center shrink-0 overflow-hidden">
                                @if($product->image)
                                    <img src="{{ asset('storage/' . $product->image) }}" alt="{{ $product->name }}" class="w-full h-full object-cover">
                                @else
                                    <i class="fas fa-box text-slate-300"></i>
                                @endif
                            </div>
                            <div>
                                <p class="font-medium">{{ $product->name }}</p>
                                @if($product->barcode)<p class="text-xs text-slate-400">{{ $product->barcode }}</p>@endif
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-3 font-mono text-xs">{{ $product->sku }}</td>
                    <td class="px-6 py-3">{{ $product->category ?? '-' }}</td>
                    <td class="px-6 py-3 text-right">${{ number_format($product->cost_price, 2) }}</td>
                    <td class="px-6 py-3 text-right font-semibold">${{ number_format($product->selling_price, 2) }}</td>
                    <td class="px-6 py-3 text-center">
                        @php
                            $stockColor = $product->stock_quantity <= 0 ? 'red' : ($product->isLowStock() ? 'amber' : 'emerald');
                        @endphp
                        <span class="inline-flex px-2 py-0.5 text-xs font-bold rounded-full bg-{{ $stockColor }}-100 text-{{ $stockColor }}-700 dark:bg-{{ $stockColor }}-900/30 dark:text-{{ $stockColor }}-400">
                            {{ $product->stock_quantity }}
                        </span>
                    </td>
                    <td class="px-6 py-3 text-center">
                        <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full {{ $product->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                            {{ $product->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </td>
                    <td class="px-6 py-3 text-center">
                        <div class="flex items-center justify-center gap-2">
                            <a href="{{ route('products.show', $product) }}" class="text-primary-600 hover:text-primary-800"><i class="fas fa-eye"></i></a>
                            <a href="{{ route('products.edit', $product) }}" class="text-slate-400 hover:text-slate-600"><i class="fas fa-edit"></i></a>
                            <button @click="deleteId = {{ $product->id }}; showDelete = true" class="text-red-400 hover:text-red-600"><i class="fas fa-trash"></i></button>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" class="px-6 py-8 text-center text-slate-400">No products found</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $products->withQueryString()->links() }}

    {{-- Delete Modal --}}
    <div x-show="showDelete" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showDelete = false">
        <div class="bg-white dark:bg-slate-800 rounded-xl p-6 max-w-sm w-full text-center" x-transition>
            <i class="fas fa-exclamation-triangle text-red-500 text-3xl mb-3"></i>
            <h3 class="text-lg font-bold mb-2">Delete Product?</h3>
            <p class="text-sm text-slate-500 mb-4">This action cannot be undone.</p>
            <div class="flex gap-3 justify-center">
                <button @click="showDelete = false" class="px-4 py-2 bg-slate-200 dark:bg-slate-700 rounded-lg text-sm">Cancel</button>
                <form :action="`{{ url('products') }}/${deleteId}`" method="POST">
                    @csrf @method('DELETE')
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-medium">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
