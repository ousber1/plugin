<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\AuditLog;
use App\Core\Controller;
use App\Core\Csv;
use App\Core\DocumentNumber;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Services\StockService;

/**
 * Matières premières et consommables (papier, vinyle, bâche, encres, toner,
 * film et poudre DTF, MDF, acrylique, supports de sublimation, textile…).
 */
final class MaterialController extends Controller
{
    public const TYPES = [
        'paper' => 'Papier', 'vinyl' => 'Vinyle', 'banner' => 'Bâche', 'ink' => 'Encre', 'toner' => 'Toner',
        'dtf_film' => 'Film DTF', 'dtf_powder' => 'Poudre DTF', 'lamination' => 'Lamination', 'mdf' => 'MDF',
        'acrylic' => 'Acrylique / plexiglas', 'sublimation_blank' => 'Support de sublimation', 'textile' => 'Textile (t-shirts…)',
        'packaging' => 'Emballage', 'office_supply' => 'Fournitures de bureau', 'spare_part' => 'Pièce détachée', 'other' => 'Autre',
    ];

    private const SORTS = ['name' => 'm.name', 'code' => 'm.code', 'type' => 'm.material_type', 'stock' => 'm.stock_quantity', 'value' => '(m.stock_quantity * m.unit_cost)'];

    private function filters(): array
    {
        return [
            'q'        => Request::string('q'),
            'type'     => isset(self::TYPES[Request::string('type')]) ? Request::string('type') : '',
            'supplier' => Request::int('supplier'),
            'stock'    => Request::string('stock'),
            'status'   => Request::string('status', 'active'),
            'sort'     => array_key_exists(Request::string('sort'), self::SORTS) ? Request::string('sort') : 'name',
            'dir'      => Request::string('dir') === 'desc' ? 'DESC' : 'ASC',
        ];
    }

    private function where(array $f): array
    {
        $where = ['m.deleted_at IS NULL'];
        $params = [];
        if ($f['q'] !== '') {
            $where[] = '(m.name LIKE :q1 OR m.code LIKE :q2 OR m.barcode = :q3)';
            $params += ['q1' => "%{$f['q']}%", 'q2' => "%{$f['q']}%", 'q3' => $f['q']];
        }
        if ($f['type'] !== '') {
            $where[] = 'm.material_type = :type';
            $params['type'] = $f['type'];
        }
        if ($f['supplier'] > 0) {
            $where[] = 'm.supplier_id = :sup';
            $params['sup'] = $f['supplier'];
        }
        if ($f['stock'] === 'low') {
            $where[] = 'm.min_stock > 0 AND m.stock_quantity <= m.min_stock';
        } elseif ($f['stock'] === 'out') {
            $where[] = 'm.stock_quantity <= 0';
        }
        if ($f['status'] === 'active') {
            $where[] = 'm.is_active = 1';
        } elseif ($f['status'] === 'inactive') {
            $where[] = 'm.is_active = 0';
        }
        return [implode(' AND ', $where), $params];
    }

    public function index(): void
    {
        $f = $this->filters();
        [$where, $params] = $this->where($f);
        $p = Paginator::fromRequest((int) $this->db->fetchColumn("SELECT COUNT(*) FROM materials m WHERE {$where}", $params));
        $this->view('materials/index', [
            'title'     => 'Matières premières',
            'materials' => $this->db->fetchAll(
                "SELECT m.*, u.symbol, s.name AS supplier_name FROM materials m JOIN units u ON u.id = m.unit_id LEFT JOIN suppliers s ON s.id = m.supplier_id
                  WHERE {$where} ORDER BY " . self::SORTS[$f['sort']] . " {$f['dir']} LIMIT {$p->limit()} OFFSET {$p->offset()}",
                $params
            ),
            'p' => $p, 'f' => $f,
            'suppliers' => $this->db->fetchAll('SELECT id, name FROM suppliers WHERE deleted_at IS NULL ORDER BY name'),
            'valuation' => StockService::valuation(),
        ]);
    }

