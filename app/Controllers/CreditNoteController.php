<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\AuditLog;
use App\Core\Controller;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Session;
use App\Services\CreditNoteService;
use App\Services\CustomerService;
use App\Services\InvoiceService;
use App\Services\PaymentService;

/** Avoirs : émission depuis une facture, imputation, remboursement, consultation. */
final class CreditNoteController extends Controller
{
    public function index(): void
    {
        $q = Request::string('q');
        $where = '1 = 1';
        $params = [];
        if ($q !== '') {
            $where = '(cn.number LIKE :a OR i.number LIKE :b OR c.full_name LIKE :c OR c.company_name LIKE :d)';
            $params = ['a' => "%{$q}%", 'b' => "%{$q}%", 'c' => "%{$q}%", 'd' => "%{$q}%"];
        }
        $from = 'FROM credit_notes cn JOIN customers c ON c.id = cn.customer_id LEFT JOIN invoices i ON i.id = cn.invoice_id';
        $p = Paginator::fromRequest((int) $this->db->fetchColumn("SELECT COUNT(*) {$from} WHERE {$where}", $params));
        $this->view('credit_notes/index', [
            'title' => 'Avoirs', 'q' => $q, 'p' => $p,
            'total' => (float) $this->db->fetchColumn("SELECT COALESCE(SUM(cn.total), 0) {$from} WHERE {$where} AND cn.status <> 'cancelled'", $params),
            'rows' => $this->db->fetchAll(
                "SELECT cn.*, i.number AS invoice_number, COALESCE(NULLIF(c.company_name, ''), c.full_name) AS customer_name
                   {$from} WHERE {$where} ORDER BY cn.id DESC LIMIT {$p->limit()} OFFSET {$p->offset()}",
                $params
            ),
        ]);
    }

