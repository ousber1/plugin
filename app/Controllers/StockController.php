<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\AuditLog;
use App\Core\Controller;
use App\Core\Csv;
use App\Core\DocumentNumber;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Session;
use App\Core\Settings;
use App\Services\StockService;

/**
 * Stock : état et valorisation, journal des mouvements, saisie manuelle
 * (entrées, sorties, pertes, ajustements) et inventaires physiques.
 * Toutes les modifications passent par StockService.
 */
final class StockController extends Controller
{
    /** Opérations manuelles proposées dans le formulaire. */
    public const OPERATIONS = [
        'purchase'        => ['Entrée — achat / réception', 1],
        'return'          => ['Entrée — retour client', 1],
        'production'      => ['Entrée — production (produit fini)', 1],
        'consumption'     => ['Sortie — consommation atelier', -1],
        'loss'            => ['Sortie — perte', -1],
        'damaged'         => ['Sortie — matière endommagée', -1],
        'supplier_return' => ['Sortie — retour fournisseur', -1],
        'adjustment'      => ['Ajustement — corriger le stock', 0],
    ];

    /* ================================================================
       État du stock
       ================================================================ */

    public function index(): void
    {
        $kind = Request::string('kind') === 'products' ? 'products' : 'materials';
        $q = Request::string('q');
        $level = Request::string('level');
        $params = [];
        if ($kind === 'products') {
            $where = ['p.deleted_at IS NULL', 'p.track_stock = 1'];
            if ($q !== '') {
                $where[] = '(p.name LIKE :a OR p.code LIKE :b OR p.barcode = :c)';
                $params = ['a' => "%{$q}%", 'b' => "%{$q}%", 'c' => $q];
            }
            if ($level === 'low') {
                $where[] = 'p.stock_quantity <= p.min_stock';
            } elseif ($level === 'out') {
                $where[] = 'p.stock_quantity <= 0';
            }
            $sqlFrom = 'FROM products p JOIN units u ON u.id = p.unit_id LEFT JOIN categories c ON c.id = p.category_id WHERE ' . implode(' AND ', $where);
            $select = "SELECT p.id, p.code, p.name, p.stock_quantity, p.min_stock, p.purchase_price AS unit_cost, u.symbol, c.name AS group_label, 'product' AS kind";
        } else {
            $where = ['m.deleted_at IS NULL'];
            if ($q !== '') {
                $where[] = '(m.name LIKE :a OR m.code LIKE :b OR m.barcode = :c)';
                $params = ['a' => "%{$q}%", 'b' => "%{$q}%", 'c' => $q];
            }
            if ($level === 'low') {
                $where[] = 'm.min_stock > 0 AND m.stock_quantity <= m.min_stock';
            } elseif ($level === 'out') {
                $where[] = 'm.stock_quantity <= 0';
            }
            $sqlFrom = 'FROM materials m JOIN units u ON u.id = m.unit_id WHERE ' . implode(' AND ', $where);
            $select = "SELECT m.id, m.code, m.name, m.stock_quantity, m.min_stock, m.unit_cost, u.symbol, m.material_type AS group_label, 'material' AS kind";
        }
        $p = Paginator::fromRequest((int) $this->db->fetchColumn("SELECT COUNT(*) {$sqlFrom}", $params), 50);
        $rows = $this->db->fetchAll("{$select} {$sqlFrom} ORDER BY name LIMIT {$p->limit()} OFFSET {$p->offset()}", $params);

        $this->view('stock/index', [
            'title' => 'État du stock', 'kind' => $kind, 'q' => $q, 'level' => $level, 'rows' => $rows, 'p' => $p,
            'valuation' => StockService::valuation(),
            'alerts' => $this->db->fetch(
                "SELECT (SELECT COUNT(*) FROM products WHERE deleted_at IS NULL AND track_stock = 1 AND stock_quantity <= min_stock) AS products_low,
                        (SELECT COUNT(*) FROM materials WHERE deleted_at IS NULL AND min_stock > 0 AND stock_quantity <= min_stock) AS materials_low,
                        (SELECT COUNT(*) FROM products WHERE deleted_at IS NULL AND track_stock = 1) AS products_count,
                        (SELECT COUNT(*) FROM materials WHERE deleted_at IS NULL) AS materials_count"
            ),
            'materialTypes' => MaterialController::TYPES,
        ]);
    }

