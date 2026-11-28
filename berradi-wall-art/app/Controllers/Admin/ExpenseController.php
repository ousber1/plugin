<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Response;
use App\Core\ValidationException;
use App\Helpers\Money;
use App\Services\ActivityLogger;
use App\Services\Auth;

/** Business expenses (TTC with recoverable VAT), with a private receipt scan. */
final class ExpenseController extends Controller
{
    public const CATEGORIES = ['rent', 'salaries', 'utilities', 'marketing', 'shipping', 'equipment', 'maintenance', 'software', 'taxes_fees', 'bank_fees', 'transport', 'other'];
    private const RECEIPT_TYPES = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public function index(): Response
    {
        $db = Database::instance();
        $month = preg_match('/^\d{4}-\d{2}$/', $this->request->str('month')) ? $this->request->str('month') : date('Y-m');
        $cat = $this->request->str('category');
        $from = $month . '-01';
        $to = date('Y-m-d', strtotime($from . ' +1 month'));
        $params = [$from, $to];
        $where = 'e.expense_date >= ? AND e.expense_date < ?';
        if (in_array($cat, self::CATEGORIES, true)) {
            $where .= ' AND e.category = ?';
            $params[] = $cat;
        }
        return $this->view('admin/expenses/index', [
            'rows' => $db->fetchAll("SELECT e.*, s.name AS supplier_name, u.name AS user_name FROM expenses e LEFT JOIN suppliers s ON s.id = e.supplier_id LEFT JOIN users u ON u.id = e.created_by WHERE {$where} ORDER BY e.expense_date DESC, e.id DESC", $params),
            'totals' => $db->fetch("SELECT COALESCE(SUM(amount), 0) AS ttc, COALESCE(SUM(tax_amount), 0) AS tax FROM expenses e WHERE {$where}", $params),
            'byCat' => $db->fetchAll("SELECT category, SUM(amount) AS ttc FROM expenses e WHERE {$where} GROUP BY category ORDER BY ttc DESC", $params),
            'month' => $month, 'cat' => $cat, 'edit' => $this->request->int('edit') > 0 ? $db->fetch('SELECT * FROM expenses WHERE id = ?', [$this->request->int('edit')]) : null,
            'suppliers' => $db->fetchAll('SELECT id, name FROM suppliers WHERE is_active = 1 ORDER BY name'),
        ]);
    }

    public function save(?string $id = null): Response
    {
        $db = Database::instance();
        $row = $id !== null ? $db->fetch('SELECT * FROM expenses WHERE id = ?', [(int) $id]) : null;
        if ($id !== null && $row === null) {
            throw new HttpException(404);
        }
        $this->request->normalizeDecimals(['amount', 'tax_amount']);
        $d = $this->validate([
            'category' => 'required|in:' . implode(',', self::CATEGORIES), 'description' => 'required|max:255', 'amount' => 'required|decimal:2|min:0.01',
            'tax_amount' => 'nullable|decimal:2|min:0', 'expense_date' => 'required|date', 'payment_method' => 'nullable|in:bank_transfer,cash,cheque,card,other',
            'supplier_id' => 'nullable|integer|exists:suppliers,id', 'reference' => 'nullable|max:120',
        ], ['description' => __('product.description'), 'amount' => __('finance.amount_ttc'), 'expense_date' => __('admin.date')]);
        $tax = Money::normalize((string) (($d['tax_amount'] ?? '') ?: '0'));
        if (Money::compare($tax, Money::normalize((string) $d['amount'])) >= 0) {
            throw new ValidationException(['tax_amount' => __('finance.err_tax_amount')], $this->request->all());
        }
        if ($d['expense_date'] > date('Y-m-d', strtotime('+1 day'))) {
            throw new ValidationException(['expense_date' => __('finance.err_future')], $this->request->all());
        }
        $values = [
            'category' => $d['category'], 'description' => $d['description'], 'amount' => Money::normalize((string) $d['amount']), 'tax_amount' => $tax,
            'expense_date' => date('Y-m-d', (int) strtotime((string) $d['expense_date'])), 'payment_method' => $d['payment_method'] ?: null,
            'supplier_id' => !empty($d['supplier_id']) ? (int) $d['supplier_id'] : null, 'reference' => $d['reference'] ?: null,
        ];
        $file = $this->request->file('receipt');
        if ($file !== null && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $values['attachment_path'] = $this->storeReceipt($file);
        }
        if ($row === null) {
            $id = (string) $db->insert('expenses', $values + ['created_by' => (int) (Auth::user()['id'] ?? 0)]);
            ActivityLogger::log('expense.created', 'expense', (int) $id, $values['description'] . ' ' . $values['amount']);
        } else {
            if (isset($values['attachment_path']) && $row['attachment_path']) {
                $this->deleteReceipt((string) $row['attachment_path']);
            }
            $db->update('expenses', $values, ['id' => (int) $id]);
            ActivityLogger::log('expense.updated', 'expense', (int) $id, $values['description'] . ' ' . $values['amount']);
        }
        $this->flash('success', __('common.saved'));
        return $this->redirect(route('admin.expenses.index', ['month' => substr($values['expense_date'], 0, 7)]));
    }

