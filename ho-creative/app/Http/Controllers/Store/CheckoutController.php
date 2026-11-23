<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\ShippingZone;
use App\Models\User;
use App\Notifications\SimpleNotification;
use App\Services\CartService;
use App\Services\OrderService;
use App\Support\Money;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class CheckoutController extends Controller
{
    public function __construct(protected CartService $cart, protected Settings $settings) {}

    public static function paymentMethods(Settings $s): array
    {
        return array_values(array_filter([
            $s->bool('payments.bank_transfer') ? 'bank_transfer' : null,
            $s->bool('payments.in_store') ? 'in_store' : null,
            $s->bool('payments.cod') ? 'cod' : null,
            $s->bool('payments.online') ? 'online' : null,
        ]));
    }

    public function show(Request $request)
    {
        $cart = $this->cart->current();
        if (! $cart || ! $cart->items()->exists()) {
            return redirect()->route('cart');
        }
        if (! auth()->check() && ! $this->settings->bool('checkout.guest_enabled')) {
            return redirect()->route('login')->with('warn', __('Connectez-vous pour finaliser votre commande.'));
        }
        $zones = ShippingZone::where('is_active', true)->orderBy('sort')->get();
        $zone = $zones->firstWhere('id', (int) $request->query('zone')) ?? $zones->firstWhere('is_default', true);
        $method = $request->query('method', 'delivery') === 'pickup' ? 'pickup' : 'delivery';
        $priced = $this->cart->priced($cart, $zone, $method);
        $addresses = auth()->user()?->customer?->addresses ?? collect();
        $methods = self::paymentMethods($this->settings);
        $key = Str::uuid()->toString();

        return view('store.checkout', compact('priced', 'zones', 'zone', 'method', 'addresses', 'methods', 'key'));
    }

    public function place(Request $request, OrderService $orders)
    {
        if ($request->input('checkout_key') && ($done = Order::where('checkout_key', (string) $request->input('checkout_key'))->first())) {
            return redirect()->route('checkout.thanks', $done->number); // double submit / browser retry
        }
        $cart = $this->cart->current();
        abort_unless($cart && $cart->items()->exists(), 404);
        $user = $request->user();
        if (! $user && ! $this->settings->bool('checkout.guest_enabled')) {
            return redirect()->route('login');
        }
        $methods = self::paymentMethods($this->settings);
        $data = $request->validate([
            'checkout_key' => 'required|uuid',
            'name' => 'required|string|max:120', 'email' => 'required|email|max:190', 'phone' => 'required|string|max:40',
            'company' => 'nullable|string|max:190', 'ice' => 'nullable|string|max:30',
            'shipping_method' => 'required|in:pickup,delivery',
            'zone_id' => 'required_if:shipping_method,delivery|nullable|exists:shipping_zones,id',
            'line1' => 'required_if:shipping_method,delivery|nullable|string|max:190', 'line2' => 'nullable|string|max:190',
            'city' => 'required_if:shipping_method,delivery|nullable|string|max:80', 'postal_code' => 'nullable|string|max:20',
            'payment_method' => 'required|in:'.implode(',', $methods),
            'notes' => 'nullable|string|max:2000', 'terms' => 'accepted',
        ]);
        if ($existing = Order::where('checkout_key', $data['checkout_key'])->first()) {
            return redirect()->route('checkout.thanks', $existing->number); // double submit
        }

        $zone = $data['shipping_method'] === 'delivery' ? ShippingZone::where('is_active', true)->findOrFail($data['zone_id']) : null;
        if ($zone && $zone->cities && ! $zone->matchesCity($data['city']) && ! $zone->is_default) {
            return back()->withInput()->with('err', __('La ville saisie ne correspond pas à la zone de livraison choisie.'));
        }
        $priced = $this->cart->priced($cart, $zone, $data['shipping_method']);
        if ($priced['has_invalid']) {
            return redirect()->route('cart')->with('err', __('Certains articles ne sont plus disponibles dans cette configuration. Veuillez les modifier.'));
        }

        $order = DB::transaction(function () use ($user, $data, $priced, $zone, $orders, $cart, $request) {
            $customer = $user?->customer;
            if (! $customer) {
                $customer = Customer::create([
                    'type' => ! empty($data['company']) ? 'company' : 'individual', 'name' => $data['name'], 'company_name' => $data['company'] ?? null,
                    'email' => $data['email'], 'phone' => $data['phone'], 'ice' => $data['ice'] ?? null, 'city' => $data['city'] ?? null, 'source' => 'web',
                ]);
                if ($user) {
                    $user->update(['customer_id' => $customer->id]);
                }
            }
            $address = $data['shipping_method'] === 'delivery' ? [
                'name' => $data['name'], 'company' => $data['company'] ?? null, 'line1' => $data['line1'], 'line2' => $data['line2'] ?? null,
                'city' => $data['city'], 'postal_code' => $data['postal_code'] ?? null, 'phone' => $data['phone'],
            ] : null;
            if ($address && $user && ! $customer->addresses()->where('line1', $data['line1'])->where('city', $data['city'])->exists()) {
                $customer->addresses()->create($address + ['type' => 'shipping', 'is_default' => ! $customer->addresses()->exists()]);
            }
            $lines = [];
            foreach ($priced['lines'] as $l) {
                $lines[] = OrderService::lineFromPrice($l['product'], $l['price'], [
                    'artwork_mode' => $l['item']->artwork_mode, 'cart_item_id' => $l['item']->id,
                    'design_id' => $l['item']->design_id, 'template_id' => $l['item']->template_id, 'notes' => $l['item']->notes,
                ]);
            }
            $deposit = '0.00';

            $order = $orders->create($customer, $lines, [
                'channel' => 'web', 'user_id' => $user?->id, 'shipping_method' => $data['shipping_method'],
                'shipping_zone_id' => $zone?->id, 'shipping_address' => $address, 'billing_address' => $address,
                'contact_name' => $data['name'], 'contact_email' => $data['email'], 'contact_phone' => $data['phone'],
                'payment_method' => $data['payment_method'], 'notes' => $data['notes'] ?? null,
                'coupon' => $priced['coupon'], 'checkout_key' => $data['checkout_key'], 'locale' => app()->getLocale(),
                'deposit_required' => $deposit,
            ]);
            $cart->items()->delete();
            $cart->update(['coupon_code' => null]);

            return $order;
        });

        if (! $user) {
            session()->push('guest_orders', $order->id);
        }
        try {
            Notification::route('mail', $order->contact_email)->notify(new SimpleNotification('order_placed', [
                'order' => $order->number, 'total' => Money::format($order->total, $order->locale),
                'url' => $user ? route('account.orders.show', $order) : route('guest.order', $order->guest_token),
            ], $order->locale));
        } catch (\Throwable $e) {
            report($e);
        }
        if ($order->payment_method === 'online') {
            return redirect()->route('payment.start', $order);
        }

        return redirect()->route('checkout.thanks', $order->number);
    }

    public function thanks(Order $order)
    {
        $allowed = (auth()->check() && $order->user_id === auth()->id()) || in_array($order->id, session('guest_orders', []));
        abort_unless($allowed, 404);

        return view('store.thanks', compact('order'));
    }
}