    public function export(): void
    {
        [$where, $params] = $this->where($this->filters());
        $rows = $this->db->fetchAll("SELECT m.*, u.symbol, s.name AS supplier_name FROM materials m JOIN units u ON u.id = m.unit_id LEFT JOIN suppliers s ON s.id = m.supplier_id WHERE {$where} ORDER BY m.code", $params);
        $showCost = can('products.view_cost');
        foreach ($rows as &$r) {
            $r['type_label'] = self::TYPES[$r['material_type']] ?? $r['material_type'];
            $r['stock_quantity'] = (float) $r['stock_quantity'];
            $r['min_stock'] = (float) $r['min_stock'];
            $r['unit_cost'] = $showCost ? (float) $r['unit_cost'] : '';
            $r['value'] = $showCost ? round(max(0, (float) $r['stock_quantity']) * (float) $r['unit_cost'], 2) : '';
        }
        unset($r);
        Csv::download('matieres-' . date('Y-m-d') . '.csv', [
            'code' => 'Code', 'name' => 'Nom', 'type_label' => 'Type', 'symbol' => 'Unité', 'stock_quantity' => 'Stock',
            'min_stock' => 'Stock minimum', 'unit_cost' => 'Coût moyen', 'value' => 'Valeur', 'supplier_name' => 'Fournisseur',
            'width_cm' => 'Laize (cm)', 'length_m' => 'Longueur (m)', 'barcode' => 'Code-barres',
        ], $rows);
    }

    public function lookup(): void
    {
        $q = Request::string('q');
        $rows = $q === '' ? [] : $this->db->fetchAll(
            'SELECT m.id, m.code, m.name, m.stock_quantity, m.unit_cost, u.symbol FROM materials m JOIN units u ON u.id = m.unit_id
              WHERE m.deleted_at IS NULL AND m.is_active = 1 AND (m.name LIKE :a OR m.code LIKE :b OR m.barcode = :c)
              ORDER BY m.name LIMIT 20',
            ['a' => "%{$q}%", 'b' => "%{$q}%", 'c' => $q]
        );
        $showCost = can('products.view_cost');
        Response::json(['success' => true, 'items' => array_map(static fn($r) => [
            'id' => (int) $r['id'], 'code' => $r['code'], 'name' => $r['name'], 'unit' => $r['symbol'], 'cost' => $showCost ? (float) $r['unit_cost'] : null,
            'detail' => 'Stock ' . number_fr($r['stock_quantity'], 2) . ' ' . $r['symbol'],
        ], $rows)]);
    }

    public function show(int $id): void
    {
        $m = $this->db->fetch(
            'SELECT m.*, u.name AS unit_name, u.symbol, s.name AS supplier_name FROM materials m JOIN units u ON u.id = m.unit_id
               LEFT JOIN suppliers s ON s.id = m.supplier_id WHERE m.id = ? AND m.deleted_at IS NULL',
            [$id]
        );
        if (!$m) {
            $this->fail('Matière introuvable.', 'materials');
        }
        $this->view('materials/show', [
            'title' => $m['name'], 'breadcrumbs' => [['Matières', 'materials'], [$m['name'], null]], 'm' => $m,
            // Comparaison : dernier prix connu de chaque fournisseur
            'supplierPrices' => $this->db->fetchAll(
                'SELECT smp.*, s.name AS supplier_name, s.id AS sid FROM supplier_material_prices smp JOIN suppliers s ON s.id = smp.supplier_id
                  WHERE smp.material_id = ? AND s.deleted_at IS NULL
                    AND smp.id = (SELECT x.id FROM supplier_material_prices x WHERE x.material_id = smp.material_id AND x.supplier_id = smp.supplier_id ORDER BY x.valid_from DESC, x.id DESC LIMIT 1)
                  ORDER BY smp.price ASC',
                [$id]
            ),
            'products'  => $this->db->fetchAll('SELECT p.id, p.code, p.name, pm.quantity, pm.waste_percent, u.symbol FROM product_materials pm JOIN products p ON p.id = pm.product_id JOIN units u ON u.id = p.unit_id WHERE pm.material_id = ? AND p.deleted_at IS NULL', [$id]),
            'movements' => $this->db->fetchAll('SELECT sm.*, u.full_name FROM stock_movements sm LEFT JOIN users u ON u.id = sm.user_id WHERE sm.material_id = ? ORDER BY sm.id DESC LIMIT 15', [$id]),
            'consumed'  => (float) $this->db->fetchColumn("SELECT COALESCE(-SUM(quantity), 0) FROM stock_movements WHERE material_id = ? AND quantity < 0 AND movement_type IN ('consumption','production','sale') AND created_at >= ?", [$id, date('Y-m-01')]),
            'machines'  => $this->db->fetchAll('SELECT ma.id, ma.name FROM machine_consumables mc JOIN machines ma ON ma.id = mc.machine_id WHERE mc.material_id = ?', [$id]),
        ]);
    }