    public function create(int $invoiceId): void
    {
        $inv = $this->findOrFail('invoices', $invoiceId, 'invoices', 'Facture');
        if (in_array($inv['status'], ['draft', 'cancelled'], true)) {
            $this->fail('Un avoir ne peut être établi que sur une facture validée.', 'invoices/' . $invoiceId);
        }
        $items = $this->db->fetchAll(
            "SELECT ii.*, u.symbol, COALESCE((SELECT SUM(cni.quantity) FROM credit_note_items cni JOIN credit_notes c ON c.id = cni.credit_note_id
                                    WHERE cni.invoice_item_id = ii.id AND c.status <> 'cancelled'), 0) AS credited
               FROM invoice_items ii LEFT JOIN units u ON u.id = ii.unit_id WHERE ii.invoice_id = ? ORDER BY ii.sort_order",
            [$invoiceId]
        );
        $this->view('credit_notes/create', [
            'title' => 'Avoir sur la facture ' . $inv['number'], 'inv' => $inv, 'items' => $items,
            'customerName' => CustomerService::displayName($this->db->fetch('SELECT * FROM customers WHERE id = ?', [$inv['customer_id']])),
            'methods' => $this->db->fetchAll('SELECT id, name FROM payment_methods WHERE is_active = 1 AND is_credit = 0 ORDER BY sort_order'),
        ]);
    }

    public function store(int $invoiceId): void
    {
        $qty = [];
        foreach ((array) ($_POST['qty'] ?? []) as $itemId => $v) {
            if ((float) str_replace(',', '.', (string) $v) > 0) {
                $qty[(int) $itemId] = (float) str_replace(',', '.', (string) $v);
            }
        }
        try {
            $id = CreditNoteService::create($invoiceId, $qty, Request::string('reason'), Request::post('restock') === '1',
                Request::string('mode'), Request::int('method_id') ?: null);
        } catch (\DomainException $e) {
            $this->fail($e->getMessage(), 'invoices/' . $invoiceId . '/credit-note');
        }
        Session::flash('success', 'Avoir émis.');
        $this->redirect('credit-notes/' . $id);
    }

    public function show(int $id): void
    {
        $cn = $this->db->fetch('SELECT cn.*, i.number AS invoice_number, u.full_name AS user_name FROM credit_notes cn LEFT JOIN invoices i ON i.id = cn.invoice_id LEFT JOIN users u ON u.id = cn.user_id WHERE cn.id = ?', [$id]);
        if (!$cn) {
            $this->fail('Avoir introuvable.', 'credit-notes');
        }
        $remaining = round((float) $cn['total'] - (float) $cn['applied_amount'] - (float) $cn['refunded_amount'], 2);
        $this->view('credit_notes/show', [
            'title' => 'Avoir ' . $cn['number'], 'cn' => $cn, 'remaining' => $remaining,
            'customerName' => CustomerService::displayName($this->db->fetch('SELECT * FROM customers WHERE id = ?', [$cn['customer_id']])),
            'allocations' => $this->db->fetchAll('SELECT a.*, i.number FROM credit_note_allocations a JOIN invoices i ON i.id = a.invoice_id WHERE a.credit_note_id = ?', [$id]),
            'refunds' => $this->db->fetchAll("SELECT p.id, p.number, p.amount, p.payment_date, pm.name AS method FROM payments p JOIN payment_methods pm ON pm.id = p.payment_method_id WHERE p.credit_note_id = ? AND p.direction = 'refund'", [$id]),
            'openInvoices' => $remaining > 0.004 ? $this->db->fetchAll(
                "SELECT id, number, balance_due FROM invoices WHERE customer_id = ? AND status IN ('validated','unpaid','partial','overdue') AND balance_due > 0.004 ORDER BY issue_date",
                [$cn['customer_id']]
            ) : [],
            'methods' => $this->db->fetchAll('SELECT id, name FROM payment_methods WHERE is_active = 1 AND is_credit = 0 ORDER BY sort_order'),
            'send' => DocumentController::sendDefaults('credit_note', $cn),
        ]);
    }

    /** Impute le solde d'un avoir sur une facture ouverte du même client. */
    public function apply(int $id): void
    {
        $invoiceId = Request::int('invoice_id');
        try {
            $this->db->transaction(function () use ($id, $invoiceId): void {
                $cn = $this->db->fetch("SELECT * FROM credit_notes WHERE id = ? AND status IN ('validated','applied') FOR UPDATE", [$id]);
                $inv = $this->db->fetch("SELECT * FROM invoices WHERE id = ? AND status IN ('validated','unpaid','partial','overdue') FOR UPDATE", [$invoiceId]);
                if (!$cn || !$inv || (int) $cn['customer_id'] !== (int) $inv['customer_id']) {
                    throw new \DomainException('Avoir ou facture invalide.');
                }
                $remaining = round((float) $cn['total'] - (float) $cn['applied_amount'] - (float) $cn['refunded_amount'], 2);
                $amount = round(min($remaining, (float) $inv['balance_due']), 2);
                if ($amount <= 0) {
                    throw new \DomainException('Rien à imputer.');
                }
                $this->db->insert('credit_note_allocations', ['credit_note_id' => $id, 'invoice_id' => $invoiceId, 'amount' => $amount, 'created_by' => auth_user()['id']]);
                $applied = round((float) $cn['applied_amount'] + $amount, 2);
                $this->db->update('credit_notes', ['applied_amount' => $applied, 'status' => $applied + (float) $cn['refunded_amount'] >= (float) $cn['total'] - 0.004 ? 'applied' : 'validated'], 'id = :id', ['id' => $id]);
                InvoiceService::recalc($invoiceId);
                AuditLog::log('apply', 'invoices', 'credit_note', $id, 'Avoir ' . $cn['number'] . ' imputé sur ' . $inv['number'] . ' : ' . money($amount));
            });
        } catch (\DomainException $e) {
            $this->fail($e->getMessage(), 'credit-notes/' . $id);
        }
        Session::flash('success', 'Avoir imputé sur la facture.');
        $this->redirect('credit-notes/' . $id);
    }

    public function refund(int $id): void
    {
        try {
            $this->db->transaction(static fn() => PaymentService::refundCreditNote($id, Request::int('method_id')));
        } catch (\DomainException $e) {
            $this->fail($e->getMessage(), 'credit-notes/' . $id);
        }
        Session::flash('success', 'Remboursement enregistré.');
        $this->redirect('credit-notes/' . $id);
    }
}
