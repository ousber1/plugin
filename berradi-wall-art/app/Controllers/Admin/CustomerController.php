<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Response;
use App\Services\ActivityLogger;
use App\Services\Auth;
use App\Services\Builder\CustomerUploadService;

final class CustomerController extends Controller
{
    public function index(): Response
    {
        $db = Database::instance();
        $q = $this->request->str('q');
        $type = $this->request->str('type');
        $sort = in_array($this->request->str('sort'), ['spent', 'orders', 'name', 'created_at'], true) ? $this->request->str('sort') : 'created_at';
        $where = ['c.deleted_at IS NULL'];
        $params = [];
        if ($q !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $where[] = "(CONCAT(c.first_name, ' ', c.last_name) LIKE :q1 OR c.email LIKE :q2 OR c.phone LIKE :q3 OR c.company_name LIKE :q4)";
            $params += ['q1' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like];
        }
        if ($type === 'registered' || $type === 'guest') {
            $where[] = 'c.is_guest = ' . ($type === 'guest' ? 1 : 0);
        }
        $order = match ($sort) {
            'spent' => 'spent DESC',
            'orders' => 'orders DESC',
            'name' => 'c.first_name ASC, c.last_name ASC',
            default => 'c.created_at DESC',
        };
        $sqlWhere = ' WHERE ' . implode(' AND ', $where);
        $total = (int) $db->value('SELECT COUNT(*) FROM customers c' . $sqlWhere, $params);
        $per = 30;
        $page = max(1, $this->request->int('page', 1));
        $rows = $db->fetchAll(
            "SELECT c.*, COUNT(o.id) AS orders, COALESCE(SUM(CASE WHEN o.status NOT IN ('cancelled','returned') THEN o.grand_total END), 0) AS spent, MAX(o.placed_at) AS last_order
             FROM customers c LEFT JOIN orders o ON o.customer_id = c.id" . $sqlWhere . " GROUP BY c.id ORDER BY {$order} LIMIT {$per} OFFSET " . (($page - 1) * $per),
            $params
        );
        return $this->view('admin/customers/index', ['customers' => new \App\Core\Paginator($rows, $total, $per, $page), 'q' => $q, 'type' => $type, 'sort' => $sort]);
    }

    /** @return array<string,mixed> */
    private function find(string $id): array
    {
        $c = Database::instance()->fetch('SELECT * FROM customers WHERE id = ? AND deleted_at IS NULL', [(int) $id]);
        if ($c === null) {
            throw new HttpException(404);
        }
        return $c;
    }

    public function show(string $id): Response
    {
        $c = $this->find($id);
        $db = Database::instance();
        $cid = (int) $c['id'];
        return $this->view('admin/customers/show', [
            'c' => $c,
            'stats' => $db->fetch(
                "SELECT COUNT(*) AS orders, COALESCE(SUM(CASE WHEN status NOT IN ('cancelled','returned') THEN grand_total END), 0) AS spent,
                        COALESCE(SUM(CASE WHEN status NOT IN ('cancelled','returned') THEN balance_due END), 0) AS balance, MIN(placed_at) AS first_order, MAX(placed_at) AS last_order
                 FROM orders WHERE customer_id = ?",
                [$cid]
            ),
            'orders' => $db->fetchAll('SELECT order_number, status, payment_status, grand_total, placed_at FROM orders WHERE customer_id = ? ORDER BY placed_at DESC LIMIT 50', [$cid]),
            'addresses' => $db->fetchAll('SELECT * FROM customer_addresses WHERE customer_id = ? ORDER BY is_default DESC, id DESC', [$cid]),
            'designs' => (int) $db->value('SELECT COUNT(*) FROM custom_designs WHERE customer_id = ?', [$cid]),
            'photos' => (int) $db->value("SELECT COUNT(*) FROM uploaded_images WHERE customer_id = ? AND status <> 'purged'", [$cid]),
            'newsletter' => $c['email'] ? $db->value("SELECT status FROM newsletter_subscribers WHERE email = ?", [$c['email']]) : null,
        ]);
    }

    public function create(): Response
    {
        return $this->view('admin/customers/form', ['c' => null]);
    }

    public function edit(string $id): Response
    {
        return $this->view('admin/customers/form', ['c' => $this->find($id)]);
    }