    /* ================================================================
       Journal des mouvements
       ================================================================ */

    private function movementFilters(): array
    {
        return [
            'product'  => Request::int('product'),
            'material' => Request::int('material'),
            'type'     => isset(StockService::TYPES[Request::string('type')]) ? Request::string('type') : '',
            'from'     => preg_match('/^\d{4}-\d{2}-\d{2}$/', Request::string('from')) ? Request::string('from') : '',
            'to'       => preg_match('/^\d{4}-\d{2}-\d{2}$/', Request::string('to')) ? Request::string('to') : '',
            'kind'     => in_array(Request::string('kind'), ['product', 'material'], true) ? Request::string('kind') : '',
        ];
    }

    private function movementWhere(array $f): array
    {
        $where = ['1 = 1'];
        $params = [];
        if ($f['product'] > 0) {
            $where[] = 'sm.product_id = :pid';
            $params['pid'] = $f['product'];
        }
        if ($f['material'] > 0) {
            $where[] = 'sm.material_id = :mid';
            $params['mid'] = $f['material'];
        }
        if ($f['kind'] === 'product') {
            $where[] = 'sm.product_id IS NOT NULL';
        } elseif ($f['kind'] === 'material') {
            $where[] = 'sm.material_id IS NOT NULL';
        }
        if ($f['type'] !== '') {
            $where[] = 'sm.movement_type = :type';
            $params['type'] = $f['type'];
        }
        if ($f['from'] !== '') {
            $where[] = 'sm.created_at >= :from';
            $params['from'] = $f['from'] . ' 00:00:00';
        }
        if ($f['to'] !== '') {
            $where[] = 'sm.created_at <= :to';
            $params['to'] = $f['to'] . ' 23:59:59';
        }
        return [implode(' AND ', $where), $params];
    }

    private const MOVEMENTS_FROM = 'FROM stock_movements sm LEFT JOIN products p ON p.id = sm.product_id LEFT JOIN materials m ON m.id = sm.material_id LEFT JOIN users u ON u.id = sm.user_id';

    public function movements(): void
    {
        $f = $this->movementFilters();
        [$where, $params] = $this->movementWhere($f);
        $p = Paginator::fromRequest((int) $this->db->fetchColumn('SELECT COUNT(*) ' . self::MOVEMENTS_FROM . " WHERE {$where}", $params), 50);
        $item = null;
        if ($f['product']) {
            $item = $this->db->fetch('SELECT name FROM products WHERE id = ?', [$f['product']]);
        } elseif ($f['material']) {
            $item = $this->db->fetch('SELECT name FROM materials WHERE id = ?', [$f['material']]);
        }
        $this->view('stock/movements', [
            'title' => 'Mouvements de stock', 'f' => $f, 'p' => $p, 'itemName' => $item['name'] ?? null,
            'movements' => $this->db->fetchAll(
                'SELECT sm.*, COALESCE(p.name, m.name) AS item_name, u.full_name ' . self::MOVEMENTS_FROM . " WHERE {$where} ORDER BY sm.id DESC LIMIT {$p->limit()} OFFSET {$p->offset()}",
                $params
            ),
        ]);
    }

