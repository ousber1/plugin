<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Paginator;
use App\Core\Response;
use App\Services\Auth;
use App\Services\Payments\PaymentService;

/** Every money movement with customers (payments and refunds) — read-only ledger, refunds from orders. */
final class PaymentController extends Controller
{
    public function index(): Response
    {
        $db = Database::instance();
        $f = ['type' => $this->request->str('type'), 'method' => $this->request->int('method'), 'from' => $this->request->str('from'), 'to' => $this->request->str('to'), 'q' => trim($this->request->str('q'))];
        $where = ["p.status = 'confirmed'"];
        $params = [];
        if (in_array($f['type'], ['payment', 'refund'], true)) {
            $where[] = 'p.payment_type = ?';
            $params[] = $f['type'];
        }
        if ($f['method'] > 0) {
            $where[] = 'p.payment_method_id = ?';
            $params[] = $f['method'];
        }
        foreach (['from' => '>=', 'to' => '<'] as $k => $op) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $f[$k])) {
                $where[] = "p.paid_at {$op} ?";
                $params[] = $k === 'to' ? date('Y-m-d', strtotime($f[$k] . ' +1 day')) : $f[$k];
            }
        }
        if ($f['q'] !== '') {
            $where[] = '(p.payment_number LIKE ? OR p.reference LIKE ? OR o.order_number LIKE ? OR i.invoice_number LIKE ? OR o.customer_name LIKE ? OR i.customer_name LIKE ?)';
            $like = '%' . addcslashes($f['q'], '%_\\') . '%';
            array_push($params, $like, $like, $like, $like, $like, $like);
        }
        $sql = ' FROM payments p LEFT JOIN orders o ON o.id = p.order_id LEFT JOIN invoices i ON i.id = p.invoice_id LEFT JOIN payment_methods m ON m.id = p.payment_method_id LEFT JOIN users u ON u.id = p.confirmed_by WHERE ' . implode(' AND ', $where);
        $per = 40;
        $page = max(1, $this->request->int('page', 1));
        $total = (int) $db->value('SELECT COUNT(*)' . $sql, $params);
        $rows = $db->fetchAll(
            'SELECT p.*, o.order_number, o.id AS oid, i.invoice_number, i.id AS iid, COALESCE(o.customer_name, i.customer_name) AS customer, m.name AS method_name, u.name AS user_name'
            . $sql . ' ORDER BY p.paid_at DESC, p.id DESC LIMIT ' . $per . ' OFFSET ' . (($page - 1) * $per),
            $params
        );
        $sum = $db->fetch("SELECT COALESCE(SUM(CASE WHEN p.payment_type = 'payment' THEN p.amount END), 0) AS inflow, COALESCE(SUM(CASE WHEN p.payment_type = 'refund' THEN p.amount END), 0) AS outflow" . $sql, $params);
        return $this->view('admin/payments/index', [
            'rows' => new Paginator($rows, $total, $per, $page), 'f' => $f, 'sum' => $sum,
            'methods' => $db->fetchAll('SELECT id, name FROM payment_methods ORDER BY sort_order, id'),
        ]);
    }

    /** Refund on an order (cash back, transfer back…). */
    public function refund(string $orderId): Response
    {
        $o = Database::instance()->fetch('SELECT id FROM orders WHERE id = ?', [(int) $orderId]);
        if ($o === null) {
            throw new HttpException(404);
        }
        $this->request->normalizeDecimals(['amount']);
        $d = $this->validate(['amount' => 'required|decimal:2|min:0.01', 'payment_method_id' => 'required|integer|exists:payment_methods,id', 'reason' => 'required|min:3|max:1000'],
            ['amount' => __('orders.amount'), 'reason' => __('finance.reason')]);
        try {
            PaymentService::refund((int) $o['id'], (string) $d['amount'], (int) $d['payment_method_id'], (string) $d['reason'], (int) (Auth::user()['id'] ?? 0));
            $this->flash('success', __('finance.refund_recorded'));
        } catch (\InvalidArgumentException $e) {
            $this->flash('danger', $e->getMessage());
        }
        return $this->redirect(route('admin.orders.show', ['id' => $o['id']]) . '#payments');
    }
}