    /** @return array<string,mixed> */
    private function data(?int $id): array
    {
        $d = $this->validate([
            'first_name' => 'required|max:100',
            'last_name' => 'nullable|max:100',
            'email' => 'nullable|email|unique:customers,email' . ($id ? ',' . $id : ''),
            'phone' => 'required|phone',
            'company_name' => 'nullable|max:150',
            'ice' => 'nullable|max:30|regex:/^[0-9A-Za-z ]+$/',
            'locale' => 'required|locale',
            'notes' => 'nullable|max:5000',
        ]);
        return [
            'first_name' => $d['first_name'], 'last_name' => (string) ($d['last_name'] ?? ''),
            'email' => $d['email'] !== null && $d['email'] !== '' ? mb_strtolower((string) $d['email']) : null,
            'phone' => $d['phone'], 'company_name' => $d['company_name'], 'ice' => $d['ice'], 'locale' => $d['locale'], 'notes' => $d['notes'],
            'is_active' => $this->request->bool('is_active') ? 1 : 0, 'marketing_opt_in' => $this->request->bool('marketing_opt_in') ? 1 : 0,
        ];
    }

    public function store(): Response
    {
        $row = $this->data(null);
        // created by staff (phone/showroom customers): no password, can register later with the same e-mail
        $id = Database::instance()->insert('customers', $row + ['is_guest' => 1]);
        ActivityLogger::log('customer.created', 'customer', $id, trim($row['first_name'] . ' ' . $row['last_name']));
        $this->flash('success', __('common.created'));
        return $this->redirectRoute('admin.customers.show', ['id' => $id]);
    }

    public function update(string $id): Response
    {
        $c = $this->find($id);
        $row = $this->data((int) $c['id']);
        Database::instance()->update('customers', $row, ['id' => (int) $c['id']]);
        ActivityLogger::log('customer.updated', 'customer', (int) $c['id']);
        $this->flash('success', __('common.saved'));
        return $this->redirectRoute('admin.customers.show', ['id' => $c['id']]);
    }

    /**
     * Right to erasure: personal data is anonymized and private photos deleted;
     * orders and invoices keep their (legally required) snapshots but lose the link to the person.
     */
    public function anonymize(string $id): Response
    {
        $c = $this->find($id);
        $db = Database::instance();
        $cid = (int) $c['id'];
        $uploads = new CustomerUploadService();
        foreach ($db->fetchAll("SELECT * FROM uploaded_images WHERE customer_id = ? AND status <> 'purged'", [$cid]) as $img) {
            if ($img['status'] !== 'ordered') {
                $uploads->purge($img, 'gdpr');
            }
        }
        $db->transaction(function (Database $db) use ($c, $cid): void {
            $db->update('customers', [
                'first_name' => 'Client', 'last_name' => 'anonymisé #' . $cid, 'email' => null, 'phone' => null, 'password_hash' => null,
                'company_name' => null, 'ice' => null, 'notes' => null, 'is_active' => 0, 'marketing_opt_in' => 0, 'deleted_at' => date('Y-m-d H:i:s'),
            ], ['id' => $cid]);
            $db->delete('customer_addresses', ['customer_id' => $cid]);
            if ($c['email']) {
                $db->query("UPDATE newsletter_subscribers SET status = 'unsubscribed', unsubscribed_at = NOW() WHERE email = ?", [$c['email']]);
            }
            $db->query("UPDATE carts SET email = NULL, phone = NULL WHERE customer_id = ?", [$cid]);
        });
        ActivityLogger::log('customer.anonymized', 'customer', $cid, 'by ' . (Auth::user()['email'] ?? ''));
        $this->flash('success', __('admin.customer_anonymized'));
        return $this->redirectRoute('admin.customers.index');
    }

    /** Data-access request: everything stored about the customer, as a JSON download. */
    public function export(string $id): Response
    {
        $row = Database::instance()->fetch('SELECT id FROM customers WHERE id = ?', [(int) $id]);
        if ($row === null) {
            throw new \App\Core\HttpException(404);
        }
        $json = (string) json_encode(\App\Services\System\RetentionService::exportCustomer((int) $row['id']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return new Response($json, 200, ['Content-Type' => 'application/json; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="client-' . (int) $row['id'] . '-donnees.json"', 'Cache-Control' => 'private, no-store']);
    }
}