    public function exportMovements(): void
    {
        [$where, $params] = $this->movementWhere($this->movementFilters());
        $rows = $this->db->fetchAll(
            'SELECT sm.*, COALESCE(p.code, m.code) AS item_code, COALESCE(p.name, m.name) AS item_name, IF(sm.product_id IS NULL, "Matière", "Produit") AS item_kind, u.full_name '
            . self::MOVEMENTS_FROM . " WHERE {$where} ORDER BY sm.id DESC LIMIT 20000",
            $params
        );
        $showCost = can('products.view_cost');
        foreach ($rows as &$r) {
            $r['type_label'] = StockService::label($r['movement_type']);
            $r['quantity'] = (float) $r['quantity'];
            $r['balance_after'] = (float) $r['balance_after'];
            $r['unit_cost'] = $showCost ? (float) $r['unit_cost'] : '';
        }
        unset($r);
        Csv::download('mouvements-stock-' . date('Y-m-d') . '.csv', [
            'created_at' => 'Date', 'item_kind' => 'Nature', 'item_code' => 'Code', 'item_name' => 'Article', 'type_label' => 'Mouvement',
            'quantity' => 'Quantité', 'unit_cost' => 'Coût unitaire', 'balance_after' => 'Solde', 'reason' => 'Motif', 'full_name' => 'Utilisateur',
        ], $rows);
    }

    /* ================================================================
       Saisie d'un mouvement
       ================================================================ */

    public function createMovement(): void
    {
        $item = null;
        if (Request::int('product')) {
            $item = $this->db->fetch("SELECT p.id, p.code, p.name, p.stock_quantity, p.purchase_price AS unit_cost, u.symbol, 'product' AS kind FROM products p JOIN units u ON u.id = p.unit_id WHERE p.id = ? AND p.deleted_at IS NULL AND p.track_stock = 1", [Request::int('product')]);
        } elseif (Request::int('material')) {
            $item = $this->db->fetch("SELECT m.id, m.code, m.name, m.stock_quantity, m.unit_cost, u.symbol, 'material' AS kind FROM materials m JOIN units u ON u.id = m.unit_id WHERE m.id = ? AND m.deleted_at IS NULL", [Request::int('material')]);
        }
        $this->view('stock/movement_form', [
            'title' => 'Mouvement de stock', 'breadcrumbs' => [['Stock', 'stock'], ['Nouveau mouvement', null]],
            'item' => $item, 'operations' => self::OPERATIONS,
        ]);
    }

