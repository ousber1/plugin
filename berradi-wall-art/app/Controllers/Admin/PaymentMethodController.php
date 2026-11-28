<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Response;
use App\Core\ValidationException;
use App\Helpers\Money;
use App\Services\ActivityLogger;
use App\Services\Payments\PaymentRegistry;
use App\Services\TranslationService;

/**
 * Payment methods offered at checkout: texts (FR/AR), order, active state, order-amount limits, and for
 * online providers the credentials (encrypted, never shown again) and test / live mode.
 */
final class PaymentMethodController extends Controller
{
    /** Methods the system relies on (seeded): can be switched off, never deleted. */
    private const CORE = ['cod', 'bank_transfer', 'manual', 'cmi', 'paypal'];

    public function index(): Response
    {
        $db = Database::instance();
        $rows = $db->fetchAll(
            'SELECT m.*, (SELECT COUNT(*) FROM orders o WHERE o.payment_method_id = m.id) AS orders_count,
                    (SELECT COUNT(*) FROM payments p WHERE p.payment_method_id = m.id) AS payments_count
             FROM payment_methods m ORDER BY m.sort_order, m.id'
        );
        $ar = [];
        foreach ($db->fetchAll("SELECT entity_id, field, value FROM translations WHERE entity_type = 'payment_method' AND locale = 'ar'") as $t) {
            $ar[(int) $t['entity_id']][$t['field']] = $t['value'];
        }
        foreach ($rows as &$m) {
            $class = PaymentRegistry::gatewayClass($m['provider']);
            $config = (int) $m['is_online'] ? PaymentRegistry::decryptConfig($m['config']) : [];
            $m['fields'] = $class !== null ? $class::fields() : [];
            $m['config_values'] = $config;
            $m['configured'] = $class !== null && (new $class($config, (bool) (int) $m['test_mode']))->isConfigured();
            $m['unsupported'] = PaymentRegistry::UNSUPPORTED[(string) $m['provider']] ?? null;
            $m['ar'] = $ar[(int) $m['id']] ?? [];
        }
        unset($m);
        return $this->view('admin/payment_methods/index', [
            'methods' => $rows,
            'callbackUrl' => absolute_url('webhooks/payments/cmi'),
            'checkoutPreview' => PaymentRegistry::available(),
        ]);
    }

