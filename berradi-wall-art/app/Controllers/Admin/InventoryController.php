<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Paginator;
use App\Core\Response;
use App\Services\ActivityLogger;
use App\Services\Auth;
use App\Services\Operations\InsufficientStockException;
use App\Services\Operations\InventoryService;

/**
 * Raw materials (canvas, paper, ink, mouldings, packaging…): stock levels, movements, physical counts.
 */
final class InventoryController extends Controller
{
    public const CATEGORIES = ['canvas', 'bache', 'vinyl', 'poster_paper', 'photo_paper', 'ink', 'frame', 'mdf', 'accessory', 'packaging', 'other'];
    public const UNITS = ['m', 'm2', 'ml', 'l', 'kg', 'piece', 'roll', 'sheet', 'box'];

    public function index(): Response
    {
        $db = Database::instance();
        $q = trim($this->request->str('q'));
        $cat = $this->request->str('category');
        $where = ['1=1'];
        $params = [];
        if ($q !== '') {
            $where[] = '(m.name LIKE ? OR m.sku LIKE ?)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            array_push($params, $like, $like);
        }
        if (in_array($cat, self::CATEGORIES, true)) {
            $where[] = 'm.category = ?';
            $params[] = $cat;
        }
        if ($this->request->bool('low')) {
            $where[] = 'm.min_stock > 0 AND m.stock_quantity <= m.min_stock';
        }
        $rows = $db->fetchAll(
            'SELECT m.*, s.name AS supplier_name, (m.stock_quantity * m.unit_cost) AS stock_value FROM materials m LEFT JOIN suppliers s ON s.id = m.supplier_id
             WHERE ' . implode(' AND ', $where) . ' ORDER BY m.is_active DESC, m.category, m.name',
            $params
        );
        $value = (string) $db->value('SELECT COALESCE(SUM(GREATEST(stock_quantity, 0) * unit_cost), 0) FROM materials WHERE is_active = 1');
        return $this->view('admin/inventory/index', [
            'rows' => $rows, 'q' => $q, 'cat' => $cat, 'low' => $this->request->bool('low'),
            'value' => \App\Helpers\Money::normalize(number_format((float) $value, 2, '.', '')), 'lowCount' => count(InventoryService::lowStock(500)),
        ]);
    }

    /** @return array<string,mixed> */
    private function find(string $id): array
    {
        $m = Database::instance()->fetch('SELECT m.*, s.name AS supplier_name FROM materials m LEFT JOIN suppliers s ON s.id = m.supplier_id WHERE m.id = ?', [(int) $id]);
        if ($m === null) {
            throw new HttpException(404);
        }
        return $m;
    }

    public function show(string $id): Response
    {
        $m = $this->find($id);
        $db = Database::instance();
        $per = 30;
        $page = max(1, $this->request->int('page', 1));
        $total = (int) $db->value('SELECT COUNT(*) FROM stock_movements WHERE material_id = ?', [(int) $m['id']]);
        $moves = $db->fetchAll(
            'SELECT s.*, u.name AS user_name, p.purchase_number, j.job_number, o.order_number FROM stock_movements s
             LEFT JOIN users u ON u.id = s.user_id LEFT JOIN purchases p ON p.id = s.purchase_id
             LEFT JOIN production_jobs j ON j.id = s.production_job_id LEFT JOIN orders o ON o.id = s.order_id
             WHERE s.material_id = ? ORDER BY s.id DESC LIMIT ' . $per . ' OFFSET ' . (($page - 1) * $per),
            [(int) $m['id']]
        );
        $usedBy = $db->fetchAll(
            "SELECT 'support' AS kind, name FROM product_materials WHERE inventory_material_id = ? UNION ALL SELECT 'frame', name FROM frames WHERE inventory_material_id = ?",
            [(int) $m['id'], (int) $m['id']]
        );
        return $this->view('admin/inventory/show', ['m' => $m, 'moves' => new Paginator($moves, $total, $per, $page), 'usedBy' => $usedBy]);
    }

    public function create(): Response
    {
        return $this->view('admin/inventory/form', ['m' => null, 'suppliers' => $this->suppliers()]);
    }

    public function edit(string $id): Response
    {
        return $this->view('admin/inventory/form', ['m' => $this->find($id), 'suppliers' => $this->suppliers()]);
    }

    /** @return list<array<string,mixed>> */
    private function suppliers(): array
    {
        return Database::instance()->fetchAll('SELECT id, name FROM suppliers WHERE is_active = 1 ORDER BY name');
    }