    public function storeMovement(): void
    {
        $kind = Request::string('kind') === 'product' ? 'product' : 'material';
        $itemId = Request::int('item_id');
        $op = Request::string('operation');
        $back = ['stock/movements/create', [$kind => $itemId]];
        if (!isset(self::OPERATIONS[$op])) {
            $this->fail('Opération invalide.', ...$back);
        }
        if ($op === 'adjustment' && !can('stock.adjust')) {
            $this->fail('Vous n\'êtes pas autorisé à ajuster le stock.', ...$back);
        }
        $table = $kind === 'product' ? 'products' : 'materials';
        $item = $this->db->fetch("SELECT id, name, stock_quantity FROM {$table} WHERE id = ? AND deleted_at IS NULL", [$itemId]);
        if (!$item) {
            $this->fail('Choisissez un article.', 'stock/movements/create');
        }
        $reason = mb_substr(Request::string('reason'), 0, 255);
        $unitCost = null;

        if ($op === 'adjustment') {
            $target = Request::decimal('new_quantity', -1);
            if ($target < 0) {
                $this->fail('Indiquez la quantité réelle constatée (0 ou plus).', ...$back);
            }
            $qty = round($target - (float) $item['stock_quantity'], 3);
            if ($qty == 0.0) {
                $this->fail('Le stock est déjà de ' . number_fr($target, 3) . ' : aucun ajustement nécessaire.', ...$back);
            }
            if ($reason === '') {
                $this->fail('Un motif est obligatoire pour un ajustement.', ...$back);
            }
        } else {
            $qty = round(Request::decimal('quantity'), 3);
            if ($qty <= 0) {
                $this->fail('La quantité doit être supérieure à zéro.', ...$back);
            }
            $qty *= self::OPERATIONS[$op][1];
            if ($op === 'purchase') {
                $cost = Request::input('unit_cost');
                $unitCost = $cost === null || trim((string) $cost) === '' ? null : Request::decimal('unit_cost');
                if ($unitCost !== null && $unitCost < 0) {
                    $this->fail('Coût unitaire invalide.', ...$back);
                }
            }
            if (in_array($op, ['loss', 'damaged'], true) && $reason === '') {
                $this->fail('Indiquez le motif de la perte.', ...$back);
            }
        }
        $supplierId = Request::int('supplier_id');
        if ($supplierId && in_array($op, ['purchase', 'supplier_return'], true)) {
            $supplier = $this->db->fetch('SELECT name FROM suppliers WHERE id = ?', [$supplierId]);
            if ($supplier) {
                $reason = trim('Fournisseur : ' . $supplier['name'] . ($reason !== '' ? ' — ' . $reason : ''));
            }
        }
        try {
            // Les mouvements manuels ne créent jamais de stock négatif, quel que soit le paramètre du POS
            $movementId = StockService::move($kind, $itemId, $qty, $op, $unitCost, $supplierId ? 'supplier' : 'manual', $supplierId ?: null, $reason ?: null, null, false);
        } catch (\DomainException $e) {
            $this->fail($e->getMessage(), ...$back);
        }
        AuditLog::log('stock_' . $op, 'stock', 'stock_movement', $movementId, StockService::label($op) . ' : ' . number_fr($qty, 3) . ' × ' . $item['name']);
        Session::flash('success', 'Mouvement enregistré : ' . StockService::label($op) . ' de ' . number_fr(abs($qty), 3) . ' — ' . $item['name'] . '.');
        if (Request::post('redirect') === 'new') {
            $this->redirect('stock/movements/create');
        }
        $this->redirect(($kind === 'product' ? 'products/' : 'materials/') . $itemId);
    }

    /* ================================================================
       Inventaires physiques
       ================================================================ */

    public function inventories(): void
    {
        $this->view('stock/inventories', [
            'title' => 'Inventaires',
            'counts' => $this->db->fetchAll(
                'SELECT ic.*, u.full_name, v.full_name AS validator, l.name AS location_name,
                        (SELECT COUNT(*) FROM inventory_count_items i WHERE i.inventory_count_id = ic.id) AS items,
                        (SELECT COUNT(*) FROM inventory_count_items i WHERE i.inventory_count_id = ic.id AND i.counted_quantity IS NOT NULL) AS counted,
                        (SELECT COALESCE(SUM(i.difference * i.unit_cost), 0) FROM inventory_count_items i WHERE i.inventory_count_id = ic.id) AS value_diff
                   FROM inventory_counts ic LEFT JOIN users u ON u.id = ic.user_id LEFT JOIN users v ON v.id = ic.validated_by
                   JOIN stock_locations l ON l.id = ic.location_id ORDER BY ic.id DESC LIMIT 50'
            ),
            'materialTypes' => MaterialController::TYPES,
        ]);
    }