    public function destroy(string $id): Response
    {
        $db = Database::instance();
        $row = $db->fetch('SELECT * FROM expenses WHERE id = ?', [(int) $id]);
        if ($row === null) {
            throw new HttpException(404);
        }
        $db->delete('expenses', ['id' => (int) $id]);
        if ($row['attachment_path']) {
            $this->deleteReceipt((string) $row['attachment_path']);
        }
        // the activity log keeps what was removed
        ActivityLogger::log('expense.deleted', 'expense', (int) $id, $row['expense_date'] . ' · ' . $row['description'] . ' · ' . $row['amount']);
        $this->flash('success', __('common.deleted'));
        return $this->redirect(route('admin.expenses.index', ['month' => substr((string) $row['expense_date'], 0, 7)]));
    }

    public function receipt(string $id): Response
    {
        $row = Database::instance()->fetch('SELECT attachment_path FROM expenses WHERE id = ?', [(int) $id]);
        $rel = (string) ($row['attachment_path'] ?? '');
        $path = storage_path('invoices/receipts/' . $rel);
        if ($rel === '' || str_contains($rel, '..') || !is_file($path)) {
            throw new HttpException(404);
        }
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        return Response::file($path, isset(self::RECEIPT_TYPES[$mime]) ? $mime : 'application/octet-stream', 'justificatif-' . (int) $id . '.' . pathinfo($rel, PATHINFO_EXTENSION));
    }

    /** @param array<string,mixed> $file */
    private function storeReceipt(array $file): string
    {
        if ((int) $file['error'] !== UPLOAD_ERR_OK || (int) $file['size'] > 10 * 1048576 || !is_uploaded_file((string) $file['tmp_name'])) {
            throw new ValidationException(['receipt' => __('validation.upload_failed')], $this->request->all());
        }
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
        if (!isset(self::RECEIPT_TYPES[$mime])) {
            throw new ValidationException(['receipt' => __('validation.upload_type')], $this->request->all());
        }
        $rel = date('Y/m') . '/' . bin2hex(random_bytes(16)) . '.' . self::RECEIPT_TYPES[$mime];
        $dir = dirname(storage_path('invoices/receipts/' . $rel));
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new ValidationException(['receipt' => __('validation.upload_failed')], $this->request->all());
        }
        move_uploaded_file((string) $file['tmp_name'], storage_path('invoices/receipts/' . $rel));
        return $rel;
    }

    private function deleteReceipt(string $rel): void
    {
        $path = storage_path('invoices/receipts/' . $rel);
        if (!str_contains($rel, '..') && is_file($path)) {
            unlink($path);
        }
    }
}