    public function save(?string $id = null): Response
    {
        $m = $id !== null ? $this->find($id) : null;
        $this->request->normalizeDecimals(['min_stock', 'initial_stock', 'unit_cost']);
        $d = $this->validate([
            'sku' => 'required|max:60|regex:/^[A-Za-z0-9-]+$/|unique:materials,sku' . ($m ? ',' . (int) $m['id'] : ''),
            'name' => 'required|max:150', 'category' => 'required|in:' . implode(',', self::CATEGORIES), 'unit' => 'required|in:' . implode(',', self::UNITS),
            'min_stock' => 'nullable|decimal:3|min:0', 'supplier_id' => 'nullable|integer|exists:suppliers,id', 'location' => 'nullable|max:100', 'notes' => 'nullable|max:2000',
            'initial_stock' => 'nullable|decimal:3|min:0', 'unit_cost' => 'nullable|decimal:4|min:0',
        ], ['sku' => 'SKU', 'name' => __('admin.name')]);
        $row = [
            'sku' => strtoupper((string) $d['sku']), 'name' => $d['name'], 'category' => $d['category'], 'unit' => $d['unit'],
            'min_stock' => (string) (($d['min_stock'] ?? '') ?: '0'), 'supplier_id' => !empty($d['supplier_id']) ? (int) $d['supplier_id'] : null,
            'location' => $d['location'], 'notes' => $d['notes'], 'is_active' => $this->request->bool('is_active') ? 1 : 0,
        ];
        $db = Database::instance();
        if ($m === null) {
            $newId = $db->insert('materials', $row + ['unit_cost' => '0.0000']);
            // opening stock goes through a movement so the history starts right
            if (!empty($d['initial_stock']) && InventoryService::toMilli((string) $d['initial_stock']) > 0) {
                InventoryService::move($newId, 'in', (string) $d['initial_stock'], (string) (($d['unit_cost'] ?? '') ?: '0'), ['user_id' => (int) (Auth::user()['id'] ?? 0), 'notes' => __('inventory.opening_stock')]);
            }
            ActivityLogger::log('material.created', 'material', $newId, (string) $d['name']);
            $this->flash('success', __('common.created'));
            return $this->redirectRoute('admin.inventory.show', ['id' => $newId]);
        }
        $db->update('materials', $row, ['id' => (int) $m['id']]);
        ActivityLogger::log('material.updated', 'material', (int) $m['id'], (string) $d['name']);
        $this->flash('success', __('common.saved'));
        return $this->redirectRoute('admin.inventory.show', ['id' => $m['id']]);
    }

    /** Manual movement: entry, exit, waste, return, or physical count. */
    public function movement(string $id): Response
    {
        $m = $this->find($id);
        $this->request->normalizeDecimals(['quantity', 'unit_cost']);
        $d = $this->validate([
            'type' => 'required|in:in,out,waste,return,count', 'quantity' => 'required|decimal:3|min:0',
            'unit_cost' => 'nullable|decimal:4|min:0', 'notes' => 'nullable|max:500',
        ], ['quantity' => __('inventory.quantity')]);
        $uid = (int) (Auth::user()['id'] ?? 0);
        try {
            if ($d['type'] === 'count') {
                $r = InventoryService::count((int) $m['id'], (string) $d['quantity'], $uid, (string) ($d['notes'] ?? ''));
                $this->flash('success', $r === null ? __('inventory.count_same') : __('inventory.count_done', ['balance' => $r['balance']]));
            } else {
                if (InventoryService::toMilli((string) $d['quantity']) <= 0) {
                    throw new \InvalidArgumentException(__('inventory.err_qty'));
                }
                $sign = in_array($d['type'], ['out', 'waste'], true) ? '-' : '';
                $cost = in_array($d['type'], ['in', 'return'], true) && ($d['unit_cost'] ?? '') !== '' ? (string) $d['unit_cost'] : null;
                if ($d['type'] === 'in' && $cost === null) {
                    throw new \InvalidArgumentException(__('inventory.err_cost'));
                }
                InventoryService::move((int) $m['id'], (string) $d['type'], $sign . $d['quantity'], $cost, ['user_id' => $uid, 'notes' => (string) ($d['notes'] ?? '')]);
                $this->flash('success', __('inventory.movement_saved'));
            }
        } catch (InsufficientStockException|\InvalidArgumentException $e) {
            $this->flash('danger', $e->getMessage());
        }
        return $this->redirectRoute('admin.inventory.show', ['id' => $m['id']]);
    }
}