    /** Ouvre un inventaire : photographie du stock théorique des articles choisis. */
    public function startInventory(): void
    {
        $scope = Request::string('scope');
        $materialType = Request::string('material_type');
        if (!in_array($scope, ['materials', 'products', 'all'], true)) {
            $this->fail('Périmètre d\'inventaire invalide.', 'stock/inventories');
        }
        $open = $this->db->fetchColumn("SELECT number FROM inventory_counts WHERE status = 'draft' LIMIT 1");
        if ($open) {
            $this->fail("L'inventaire {$open} est encore en cours : validez-le ou annulez-le d'abord.", 'stock/inventories');
        }
        $locationId = (int) Settings::get('default_location_id', 1);
        $id = $this->db->transaction(function () use ($scope, $materialType, $locationId): int {
            $id = $this->db->insert('inventory_counts', [
                'number' => DocumentNumber::next('inventory'), 'location_id' => $locationId, 'status' => 'draft',
                'count_date' => date('Y-m-d'), 'notes' => mb_substr(Request::string('notes'), 0, 500) ?: null, 'user_id' => auth_user()['id'],
            ]);
            if ($scope !== 'materials') {
                $this->db->query(
                    'INSERT INTO inventory_count_items (inventory_count_id, product_id, system_quantity, unit_cost)
                     SELECT ?, p.id, COALESCE(sl.quantity, 0), p.purchase_price FROM products p
                       LEFT JOIN stock_levels sl ON sl.product_id = p.id AND sl.location_id = ?
                      WHERE p.deleted_at IS NULL AND p.track_stock = 1',
                    [$id, $locationId]
                );
            }
            if ($scope !== 'products') {
                $typeFilter = isset(MaterialController::TYPES[$materialType]) ? ' AND m.material_type = ?' : '';
                $params = [$id, $locationId];
                if ($typeFilter) {
                    $params[] = $materialType;
                }
                $this->db->query(
                    'INSERT INTO inventory_count_items (inventory_count_id, material_id, system_quantity, unit_cost)
                     SELECT ?, m.id, COALESCE(sl.quantity, 0), m.unit_cost FROM materials m
                       LEFT JOIN stock_levels sl ON sl.material_id = m.id AND sl.location_id = ?
                      WHERE m.deleted_at IS NULL AND m.is_active = 1' . $typeFilter,
                    $params
                );
            }
            return $id;
        });
        if ((int) $this->db->fetchColumn('SELECT COUNT(*) FROM inventory_count_items WHERE inventory_count_id = ?', [$id]) === 0) {
            $this->db->query('DELETE FROM inventory_counts WHERE id = ?', [$id]);
            $this->fail('Aucun article à inventorier dans ce périmètre.', 'stock/inventories');
        }
        AuditLog::log('create', 'stock', 'inventory', $id, 'Inventaire ouvert');
        Session::flash('success', 'Inventaire ouvert. Saisissez les quantités comptées puis validez.');
        $this->redirect('stock/inventories/' . $id);
    }

    public function showInventory(int $id): void
    {
        $count = $this->db->fetch(
            'SELECT ic.*, u.full_name, v.full_name AS validator FROM inventory_counts ic LEFT JOIN users u ON u.id = ic.user_id LEFT JOIN users v ON v.id = ic.validated_by WHERE ic.id = ?',
            [$id]
        );
        if (!$count) {
            $this->fail('Inventaire introuvable.', 'stock/inventories');
        }
        $this->view('stock/inventory', [
            'title' => 'Inventaire ' . $count['number'],
            'breadcrumbs' => [['Inventaires', 'stock/inventories'], [$count['number'], null]],
            'count' => $count,
            'items' => $this->db->fetchAll(
                "SELECT i.*, COALESCE(p.code, m.code) AS code, COALESCE(p.name, m.name) AS name, u.symbol,
                        IF(i.product_id IS NULL, 'Matière', 'Produit') AS kind,
                        COALESCE(sl.quantity, 0) AS current_quantity
                   FROM inventory_count_items i
                   LEFT JOIN products p ON p.id = i.product_id LEFT JOIN materials m ON m.id = i.material_id
                   JOIN units u ON u.id = COALESCE(p.unit_id, m.unit_id)
                   LEFT JOIN stock_levels sl ON sl.location_id = ? AND ((i.product_id IS NOT NULL AND sl.product_id = i.product_id) OR (i.material_id IS NOT NULL AND sl.material_id = i.material_id))
                  WHERE i.inventory_count_id = ? ORDER BY kind DESC, name",
                [$count['location_id'], $id]
            ),
        ]);
    }

