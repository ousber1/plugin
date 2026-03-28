@extends('layouts.app')
@section('title', 'Edit Order')

@section('content')
<div x-data="editOrderForm()" class="max-w-4xl space-y-6">
    <div class="flex items-center gap-3">
        <a href="{{ route('orders.show', $order) }}" class="text-slate-400 hover:text-slate-600"><i class="fas fa-arrow-left"></i></a>
        <h2 class="text-xl font-bold">Edit Order {{ $order->invoice_number }}</h2>
    </div>

    <form method="POST" action="{{ route('orders.update', $order) }}" @submit.prevent="submitForm($event)">
        @csrf @method('PUT')

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6 space-y-4">
                    <h3 class="font-semibold text-sm">Order Details</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-1">Customer</label>
                            <select name="customer_id" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
                                <option value="">Walk-in Customer</option>
                                @foreach($customers as $customer)
                                <option value="{{ $customer->id }}" {{ $order->customer_id == $customer->id ? 'selected' : '' }}>{{ $customer->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Status</label>
                            <select name="status" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
                                @foreach(['pending','confirmed','shipped','delivered','cancelled'] as $s)
                                <option value="{{ $s }}" {{ $order->status === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                {{-- Products --}}
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6 space-y-4">
                    <h3 class="font-semibold text-sm">Products</h3>
                    <div class="relative">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                        <input type="text" x-model="productSearch" @input.debounce.300ms="searchProducts()" placeholder="Add products..."
                               class="w-full pl-10 pr-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm bg-transparent">
                        <div x-show="searchResults.length > 0" class="absolute z-10 w-full mt-1 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                            <template x-for="p in searchResults" :key="p.id">
                                <button type="button" @click="addItem(p); searchResults = []; productSearch = ''" class="w-full px-4 py-2 text-left text-sm hover:bg-slate-50 dark:hover:bg-slate-700 flex justify-between">
                                    <span x-text="p.name"></span>
                                    <span class="text-slate-400" x-text="'$' + parseFloat(p.selling_price).toFixed(2)"></span>
                                </button>
                            </template>
                        </div>
                    </div>

                    <table class="w-full text-sm" x-show="items.length > 0">
                        <thead class="text-xs text-slate-500">
                            <tr><th class="text-left py-2">Product</th><th class="text-center py-2 w-24">Qty</th><th class="text-right py-2 w-28">Price</th><th class="text-right py-2 w-28">Total</th><th class="w-10"></th></tr>
                        </thead>
                        <tbody>
                            <template x-for="(item, i) in items" :key="i">
                                <tr class="border-t border-slate-100 dark:border-slate-700">
                                    <td class="py-2" x-text="item.name"></td>
                                    <td class="py-2 text-center">
                                        <input type="number" x-model.number="item.qty" min="1" class="w-20 text-center border border-slate-300 dark:border-slate-600 rounded px-2 py-1 text-sm bg-transparent">
                                        <input type="hidden" :name="'items['+i+'][product_id]'" :value="item.id">
                                        <input type="hidden" :name="'items['+i+'][quantity]'" :value="item.qty">
                                        <input type="hidden" :name="'items['+i+'][unit_price]'" :value="item.price">
                                    </td>
                                    <td class="py-2 text-right" x-text="'$' + item.price.toFixed(2)"></td>
                                    <td class="py-2 text-right font-semibold" x-text="'$' + (item.qty * item.price).toFixed(2)"></td>
                                    <td class="py-2"><button type="button" @click="items.splice(i, 1)" class="text-red-400 hover:text-red-600"><i class="fas fa-times"></i></button></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6">
                    <label class="block text-sm font-medium mb-1">Notes</label>
                    <textarea name="notes" rows="3" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">{{ $order->notes }}</textarea>
                </div>
            </div>

            <div>
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6 space-y-4 sticky top-24">
                    <h3 class="font-semibold text-sm">Order Summary</h3>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between"><span class="text-slate-500">Subtotal</span><span x-text="'$' + subtotal.toFixed(2)"></span></div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-500">Discount</span>
                            <input type="number" name="discount" x-model.number="discount" min="0" step="0.01" class="w-24 text-right border border-slate-300 dark:border-slate-600 rounded px-2 py-1 text-sm bg-transparent">
                        </div>
                        <div class="flex justify-between font-bold text-lg border-t border-slate-200 dark:border-slate-700 pt-3">
                            <span>Total</span>
                            <span class="text-primary-600" x-text="'$' + total.toFixed(2)"></span>
                        </div>
                    </div>
                    <p class="text-xs text-slate-400" x-text="items.length + ' items'"></p>
                    <button type="submit" class="w-full py-2.5 bg-primary-600 text-white rounded-lg text-sm font-bold hover:bg-primary-700">
                        <i class="fas fa-save mr-1"></i> Save Changes
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

@endsection

@push('scripts')
<script>
function editOrderForm() {
    return {
        items: @json($orderItems),
        discount: {{ $order->discount ?? 0 }},
        productSearch: '',
        searchResults: [],

        get subtotal() { return this.items.reduce((s, i) => s + i.qty * i.price, 0); },
        get total() { return Math.max(0, this.subtotal - this.discount); },

        addItem(product) {
            const existing = this.items.find(i => i.id === product.id);
            if (existing) { existing.qty++; } else {
                this.items.push({ id: product.id, name: product.name, price: parseFloat(product.selling_price), qty: 1 });
            }
        },

        async searchProducts() {
            if (this.productSearch.length < 2) { this.searchResults = []; return; }
            const res = await fetch(`{{ route('pos.products') }}?search=${this.productSearch}`);
            const json = await res.json();
            this.searchResults = json.data || [];
        },

        submitForm(e) { e.target.submit(); }
    };
}
</script>
@endpush
