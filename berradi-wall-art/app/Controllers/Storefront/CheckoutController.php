<?php

declare(strict_types=1);

namespace App\Controllers\Storefront;

use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Response;
use App\Core\Session;
use App\Core\ValidationException;
use App\Services\Auth;
use App\Services\Payments\PaymentRegistry;
use App\Services\Sales\CartService;
use App\Services\Sales\CheckoutCalculator;
use App\Services\Sales\OrderService;
use App\Services\Sales\ShippingService;
use App\Services\SeoService;

final class CheckoutController extends Controller
{
    private function guard(): ?Response
    {
        $cart = CartService::current(false);
        if ($cart === null || CartService::count() === 0) {
            $this->flash('warning', __('cart.empty'));
            return $this->redirectRoute('cart.index');
        }
        if (Auth::customer() === null && !setting('guest_checkout_enabled', true)) {
            Session::put('url.intended_customer', '/checkout');
            $this->flash('info', __('checkout.login_required'));
            return $this->redirectRoute('customer.login');
        }
        return null;
    }

    public function show(): Response
    {
        if ($r = $this->guard()) {
            return $r;
        }
        $customer = Auth::customer();
        $addresses = $customer ? Database::instance()->fetchAll('SELECT * FROM customer_addresses WHERE customer_id = ? ORDER BY is_default DESC, id DESC', [(int) $customer['id']]) : [];
        $default = $addresses[0] ?? null;
        $cart = CartService::current(false);
        $lines = CartService::lines($cart);
        $city = (string) old('city', $default['city'] ?? Session::get('cart_city', ''));
        return $this->view('storefront/checkout/index', [
            'lines' => $lines,
            'totals' => CheckoutCalculator::totals($cart, $lines, $city !== '' ? $city : null),
            'methods' => PaymentRegistry::available(),
            'cities' => ShippingService::cities(),
            'customer' => $customer,
            'addresses' => $addresses,
            'prefill' => [
                'full_name' => $default['full_name'] ?? ($customer ? trim($customer['first_name'] . ' ' . $customer['last_name']) : ''),
                'phone' => $default['phone'] ?? ($customer['phone'] ?? ''),
                'email' => $customer['email'] ?? '',
                'city' => $city,
                'address' => trim(($default['address_line1'] ?? '') . ' ' . ($default['address_line2'] ?? '')),
                'postal_code' => $default['postal_code'] ?? '',
            ],
            'seo' => SeoService::page(__('checkout.title'), null, ['noindex' => true]),
        ]);
    }

    public function place(): Response
    {
        if ($r = $this->guard()) {
            return $r;
        }
        $guest = Auth::customer() === null;
        $rules = [
            'full_name' => 'required|min:3|max:150',
            'phone' => 'required|phone',
            'email' => ($guest && $this->request->bool('create_account') ? 'required' : 'nullable') . '|email',
            'country' => 'required|in:MA',
            'city' => 'required|min:2|max:100',
            'address' => 'required|min:5|max:255',
            'postal_code' => 'nullable|max:20|regex:/^[0-9A-Za-z -]+$/',
            'delivery_instructions' => 'nullable|max:500',
            'customer_notes' => 'nullable|max:1000',
            'payment_method' => 'required|alpha_dash|max:40',
            'accept_terms' => 'required|in:1,on',
        ];
        if ($guest && $this->request->bool('create_account')) {
            $rules['password'] = 'required|password|confirmed|max:128';
        }
        // remember contact details on the cart for abandoned-cart follow-up even if validation fails
        $cart = CartService::current(false);
        if ($cart !== null) {
            $email = $this->request->str('email');
            Database::instance()->update('carts', [
                'email' => filter_var($email, FILTER_VALIDATE_EMAIL) ? mb_strtolower($email) : $cart['email'],
                'phone' => mb_substr($this->request->str('phone'), 0, 30) ?: $cart['phone'],
            ], ['id' => (int) $cart['id']]);
        }
        $data = $this->validate($rules, [
            'full_name' => __('checkout.full_name'), 'city' => __('checkout.city'), 'address' => __('checkout.address'),
            'payment_method' => __('checkout.payment'), 'accept_terms' => __('checkout.terms_short'),
        ]);
        if (!$guest) {
            $data['email'] = Auth::customer()['email']; // never taken from the form for logged-in customers
        }
        try {
            $result = OrderService::place([
                'full_name' => (string) $data['full_name'],
                'phone' => (string) $data['phone'],
                'email' => $data['email'] ?? null,
                'country' => 'MA',
                'city' => (string) $data['city'],
                'address' => (string) $data['address'],
                'postal_code' => $data['postal_code'] ?? null,
                'delivery_instructions' => $data['delivery_instructions'] ?? null,
                'customer_notes' => $data['customer_notes'] ?? null,
                'payment_method' => (string) $data['payment_method'],
                'create_account' => $guest && $this->request->bool('create_account'),
                'password' => $data['password'] ?? null,
                'save_address' => $this->request->bool('save_address') || ($guest && $this->request->bool('create_account')),
            ], $this->request->ip());
        } catch (ValidationException $e) {
            throw new ValidationException($e->errors, $this->request->all());
        }
        $order = $result['order'];
        // this browser may view the confirmation page without the token in the URL
        $recent = (array) Session::get('recent_orders', []);
        $recent[$order['order_number']] = $result['token'];
        Session::put('recent_orders', array_slice($recent, -5, null, true));
        return $this->redirectRoute('checkout.confirmation', ['number' => $order['order_number']]);
    }

    public function confirmation(string $number): Response
    {
        $token = ((array) Session::get('recent_orders', []))[$number] ?? null;
        $order = $token ? OrderService::findForTracking($number, null, (string) $token) : null;
        if ($order === null) {
            throw new HttpException(404);
        }
        $method = Database::instance()->fetch('SELECT code, name, instructions FROM payment_methods WHERE id = ?', [(int) $order['payment_method_id']]);
        return $this->view('storefront/checkout/confirmation', [
            'order' => $order,
            'items' => OrderService::items((int) $order['id']),
            'method' => $method,
            'trackUrl' => route('order.track.token', ['number' => $order['order_number'], 'token' => (string) $token]),
            'seo' => SeoService::page(__('checkout.thanks_title'), null, ['noindex' => true]),
        ]);
    }
}
