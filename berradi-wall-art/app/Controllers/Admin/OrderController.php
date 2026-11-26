<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Paginator;
use App\Core\Response;
use App\Core\ValidationException;
use App\Helpers\Money;
use App\Services\Auth;
use App\Services\Operations\ArtworkService;
use App\Services\Payments\PaymentService;
use App\Services\Sales\AdminOrderService;
use App\Services\Sales\OrderService;
use App\Services\Sales\OrderStatus;
use App\Services\Sales\OrderWorkflow;
use App\Services\Sales\ShipmentService;
use App\Services\Sales\ShippingService;
use App\Services\Sales\WorkflowException;

/**
 * Orders back office: list, detail, status workflow, delivery details, shipments, manual payments, phone orders.
 */
final class OrderController extends Controller
{
    private function userId(): int
    {
        return (int) (Auth::user()['id'] ?? 0);
    }

    public function index(): Response
    {
        $db = Database::instance();
        $f = [
            'q' => trim($this->request->str('q')),
            'status' => $this->request->str('status'),
            'payment' => $this->request->str('payment'),
            'source' => $this->request->str('source'),
            'from' => $this->request->str('from'),
            'to' => $this->request->str('to'),
        ];
        $where = [];
        $params = [];
        if ($f['q'] !== '') {
            $where[] = '(o.order_number LIKE ? OR o.customer_name LIKE ? OR o.customer_phone LIKE ? OR o.customer_email LIKE ?)';
            $like = '%' . addcslashes($f['q'], '%_\\') . '%';
            array_push($params, $like, $like, $like, $like);
        }
        if ($f['status'] === 'open') {
            $where[] = "o.status NOT IN ('delivered','cancelled','returned')";
        } elseif (in_array($f['status'], OrderStatus::ALL, true)) {
            $where[] = 'o.status = ?';
            $params[] = $f['status'];
        }
        if (in_array($f['payment'], ['unpaid', 'partial', 'paid', 'refunded', 'partially_refunded'], true)) {
            $where[] = 'o.payment_status = ?';
            $params[] = $f['payment'];
        }
        if (in_array($f['source'], ['storefront', 'admin', 'quotation', 'phone', 'whatsapp'], true)) {
            $where[] = 'o.source = ?';
            $params[] = $f['source'];
        }
        foreach (['from' => '>=', 'to' => '<'] as $k => $op) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $f[$k])) {
                $where[] = "o.placed_at {$op} ?";
                $params[] = $k === 'to' ? date('Y-m-d', strtotime($f[$k] . ' +1 day')) : $f[$k];
            }
        }
        $sql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $per = 25;
        $page = max(1, $this->request->int('page', 1));
        $total = (int) $db->value('SELECT COUNT(*) FROM orders o' . $sql, $params);
        $rows = $db->fetchAll(
            "SELECT o.id, o.order_number, o.status, o.payment_status, o.source, o.customer_name, o.customer_phone, o.shipping_city, o.grand_total,
                    o.balance_due, o.placed_at, (SELECT SUM(quantity) FROM order_items WHERE order_id = o.id) AS items,
                    (SELECT COUNT(*) FROM order_customizations oc JOIN order_items oi ON oi.id = oc.order_item_id WHERE oi.order_id = o.id AND oc.approval_status <> 'approved') AS artwork_pending
             FROM orders o{$sql} ORDER BY o.placed_at DESC, o.id DESC LIMIT {$per} OFFSET " . (($page - 1) * $per),
            $params
        );
        $counts = [];
        foreach ($db->fetchAll('SELECT status, COUNT(*) AS n FROM orders GROUP BY status') as $r) {
            $counts[$r['status']] = (int) $r['n'];
        }
        return $this->view('admin/orders/index', ['orders' => new Paginator($rows, $total, $per, $page), 'f' => $f, 'counts' => $counts]);
    }

    /** @return array<string,mixed> */
    private function find(string $id): array
    {
        $o = Database::instance()->fetch('SELECT * FROM orders WHERE id = ?', [(int) $id]);
        if ($o === null) {
            throw new HttpException(404);
        }
        return $o;
    }

    public function show(string $id): Response
    {
        $o = $this->find($id);
        $db = Database::instance();
        $items = OrderService::items((int) $o['id']);
        $art = [];
        foreach ($items as $it) {
            if ($it['custom_design_id'] !== null) {
                $art[(int) $it['id']] = ArtworkService::forItem((int) $it['id']);
            }
        }
        $hasArtwork = $art !== [];
        $allowed = array_values(array_filter(OrderWorkflow::allowed((string) $o['status']), function (string $s) use ($hasArtwork): bool {
            if ($s === OrderStatus::AWAITING_ARTWORK_APPROVAL && !$hasArtwork) {
                return false; // nothing for the customer to validate
            }
            // cancelling or returning needs full order management rights
            return in_array($s, [OrderStatus::CANCELLED, OrderStatus::RETURNED], true) ? Auth::can('orders.manage') : true;
        }));
        return $this->view('admin/orders/show', [
            'o' => $o,
            'items' => $items,
            'art' => $art,
            'history' => $db->fetchAll('SELECT h.*, u.name AS user_name FROM order_status_history h LEFT JOIN users u ON u.id = h.changed_by WHERE h.order_id = ? ORDER BY h.id DESC', [(int) $o['id']]),
            'payments' => $db->fetchAll('SELECT p.*, m.name AS method_name FROM payments p LEFT JOIN payment_methods m ON m.id = p.payment_method_id WHERE p.order_id = ? ORDER BY p.id', [(int) $o['id']]),
            'shipments' => ShipmentService::forOrder((int) $o['id']),
            'jobs' => $db->fetchAll('SELECT j.*, oi.title FROM production_jobs j LEFT JOIN order_items oi ON oi.id = j.order_item_id WHERE j.order_id = ? ORDER BY j.id', [(int) $o['id']]),
            'allowed' => $allowed,
            'method' => $o['payment_method_id'] ? $db->fetch('SELECT * FROM payment_methods WHERE id = ?', [(int) $o['payment_method_id']]) : null,
            'methods' => $db->fetchAll('SELECT id, name FROM payment_methods WHERE is_active = 1 ORDER BY sort_order, id'),
            'customerOrders' => $o['customer_id'] ? (int) $db->value('SELECT COUNT(*) FROM orders WHERE customer_id = ?', [(int) $o['customer_id']]) : 0,
            'couriers' => ShipmentService::COURIERS,
            'invoices' => $db->fetchAll('SELECT id, invoice_number, invoice_type, status, total_ttc FROM invoices WHERE order_id = ? ORDER BY id', [(int) $o['id']]),
            'activeInvoice' => \App\Services\Finance\InvoiceService::activeForOrder((int) $o['id']),
        ]);
    }

    public function status(string $id): Response
    {
        $o = $this->find($id);
        $to = $this->request->str('status');
        if (in_array($to, [OrderStatus::CANCELLED, OrderStatus::RETURNED], true) && !Auth::can('orders.manage')) {
            throw new HttpException(403);
        }
        try {
            OrderWorkflow::change((int) $o['id'], $to, [
                'comment' => mb_substr($this->request->str('comment'), 0, 1000), 'notify' => $this->request->bool('notify'), 'user_id' => $this->userId(),
            ]);
            $this->flash('success', __('orders.status_changed', ['status' => OrderStatus::label($to)]));
        } catch (WorkflowException $e) {
            $this->flash('danger', $e->getMessage());
        }
        return $this->redirectRoute('admin.orders.show', ['id' => $o['id']]);
    }

    public function update(string $id): Response
    {
        $o = $this->find($id);
        $d = $this->validate([
            'full_name' => 'required|max:150', 'phone' => 'required|phone', 'email' => 'nullable|email',
            'city' => 'required|max:100', 'address' => 'required|max:255', 'postal_code' => 'nullable|max:20',
            'delivery_instructions' => 'nullable|max:500', 'internal_notes' => 'nullable|max:10000',
        ], ['full_name' => __('checkout.full_name'), 'phone' => __('auth.phone'), 'city' => __('checkout.city'), 'address' => __('checkout.address')]);
        try {
            AdminOrderService::updateDetails((int) $o['id'], $d, $this->userId());
            $this->flash('success', __('common.saved'));
        } catch (WorkflowException $e) {
            $this->flash('danger', $e->getMessage());
        }
        return $this->redirect(route('admin.orders.show', ['id' => $o['id']]) . '#details');
    }

    public function shipment(string $id): Response
    {
        $o = $this->find($id);
        $this->request->normalizeDecimals(['shipping_cost', 'cod_amount']);
        $d = $this->validate([
            'courier' => 'required|max:100', 'tracking_number' => 'nullable|max:100', 'tracking_url' => 'nullable|url|max:255',
            'shipping_cost' => 'nullable|decimal:2|min:0', 'cod_amount' => 'nullable|decimal:2|min:0', 'packages' => 'nullable|integer|between:1,50',
            'notes' => 'nullable|max:1000',
        ], ['courier' => __('orders.courier')]);
        try {
            ShipmentService::create((int) $o['id'], $d, $this->userId(), $this->request->bool('mark_shipped'), $this->request->bool('notify'));
            $this->flash('success', __('orders.shipment_created'));
        } catch (WorkflowException $e) {
            $this->flash('danger', $e->getMessage());
        }
        return $this->redirect(route('admin.orders.show', ['id' => $o['id']]) . '#shipping');
    }

    public function shipmentStatus(string $id, string $shipmentId): Response
    {
        $o = $this->find($id);
        $sid = (int) Database::instance()->value('SELECT id FROM shipments WHERE id = ? AND order_id = ?', [(int) $shipmentId, (int) $o['id']]);
        if ($sid === 0) {
            throw new HttpException(404);
        }
        try {
            ShipmentService::updateStatus($sid, $this->request->str('status'), $this->userId(), $this->request->bool('notify'));
            $this->flash('success', __('common.saved'));
        } catch (WorkflowException $e) {
            $this->flash('danger', $e->getMessage());
        }
        return $this->redirect(route('admin.orders.show', ['id' => $o['id']]) . '#shipping');
    }

    /** Offline payment received (cash on delivery handed over by the courier, transfer, cheque…). */
    public function payment(string $id): Response
    {
        $o = $this->find($id);
        $this->request->normalizeDecimals(['amount']);
        $d = $this->validate([
            'amount' => 'required|decimal:2|min:0.01', 'payment_method_id' => 'required|integer|exists:payment_methods,id', 'reference' => 'nullable|max:120',
        ], ['amount' => __('orders.amount'), 'payment_method_id' => __('orders.payment_method')]);
        if (in_array($o['status'], [OrderStatus::CANCELLED], true)) {
            $this->flash('danger', __('orders.err_payment_cancelled'));
        } elseif (Money::compare(Money::normalize((string) $d['amount']), (string) $o['balance_due']) > 0) {
            $this->flash('danger', __('orders.err_overpay', ['max' => money($o['balance_due'])]));
        } else {
            PaymentService::recordConfirmed((int) $o['id'], (string) $d['amount'], (int) $d['payment_method_id'], null, $d['reference'] ?: null, null, $this->userId());
            $this->flash('success', __('orders.payment_recorded'));
        }
        return $this->redirect(route('admin.orders.show', ['id' => $o['id']]) . '#payments');
    }

    /** Private mock-up of a custom line (staff only). */
    public function itemPreview(string $id, string $itemId): Response
    {
        $o = $this->find($id);
        $item = Database::instance()->fetch('SELECT id, preview_path FROM order_items WHERE id = ? AND order_id = ? AND custom_design_id IS NOT NULL', [(int) $itemId, (int) $o['id']]);
        $rel = $item !== null ? ArtworkService::previewPathFor($item) : null;
        if ($rel === null) {
            throw new HttpException(404);
        }
        return ArtworkService::servePreview($rel);
    }

    /** Printable work order / packing slip. */
    public function printable(string $id): Response
    {
        $o = $this->find($id);
        return $this->view('admin/orders/print', ['o' => $o, 'items' => OrderService::items((int) $o['id']), 'shipments' => ShipmentService::forOrder((int) $o['id'])]);
    }

    // ------------------------------------------------------------------ manual orders

    public function create(): Response
    {
        $db = Database::instance();
        $products = [];
        foreach ($db->fetchAll(
            "SELECT p.id, p.title, p.sku, p.selling_price, v.id AS variant_id, v.name AS variant_name, v.price
             FROM products p LEFT JOIN product_variants v ON v.product_id = p.id AND v.is_active = 1
             WHERE p.is_active = 1 AND p.deleted_at IS NULL AND p.is_customizable = 0 ORDER BY p.title, v.sort_order"
        ) as $r) {
            $products[] = [
                'value' => $r['id'] . ':' . ($r['variant_id'] ?? ''),
                'label' => $r['title'] . ($r['variant_name'] ? ' — ' . $r['variant_name'] : '') . ' (' . money($r['variant_id'] ? $r['price'] : $r['selling_price']) . ')',
            ];
        }
        return $this->view('admin/orders/create', [
            'products' => $products,
            'customers' => $db->fetchAll("SELECT id, first_name, last_name, phone, email FROM customers WHERE deleted_at IS NULL AND first_name <> 'Client' ORDER BY updated_at DESC, id DESC LIMIT 500"),
            'methods' => $db->fetchAll('SELECT id, name FROM payment_methods WHERE is_active = 1 ORDER BY sort_order, id'),
            'cities' => ShippingService::cities(),
        ]);
    }

    public function store(): Response
    {
        $this->request->normalizeDecimals(['shipping', 'discount']);
        $d = $this->validate([
            'source' => 'required|in:phone,whatsapp,admin', 'customer_id' => 'nullable|integer',
            'full_name' => 'required|max:150', 'phone' => 'required|phone', 'email' => 'nullable|email',
            'city' => 'required|max:100', 'address' => 'required|max:255', 'postal_code' => 'nullable|max:20',
            'delivery_instructions' => 'nullable|max:500', 'payment_method_id' => 'nullable|integer|exists:payment_methods,id',
            'shipping' => 'nullable|decimal:2|min:0', 'discount' => 'nullable|decimal:2|min:0', 'locale' => 'required|locale',
            'customer_notes' => 'nullable|max:1000', 'internal_notes' => 'nullable|max:5000',
        ], ['full_name' => __('checkout.full_name'), 'phone' => __('auth.phone'), 'city' => __('checkout.city'), 'address' => __('checkout.address')]);
        $lines = [];
        foreach ((array) $this->request->input('lines', []) as $l) {
            if (!is_array($l)) {
                continue;
            }
            $ref = (string) ($l['product'] ?? '');
            $title = trim((string) ($l['title'] ?? ''));
            if ($ref === '' && $title === '') {
                continue; // empty row of the form
            }
            if ($ref !== '') {
                [$pid, $vid] = array_pad(explode(':', $ref, 2), 2, '');
                $lines[] = ['kind' => 'catalog', 'product_id' => (int) $pid, 'variant_id' => $vid !== '' ? (int) $vid : null, 'quantity' => (int) ($l['quantity'] ?? 1), 'unit_price' => (string) ($l['unit_price'] ?? '')];
            } else {
                $lines[] = ['kind' => 'custom'] + $l;
            }
        }
        $order = AdminOrderService::create($d, $lines, $this->userId());
        $this->flash('success', __('orders.created', ['number' => $order['order_number']]));
        return $this->redirectRoute('admin.orders.show', ['id' => $order['id']]);
    }
}
