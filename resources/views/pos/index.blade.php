@extends('layouts.app')
@section('title', \App\Helpers\Lang::t('pos.title'))

@php $L = \App\Helpers\Lang::class; $isFr = $L::locale() === 'fr'; @endphp

@section('content')
<div x-data="posTerminal()" class="flex gap-6 -mt-2" style="height: calc(100vh - 120px);">
    {{-- Products Panel --}}
    <div class="flex-1 flex flex-col min-w-0">
        <div class="flex gap-3 mb-3">
            <div class="flex-1 relative">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                <input type="text" x-model="search" @input.debounce.300ms="fetchProducts()"
                       placeholder="{{ $L::t('pos.search') }}"
                       class="w-full pl-10 pr-4 py-2.5 border border-slate-300 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-800 focus:ring-2 focus:ring-primary-500 outline-none">
            </div>
            <a href="{{ route('pos.sessions') }}" class="px-4 py-2.5 bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg text-sm font-medium hover:bg-slate-300 dark:hover:bg-slate-600 flex items-center gap-2 shrink-0">
                <i class="fas fa-clock"></i> <span class="hidden sm:inline">{{ $L::t('pos.sessions') }}</span>
            </a>
        </div>

        {{-- Category Filter --}}
        <div class="flex gap-2 mb-3 overflow-x-auto pb-1 shrink-0">
            <button @click="selectedCategory = ''; fetchProducts()"
                    :class="selectedCategory === '' ? 'bg-primary-600 text-white' : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700'"
                    class="px-3 py-1.5 rounded-lg text-xs font-medium whitespace-nowrap transition">{{ $L::t('common.all') }}</button>
            @foreach($categories as $cat)
            <button @click="selectedCategory = '{{ $cat }}'; fetchProducts()"
                    :class="selectedCategory === '{{ $cat }}' ? 'bg-primary-600 text-white' : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700'"
                    class="px-3 py-1.5 rounded-lg text-xs font-medium whitespace-nowrap transition">{{ $cat }}</button>
            @endforeach
        </div>

        <div class="flex-1 overflow-y-auto grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-3 content-start">
            <template x-for="product in products" :key="product.id">
                <button @click="addToCart(product)"
                        class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-3 text-left hover:shadow-md hover:border-primary-300 dark:hover:border-primary-600 transition-all group">
                    <div class="w-full bg-slate-100 dark:bg-slate-700 rounded-lg mb-2.5 flex items-center justify-center overflow-hidden" style="aspect-ratio: 1/1;">
                        <template x-if="product.image">
                            <img :src="'/storage/' + product.image" :alt="product.name" class="w-full h-full object-cover">
                        </template>
                        <template x-if="!product.image">
                            <i class="fas fa-box text-3xl text-slate-300 dark:text-slate-500 group-hover:text-primary-400 transition"></i>
                        </template>
                    </div>
                    <p class="text-sm font-medium truncate" x-text="product.name"></p>
                    <div class="flex items-center justify-between mt-1">
                        <span class="text-sm font-bold text-primary-600 dark:text-primary-400" x-text="parseFloat(product.selling_price).toFixed(2) + ' {{ $isFr ? 'DH' : 'DH' }}'"></span>
                        <span class="text-xs text-slate-400" x-text="product.stock_quantity + ' {{ $isFr ? 'en stock' : 'in stock' }}'"></span>
                    </div>
                </button>
            </template>
            <div x-show="products.length === 0" class="col-span-full flex items-center justify-center py-12">
                <p class="text-slate-400 text-sm">{{ $isFr ? 'Aucun produit trouvé' : 'No products found' }}</p>
            </div>
        </div>
    </div>

    {{-- Cart Panel --}}
    <div class="w-96 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl flex flex-col shadow-sm shrink-0">
        <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700">
            <h3 class="font-semibold flex items-center gap-2">
                <i class="fas fa-shopping-cart text-primary-500"></i> {{ $L::t('pos.cart') }}
                <span class="ml-auto text-xs bg-primary-100 dark:bg-primary-900/30 text-primary-600 dark:text-primary-400 px-2 py-0.5 rounded-full" x-text="cart.length + ' {{ $L::t('pos.items') }}'" x-show="cart.length > 0"></span>
            </h3>
        </div>

        <div class="flex-1 overflow-y-auto p-4 space-y-2">
            <template x-for="(item, index) in cart" :key="item.id">
                <div class="flex items-center gap-3 bg-slate-50 dark:bg-slate-700/30 rounded-lg p-3">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium truncate" x-text="item.name"></p>
                        <p class="text-xs text-slate-400" x-text="parseFloat(item.selling_price).toFixed(2) + ' DH'"></p>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <button @click="updateQty(index, -1)" class="w-7 h-7 rounded bg-slate-200 dark:bg-slate-600 hover:bg-slate-300 flex items-center justify-center text-xs">-</button>
                        <span class="w-8 text-center text-sm font-semibold" x-text="item.qty"></span>
                        <button @click="updateQty(index, 1)" class="w-7 h-7 rounded bg-slate-200 dark:bg-slate-600 hover:bg-slate-300 flex items-center justify-center text-xs">+</button>
                    </div>
                    <span class="text-sm font-bold w-20 text-right" x-text="(item.qty * item.selling_price).toFixed(2) + ' DH'"></span>
                    <button @click="removeFromCart(index)" class="text-red-400 hover:text-red-600 text-sm"><i class="fas fa-trash"></i></button>
                </div>
            </template>
            <div x-show="cart.length === 0" class="flex flex-col items-center justify-center py-12 text-slate-400">
                <i class="fas fa-shopping-basket text-3xl mb-2"></i>
                <p class="text-sm">{{ $isFr ? 'Panier vide' : 'Cart is empty' }}</p>
            </div>
        </div>

        {{-- Cart Footer --}}
        <div class="border-t border-slate-200 dark:border-slate-700 p-4 space-y-2.5">
            {{-- Customer --}}
            <div>
                <select x-model="customerId" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
                    <option value="">{{ $L::t('pos.walk_in') }}</option>
                    @foreach(\App\Models\Customer::orderBy('name')->get(['id','name','phone']) as $c)
                    <option value="{{ $c->id }}">{{ $c->name }} ({{ $c->phone }})</option>
                    @endforeach
                </select>
            </div>

            <div class="flex justify-between text-sm">
                <span class="text-slate-500">{{ $L::t('pos.subtotal') }}</span>
                <span class="font-medium" x-text="subtotal.toFixed(2) + ' DH'"></span>
            </div>
            <div class="flex items-center justify-between text-sm">
                <span class="text-slate-500">{{ $L::t('pos.discount') }}</span>
                <input type="number" x-model.number="discount" min="0" step="0.01" class="w-24 text-right border border-slate-300 dark:border-slate-600 rounded px-2 py-1 text-sm bg-transparent">
            </div>
            <div class="flex justify-between text-lg font-bold border-t border-slate-200 dark:border-slate-700 pt-2">
                <span>{{ $L::t('pos.total') }}</span>
                <span class="text-primary-600 dark:text-primary-400" x-text="total.toFixed(2) + ' DH'"></span>
            </div>

            <div class="space-y-2">
                <div class="flex gap-1.5">
                    <button @click="paymentMethod = 'cash'" :class="paymentMethod === 'cash' ? 'bg-primary-600 text-white' : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300'" class="flex-1 py-2 rounded-lg text-xs font-medium transition">
                        <i class="fas fa-money-bill mr-0.5"></i> {{ $L::t('pos.cash') }}
                    </button>
                    <button @click="paymentMethod = 'card'" :class="paymentMethod === 'card' ? 'bg-primary-600 text-white' : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300'" class="flex-1 py-2 rounded-lg text-xs font-medium transition">
                        <i class="fas fa-credit-card mr-0.5"></i> {{ $L::t('pos.card') }}
                    </button>
                    <button @click="paymentMethod = 'bank_transfer'" :class="paymentMethod === 'bank_transfer' ? 'bg-primary-600 text-white' : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300'" class="flex-1 py-2 rounded-lg text-xs font-medium transition">
                        <i class="fas fa-university mr-0.5"></i> {{ $L::t('pos.bank') }}
                    </button>
                </div>

                <div x-show="paymentMethod === 'cash'" class="flex items-center gap-2">
                    <input type="number" x-model.number="cashReceived" placeholder="{{ $isFr ? 'Montant reçu' : 'Cash received' }}" class="flex-1 border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
                    <span class="text-sm font-semibold whitespace-nowrap" :class="change >= 0 ? 'text-emerald-600' : 'text-red-500'" x-text="'{{ $L::t('pos.change') }}: ' + change.toFixed(2) + ' DH'"></span>
                </div>
            </div>

            <div class="flex gap-2 pt-1">
                <button @click="clearCart()" class="px-3 py-2.5 bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300 rounded-lg text-sm font-medium hover:bg-slate-300 flex-shrink-0" title="{{ $L::t('pos.clear') }}">
                    <i class="fas fa-times"></i>
                </button>
                <button @click="holdOrder()" :disabled="cart.length === 0" class="px-3 py-2.5 bg-amber-500 hover:bg-amber-600 text-white rounded-lg text-sm font-medium transition disabled:opacity-50 flex-shrink-0" title="{{ $L::t('pos.hold') }}">
                    <i class="fas fa-pause"></i>
                </button>
                <button @click="completeSale()" :disabled="cart.length === 0 || processing"
                        class="flex-1 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm font-bold transition disabled:opacity-50 disabled:cursor-not-allowed">
                    <i class="fas fa-check mr-1"></i> {{ $L::t('pos.complete_sale') }}
                </button>
            </div>
        </div>
    </div>

    {{-- Held Orders Button --}}
    <button x-show="heldOrders.length > 0" @click="showHeld = true" x-cloak
            class="fixed bottom-6 right-6 bg-amber-500 hover:bg-amber-600 text-white rounded-full w-14 h-14 flex items-center justify-center shadow-lg z-40 transition">
        <i class="fas fa-pause"></i>
        <span class="absolute -top-1 -right-1 bg-red-500 text-white text-[10px] font-bold w-5 h-5 rounded-full flex items-center justify-center" x-text="heldOrders.length"></span>
    </button>

    {{-- Held Orders Modal --}}
    <div x-show="showHeld" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showHeld = false">
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl p-6 max-w-md w-full" x-transition>
            <h3 class="text-lg font-bold mb-4 flex items-center gap-2">
                <i class="fas fa-pause-circle text-amber-500"></i> {{ $L::t('pos.held_orders') }}
            </h3>
            <div class="space-y-3 max-h-80 overflow-y-auto">
                <template x-for="(held, i) in heldOrders" :key="held.id">
                    <div class="bg-slate-50 dark:bg-slate-700/30 rounded-lg p-3 flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium" x-text="held.cart.length + ' {{ $L::t('pos.items') }} - ' + held.cart.reduce((s, item) => s + item.qty * item.selling_price, 0).toFixed(2) + ' DH'"></p>
                            <p class="text-xs text-slate-400" x-text="'{{ $isFr ? 'Mis en attente à' : 'Held at' }} ' + held.time"></p>
                        </div>
                        <div class="flex gap-2">
                            <button @click="restoreOrder(i)" class="px-3 py-1.5 bg-primary-600 text-white rounded-lg text-xs font-medium hover:bg-primary-700">{{ $L::t('pos.restore') }}</button>
                            <button @click="removeHeld(i)" class="px-2 py-1.5 text-red-400 hover:text-red-600"><i class="fas fa-trash text-xs"></i></button>
                        </div>
                    </div>
                </template>
                <div x-show="heldOrders.length === 0" class="text-center py-4 text-slate-400 text-sm">{{ $isFr ? 'Aucune commande en attente' : 'No held orders' }}</div>
            </div>
            <button @click="showHeld = false" class="w-full mt-4 py-2 bg-slate-200 dark:bg-slate-700 rounded-lg text-sm font-medium">{{ $L::t('common.close') }}</button>
        </div>
    </div>

    {{-- Success Modal --}}
    <div x-show="showSuccess" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showSuccess = false">
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl p-8 max-w-sm w-full text-center" x-transition>
            <div class="w-16 h-16 bg-emerald-100 dark:bg-emerald-900/30 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-check text-2xl text-emerald-600"></i>
            </div>
            <h3 class="text-lg font-bold mb-2">{{ $L::t('pos.sale_complete') }}</h3>
            <p class="text-sm text-slate-500 mb-1">{{ $isFr ? 'Facture' : 'Invoice' }}: <span x-text="lastInvoice" class="font-mono"></span></p>
            <p class="text-2xl font-bold text-primary-600 mb-4" x-text="lastTotal.toFixed(2) + ' DH'"></p>
            <div class="flex gap-3">
                <a :href="receiptUrl" target="_blank" class="flex-1 py-2 bg-slate-200 dark:bg-slate-700 rounded-lg text-sm font-medium hover:bg-slate-300">
                    <i class="fas fa-print mr-1"></i> {{ $L::t('pos.receipt') }}
                </a>
                <button @click="showSuccess = false" class="flex-1 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700">
                    {{ $L::t('pos.new_sale') }}
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
        heldOrders: JSON.parse(localStorage.getItem('heldOrders') || '[]'),
        search: '',
        selectedCategory: '',
        customerId: '',
        discount: 0,
        paymentMethod: 'cash',
        cashReceived: 0,
        processing: false,
        showSuccess: false,
        showHeld: false,
        lastInvoice: '',
        lastTotal: 0,
        receiptUrl: '',

        init() { this.fetchProducts(); },

        get subtotal() { return this.cart.reduce((sum, item) => sum + (item.qty * item.selling_price), 0); },
        get total() { return Math.max(0, this.subtotal - this.discount); },
        get change() { return this.cashReceived - this.total; },

        async fetchProducts() {
            let url = `{{ route('pos.products') }}?search=${this.search}`;
            if (this.selectedCategory) url += `&category=${this.selectedCategory}`;
            const res = await fetch(url);
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
        clearCart() { this.cart = []; this.discount = 0; this.cashReceived = 0; this.customerId = ''; },

        holdOrder() {
            if (this.cart.length === 0) return;
            this.heldOrders.push({
                id: Date.now(),
                cart: [...this.cart],
                discount: this.discount,
                customerId: this.customerId,
                time: new Date().toLocaleTimeString(),
            });
            localStorage.setItem('heldOrders', JSON.stringify(this.heldOrders));
            this.clearCart();
        },

        restoreOrder(index) {
            const order = this.heldOrders[index];
            this.cart = order.cart;
            this.discount = order.discount;
            this.customerId = order.customerId || '';
            this.heldOrders.splice(index, 1);
            localStorage.setItem('heldOrders', JSON.stringify(this.heldOrders));
            this.showHeld = false;
        },

        removeHeld(index) {
            this.heldOrders.splice(index, 1);
            localStorage.setItem('heldOrders', JSON.stringify(this.heldOrders));
        },

        async completeSale() {
            if (this.cart.length === 0 || this.processing) return;
            this.processing = true;
            try {
                const res = await fetch('{{ route("pos.sale") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify({
                        items: this.cart.map(i => ({ product_id: i.id, quantity: i.qty, unit_price: i.selling_price })),
                        customer_id: this.customerId || null,
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
                    alert(data.message || '{{ $isFr ? "Erreur de vente" : "Sale failed" }}');
                }
            } catch (e) { alert('{{ $isFr ? "Erreur lors du traitement" : "Error processing sale" }}'); }
            this.processing = false;
        }
    };
}
</script>
@endpush
