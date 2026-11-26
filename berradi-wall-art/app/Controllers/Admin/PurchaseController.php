<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Response;
use App\Core\ValidationException;
use App\Services\Auth;
use App\Services\Operations\PurchaseService;

/** Supplier purchases: lines, reception into stock, payments. */
final class PurchaseController extends Controller
{
    private function userId(): int
    {
        return (int) (Auth::user()['id'] ?? 0);
    }

    public function index(): Response
    {
        $status = $this->request->str('status');
        $params = [];
        $where = '';
        if (in_array($status, PurchaseService::STATUSES, true)) {
            $where = ' WHERE p.status = ?';
            $params[] = $status;
        }
        return $this->view('admin/purchases/index', ['status' => $status, 'rows' => Database::instance()->fetchAll(
            'SELECT p.*, s.name AS supplier_name, (SELECT COUNT(*) FROM purchase_items WHERE purchase_id = p.id) AS line_count
             FROM purchases p JOIN suppliers s ON s.id = p.supplier_id' . $where . ' ORDER BY p.purchase_date DESC, p.id DESC LIMIT 300',
            $params
        )]);
    }

    /** @return array<string,mixed> */
    private function find(string $id): array
    {
        $p = Database::instance()->fetch('SELECT p.*, s.name AS supplier_name FROM purchases p JOIN suppliers s ON s.id = p.supplier_id WHERE p.id = ?', [(int) $id]);
        if ($p === null) {
            throw new HttpException(404);
        }
        return $p;
    }

    /** @return array<string,mixed> */
    private function formData(?array $p): array
    {
        $db = Database::instance();
        return [
            'p' => $p,
            'lines' => $p ? $db->fetchAll('SELECT * FROM purchase_items WHERE purchase_id = ? ORDER BY id', [(int) $p['id']]) : [],
            'suppliers' => $db->fetchAll('SELECT id, name FROM suppliers WHERE is_active = 1 ORDER BY name'),
            'materials' => $db->fetchAll('SELECT id, sku, name, unit, unit_cost FROM materials WHERE is_active = 1 ORDER BY name'),
            'taxRate' => (string) setting('tax_default_rate', '20'),
        ];
    }

    public function create(): Response
    {
        return $this->view('admin/purchases/form', $this->formData(null));
    }

    public function edit(string $id): Response
    {
        $p = $this->find($id);
        if (!in_array($p['status'], ['draft', 'ordered'], true)) {
            return $this->redirectRoute('admin.purchases.show', ['id' => $p['id']]);
        }
        return $this->view('admin/purchases/form', $this->formData($p));
    }

    public function show(string $id): Response
    {
        $p = $this->find($id);
        $db = Database::instance();
        return $this->view('admin/purchases/show', [
            'p' => $p,
            'lines' => $db->fetchAll('SELECT i.*, m.unit, m.sku FROM purchase_items i LEFT JOIN materials m ON m.id = i.material_id WHERE i.purchase_id = ? ORDER BY i.id', [(int) $p['id']]),
            'payments' => $db->fetchAll('SELECT sp.*, u.name AS user_name FROM supplier_payments sp LEFT JOIN users u ON u.id = sp.created_by WHERE sp.purchase_id = ? ORDER BY sp.id', [(int) $p['id']]),
        ]);
    }

    public function save(?string $id = null): Response
    {
        $p = $id !== null ? $this->find($id) : null;
        $d = $this->validate([
            'supplier_id' => 'required|integer|exists:suppliers,id', 'purchase_date' => 'required|date', 'supplier_invoice_ref' => 'nullable|max:100',
            'notes' => 'nullable|max:2000', 'status' => 'required|in:draft,ordered',
        ], ['supplier_id' => __('inventory.supplier'), 'purchase_date' => __('admin.date')]);
        $lines = [];
        foreach ((array) $this->request->input('lines', []) as $l) {
            if (is_array($l)) {
                $lines[] = ['material_id' => $l['material_id'] ?? null, 'description' => (string) ($l['description'] ?? ''), 'quantity' => (string) ($l['quantity'] ?? ''),
                    'unit_cost' => (string) ($l['unit_cost'] ?? ''), 'tax_rate' => (string) ($l['tax_rate'] ?? '0')];
            }
        }
        $newId = PurchaseService::save($p ? (int) $p['id'] : null, $d, $lines, $this->userId());
        $this->flash('success', __('common.saved'));
        return $this->redirectRoute('admin.purchases.show', ['id' => $newId]);
    }

    public function receive(string $id): Response
    {
        $p = $this->find($id);
        try {
            $n = PurchaseService::receive((int) $p['id'], $this->userId());
            $this->flash('success', __('inventory.received', ['n' => $n]));
        } catch (ValidationException $e) {
            $this->flash('danger', (string) (array_values($e->errors)[0] ?? ''));
        }
        return $this->redirectRoute('admin.purchases.show', ['id' => $p['id']]);
    }

    public function cancel(string $id): Response
    {
        $p = $this->find($id);
        try {
            PurchaseService::cancel((int) $p['id']);
            $this->flash('success', __('inventory.purchase_cancelled'));
        } catch (ValidationException $e) {
            $this->flash('danger', (string) (array_values($e->errors)[0] ?? ''));
        }
        return $this->redirectRoute('admin.purchases.show', ['id' => $p['id']]);
    }

    public function pay(string $id): Response
    {
        $p = $this->find($id);
        $this->request->normalizeDecimals(['amount']);
        $d = $this->validate([
            'amount' => 'required|decimal:2|min:0.01', 'payment_method' => 'required|in:bank_transfer,cash,cheque,card,other',
            'paid_at' => 'required|date', 'reference' => 'nullable|max:120',
        ], ['amount' => __('orders.amount')]);
        try {
            PurchaseService::pay((int) $p['id'], (string) $d['amount'], (string) $d['payment_method'], (string) $d['paid_at'], $d['reference'] ?: null, $this->userId());
            $this->flash('success', __('inventory.paid'));
        } catch (ValidationException $e) {
            $this->flash('danger', (string) (array_values($e->errors)[0] ?? ''));
        }
        return $this->redirectRoute('admin.purchases.show', ['id' => $p['id']]);
    }
}
