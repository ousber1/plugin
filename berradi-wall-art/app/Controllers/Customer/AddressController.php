<?php

declare(strict_types=1);

namespace App\Controllers\Customer;

use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Response;
use App\Services\Auth;
use App\Services\Sales\ShippingService;

final class AddressController extends Controller
{
    private const MAX = 10;

    private function customerId(): int
    {
        return (int) Auth::customer()['id'];
    }

    public function index(): Response
    {
        $addresses = Database::instance()->fetchAll('SELECT * FROM customer_addresses WHERE customer_id = ? ORDER BY is_default DESC, id DESC', [$this->customerId()]);
        $edit = null;
        $editId = $this->request->int('edit');
        foreach ($addresses as $a) {
            if ((int) $a['id'] === $editId) {
                $edit = $a;
            }
        }
        return $this->view('customer/addresses/index', ['addresses' => $addresses, 'edit' => $edit, 'cities' => ShippingService::cities()]);
    }

    /** @return array<string,mixed> */
    private function data(): array
    {
        return $this->validate([
            'label' => 'nullable|max:60',
            'full_name' => 'required|min:3|max:150',
            'phone' => 'required|phone',
            'city' => 'required|min:2|max:100',
            'address_line1' => 'required|min:5|max:255',
            'address_line2' => 'nullable|max:255',
            'postal_code' => 'nullable|max:20|regex:/^[0-9A-Za-z -]+$/',
        ], ['full_name' => __('checkout.full_name'), 'city' => __('checkout.city'), 'address_line1' => __('checkout.address')]);
    }

    public function store(): Response
    {
        $db = Database::instance();
        if ((int) $db->value('SELECT COUNT(*) FROM customer_addresses WHERE customer_id = ?', [$this->customerId()]) >= self::MAX) {
            $this->flash('danger', __('account.address_limit', ['max' => self::MAX]));
            return $this->redirectRoute('customer.addresses');
        }
        $d = $this->data();
        $db->transaction(function (Database $db) use ($d): void {
            $first = (int) $db->value('SELECT COUNT(*) FROM customer_addresses WHERE customer_id = ?', [$this->customerId()]) === 0;
            $default = $first || $this->request->bool('is_default');
            if ($default) {
                $db->query('UPDATE customer_addresses SET is_default = 0 WHERE customer_id = ?', [$this->customerId()]);
            }
            $db->insert('customer_addresses', $d + ['customer_id' => $this->customerId(), 'country' => 'MA', 'is_default' => $default ? 1 : 0]);
        });
        $this->flash('success', __('common.created'));
        return $this->redirectRoute('customer.addresses');
    }

    /** @return array<string,mixed> */
    private function find(string $id): array
    {
        $a = Database::instance()->fetch('SELECT * FROM customer_addresses WHERE id = ? AND customer_id = ?', [(int) $id, $this->customerId()]);
        if ($a === null) {
            throw new HttpException(404);
        }
        return $a;
    }

    public function update(string $id): Response
    {
        $a = $this->find($id);
        $d = $this->data();
        Database::instance()->transaction(function (Database $db) use ($a, $d): void {
            if ($this->request->bool('is_default')) {
                $db->query('UPDATE customer_addresses SET is_default = 0 WHERE customer_id = ?', [$this->customerId()]);
                $d['is_default'] = 1;
            }
            $db->update('customer_addresses', $d, ['id' => (int) $a['id']]);
        });
        $this->flash('success', __('common.saved'));
        return $this->redirectRoute('customer.addresses');
    }

    public function destroy(string $id): Response
    {
        $a = $this->find($id);
        $db = Database::instance();
        $db->delete('customer_addresses', ['id' => (int) $a['id']]);
        if ((int) $a['is_default'] === 1) {
            $db->query('UPDATE customer_addresses SET is_default = 1 WHERE customer_id = ? ORDER BY id DESC LIMIT 1', [$this->customerId()]);
        }
        $this->flash('success', __('common.deleted'));
        return $this->redirectRoute('customer.addresses');
    }
}
