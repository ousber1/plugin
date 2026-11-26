<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Paginator;
use App\Core\Response;
use App\Core\ValidationException;
use App\Services\ActivityLogger;
use App\Services\Auth;
use App\Services\Finance\CompanyInfo;
use App\Services\Finance\InvoiceService;
use App\Services\Finance\PdfService;
use App\Services\Payments\PaymentService;
use App\Services\Sales\OrderMailer;

/** Invoices & credit notes: drafts, issuing, credit notes, payments on standalone invoices, PDF and e-mail. */
final class InvoiceController extends Controller
{
    private function userId(): int
    {
        return (int) (Auth::user()['id'] ?? 0);
    }

    public function index(): Response
    {
        $db = Database::instance();
        $f = ['type' => $this->request->str('type'), 'status' => $this->request->str('status'), 'q' => trim($this->request->str('q')),
            'from' => $this->request->str('from'), 'to' => $this->request->str('to')];
        $where = [];
        $params = [];
        if (in_array($f['type'], ['invoice', 'credit_note'], true)) {
            $where[] = 'i.invoice_type = ?';
            $params[] = $f['type'];
        }
        if (in_array($f['status'], ['draft', 'issued', 'partially_paid', 'paid', 'cancelled'], true)) {
            $where[] = 'i.status = ?';
            $params[] = $f['status'];
        } elseif ($f['status'] === 'unpaid') {
            $where[] = "i.invoice_type = 'invoice' AND i.status IN ('issued','partially_paid')";
        }
        if ($f['q'] !== '') {
            $where[] = '(i.invoice_number LIKE ? OR i.customer_name LIKE ? OR i.customer_company LIKE ? OR o.order_number LIKE ?)';
            $like = '%' . addcslashes($f['q'], '%_\\') . '%';
            array_push($params, $like, $like, $like, $like);
        }
        foreach (['from' => '>=', 'to' => '<='] as $k => $op) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $f[$k])) {
                $where[] = "i.issue_date {$op} ?";
                $params[] = $f[$k];
            }
        }
        $sql = ' FROM invoices i LEFT JOIN orders o ON o.id = i.order_id' . ($where ? ' WHERE ' . implode(' AND ', $where) : '');
        $per = 30;
        $page = max(1, $this->request->int('page', 1));
        $total = (int) $db->value('SELECT COUNT(*)' . $sql, $params);
        $rows = $db->fetchAll('SELECT i.*, o.order_number' . $sql . ' ORDER BY i.id DESC LIMIT ' . $per . ' OFFSET ' . (($page - 1) * $per), $params);
        $sums = $db->fetch(
            "SELECT COALESCE(SUM(CASE WHEN i.invoice_type = 'invoice' THEN i.total_ttc ELSE -i.total_ttc END), 0) AS net FROM invoices i LEFT JOIN orders o ON o.id = i.order_id WHERE "
            . implode(' AND ', array_merge($where, ["i.status <> 'draft'"])),
            $params
        ) ?? ['net' => 0];
        return $this->view('admin/invoices/index', ['rows' => new Paginator($rows, $total, $per, $page), 'f' => $f, 'net' => (string) $sums['net'],
            'drafts' => (int) $db->value("SELECT COUNT(*) FROM invoices WHERE status = 'draft'")]);
    }

    /** @return array<string,mixed> */
    private function find(string $id): array
    {
        $inv = InvoiceService::find((int) $id);
        if ($inv === null) {
            throw new HttpException(404);
        }
        return $inv;
    }

    public function show(string $id): Response
    {
        $inv = $this->find($id);
        $db = Database::instance();
        return $this->view('admin/invoices/show', [
            'inv' => $inv, 'items' => InvoiceService::items((int) $inv['id']),
            'order' => $inv['order_id'] ? $db->fetch('SELECT id, order_number, status, amount_paid FROM orders WHERE id = ?', [(int) $inv['order_id']]) : null,
            'original' => $inv['original_invoice_id'] ? InvoiceService::find((int) $inv['original_invoice_id']) : null,
            'creditNotes' => $db->fetchAll("SELECT * FROM invoices WHERE original_invoice_id = ? AND invoice_type = 'credit_note' ORDER BY id", [(int) $inv['id']]),
            'payments' => $db->fetchAll('SELECT p.*, m.name AS method_name FROM payments p LEFT JOIN payment_methods m ON m.id = p.payment_method_id WHERE p.invoice_id = ? ORDER BY p.id', [(int) $inv['id']]),
            'methods' => $db->fetchAll('SELECT id, name FROM payment_methods WHERE is_active = 1 ORDER BY sort_order, id'),
            'integrity' => InvoiceService::verify($inv), 'credited' => InvoiceService::credited((int) $inv['id']), 'balance' => InvoiceService::balance($inv),
            'missingLegal' => CompanyInfo::missingForInvoices(), 'pdf' => PdfService::available(),
        ]);
    }

    public function create(): Response
    {
        $db = Database::instance();
        return $this->view('admin/invoices/form', [
            'inv' => null, 'lines' => [], 'taxRate' => (string) setting('tax_default_rate', '20'),
            'customers' => $db->fetchAll("SELECT id, first_name, last_name, company_name, ice, email, phone FROM customers WHERE deleted_at IS NULL AND first_name <> 'Client' ORDER BY updated_at DESC, id DESC LIMIT 500"),
        ]);
    }

    public function edit(string $id): Response
    {
        $inv = $this->find($id);
        if ($inv['status'] !== 'draft') {
            return $this->redirectRoute('admin.invoices.show', ['id' => $inv['id']]);
        }
        $db = Database::instance();
        return $this->view('admin/invoices/form', [
            'inv' => $inv, 'lines' => InvoiceService::items((int) $inv['id']), 'taxRate' => (string) setting('tax_default_rate', '20'),
            'customers' => $db->fetchAll("SELECT id, first_name, last_name, company_name, ice, email, phone FROM customers WHERE deleted_at IS NULL AND first_name <> 'Client' ORDER BY updated_at DESC, id DESC LIMIT 500"),
        ]);
    }

    /** @return array<string,mixed> */
    private function head(): array
    {
        return $this->validate([
            'customer_id' => 'nullable|integer|exists:customers,id', 'customer_name' => 'required|max:150', 'customer_company' => 'nullable|max:150',
            'customer_ice' => 'nullable|max:30|regex:/^[0-9A-Za-z ]+$/', 'customer_email' => 'nullable|email', 'customer_phone' => 'nullable|phone',
            'customer_address' => 'nullable|max:500', 'payment_terms' => 'nullable|max:500', 'notes' => 'nullable|max:5000', 'locale' => 'required|locale',
        ], ['customer_name' => __('finance.customer')]);
    }

    public function save(?string $id = null): Response
    {
        $inv = $id !== null ? $this->find($id) : null;
        $head = $this->head();
        if ($inv !== null && $inv['order_id'] !== null) {
            InvoiceService::updateDraftHead((int) $inv['id'], $head);
            $newId = (int) $inv['id'];
        } else {
            $newId = InvoiceService::saveManual($inv ? (int) $inv['id'] : null, $head, (array) $this->request->input('lines', []), $this->userId());
        }
        $this->flash('success', __('common.saved'));
        return $this->redirectRoute('admin.invoices.show', ['id' => $newId]);
    }

    /** Draft invoice for an order (button on the order page). */
    public function fromOrder(string $orderId): Response
    {
        try {
            $id = InvoiceService::fromOrder((int) $orderId, $this->userId());
            $this->flash('success', __('finance.draft_created'));
            return $this->redirectRoute('admin.invoices.show', ['id' => $id]);
        } catch (ValidationException $e) {
            $this->flash('danger', (string) (array_values($e->errors)[0] ?? ''));
            return $this->redirectRoute('admin.orders.show', ['id' => (int) $orderId]);
        }
    }

    public function issue(string $id): Response
    {
        $inv = $this->find($id);
        try {
            $issued = InvoiceService::issue((int) $inv['id'], $this->userId());
            $this->flash('success', __('finance.issued', ['number' => $issued['invoice_number']]));
            if ($this->request->bool('send')) {
                $this->sendMail($issued);
            }
        } catch (ValidationException $e) {
            $this->flash('danger', (string) (array_values($e->errors)[0] ?? ''));
        }
        return $this->redirectRoute('admin.invoices.show', ['id' => $inv['id']]);
    }

    public function destroy(string $id): Response
    {
        $inv = $this->find($id);
        try {
            InvoiceService::deleteDraft((int) $inv['id']);
            $this->flash('success', __('finance.draft_deleted'));
            return $this->redirectRoute('admin.invoices.index');
        } catch (ValidationException $e) {
            $this->flash('danger', (string) (array_values($e->errors)[0] ?? ''));
            return $this->redirectRoute('admin.invoices.show', ['id' => $inv['id']]);
        }
    }

    public function credit(string $id): Response
    {
        $inv = $this->find($id);
        $this->request->normalizeDecimals(['amount']);
        $mode = $this->request->str('mode');
        try {
            $note = InvoiceService::creditNote((int) $inv['id'], $this->request->str('reason'), $this->userId(), $mode === 'partial' ? $this->request->str('amount') : null);
            $this->flash('success', __('finance.credit_issued', ['number' => $note['invoice_number']]));
            return $this->redirectRoute('admin.invoices.show', ['id' => $note['id']]);
        } catch (ValidationException $e) {
            $this->flash('danger', (string) (array_values($e->errors)[0] ?? ''));
            return $this->redirectRoute('admin.invoices.show', ['id' => $inv['id']]);
        }
    }

    public function payment(string $id): Response
    {
        $inv = $this->find($id);
        $this->request->normalizeDecimals(['amount']);
        $d = $this->validate(['amount' => 'required|decimal:2|min:0.01', 'payment_method_id' => 'required|integer|exists:payment_methods,id', 'reference' => 'nullable|max:120'],
            ['amount' => __('orders.amount')]);
        try {
            PaymentService::recordForInvoice((int) $inv['id'], (string) $d['amount'], (int) $d['payment_method_id'], $d['reference'] ?: null, $this->userId());
            $this->flash('success', __('orders.payment_recorded'));
        } catch (\InvalidArgumentException $e) {
            $this->flash('danger', $e->getMessage());
        }
        return $this->redirectRoute('admin.invoices.show', ['id' => $inv['id']]);
    }

    public function send(string $id): Response
    {
        $inv = $this->find($id);
        if ($inv['status'] === 'draft' || empty($inv['customer_email'])) {
            $this->flash('danger', __('finance.err_send'));
        } else {
            $this->sendMail($inv);
        }
        return $this->redirectRoute('admin.invoices.show', ['id' => $inv['id']]);
    }

    /** @param array<string,mixed> $inv */
    private function sendMail(array $inv): void
    {
        if (empty($inv['customer_email'])) {
            return;
        }
        $raw = InvoiceService::newAccessToken((int) $inv['id']);
        OrderMailer::document($inv + ['order_number' => $inv['invoice_number']], 'invoice', 'invoice_sent', [
            'url' => absolute_url('invoice/' . $inv['id'] . '/' . $raw), 'invoice' => $inv,
        ]);
        Database::instance()->update('invoices', ['sent_at' => date('Y-m-d H:i:s')], ['id' => (int) $inv['id']]);
        ActivityLogger::log('invoice.sent', 'invoice', (int) $inv['id'], (string) $inv['customer_email']);
        $this->flash('success', __('finance.sent', ['email' => $inv['customer_email']]));
    }

    public function pdf(string $id): Response
    {
        $inv = $this->find($id);
        return new Response(PdfService::invoice($inv), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($this->request->bool('download') ? 'attachment' : 'inline') . '; filename="' . ($inv['invoice_number'] ?: 'brouillon-' . $inv['id']) . '.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
