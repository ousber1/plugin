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
            'invoices' => $db->fetchAll("SELECT id, invoice_number, invoice_type, total_ttc, issue_date FROM invoices WHERE order_id = ? AND status <> 'draft' ORDER BY id", [(int) $order['id']]),
            'cancellable' => OrderService::isCancellable($order),
            'reviewable' => $order['status'] === 'delivered' ? $db->fetchAll(
                'SELECT DISTINCT oi.product_id, oi.title FROM order_items oi JOIN products p ON p.id = oi.product_id AND p.deleted_at IS NULL
                 WHERE oi.order_id = ? AND NOT EXISTS (SELECT 1 FROM reviews r WHERE r.order_id = oi.order_id AND r.product_id = oi.product_id)',
                [(int) $order['id']]
            ) : [],
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

    /** Review of a product from a delivered order (verified purchase), published after moderation. */
    public function review(string $number): Response
    {
        $order = $this->find($number);
        $d = $this->validate([
            'product_id' => 'required|integer', 'rating' => 'required|integer|between:1,5', 'title' => 'nullable|max:191', 'body' => 'required|min:10|max:3000',
        ], ['body' => __('reviews.your_review'), 'rating' => __('reviews.rating')]);
        $db = Database::instance();
        $ok = $order['status'] === 'delivered'
            && (int) $db->value('SELECT COUNT(*) FROM order_items WHERE order_id = ? AND product_id = ?', [(int) $order['id'], (int) $d['product_id']]) > 0
            && (int) $db->value('SELECT COUNT(*) FROM reviews WHERE order_id = ? AND product_id = ?', [(int) $order['id'], (int) $d['product_id']]) === 0;
        if (!$ok) {
            throw new HttpException(404);
        }
        $c = Auth::customer();
        $id = $db->insert('reviews', [
            'product_id' => (int) $d['product_id'], 'customer_id' => (int) $c['id'], 'order_id' => (int) $order['id'],
            'author_name' => mb_substr(trim($c['first_name'] . ' ' . mb_substr((string) $c['last_name'], 0, 1) . '.'), 0, 120), 'author_city' => $order['shipping_city'],
            'rating' => (int) $d['rating'], 'title' => $d['title'] ?: null, 'body' => trim((string) $d['body']), 'is_verified_purchase' => 1, 'is_approved' => 0,
            'locale' => \App\Core\Translator::locale(),
        ]);
        \App\Services\ActivityLogger::log('review.submitted', 'review', $id);
        \App\Services\Notifications\StaffNotifier::notify('review_new', 'reviews.manage', __('notifications.review_new', ['rating' => (int) $d['rating'], 'number' => $number]), 'reviews', 'review', $id);
        $this->flash('success', __('reviews.thanks'));
        return $this->redirectRoute('customer.orders.show', ['number' => $number]);
    }
}
