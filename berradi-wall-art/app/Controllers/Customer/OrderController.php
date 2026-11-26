<?php

declare(strict_types=1);

namespace App\Controllers\Customer;

use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Response;
use App\Services\Auth;
use App\Services\Sales\OrderService;

/**
 * Customer order history, detail, cancellation of eligible orders.
 */
final class OrderController extends Controller
{
    public function index(): Response
    {
        $customer = Auth::customer();
        $orders = Database::instance()->table('orders')
            ->select(['id', 'order_number', 'status', 'payment_status', 'grand_total', 'placed_at'])
            ->where('customer_id', (int) $customer['id'])
            ->orderBy('placed_at', 'DESC')
            ->paginate(15);
        $counts = [];
        foreach ($orders->items as $o) {
            $counts[(int) $o['id']] = (int) Database::instance()->value('SELECT COALESCE(SUM(quantity), 0) FROM order_items WHERE order_id = ?', [(int) $o['id']]);
        }
        return $this->view('customer/orders/index', compact('orders', 'counts'));
    }

    /** @return array<string,mixed> */
    private function find(string $number): array
    {
        $o = Database::instance()->fetch('SELECT * FROM orders WHERE order_number = ? AND customer_id = ?', [$number, (int) Auth::customer()['id']]);
        if ($o === null) {
            throw new HttpException(404);
        }
        return $o;
    }

    public function show(string $number): Response
    {
        $order = $this->find($number);
        $db = Database::instance();
        $items = OrderService::items((int) $order['id']);
        return $this->view('customer/orders/show', [
            'order' => $order,
            'items' => $items,
            'history' => OrderService::history((int) $order['id']),
            'method' => $db->fetch('SELECT code, name, instructions FROM payment_methods WHERE id = ?', [(int) $order['payment_method_id']]),
            'shipments' => $db->fetchAll('SELECT courier, tracking_number, tracking_url, status, shipped_at FROM shipments WHERE order_id = ? ORDER BY id DESC', [(int) $order['id']]),
            'invoices' => $db->fetchAll("SELECT id, invoice_number, total_ttc, issue_date FROM invoices WHERE order_id = ? AND status <> 'draft' AND invoice_type = 'invoice' ORDER BY id", [(int) $order['id']]),
            'cancellable' => OrderService::isCancellable($order),
        ]);
    }

    public function cancel(string $number): Response
    {
        $order = $this->find($number);
        $reason = mb_substr($this->request->str('reason'), 0, 500);
        if (OrderService::cancelByCustomer($order, $reason)) {
            $this->flash('success', __('account.order_cancelled'));
        } else {
            $this->flash('danger', __('account.order_not_cancellable'));
        }
        return $this->redirectRoute('customer.orders.show', ['number' => $number]);
    }
}
