<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csv;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Session;
use App\Services\CashService;
use App\Services\CustomerService;
use App\Services\PaymentService;

/**
 * Encaissements clients : règlement de factures et tickets à crédit
 * (imputation manuelle ou automatique par ancienneté), avances, reçus, annulation.
 */
final class PaymentController extends Controller
{
    private function filters(): array
    {
        return ['q' => Request::string('q'), 'from' => Request::string('from'), 'to' => Request::string('to'),
            'method' => Request::int('method'), 'customer' => Request::int('customer'), 'advances' => Request::get('advances') === '1'];
    }

    private function where(array $f): array
    {
        $where = ['1 = 1'];
        $params = [];
        if ($f['q'] !== '') {
            $where[] = '(p.number LIKE :a OR p.reference LIKE :b OR c.full_name LIKE :c OR c.company_name LIKE :d)';
            $params += ['a' => "%{$f['q']}%", 'b' => "%{$f['q']}%", 'c' => "%{$f['q']}%", 'd' => "%{$f['q']}%"];
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['from'])) {
            $where[] = 'p.payment_date >= :from';
            $params['from'] = $f['from'] . ' 00:00:00';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['to'])) {
            $where[] = 'p.payment_date <= :to';
            $params['to'] = $f['to'] . ' 23:59:59';
        }
        if ($f['method'] > 0) {
            $where[] = 'p.payment_method_id = :m';
            $params['m'] = $f['method'];
        }
        if ($f['customer'] > 0) {
            $where[] = 'p.customer_id = :cid';
            $params['cid'] = $f['customer'];
        }
        if ($f['advances']) {
            $where[] = "p.unallocated_amount > 0.004 AND p.status = 'valid'";
        }
        return [implode(' AND ', $where), $params];
    }

    public function index(): void
    {
        $f = $this->filters();
        [$w, $params] = $this->where($f);
        $from = 'FROM payments p JOIN customers c ON c.id = p.customer_id JOIN payment_methods pm ON pm.id = p.payment_method_id LEFT JOIN users u ON u.id = p.user_id';
        $p = Paginator::fromRequest((int) $this->db->fetchColumn("SELECT COUNT(*) {$from} WHERE {$w}", $params));
        $this->view('payments/index', [
            'title' => 'Encaissements', 'f' => $f, 'p' => $p,
            'totals' => $this->db->fetch(
                "SELECT COALESCE(SUM(CASE WHEN p.status = 'valid' AND p.direction = 'in' THEN p.amount END), 0) AS received,
                        COALESCE(SUM(CASE WHEN p.status = 'valid' AND p.direction = 'refund' THEN p.amount END), 0) AS refunded,
                        COALESCE(SUM(CASE WHEN p.status = 'valid' THEN p.unallocated_amount END), 0) AS advances
                   {$from} WHERE {$w}",
                $params
            ),
            'rows' => $this->db->fetchAll(
                "SELECT p.*, pm.name AS method, COALESCE(NULLIF(c.company_name, ''), c.full_name) AS customer_name, c.is_walk_in, u.full_name AS user_name,
                        (SELECT GROUP_CONCAT(i.number SEPARATOR ', ') FROM payment_allocations pa JOIN invoices i ON i.id = pa.invoice_id WHERE pa.payment_id = p.id) AS invoices,
                        (SELECT s.number FROM sales s WHERE s.id = p.sale_id) AS sale_number
                   {$from} WHERE {$w} ORDER BY p.payment_date DESC, p.id DESC LIMIT {$p->limit()} OFFSET {$p->offset()}",
                $params
            ),
            'methods' => $this->db->fetchAll('SELECT id, name FROM payment_methods WHERE is_credit = 0 ORDER BY sort_order'),
            'customer' => $f['customer'] ? $this->db->fetch('SELECT * FROM customers WHERE id = ?', [$f['customer']]) : null,
        ]);
    }

    public function export(): void
    {
        [$w, $params] = $this->where($this->filters());
        $rows = $this->db->fetchAll(
            "SELECT p.number, p.payment_date, p.direction, p.status, COALESCE(NULLIF(c.company_name, ''), c.full_name) AS customer_name,
                    pm.name AS method, p.reference, p.amount, p.unallocated_amount
               FROM payments p JOIN customers c ON c.id = p.customer_id JOIN payment_methods pm ON pm.id = p.payment_method_id
              WHERE {$w} ORDER BY p.payment_date LIMIT 50000",
            $params
        );
        foreach ($rows as &$r) {
            $r['direction'] = $r['direction'] === 'in' ? 'Encaissement' : 'Remboursement';
            $r['status'] = $r['status'] === 'valid' ? 'Valide' : 'Annulé';
            $r['amount'] = (float) $r['amount'];
            $r['unallocated_amount'] = (float) $r['unallocated_amount'];
        }
        unset($r);
        Csv::download('encaissements-' . date('Y-m-d') . '.csv', ['number' => 'N° reçu', 'payment_date' => 'Date', 'direction' => 'Sens', 'status' => 'Statut',
            'customer_name' => 'Client', 'method' => 'Mode', 'reference' => 'Référence', 'amount' => 'Montant', 'unallocated_amount' => 'Avance non imputée'], $rows);
    }

    public function create(): void
    {
        $customer = null;
        $open = null;
        if ($cid = Request::int('customer')) {
            $customer = $this->db->fetch('SELECT * FROM customers WHERE id = ? AND deleted_at IS NULL', [$cid]) ?: null;
            if ($customer) {
                $open = PaymentService::openItems($cid);
            }
        }
        $this->view('payments/create', [
            'title' => 'Encaisser un règlement', 'customer' => $customer, 'open' => $open,
            'customerName' => $customer ? CustomerService::displayName($customer) : '',
            'balance' => $customer ? CustomerService::balance((int) $customer['id']) : 0.0,
            'focusInvoice' => Request::int('invoice'), 'focusSale' => Request::int('sale'),
            'methods' => $this->db->fetchAll('SELECT * FROM payment_methods WHERE is_active = 1 AND is_credit = 0 ORDER BY sort_order'),
            'cashOpen' => (bool) CashService::openSession(),
        ]);
    }

    public function store(): void
    {
        $cid = Request::int('customer_id');
        $amount = (float) str_replace([' ', ','], ['', '.'], Request::string('amount'));
        $alloc = static function (string $key): array {
            $out = [];
            foreach ((array) ($_POST[$key] ?? []) as $id => $v) {
                $v = (float) str_replace([' ', ','], ['', '.'], (string) $v);
                if ($v > 0) {
                    $out[(int) $id] = $v;
                }
            }
            return $out;
        };
        try {
            $r = PaymentService::register($cid, $amount, Request::int('method_id'), Request::string('payment_date') ?: date('Y-m-d'),
                Request::string('reference') ?: null, Request::string('notes') ?: null, $alloc('inv'), $alloc('sale'));
        } catch (\DomainException $e) {
            $this->fail($e->getMessage(), 'payments/create', ['customer' => $cid]);
        }
        Session::flash('success', 'Règlement de ' . money($amount) . ' enregistré (reçu ' . implode(', ', $r['numbers']) . ')'
            . ($r['advance'] > 0.004 ? '. Avance non imputée : ' . money($r['advance']) . '.' : '.'));
        $this->redirect('payments/' . $r['ids'][0]);
    }

    public function show(int $id): void
    {
        $p = $this->db->fetch(
            'SELECT p.*, pm.name AS method, u.full_name AS user_name, cu.full_name AS cancelled_name, s.number AS sale_number, cn.number AS credit_number
               FROM payments p JOIN payment_methods pm ON pm.id = p.payment_method_id LEFT JOIN users u ON u.id = p.user_id
               LEFT JOIN users cu ON cu.id = p.cancelled_by LEFT JOIN sales s ON s.id = p.sale_id LEFT JOIN credit_notes cn ON cn.id = p.credit_note_id
              WHERE p.id = ?',
            [$id]
        );
        if (!$p) {
            $this->fail('Paiement introuvable.', 'payments');
        }
        $this->view('payments/show', [
            'title' => 'Reçu ' . $p['number'], 'pay' => $p,
            'customerName' => CustomerService::displayName($this->db->fetch('SELECT * FROM customers WHERE id = ?', [$p['customer_id']])),
            'allocations' => $this->db->fetchAll('SELECT pa.*, i.number, i.balance_due, i.status FROM payment_allocations pa JOIN invoices i ON i.id = pa.invoice_id WHERE pa.payment_id = ?', [$id]),
            'group' => $p['payment_group'] ? $this->db->fetchAll('SELECT id, number, amount FROM payments WHERE payment_group = ? AND id <> ?', [$p['payment_group'], $id]) : [],
            'openInvoices' => (float) $p['unallocated_amount'] > 0.004 ? $this->db->fetchAll(
                "SELECT id, number, balance_due FROM invoices WHERE customer_id = ? AND status IN ('validated','unpaid','partial','overdue') AND balance_due > 0.004 ORDER BY COALESCE(due_date, issue_date)",
                [$p['customer_id']]
            ) : [],
            'send' => DocumentController::sendDefaults('payment', $p),
        ]);
    }

    public function allocate(int $id): void
    {
        try {
            PaymentService::allocateAdvance($id, Request::int('invoice_id'), (float) str_replace(',', '.', Request::string('amount')));
        } catch (\DomainException $e) {
            Session::flash('danger', $e->getMessage());
            $this->back('payments/' . $id);
        }
        Session::flash('success', 'Avance imputée sur la facture.');
        $this->back('payments/' . $id);
    }

    public function cancel(int $id): void
    {
        try {
            PaymentService::cancel($id, Request::string('reason'));
        } catch (\DomainException $e) {
            $this->fail($e->getMessage(), 'payments/' . $id);
        }
        Session::flash('success', 'Paiement annulé ; les factures concernées ont été recalculées.');
        $this->redirect('payments/' . $id);
    }
}
