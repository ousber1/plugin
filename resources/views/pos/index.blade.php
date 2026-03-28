@extends('layouts.app')
@section('title', 'POS Terminal')

@section('content')
<div x-data="posTerminal()" class="flex gap-6 -mt-2" style="height: calc(100vh - 120px);">
    {{-- Products Panel --}}
    <div class="flex-1 flex flex-col min-w-0">
        <div class="flex gap-3 mb-4">
            <div class="flex-1 relative">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                <input type="text" x-model="search" @input.debounce.300ms="fetchProducts()"
                       placeholder="Search products or scan barcode..."
                       class="w-full pl-10 pr-4 py-2.5 border border-slate-300 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-800 focus:ring-2 focus:ring-primary-500 outline-none">
            </div>
            <a href="{{ route('pos.sessions') }}" class="px-4 py-2.5 bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg text-sm font-medium hover:bg-slate-300 dark:hover:bg-slate-600 flex items-center gap-2">
                <i class="fas fa-clock"></i> Sessions
            </a>
        </div>

        <div class="flex-1 overflow-y-auto grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-3 content-start">
            <template x-for="product in products" :key="product.id">
                <button @click="addToCart(product)"
                        class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-4 text-left hover:shadow-md hover:border-primary-300 dark:hover:border-primary-600 transition-all group">
                    <div class="w-full h-20 bg-slate-100 dark:bg-slate-700 rounded-lg mb-3 flex items-center justify-center overflow-hidden">
                        <template x-if="product.image">
                            <img :src="'/storage/' + product.image" :alt="product.name" class="w-full h-full object-cover">
                        </template>
                        <template x-if="!product.image">
                            <i class="fas fa-box text-2xl text-slate-300 dark:text-slate-500 group-hover:text-primary-400 transition"></i>
                        </template>
                    </div>
                    <p class="text-sm font-medium truncate" x-text="product.name"></p>
                    <div class="flex items-center justify-between mt-1.5">
                        <span class="text-sm font-bold text-primary-600 dark:text-primary-400" x-text="'$' + parseFloat(product.selling_price).toFixed(2)"></span>
                        <span class="text-xs text-slate-400" x-text="product.stock_quantity + ' in stock'"></span>
                    </div>
                </button>
            </template>
            <div x-show="products.length === 0" class="col-span-full flex items-center justify-center py-12">
                <p class="text-slate-400 text-sm">No products found</p>
            </div>
        </div>
    </div>

    {{-- Cart Panel --}}
    <div class="w-96 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl flex flex-col shadow-sm shrink-0">
        <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700">
            <h3 class="font-semibold flex items-center gap-2">
                <i class="fas fa-shopping-cart text-primary-500"></i> Cart
                <span class="ml-auto text-xs bg-primary-100 dark:bg-primary-900/30 text-primary-600 dark:text-primary-400 px-2 py-0.5 rounded-full" x-text="cart.length + ' items'" x-show="cart.length > 0"></span>
            </h3>
        </div>

        <div class="flex-1 overflow-y-auto p-4 space-y-2">
            <template x-for="(item, index) in cart" :key="item.id">
                <div class="flex items-center gap-3 bg-slate-50 dark:bg-slate-700/30 rounded-lg p-3">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium truncate" x-text="item.name"></p>
                        <p class="text-xs text-slate-400" x-text="'$' + parseFloat(item.selling_price).toFixed(2) + ' each'"></p>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <button @click="updateQty(index, -1)" class="w-7 h-7 rounded bg-slate-200 dark:bg-slate-600 hover:bg-slate-300 flex items-center justify-center text-xs">-</button>
                        <span class="w-8 text-center text-sm font-semibold" x-text="item.qty"></span>
                        <button @click="updateQty(index, 1)" class="w-7 h-7 rounded bg-slate-200 dark:bg-slate-600 hover:bg-slate-300 flex items-center justify-center text-xs">+</button>
                    </div>
                    <span class="text-sm font-bold w-16 text-right" x-text="'$' + (item.qty * item.selling_price).toFixed(2)"></span>
                    <button @click="removeFromCart(index)" class="text-red-400 hover:text-red-600 text-sm"><i class="fas fa-trash"></i></button>
                </div>
            </template>
            <div x-show="cart.length === 0" class="flex flex-col items-center justify-center py-12 text-slate-400">
                <i class="fas fa-shopping-basket text-3xl mb-2"></i>
                <p class="text-sm">Cart is empty</p>
            </div>
        </div>

        {{-- Cart Footer --}}
        <div class="border-t border-slate-200 dark:border-slate-700 p-5 space-y-3">
            <div class="flex justify-between text-sm">
                <span class="text-slate-500">Subtotal</span>
                <span class="font-medium" x-text="'$' + subtotal.toFixed(2)"></span>
            </div>
            <div class="flex items-center justify-between text-sm">
                <span class="text-slate-500">Discount</span>
                <input type="number" x-model.number="discount" min="0" step="0.01" class="w-24 text-right border border-slate-300 dark:border-slate-600 rounded px-2 py-1 text-sm bg-transparent">
            </div>
            <div class="flex justify-between text-lg font-bold border-t border-slate-200 dark:border-slate-700 pt-3">
                <span>Total</span>
                <span class="text-primary-600 dark:text-primary-400" x-text="'$' + total.toFixed(2)"></span>
            </div>

            <div class="space-y-2">
                <div class="flex gap-2">
                    <button @click="paymentMethod = 'cash'" :class="paymentMethod === 'cash' ? 'bg-primary-600 text-white' : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300'" class="flex-1 py-2 rounded-lg text-sm font-medium transition">
                        <i class="fas fa-money-bill mr-1"></i> Cash
                    </button>
                    <button @click="paymentMethod = 'card'" :class="paymentMethod === 'card' ? 'bg-primary-600 text-white' : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300'" class="flex-1 py-2 rounded-lg text-sm font-medium transition">
                        <i class="fas fa-credit-card mr-1"></i> Card
                    </button>
                </div>

                <div x-show="paymentMethod === 'cash'" class="flex items-center gap-2">
                    <input type="number" x-model.number="cashReceived" placeholder="Cash received" class="flex-1 border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
                    <span class="text-sm font-semibold" :class="change >= 0 ? 'text-emerald-600' : 'text-red-500'" x-text="'Change: $' + change.toFixed(2)"></span>
                </div>
            </div>

            <div class="flex gap-2 pt-2">
                <button @click="clearCart()" class="px-4 py-2.5 bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300 rounded-lg text-sm font-medium hover:bg-slate-300 flex-shrink-0">
                    <i class="fas fa-times"></i> Clear
                </button>
                <button @click="completeSale()" :disabled="cart.length === 0 || processing"
                        class="flex-1 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm font-bold transition disabled:opacity-50 disabled:cursor-not-allowed">
                    <i class="fas fa-check mr-1"></i> Complete Sale
                </button>
            </div>
        </div>
    </div>

    {{-- Success Modal --}}
    <div x-show="showSuccess" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showSuccess = false">
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl p-8 max-w-sm w-full text-center" x-transition>
            <div class="w-16 h-16 bg-emerald-100 dark:bg-emerald-900/30 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-check text-2xl text-emerald-600"></i>
            </div>
            <h3 class="text-lg font-bold mb-2">Sale Complete!</h3>
            <p class="text-sm text-slate-500 mb-1">Invoice: <span x-text="lastInvoice" class="font-mono"></span></p>
            <p class="text-2xl font-bold text-primary-600 mb-4" x-text="'$' + lastTotal.toFixed(2)"></p>
            <div class="flex gap-3">
                <a :href="receiptUrl" target="_blank" class="flex-1 py-2 bg-slate-200 dark:bg-slate-700 rounded-lg text-sm font-medium hover:bg-slate-300">
                    <i class="fas fa-print mr-1"></i> Receipt
                </a>
                <button @click="showSuccess = false" class="flex-1 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700">
                    New Sale
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
function posTerminal() {
    return {
        products: [],
        cart: [],
        search: '',
        discount: 0,
        paymentMethod: 'cash',
        cashReceived: 0,
        processing: false,
        showSuccess: false,
        lastInvoice: '',
        lastTotal: 0,
        receiptUrl: '',

        init() { this.fetchProducts(); },

        get subtotal() { return this.cart.reduce((sum, item) => sum + (item.qty * item.selling_price), 0); },
        get total() { return Math.max(0, this.subtotal - this.discount); },
        get change() { return this.cashReceived - this.total; },

        async fetchProducts() {
            const res = await fetch(`{{ route('pos.products') }}?search=${this.search}`);
            const json = await res.json();
            this.products = json.data || [];
        },

        addToCart(product) {
            const existing = this.cart.find(i => i.id === product.id);
            if (existing) {
                if (existing.qty < product.stock_quantity) existing.qty++;
            } else {
                this.cart.push({ ...product, qty: 1, selling_price: parseFloat(product.selling_price) });
            }
        },

        updateQty(index, delta) {
            this.cart[index].qty += delta;
            if (this.cart[index].qty <= 0) this.cart.splice(index, 1);
        },

        removeFromCart(index) { this.cart.splice(index, 1); },
        clearCart() { this.cart = []; this.discount = 0; this.cashReceived = 0; },

        async completeSale() {
            if (this.cart.length === 0 || this.processing) return;
            this.processing = true;
            try {
                const res = await fetch('{{ route("pos.sale") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify({
                        items: this.cart.map(i => ({ product_id: i.id, quantity: i.qty, unit_price: i.selling_price })),
                        discount: this.discount,
                        payment_method: this.paymentMethod,
                        payment_amount: this.paymentMethod === 'cash' ? this.cashReceived : this.total,
                    })
                });
                const data = await res.json();
                if (data.success) {
                    this.lastInvoice = data.data.invoice_number;
                    this.lastTotal = parseFloat(data.data.total);
                    this.receiptUrl = '{{ url("pos/receipt") }}/' + data.data.id;
                    this.showSuccess = true;
                    this.clearCart();
                    this.fetchProducts();
                } else {
                    alert(data.message || 'Sale failed');
                }
            } catch (e) { alert('Error processing sale'); }
            this.processing = false;
        }
    };
}
</script>
@endpush
