<?php

declare(strict_types=1);

namespace App\Controllers\Customer;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Response;
use App\Core\ValidationException;
use App\Services\ActivityLogger;
use App\Services\Auth;

/**
 * Customer area. Orders, addresses, invoices and tracking are added in Phase 4.
 */
final class AccountController extends Controller
{
    public function dashboard(): Response
    {
        $customer = Auth::customer();
        $db = Database::instance();
        $ordersCount = (int) $db->value('SELECT COUNT(*) FROM orders WHERE customer_id = ?', [(int) $customer['id']]);
        $recentOrders = $db->fetchAll(
            'SELECT order_number, status, grand_total, placed_at FROM orders WHERE customer_id = ? ORDER BY placed_at DESC LIMIT 5',
            [(int) $customer['id']]
        );
        return $this->view('customer/account/dashboard', compact('customer', 'ordersCount', 'recentOrders'));
    }

    public function profile(): Response
    {
        return $this->view('customer/account/profile', ['customer' => Auth::customer()]);
    }

    public function updateProfile(): Response
    {
        $customer = Auth::customer();
        $id = (int) $customer['id'];
        $data = $this->validate([
            'first_name' => 'required|max:100',
            'last_name' => 'required|max:100',
            'email' => 'required|email|unique:customers,email,' . $id,
            'phone' => 'required|phone',
            'company_name' => 'nullable|max:150',
            'ice' => 'nullable|max:30|regex:/^[0-9A-Za-z ]+$/',
            'locale' => 'required|locale',
        ]);
        Database::instance()->update('customers', [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => mb_strtolower((string) $data['email']),
            'phone' => $data['phone'],
            'company_name' => $data['company_name'],
            'ice' => $data['ice'],
            'locale' => $data['locale'],
            'marketing_opt_in' => $this->request->bool('marketing_opt_in') ? 1 : 0,
        ], ['id' => $id]);
        ActivityLogger::log('customer.profile_updated', 'customer', $id);
        $this->flash('success', __('common.saved'));
        return $this->redirectRoute('customer.profile');
    }

    public function updatePassword(): Response
    {
        $customer = Auth::customer();
        $data = $this->validate([
            'current_password' => 'required',
            'password' => 'required|password|confirmed|max:128|different:current_password',
        ]);
        $hash = (string) Database::instance()->value('SELECT password_hash FROM customers WHERE id = ?', [(int) $customer['id']]);
        if (!password_verify((string) $data['current_password'], $hash)) {
            throw new ValidationException(['current_password' => __('auth.current_password_wrong')]);
        }
        Database::instance()->update('customers', ['password_hash' => Auth::hashPassword((string) $data['password'])], ['id' => (int) $customer['id']]);
        ActivityLogger::log('customer.password_changed', 'customer', (int) $customer['id']);
        $this->flash('success', __('auth.password_changed'));
        return $this->redirectRoute('customer.profile');
    }
}