    public function create(): void
    {
        $this->form(null);
    }

    public function edit(int $id): void
    {
        $this->form($this->findOrFail('materials', $id, 'materials', 'Matière'));
    }

    private function form(?array $m): void
    {
        $this->view('materials/form', [
            'title' => $m ? 'Modifier : ' . $m['name'] : 'Nouvelle matière',
            'breadcrumbs' => [['Matières', 'materials'], [$m ? $m['name'] : 'Nouvelle', null]],
            'm' => $m,
            'units' => $this->db->fetchAll('SELECT id, name, symbol FROM units WHERE is_active = 1 ORDER BY sort_order'),
            'supplier' => $m && $m['supplier_id'] ? $this->db->fetch('SELECT id, code, name FROM suppliers WHERE id = ?', [$m['supplier_id']]) : null,
            'machines' => $this->db->fetchAll('SELECT id, name FROM machines WHERE deleted_at IS NULL ORDER BY name'),
            'linkedMachines' => $m ? array_map('intval', array_column($this->db->fetchAll('SELECT machine_id FROM machine_consumables WHERE material_id = ?', [$m['id']]), 'machine_id')) : [],
        ]);
    }

    public function store(): void
    {
        $data = $this->validated(null);
        $initial = can('stock.movements') ? Request::decimal('initial_stock') : 0.0;
        try {
            $id = $this->db->transaction(function () use (&$data, $initial): int {
                $data['code'] = $data['code'] ?: DocumentNumber::next('material');
                $id = $this->db->insert('materials', $data);
                $this->syncMachines($id);
                if ($initial > 0) {
                    StockService::move('material', $id, $initial, 'adjustment', (float) $data['unit_cost'], 'material', $id, 'Stock initial');
                }
                return $id;
            });
        } catch (\DomainException $e) {
            $this->fail($e->getMessage(), 'materials/create');
        }
        AuditLog::log('create', 'stock', 'material', $id, 'Matière créée : ' . $data['code'] . ' ' . $data['name'], null, $data);
        Session::flash('success', 'Matière « ' . $data['name'] . ' » créée.');
        $this->redirect('materials/' . $id);
    }

    public function update(int $id): void
    {
        $before = $this->findOrFail('materials', $id, 'materials', 'Matière');
        $data = $this->validated($id);
        if ($data['code'] === '') {
            unset($data['code']);
        }
        // Le coût moyen n'est modifiable manuellement que si le stock est nul (sinon il résulte des entrées)
        if ((float) $before['stock_quantity'] > 0 && abs((float) $data['unit_cost'] - (float) $before['unit_cost']) > 0.00005) {
            unset($data['unit_cost']);
            Session::flash('warning', 'Le coût moyen n\'a pas été modifié : il est recalculé automatiquement à chaque entrée valorisée tant qu\'il reste du stock.');
        }
        $this->db->transaction(function () use ($id, $data): void {
            $this->db->update('materials', $data, 'id = :id', ['id' => $id]);
            $this->syncMachines($id);
        });
        [$old, $new] = AuditLog::diff($before, $data);
        AuditLog::log('update', 'stock', 'material', $id, 'Matière modifiée : ' . $before['code'], $old, $new);
        Session::flash('success', 'Matière mise à jour.');
        $this->redirect('materials/' . $id);
    }

