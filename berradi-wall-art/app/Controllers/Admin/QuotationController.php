<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Response;
use App\Core\ValidationException;
use App\Services\Auth;
use App\Services\Finance\PdfService;
use App\Services\Finance\QuotationService;

/** Quotations back office: list, editor, sending, customer answer, conversion into an order, PDF. */
final class QuotationController extends Controller
{
    private function userId(): int
    {
        return (int) (Auth::user()['id'] ?? 0);
    }

    public function index(): Response
    {
        $status = $this->request->str('status');
        $q = trim($this->request->str('q'));
        $where = [];
        $params = [];
        if (in_array($status, QuotationService::STATUSES, true)) {
            $where[] = 'status = ?';
            $params[] = $status;
        }
        if ($q !== '') {
            $where[] = '(quotation_number LIKE ? OR customer_name LIKE ? OR customer_company LIKE ?)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            array_push($params, $like, $like, $like);
        }
        $db = Database::instance();
        $counts = array_column($db->fetchAll('SELECT status, COUNT(*) AS n FROM quotations GROUP BY status'), 'n', 'status');
        return $this->view('admin/quotations/index', [
            'rows' => $db->fetchAll('SELECT * FROM quotations' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY id DESC LIMIT 300', $params),
            'status' => $status, 'q' => $q, 'counts' => $counts,
        ]);
    }

    /** @return array<string,mixed> */
    private function find(string $id): array
    {
        $q = QuotationService::find((int) $id);
        if ($q === null) {
            throw new HttpException(404);
        }
        return $q;
    }

    /** @return array<string,mixed> */
    private function formData(?array $q): array
    {
        $db = Database::instance();
        $prefill = null;
        if ($q === null && $this->request->int('customer') > 0) {
            $prefill = $db->fetch('SELECT * FROM customers WHERE id = ? AND deleted_at IS NULL', [$this->request->int('customer')]);
        }
        return [
            'q' => $q, 'lines' => $q ? QuotationService::items((int) $q['id']) : [], 'prefill' => $prefill,
            'customers' => $db->fetchAll("SELECT id, first_name, last_name, company_name, ice, email, phone FROM customers WHERE deleted_at IS NULL AND first_name <> 'Client' ORDER BY updated_at DESC, id DESC LIMIT 500"),
            'taxRate' => (string) setting('tax_default_rate', '20'),
            'validity' => (int) setting('quotation_validity_days', 15),
        ];
    }

    public function create(): Response
    {
        return $this->view('admin/quotations/form', $this->formData(null));
    }

    public function edit(string $id): Response
    {
        $q = $this->find($id);
        if (!in_array($q['status'], QuotationService::EDITABLE, true)) {
            return $this->redirectRoute('admin.quotations.show', ['id' => $q['id']]);
        }
        return $this->view('admin/quotations/form', $this->formData($q));
    }

    public function show(string $id): Response
    {
        $q = $this->find($id);
        $db = Database::instance();
        return $this->view('admin/quotations/show', [
            'q' => $q, 'lines' => QuotationService::items((int) $q['id']),
            'order' => $q['converted_order_id'] ? $db->fetch('SELECT id, order_number, status FROM orders WHERE id = ?', [(int) $q['converted_order_id']]) : null,
            'customer' => $q['customer_id'] ? $db->fetch('SELECT * FROM customers WHERE id = ?', [(int) $q['customer_id']]) : null,
            'methods' => $db->fetchAll('SELECT id, name FROM payment_methods WHERE is_active = 1 ORDER BY sort_order, id'),
        ]);
    }

    /** @return array<string,mixed> */
    private function head(): array
    {
        return $this->validate([
            'customer_id' => 'nullable|integer|exists:customers,id', 'customer_name' => 'required|max:150', 'customer_company' => 'nullable|max:150',
            'customer_ice' => 'nullable|max:30|regex:/^[0-9A-Za-z ]+$/', 'customer_email' => 'nullable|email', 'customer_phone' => 'nullable|phone',
            'customer_address' => 'nullable|max:500', 'issue_date' => 'required|date', 'valid_until' => 'required|date', 'locale' => 'required|locale',
            'terms' => 'nullable|max:5000', 'notes' => 'nullable|max:5000',
        ], ['customer_name' => __('finance.customer'), 'valid_until' => __('finance.valid_until')]);
    }

    public function save(?string $id = null): Response
    {
        $q = $id !== null ? $this->find($id) : null;
        $newId = QuotationService::save($q ? (int) $q['id'] : null, $this->head(), (array) $this->request->input('lines', []), $this->userId());
        $this->flash('success', $q && $q['status'] === 'sent' ? __('finance.quote_back_to_draft') : __('common.saved'));
        return $this->redirectRoute('admin.quotations.show', ['id' => $newId]);
    }

    public function send(string $id): Response
    {
        $q = $this->find($id);
        try {
            $url = QuotationService::send((int) $q['id'], $this->userId(), mb_substr($this->request->str('message'), 0, 2000));
            $this->flash('success', $q['customer_email'] ? __('finance.quote_sent', ['email' => $q['customer_email']]) : __('finance.quote_link_ready'));
            \App\Core\Session::flash('share_url', $url);
        } catch (ValidationException $e) {
            $this->flash('danger', (string) (array_values($e->errors)[0] ?? ''));
        }
        return $this->redirectRoute('admin.quotations.show', ['id' => $q['id']]);
    }

    public function status(string $id): Response
    {
        $q = $this->find($id);
        try {
            QuotationService::setStatus((int) $q['id'], $this->request->str('status'), $this->userId());
            $this->flash('success', __('common.saved'));
        } catch (ValidationException $e) {
            $this->flash('danger', (string) (array_values($e->errors)[0] ?? ''));
        }
        return $this->redirectRoute('admin.quotations.show', ['id' => $q['id']]);
    }

    public function duplicate(string $id): Response
    {
        $new = QuotationService::duplicate((int) $this->find($id)['id'], $this->userId());
        $this->flash('success', __('finance.quote_duplicated'));
        return $this->redirectRoute('admin.quotations.edit', ['id' => $new]);
    }

    public function convert(string $id): Response
    {
        $q = $this->find($id);
        $d = $this->validate([
            'city' => 'required|max:100', 'address' => 'required|max:255', 'phone' => 'required|phone', 'payment_method_id' => 'nullable|integer|exists:payment_methods,id',
        ], ['city' => __('checkout.city'), 'address' => __('checkout.address'), 'phone' => __('auth.phone')]);
        try {
            $order = QuotationService::convert((int) $q['id'], ['city' => $d['city'], 'address' => $d['address'], 'phone' => $d['phone'], 'payment_method_id' => !empty($d['payment_method_id']) ? (int) $d['payment_method_id'] : null], $this->userId());
        } catch (ValidationException $e) {
            $this->flash('danger', (string) (array_values($e->errors)[0] ?? ''));
            return $this->redirectRoute('admin.quotations.show', ['id' => $q['id']]);
        }
        $this->flash('success', __('orders.created', ['number' => $order['order_number']]));
        return $this->redirectRoute('admin.orders.show', ['id' => $order['id']]);
    }

    public function pdf(string $id): Response
    {
        $q = $this->find($id);
        return new Response(PdfService::quotation($q), 200, [
            'Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="' . $q['quotation_number'] . '.pdf"', 'Cache-Control' => 'private, no-store',
        ]);
    }
}