    public function save(?string $id = null): Response
    {
        $db = Database::instance();
        $row = $id !== null ? $db->fetch('SELECT * FROM payment_methods WHERE id = ?', [(int) $id]) : null;
        if ($id !== null && $row === null) {
            throw new HttpException(404);
        }
        $this->request->normalizeDecimals(['min_order_amount', 'max_order_amount']);
        $online = $row !== null && (int) $row['is_online'] === 1;
        $rules = [
            'name' => 'required|min:2|max:120',
            'description' => 'nullable|max:500',
            'instructions' => 'nullable|max:2000',
            'ar_name' => 'nullable|max:120',
            'ar_description' => 'nullable|max:500',
            'ar_instructions' => 'nullable|max:2000',
            'sort_order' => 'nullable|integer|between:0,999',
            'min_order_amount' => 'nullable|decimal:2|min:0',
            'max_order_amount' => 'nullable|decimal:2|min:0',
        ];
        $class = $online ? PaymentRegistry::gatewayClass($row['provider']) : null;
        if ($class !== null) {
            foreach ($class::fields() as $f) {
                $rules['cfg_' . $f['key']] = ['nullable', 'max:191', 'regex:' . $f['pattern']];
            }
        }
        $d = $this->validate($rules, ['name' => __('admin.name'), 'min_order_amount' => __('payment_methods.min_amount'), 'max_order_amount' => __('payment_methods.max_amount')]);
        $min = ($d['min_order_amount'] ?? '') !== '' ? Money::normalize((string) $d['min_order_amount']) : null;
        $max = ($d['max_order_amount'] ?? '') !== '' ? Money::normalize((string) $d['max_order_amount']) : null;
        if ($min !== null && $max !== null && Money::compare($min, $max) > 0) {
            throw new ValidationException(['max_order_amount' => __('payment_methods.err_min_max')], $this->request->all());
        }
        $active = $this->request->bool('is_active');
        $values = [
            'name' => (string) $d['name'],
            'description' => $d['description'] ?? null,
            'instructions' => $d['instructions'] ?? null,
            'sort_order' => (int) ($d['sort_order'] ?? 0),
            'min_order_amount' => $min,
            'max_order_amount' => $max,
        ];
        $changedSecret = false;
        if ($online) {
            $values['test_mode'] = $this->request->bool('test_mode') ? 1 : 0;
            if ($class === null) {
                if ($active) {
                    throw new ValidationException(['is_active' => __(PaymentRegistry::UNSUPPORTED[(string) $row['provider']] ?? 'payment_methods.no_integration')], $this->request->all());
                }
            } else {
                $config = PaymentRegistry::decryptConfig($row['config']);
                foreach ($class::fields() as $f) {
                    $v = trim((string) ($d['cfg_' . $f['key']] ?? ''));
                    if ($f['secret']) {
                        if ($this->request->bool('clear_' . $f['key'])) {
                            unset($config[$f['key']]);
                            $changedSecret = true;
                        } elseif ($v !== '') {
                            $config[$f['key']] = $v; // empty secret field = keep the stored value
                            $changedSecret = true;
                        }
                    } else {
                        $config[$f['key']] = $v;
                    }
                }
                $values['config'] = PaymentRegistry::encryptConfig($config);
                if ($active && !(new $class($config, (bool) $values['test_mode']))->isConfigured()) {
                    throw new ValidationException(['is_active' => __('payment_methods.err_not_configured')], $this->request->all());
                }
            }
        }
        $values['is_active'] = $active ? 1 : 0;
        if ($row !== null && (int) $row['is_active'] === 1 && !$active && (int) $row['is_online'] === 0
            && (int) $db->value('SELECT COUNT(*) FROM payment_methods WHERE is_active = 1 AND is_online = 0 AND id <> ?', [(int) $row['id']]) === 0) {
            throw new ValidationException(['is_active' => __('payment_methods.err_last_offline')], $this->request->all());
        }
        if ($row === null) {
            $values['code'] = $this->uniqueCode((string) $d['name']);
            $values['is_online'] = 0;
            $id = (string) $db->insert('payment_methods', $values);
        } else {
            $db->update('payment_methods', $values, ['id' => (int) $id]);
        }
        TranslationService::save('payment_method', (int) $id, 'ar', [
            'name' => $d['ar_name'] ?? null, 'description' => $d['ar_description'] ?? null, 'instructions' => $d['ar_instructions'] ?? null,
        ]);
        // credentials are never written to the log, only the fact that they changed
        ActivityLogger::log($row === null ? 'payment_method.created' : 'payment_method.updated', 'payment_method', (int) $id, (string) $values['name'], [
            'active' => $values['is_active'], 'test_mode' => $values['test_mode'] ?? null, 'credentials_changed' => $changedSecret,
        ]);
        $this->flash('success', __('common.saved'));
        return $this->redirect(route('admin.payment_methods.index') . '#method-' . (int) $id);
    }

    public function destroy(string $id): Response
    {
        $db = Database::instance();
        $m = $db->fetch('SELECT * FROM payment_methods WHERE id = ?', [(int) $id]);
        if ($m === null) {
            throw new HttpException(404);
        }
        $used = (int) $db->value('SELECT COUNT(*) FROM orders WHERE payment_method_id = ?', [(int) $id])
            + (int) $db->value('SELECT COUNT(*) FROM payments WHERE payment_method_id = ?', [(int) $id]);
        if (in_array($m['code'], self::CORE, true) || $used > 0) {
            // history keeps pointing to it: switch it off instead
            if ((int) $m['is_active'] === 1 && (int) $m['is_online'] === 0
                && (int) $db->value('SELECT COUNT(*) FROM payment_methods WHERE is_active = 1 AND is_online = 0 AND id <> ?', [(int) $id]) === 0) {
                $this->flash('danger', __('payment_methods.err_last_offline'));
                return $this->redirectRoute('admin.payment_methods.index');
            }
            $db->update('payment_methods', ['is_active' => 0], ['id' => (int) $id]);
            ActivityLogger::log('payment_method.deactivated', 'payment_method', (int) $id, (string) $m['name']);
            $this->flash('warning', __('payment_methods.kept_deactivated'));
            return $this->redirectRoute('admin.payment_methods.index');
        }
        $db->delete('payment_methods', ['id' => (int) $id]);
        $db->query("DELETE FROM translations WHERE entity_type = 'payment_method' AND entity_id = ?", [(int) $id]);
        ActivityLogger::log('payment_method.deleted', 'payment_method', (int) $id, (string) $m['name']);
        $this->flash('success', __('common.deleted'));
        return $this->redirectRoute('admin.payment_methods.index');
    }

    private function uniqueCode(string $name): string
    {
        $base = trim((string) preg_replace('/[^a-z0-9]+/', '_', strtolower(\App\Services\Sales\ShippingService::normalize($name))), '_');
        $base = mb_substr($base !== '' ? $base : 'method', 0, 34);
        $code = $base;
        $i = 2;
        while (Database::instance()->value('SELECT id FROM payment_methods WHERE code = ?', [$code]) !== null) {
            $code = $base . '_' . $i++;
        }
        return $code;
    }
}