    private function validated(?int $id): array
    {
        $in = [
            'code' => mb_strtoupper(Request::string('code')), 'name' => Request::string('name'),
            'material_type' => Request::string('material_type'), 'unit_id' => Request::int('unit_id'),
            'barcode' => Request::string('barcode'), 'notes' => Request::string('notes'),
        ];
        $back = $id ? 'materials/' . $id . '/edit' : 'materials/create';
        $v = Validator::make($in, [
            'code' => 'nullable|alpha_dash|maxlen:40|unique:materials,code' . ($id ? ',' . $id : ''),
            'name' => 'required|maxlen:190', 'material_type' => 'required|in:' . implode(',', array_keys(self::TYPES)),
            'unit_id' => 'required|exists:units,id', 'barcode' => 'nullable|alpha_dash|maxlen:60', 'notes' => 'nullable|maxlen:5000',
        ], ['code' => 'Code', 'name' => 'Nom', 'material_type' => 'Type', 'unit_id' => 'Unité', 'barcode' => 'Code-barres', 'notes' => 'Notes']);
        if ($v->fails()) {
            $this->fail((string) $v->first(), $back);
        }
        $cost = Request::decimal('unit_cost');
        $width = Request::decimal('width_cm');
        $length = Request::decimal('length_m');
        if ($cost < 0 || $width < 0 || $length < 0) {
            $this->fail('Le coût et les dimensions ne peuvent pas être négatifs.', $back);
        }
        $supplier = Request::int('supplier_id');
        return $in + [
            'unit_cost'   => round($cost, 4),
            'min_stock'   => max(0, round(Request::decimal('min_stock'), 3)),
            'width_cm'    => $width > 0 ? round($width, 2) : null,
            'length_m'    => $length > 0 ? round($length, 2) : null,
            'supplier_id' => $supplier && $this->db->fetchColumn('SELECT id FROM suppliers WHERE id = ?', [$supplier]) ? $supplier : null,
            'is_active'   => Request::post('is_active') ? 1 : 0,
        ];
    }

    private function syncMachines(int $id): void
    {
        $this->db->query('DELETE FROM machine_consumables WHERE material_id = ?', [$id]);
        $valid = array_map('intval', array_column($this->db->fetchAll('SELECT id FROM machines WHERE deleted_at IS NULL'), 'id'));
        foreach (array_unique(array_map('intval', (array) ($_POST['machines'] ?? []))) as $mid) {
            if (in_array($mid, $valid, true)) {
                $this->db->insert('machine_consumables', ['machine_id' => $mid, 'material_id' => $id]);
            }
        }
    }

    public function delete(int $id): void
    {
        $m = $this->findOrFail('materials', $id, 'materials', 'Matière');
        if ((float) $m['stock_quantity'] > 0) {
            $this->fail('Cette matière a encore ' . number_fr($m['stock_quantity'], 3) . ' en stock : sortez ou ajustez le stock d\'abord.', 'materials/' . $id);
        }
        $used = (int) $this->db->fetchColumn('SELECT COUNT(*) FROM product_materials pm JOIN products p ON p.id = pm.product_id WHERE pm.material_id = ? AND p.deleted_at IS NULL', [$id]);
        if ($used) {
            $this->fail("Cette matière est utilisée dans la nomenclature de {$used} produit(s).", 'materials/' . $id);
        }
        $suffix = '-DEL' . $id;
        $this->db->update('materials', ['deleted_at' => date('Y-m-d H:i:s'), 'is_active' => 0, 'barcode' => null,
            'code' => mb_substr($m['code'], 0, 40 - strlen($suffix)) . $suffix], 'id = :id', ['id' => $id]);
        AuditLog::log('delete', 'stock', 'material', $id, 'Matière supprimée : ' . $m['code'] . ' ' . $m['name']);
        Session::flash('success', 'Matière supprimée. Son historique de mouvements est conservé.');
        $this->redirect('materials');
    }
}
