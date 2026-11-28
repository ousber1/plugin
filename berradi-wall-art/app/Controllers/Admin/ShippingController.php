<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Response;
use App\Core\ValidationException;
use App\Services\ActivityLogger;

/** Delivery zones and per-city rates (used by the cart, checkout and phone orders). */
final class ShippingController extends Controller
{
    public function index(): Response
    {
        $db = Database::instance();
        $zones = $db->fetchAll('SELECT z.*, (SELECT COUNT(*) FROM shipping_rates r WHERE r.shipping_zone_id = z.id) AS n FROM shipping_zones z ORDER BY z.sort_order, z.id');
        $rates = [];
        foreach ($db->fetchAll('SELECT * FROM shipping_rates ORDER BY city') as $r) {
            $rates[(int) $r['shipping_zone_id']][] = $r;
        }
        return $this->view('admin/shipping/index', ['zones' => $zones, 'rates' => $rates, 'edit' => $this->request->int('rate'),
            'defaults' => ['flat' => (string) setting('shipping_flat_rate', '40.00'), 'free' => (string) setting('shipping_free_threshold', ''),
                'min' => (int) setting('shipping_min_days', 2), 'max' => (int) setting('shipping_max_days', 5), 'production' => (int) setting('production_days', 3)]]);
    }

    /** @return array<string,mixed> */
    private function common(): array
    {
        $this->request->normalizeDecimals(['price', 'default_price', 'free_shipping_threshold']);
        return $this->validate([
            'free_shipping_threshold' => 'nullable|decimal:2|min:0', 'min_days' => 'required|integer|between:0,30', 'max_days' => 'required|integer|between:0,60',
        ] + ($this->request->input('city') !== null
            ? ['shipping_zone_id' => 'required|integer|exists:shipping_zones,id', 'city' => 'required|max:100', 'price' => 'required|decimal:2|min:0']
            : ['name' => 'required|max:120', 'description' => 'nullable|max:255', 'default_price' => 'required|decimal:2|min:0', 'sort_order' => 'nullable|integer']),
            ['city' => __('checkout.city'), 'price' => __('admin.p_price')]);
    }

    public function saveZone(?string $id = null): Response
    {
        $d = $this->common();
        if ((int) $d['max_days'] < (int) $d['min_days']) {
            throw new ValidationException(['max_days' => __('shipping.err_days')], $this->request->all());
        }
        $row = ['name' => $d['name'], 'description' => $d['description'], 'country' => 'MA', 'default_price' => $d['default_price'],
            'free_shipping_threshold' => ($d['free_shipping_threshold'] ?? '') !== '' ? $d['free_shipping_threshold'] : null,
            'min_days' => (int) $d['min_days'], 'max_days' => (int) $d['max_days'], 'sort_order' => (int) ($d['sort_order'] ?? 0), 'is_active' => $this->request->bool('is_active') ? 1 : 0];
        $db = Database::instance();
        if ($id === null) {
            $id = (string) $db->insert('shipping_zones', $row);
        } else {
            if ($db->value('SELECT id FROM shipping_zones WHERE id = ?', [(int) $id]) === null) {
                throw new HttpException(404);
            }
            $db->update('shipping_zones', $row, ['id' => (int) $id]);
        }
        ActivityLogger::log('shipping.zone_saved', 'shipping_zone', (int) $id, $row['name']);
        $this->flash('success', __('common.saved'));
        return $this->redirect(route('admin.shipping.index') . '#zone-' . (int) $id);
    }

    public function saveRate(?string $id = null): Response
    {
        $d = $this->common();
        if ((int) $d['max_days'] < (int) $d['min_days']) {
            throw new ValidationException(['max_days' => __('shipping.err_days')], $this->request->all());
        }
        $db = Database::instance();
        $city = trim((string) $d['city']);
        $norm = \App\Services\Sales\ShippingService::normalize($city);
        foreach ($db->fetchAll('SELECT id, city FROM shipping_rates WHERE id <> ?', [(int) ($id ?? 0)]) as $r) {
            if (\App\Services\Sales\ShippingService::normalize((string) $r['city']) === $norm) {
                throw new ValidationException(['city' => __('shipping.err_city_exists', ['city' => $r['city']])], $this->request->all());
            }
        }
        $row = ['shipping_zone_id' => (int) $d['shipping_zone_id'], 'city' => $city, 'price' => $d['price'],
            'free_shipping_threshold' => ($d['free_shipping_threshold'] ?? '') !== '' ? $d['free_shipping_threshold'] : null,
            'min_days' => (int) $d['min_days'], 'max_days' => (int) $d['max_days'], 'is_active' => $this->request->bool('is_active') ? 1 : 0];
        if ($id === null) {
            $id = (string) $db->insert('shipping_rates', $row);
        } else {
            if ($db->value('SELECT id FROM shipping_rates WHERE id = ?', [(int) $id]) === null) {
                throw new HttpException(404);
            }
            $db->update('shipping_rates', $row, ['id' => (int) $id]);
        }
        ActivityLogger::log('shipping.rate_saved', 'shipping_rate', (int) $id, $city . ' ' . $row['price']);
        $this->flash('success', __('common.saved'));
        return $this->redirect(route('admin.shipping.index') . '#zone-' . $row['shipping_zone_id']);
    }

    public function deleteRate(string $id): Response
    {
        $db = Database::instance();
        $r = $db->fetch('SELECT * FROM shipping_rates WHERE id = ?', [(int) $id]);
        if ($r === null) {
            throw new HttpException(404);
        }
        // past orders keep their snapshot (orders.shipping_rate_id is set to NULL)
        $db->delete('shipping_rates', ['id' => (int) $id]);
        ActivityLogger::log('shipping.rate_deleted', 'shipping_rate', (int) $id, (string) $r['city']);
        $this->flash('success', __('common.deleted'));
        return $this->redirect(route('admin.shipping.index') . '#zone-' . (int) $r['shipping_zone_id']);
    }
}