    /** Enregistre les quantités comptées (brouillon). */
    public function saveInventory(int $id): void
    {
        $count = $this->draftInventory($id);
        $counted = (array) ($_POST['counted'] ?? []);
        $this->db->transaction(function () use ($id, $counted): void {
            foreach ($this->db->fetchAll('SELECT id, system_quantity FROM inventory_count_items WHERE inventory_count_id = ?', [$id]) as $it) {
                $raw = $counted[$it['id']] ?? null;
                $value = is_string($raw) ? Csv::number($raw) : null;
                if ($value !== null && $value < 0) {
                    $value = null;
                }
                $this->db->update('inventory_count_items', [
                    'counted_quantity' => $value,
                    'difference'       => $value === null ? null : round($value - (float) $it['system_quantity'], 3),
                ], 'id = :id', ['id' => $it['id']]);
            }
        });
        if (Request::post('action') === 'validate') {
            $this->validateInventory($id, $count);
        }
        Session::flash('success', 'Comptage enregistré.');
        $this->redirect('stock/inventories/' . $id);
    }

    /**
     * Validation : pour chaque article compté, un mouvement « inventaire » applique
     * l'écart (quantité comptée − stock théorique au moment de l'ouverture). On applique
     * un écart et non une valeur absolue : les ventes ou consommations enregistrées
     * pendant le comptage restent donc correctement prises en compte.
     */
    private function validateInventory(int $id, array $count): never
    {
        if (!can('stock.inventory')) {
            $this->fail('Vous n\'êtes pas autorisé à valider un inventaire.', 'stock/inventories/' . $id);
        }
        $items = $this->db->fetchAll('SELECT * FROM inventory_count_items WHERE inventory_count_id = ? AND counted_quantity IS NOT NULL', [$id]);
        if (!$items) {
            $this->fail('Aucune quantité comptée : rien à valider.', 'stock/inventories/' . $id);
        }
        $adjusted = 0;
        try {
            $this->db->transaction(function () use ($id, $count, $items, &$adjusted): void {
                $locked = $this->db->fetchColumn('SELECT status FROM inventory_counts WHERE id = ? FOR UPDATE', [$id]);
                if ($locked !== 'draft') {
                    throw new \DomainException('Cet inventaire a déjà été traité.');
                }
                foreach ($items as $it) {
                    $diff = round((float) $it['difference'], 3);
                    if ($diff == 0.0) {
                        continue;
                    }
                    $kind = $it['product_id'] ? 'product' : 'material';
                    StockService::move($kind, (int) ($it['product_id'] ?: $it['material_id']), $diff, 'inventory', null,
                        'inventory', $id, 'Inventaire ' . $count['number'], (int) $count['location_id'], true);
                    $adjusted++;
                }
                $this->db->update('inventory_counts', ['status' => 'validated', 'validated_by' => auth_user()['id'], 'validated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $id]);
            });
        } catch (\DomainException $e) {
            $this->fail($e->getMessage(), 'stock/inventories/' . $id);
        }
        AuditLog::log('validate', 'stock', 'inventory', $id, 'Inventaire ' . $count['number'] . ' validé : ' . $adjusted . ' ajustement(s)');
        Session::flash('success', 'Inventaire validé : ' . $adjusted . ' article(s) ajusté(s).');
        $this->redirect('stock/inventories/' . $id);
    }

    public function cancelInventory(int $id): void
    {
        $count = $this->draftInventory($id);
        $this->db->update('inventory_counts', ['status' => 'cancelled'], 'id = :id', ['id' => $id]);
        AuditLog::log('cancel', 'stock', 'inventory', $id, 'Inventaire ' . $count['number'] . ' annulé');
        Session::flash('success', 'Inventaire annulé. Aucun stock n\'a été modifié.');
        $this->redirect('stock/inventories');
    }

    private function draftInventory(int $id): array
    {
        $count = $this->db->fetch('SELECT * FROM inventory_counts WHERE id = ?', [$id]);
        if (!$count || $count['status'] !== 'draft') {
            $this->fail('Inventaire introuvable ou déjà clôturé.', 'stock/inventories');
        }
        return $count;
    }
}
