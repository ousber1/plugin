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

/** Promo codes: percent / fixed / free shipping, limits, dates, product or category scope, usage. */
final class CouponController extends Controller
{
    public function index(): Response
    {
        $db = Database::instance();
        return $this->view('admin/coupons/index', [
            'rows' => $db->fetchAll(
                "SELECT c.*, p.title AS product_title, cat.name AS category_name,
                        (SELECT COALESCE(SUM(cr.discount_amount), 0) FROM coupon_redemptions cr JOIN orders o ON o.id = cr.order_id WHERE cr.coupon_id = c.id AND o.status NOT IN ('cancelled','returned')) AS given
                 FROM coupons c LEFT JOIN products p ON p.id = c.product_id LEFT JOIN categories cat ON cat.id = c.category_id ORDER BY c.is_active DESC, c.id DESC"
            ),
            'products' => $db->fetchAll('SELECT id, title FROM products WHERE deleted_at IS NULL ORDER BY title'),
            'categories' => $db->fetchAll('SELECT id, name FROM categories ORDER BY name'),
        ]);
    }

    public function save(?string $id = null): Response
    {
        $this->request->normalizeDecimals(['discount_value', 'min_order_amount', 'max_discount_amount']);
        $d = $this->validate([
            'code' => 'required|max:50|regex:/^[A-Za-z0-9_-]{2,50}$/', 'description' => 'nullable|max:255',
            'discount_type' => 'required|in:percent,fixed,free_shipping', 'discount_value' => 'nullable|decimal:2|min:0',
            'min_order_amount' => 'nullable|decimal:2|min:0', 'max_discount_amount' => 'nullable|decimal:2|min:0',
            'product_id' => 'nullable|integer|exists:products,id', 'category_id' => 'nullable|integer|exists:categories,id',
            'usage_limit' => 'nullable|integer|between:1,1000000', 'usage_limit_per_customer' => 'nullable|integer|between:1,1000',
            'starts_at' => 'nullable|date', 'ends_at' => 'nullable|date',
        ], ['code' => __('coupons.code'), 'discount_value' => __('coupons.value')]);
        $code = strtoupper((string) $d['code']);
        $value = Money::normalize((string) (($d['discount_value'] ?? '') ?: '0'));
        $errors = [];
        if ($d['discount_type'] === 'percent' && (Money::toCents($value) <= 0 || Money::compare($value, '100') > 0)) {
            $errors['discount_value'] = __('coupons.err_percent');
        } elseif ($d['discount_type'] === 'fixed' && Money::toCents($value) <= 0) {
            $errors['discount_value'] = __('coupons.err_value');
        }
        if (!empty($d['starts_at']) && !empty($d['ends_at']) && strtotime((string) $d['ends_at']) <= strtotime((string) $d['starts_at'])) {
            $errors['ends_at'] = __('admin.dates_order');
        }
        $db = Database::instance();
        if ($db->value('SELECT id FROM coupons WHERE UPPER(code) = ? AND id <> ?', [$code, (int) ($id ?? 0)]) !== null) {
            $errors['code'] = __('coupons.err_code');
        }
        if ($errors !== []) {
            throw new ValidationException($errors, $this->request->all());
        }
        $dt = fn ($v) => $v !== null && $v !== '' ? date('Y-m-d H:i:s', (int) strtotime((string) $v)) : null;
        $row = [
            'code' => $code, 'description' => $d['description'], 'discount_type' => $d['discount_type'], 'discount_value' => $d['discount_type'] === 'free_shipping' ? '0.00' : $value,
            'min_order_amount' => ($d['min_order_amount'] ?? '') !== '' ? $d['min_order_amount'] : null,
            'max_discount_amount' => $d['discount_type'] === 'percent' && ($d['max_discount_amount'] ?? '') !== '' ? $d['max_discount_amount'] : null,
            'product_id' => !empty($d['product_id']) ? (int) $d['product_id'] : null, 'category_id' => !empty($d['category_id']) ? (int) $d['category_id'] : null,
            'usage_limit' => !empty($d['usage_limit']) ? (int) $d['usage_limit'] : null, 'usage_limit_per_customer' => !empty($d['usage_limit_per_customer']) ? (int) $d['usage_limit_per_customer'] : null,
            'starts_at' => $dt($d['starts_at'] ?? null), 'ends_at' => $dt($d['ends_at'] ?? null), 'is_active' => $this->request->bool('is_active') ? 1 : 0,
        ];
        if ($id === null) {
            $id = (string) $db->insert('coupons', $row);
        } else {
            if ($db->value('SELECT id FROM coupons WHERE id = ?', [(int) $id]) === null) {
                throw new HttpException(404);
            }
            $db->update('coupons', $row, ['id' => (int) $id]);
        }
        ActivityLogger::log('coupon.saved', 'coupon', (int) $id, $code);
        $this->flash('success', __('common.saved'));
        return $this->redirect(route('admin.coupons.index') . '#coupon-' . (int) $id);
    }

    public function destroy(string $id): Response
    {
        $db = Database::instance();
        $c = $db->fetch('SELECT * FROM coupons WHERE id = ?', [(int) $id]);
        if ($c === null) {
            throw new HttpException(404);
        }
        if ((int) $db->value('SELECT COUNT(*) FROM coupon_redemptions WHERE coupon_id = ?', [(int) $id]) > 0) {
            // used codes stay for the order history; they can only be deactivated
            $db->update('coupons', ['is_active' => 0], ['id' => (int) $id]);
            $this->flash('warning', __('coupons.deactivated_instead'));
        } else {
            $db->delete('coupons', ['id' => (int) $id]);
            $this->flash('success', __('common.deleted'));
        }
        ActivityLogger::log('coupon.deleted', 'coupon', (int) $id, (string) $c['code']);
        return $this->redirectRoute('admin.coupons.index');
    }
}
