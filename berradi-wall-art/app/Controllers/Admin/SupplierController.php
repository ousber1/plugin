<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Response;
use App\Services\ActivityLogger;

/** Suppliers with what is bought from them and what is still owed. */
final class SupplierController extends Controller
{
    public function index(): Response
    {
        return $this->view('admin/suppliers/index', ['rows' => Database::instance()->fetchAll(
            "SELECT s.*, COUNT(p.id) AS purchases,
                    COALESCE(SUM(CASE WHEN p.status <> 'cancelled' THEN p.total END), 0) AS purchased,
                    COALESCE(SUM(CASE WHEN p.status <> 'cancelled' THEN p.total - p.amount_paid END), 0) AS owed
             FROM suppliers s LEFT JOIN purchases p ON p.supplier_id = s.id GROUP BY s.id ORDER BY s.is_active DESC, s.name"
        ), 'edit' => $this->request->int('edit')]);
    }

    public function save(?string $id = null): Response
    {
        $d = $this->validate([
            'name' => 'required|max:150', 'contact_name' => 'nullable|max:150', 'email' => 'nullable|email', 'phone' => 'nullable|phone',
            'address' => 'nullable|max:255', 'city' => 'nullable|max:100', 'ice' => 'nullable|max:30|regex:/^[0-9A-Za-z ]+$/', 'notes' => 'nullable|max:2000',
        ], ['name' => __('admin.name')]);
        $db = Database::instance();
        $row = $d + ['is_active' => $this->request->bool('is_active') ? 1 : 0];
        if ($id === null) {
            $id = (string) $db->insert('suppliers', $row);
        } else {
            if ($db->value('SELECT id FROM suppliers WHERE id = ?', [(int) $id]) === null) {
                throw new HttpException(404);
            }
            $db->update('suppliers', $row, ['id' => (int) $id]);
        }
        ActivityLogger::log('supplier.saved', 'supplier', (int) $id, (string) $d['name']);
        $this->flash('success', __('common.saved'));
        return $this->redirect(route('admin.suppliers.index') . '#supplier-' . (int) $id);
    }
}
