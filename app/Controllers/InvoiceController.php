<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csv;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Session;
use App\Services\CustomerService;
use App\Services\DocumentService;
use App\Services\InvoiceService;

/**
 * Factures (standard, acompte, solde) : brouillon modifiable, validation avec
 * numérotation définitive et verrouillage, suivi des règlements, relances,
 * annulation par avoir.
 */
final class InvoiceController extends Controller
{
    private function filters(): array
    {
        return [
            'q' => Request::string('q'), 'status' => Request::string('status'), 'type' => Request::string('type'),
            'from' => Request::string('from'), 'to' => Request::string('to'), 'customer' => Request::int('customer'),
            'due' => Request::get('due') === '1',
        ];
    }

    /** @return array{0: string, 1: array} */
    private function where(array $f): array
    {
        $where = ['1 = 1'];
        $params = [];
        if ($f['q'] !== '') {
            $where[] = '(i.number LIKE :a OR c.full_name LIKE :b OR c.company_name LIKE :c OR c.phone LIKE :d OR c.ice LIKE :e)';
            $params += ['a' => "%{$f['q']}%", 'b' => "%{$f['q']}%", 'c' => "%{$f['q']}%", 'd' => "%{$f['q']}%", 'e' => "%{$f['q']}%"];
        }
        if (isset(status_map('invoice')[$f['status']])) {
            $where[] = 'i.status = :st';
            $params['st'] = $f['status'];
        }
        if (isset(InvoiceService::TYPES[$f['type']])) {
            $where[] = 'i.invoice_type = :ty';
            $params['ty'] = $f['type'];
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['from'])) {
            $where[] = 'i.issue_date >= :from';
            $params['from'] = $f['from'];
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['to'])) {
            $where[] = 'i.issue_date <= :to';
            $params['to'] = $f['to'];
        }
        if ($f['customer'] > 0) {
            $where[] = 'i.customer_id = :cid';
            $params['cid'] = $f['customer'];
        }
        if ($f['due']) {
            $where[] = "i.status IN ('validated','unpaid','partial','overdue') AND i.balance_due > 0.004";
        }
        return [implode(' AND ', $where), $params];
    }

    public function index(): void
    {
        InvoiceService::refreshOverdue();
        $f = $this->filters();
        [$w, $params] = $this->where($f);
        $from = 'FROM invoices i JOIN customers c ON c.id = i.customer_id';
        $p = Paginator::fromRequest((int) $this->db->fetchColumn("SELECT COUNT(*) {$from} WHERE {$w}", $params));
        $stats = $this->db->fetch(
            "SELECT COUNT(*) AS n, COALESCE(SUM(CASE WHEN i.status NOT IN ('draft','cancelled') THEN i.total END), 0) AS total,
                    COALESCE(SUM(CASE WHEN i.status NOT IN ('draft','cancelled') THEN i.balance_due END), 0) AS due,
                    COALESCE(SUM(CASE WHEN i.status = 'overdue' THEN i.balance_due END), 0) AS overdue
               {$from} WHERE {$w}",
            $params
        );
        $this->view('invoices/index', [
            'title' => 'Factures', 'f' => $f, 'p' => $p, 'stats' => $stats,
            'rows' => $this->db->fetchAll(
                "SELECT i.*, COALESCE(NULLIF(c.company_name, ''), c.full_name) AS customer_name, c.phone,
                        DATEDIFF(CURDATE(), i.due_date) AS late_days
                   {$from} WHERE {$w} ORDER BY (i.status = 'draft') DESC, i.issue_date DESC, i.id DESC LIMIT {$p->limit()} OFFSET {$p->offset()}",
                $params
            ),
        ]);
    }

    public function export(): void
    {
        [$w, $params] = $this->where($this->filters());
        $rows = $this->db->fetchAll(
            "SELECT i.number, i.invoice_type, i.issue_date, i.due_date, i.status, COALESCE(NULLIF(c.company_name, ''), c.full_name) AS customer_name, c.ice,
                    i.total - i.tax_total AS net, i.tax_total, i.total, i.paid_amount, i.credited_amount, i.balance_due
               FROM invoices i JOIN customers c ON c.id = i.customer_id WHERE {$w} AND i.status <> 'draft' ORDER BY i.issue_date, i.number LIMIT 50000",
            $params
        );
        foreach ($rows as &$r) {
            $r['status'] = status_map('invoice')[$r['status']][0] ?? $r['status'];
            $r['invoice_type'] = InvoiceService::TYPES[$r['invoice_type']] ?? $r['invoice_type'];
            foreach (['net', 'tax_total', 'total', 'paid_amount', 'credited_amount', 'balance_due'] as $k) {
                $r[$k] = (float) $r[$k];
            }
        }
        unset($r);
        Csv::download('factures-' . date('Y-m-d') . '.csv', [
            'number' => 'N° facture', 'invoice_type' => 'Type', 'issue_date' => 'Date', 'due_date' => 'Échéance', 'customer_name' => 'Client',
            'ice' => 'ICE client', 'status' => 'Statut', 'net' => 'Total HT', 'tax_total' => 'TVA', 'total' => 'Total TTC',
            'paid_amount' => 'Réglé', 'credited_amount' => 'Avoirs', 'balance_due' => 'Reste dû',
        ], $rows);
    }

    private function formData(): array
    {
        return [
            'taxes' => $this->db->fetchAll('SELECT id, name, rate FROM taxes WHERE is_active = 1 ORDER BY rate DESC'),
            'units' => $this->db->fetchAll('SELECT id, symbol FROM units WHERE is_active = 1 ORDER BY sort_order'),
            'scripts' => [asset('js/document-form.js')],
        ];
    }

    private function input(): array
    {
        $in = $_POST;
        $items = json_decode((string) ($in['items_json'] ?? ''), true);
        $in['items'] = is_array($items) ? $items : [];
        return $in;
    }

    public function create(): void
    {
        $customer = null;
        if ($cid = Request::int('customer')) {
            $customer = $this->db->fetch('SELECT * FROM customers WHERE id = ? AND deleted_at IS NULL AND is_walk_in = 0', [$cid]) ?: null;
        }
        $this->view('invoices/form', ['title' => 'Nouvelle facture', 'doc' => [], 'items' => [], 'customer' => $customer] + $this->formData());
    }

    public function store(): void
    {
        try {
            $id = InvoiceService::saveDraft(null, $this->input());
        } catch (\DomainException $e) {
            $this->fail($e->getMessage(), 'invoices/create');
        }
        Session::flash('success', 'Facture enregistrée en brouillon. Validez-la pour lui attribuer son numéro définitif.');
        $this->redirect('invoices/' . $id);
    }

    public function edit(int $id): void
    {
        $inv = $this->findOrFail('invoices', $id, 'invoices', 'Facture');
        if ($inv['status'] !== 'draft') {
            Session::flash('warning', 'Une facture validée n\'est plus modifiable : corrigez-la par un avoir.');
            $this->redirect('invoices/' . $id);
        }
        $this->view('invoices/form', [
            'title' => 'Modifier la facture brouillon', 'doc' => $inv,
            'items' => DocumentService::itemsForForm('invoice_items', 'invoice_id', $id),
            'customer' => $this->db->fetch('SELECT * FROM customers WHERE id = ?', [$inv['customer_id']]),
        ] + $this->formData());
    }

    public function update(int $id): void
    {
        $inv = $this->findOrFail('invoices', $id, 'invoices', 'Facture');
        if ($inv['sale_id']) {
            $this->fail('Une facture de ticket reprend la vente à l\'identique et ne se modifie pas.', 'invoices/' . $id);
        }
        try {
            InvoiceService::saveDraft($id, $this->input());
        } catch (\DomainException $e) {
            $this->fail($e->getMessage(), 'invoices/' . $id . '/edit');
        }
        Session::flash('success', 'Brouillon mis à jour.');
        $this->redirect('invoices/' . $id);
    }

    public function show(int $id): void
    {
        $inv = $this->findOrFail('invoices', $id, 'invoices', 'Facture');
        if (in_array($inv['status'], ['validated', 'unpaid', 'partial'], true) && $inv['due_date'] && $inv['due_date'] < date('Y-m-d')) {
            InvoiceService::refreshOverdue();
            $inv = $this->db->fetch('SELECT * FROM invoices WHERE id = ?', [$id]);
        }
        $customer = $this->db->fetch('SELECT * FROM customers WHERE id = ?', [$inv['customer_id']]);
        $this->view('invoices/show', [
            'title' => (InvoiceService::TYPES[$inv['invoice_type']] ?? 'Facture') . ' ' . ($inv['number'] ?? 'brouillon'),
            'inv' => $inv, 'customer' => $customer, 'customerName' => CustomerService::displayName($customer),
            'payments' => $this->db->fetchAll(
                "SELECT pa.amount AS allocated, p.id, p.number, p.payment_date, p.status, p.reference, pm.name AS method
                   FROM payment_allocations pa JOIN payments p ON p.id = pa.payment_id JOIN payment_methods pm ON pm.id = p.payment_method_id
                  WHERE pa.invoice_id = ? ORDER BY p.payment_date",
                [$id]
            ),
            'creditNotes' => $this->db->fetchAll('SELECT id, number, status, total, reason, issue_date FROM credit_notes WHERE invoice_id = ? ORDER BY id', [$id]),
            'creditApplied' => $this->db->fetchAll(
                'SELECT a.amount, c.id, c.number FROM credit_note_allocations a JOIN credit_notes c ON c.id = a.credit_note_id WHERE a.invoice_id = ? AND c.invoice_id <> a.invoice_id',
                [$id]
            ),
            'deposits' => $this->db->fetchAll('SELECT d.amount, i.id, i.number, i.status FROM invoice_deposits d JOIN invoices i ON i.id = d.deposit_invoice_id WHERE d.balance_invoice_id = ?', [$id]),
            'balanceInvoice' => $inv['invoice_type'] === 'deposit'
                ? $this->db->fetch('SELECT i.id, i.number FROM invoice_deposits d JOIN invoices i ON i.id = d.balance_invoice_id WHERE d.deposit_invoice_id = ? AND i.status <> \'cancelled\'', [$id]) : null,
            'quotation' => $inv['quotation_id'] ? $this->db->fetch('SELECT id, number FROM quotations WHERE id = ?', [$inv['quotation_id']]) : null,
            'sale' => $inv['sale_id'] ? $this->db->fetch('SELECT id, number FROM sales WHERE id = ?', [$inv['sale_id']]) : null,
            'reminders' => $this->db->fetchAll('SELECT r.*, u.full_name FROM payment_reminders r LEFT JOIN users u ON u.id = r.user_id WHERE r.invoice_id = ? ORDER BY r.id DESC', [$id]),
            'send' => DocumentController::sendDefaults('invoice', $inv),
            'remind' => DocumentController::sendDefaults('invoice', $inv, true),
            'advances' => $this->db->fetchAll("SELECT id, number, payment_date, unallocated_amount FROM payments WHERE customer_id = ? AND status = 'valid' AND direction = 'in' AND unallocated_amount > 0.004 ORDER BY payment_date", [$inv['customer_id']]),
        ]);
    }

    public function validate(int $id): void
    {
        $this->findOrFail('invoices', $id, 'invoices', 'Facture');
        try {
            $number = InvoiceService::validate($id);
        } catch (\DomainException $e) {
            $this->fail($e->getMessage(), 'invoices/' . $id);
        }
        Session::flash('success', 'Facture validée sous le numéro ' . $number . '. Elle est désormais verrouillée.');
        $this->redirect('invoices/' . $id);
    }

    public function delete(int $id): void
    {
        try {
            InvoiceService::deleteDraft($id);
        } catch (\DomainException $e) {
            $this->fail($e->getMessage(), 'invoices/' . $id);
        }
        Session::flash('success', 'Brouillon supprimé.');
        $this->redirect('invoices');
    }

    public function cancel(int $id): void
    {
        try {
            $cn = InvoiceService::cancel($id, Request::string('reason'));
        } catch (\DomainException $e) {
            $this->fail($e->getMessage(), 'invoices/' . $id);
        }
        Session::flash('success', 'Facture annulée : l\'avoir ' . $this->db->fetchColumn('SELECT number FROM credit_notes WHERE id = ?', [$cn]) . ' a été émis automatiquement.');
        $this->redirect('invoices/' . $id);
    }

    /** Ticket de caisse → facture (validée immédiatement, règlements du ticket imputés). */
    public function fromSale(int $saleId): void
    {
        try {
            $id = InvoiceService::fromSale($saleId);
        } catch (\DomainException $e) {
            $this->fail($e->getMessage(), 'sales/' . $saleId);
        }
        Session::flash('success', 'Facture établie à partir du ticket.');
        $this->redirect('invoices/' . $id);
    }

    /** Relance enregistrée manuellement (appel téléphonique, courrier). */
    public function remind(int $id): void
    {
        $inv = $this->findOrFail('invoices', $id, 'invoices', 'Facture');
        $channel = Request::string('channel');
        if (!in_array($channel, ['phone', 'sms', 'letter'], true) || in_array($inv['status'], ['draft', 'paid', 'cancelled'], true)) {
            $this->fail('Relance impossible.', 'invoices/' . $id);
        }
        $this->db->insert('payment_reminders', ['invoice_id' => $id, 'customer_id' => $inv['customer_id'], 'channel' => $channel,
            'message' => mb_substr(Request::string('message'), 0, 2000) ?: null, 'user_id' => auth_user()['id']]);
        $this->db->update('invoices', ['last_reminder_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $id]);
        Session::flash('success', 'Relance enregistrée.');
        $this->redirect('invoices/' . $id);
    }
}
