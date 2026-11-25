<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;
use App\Services\CashService;
use App\Services\CustomerService;
use App\Services\PricingService;
use App\Services\SaleService;

/**
 * Écran du point de vente et son API JSON (catalogue, prix client, encaissement,
 * ventes en attente). Toutes les écritures passent par SaleService.
 */
final class PosController extends Controller
{
    public function index(): void
    {
        $walkInId = (int) Settings::get('walk_in_customer_id', 1);
        $walkIn = $this->db->fetch('SELECT id, code, full_name, company_name FROM customers WHERE id = ?', [$walkInId]);
        $this->view('pos/index', [
            'title'      => 'Point de vente',
            'session'    => CashService::openSession(),
            'categories' => $this->db->fetchAll(
                'SELECT c.id, c.name, c.color, c.icon FROM categories c
                  WHERE c.deleted_at IS NULL AND c.is_active = 1
                    AND EXISTS (SELECT 1 FROM products p LEFT JOIN categories pc ON pc.id = p.category_id
                                 WHERE p.deleted_at IS NULL AND p.is_active = 1 AND (p.category_id = c.id OR pc.parent_id = c.id))
                  ORDER BY c.sort_order, c.name'
            ),
            'hasPacks'   => (bool) $this->db->fetchColumn('SELECT COUNT(*) FROM packs WHERE deleted_at IS NULL AND is_active = 1'),
            'methods'    => $this->db->fetchAll('SELECT id, code, name, affects_cash, requires_reference, is_credit FROM payment_methods WHERE is_active = 1 ORDER BY sort_order'),
            'taxes'      => $this->db->fetchAll('SELECT id, name, rate, is_default FROM taxes WHERE is_active = 1 ORDER BY rate DESC'),
            'units'      => $this->db->fetchAll('SELECT id, name, symbol FROM units WHERE is_active = 1 AND is_area = 0 ORDER BY sort_order'),
            'walkIn'     => ['id' => $walkInId, 'name' => $walkIn ? CustomerService::displayName($walkIn) : 'Client comptoir', 'code' => $walkIn['code'] ?? ''],
            'config'     => [
                'currency'       => (string) Settings::get('currency_symbol', 'DH'),
                'maxDiscount'    => (float) Settings::get('max_discount_percent', 20),
                'receiptWidth'   => (int) Settings::get('receipt_width', 80),
                'autoPrint'      => (bool) Settings::get('auto_print_receipt', false),
                'requireSession' => (bool) Settings::get('require_cash_session', true),
                'taxEnabled'     => (bool) Settings::get('tax_enabled', true),
                'defaultTax'     => (float) ($this->db->fetchColumn('SELECT rate FROM taxes WHERE id = ?', [(int) Settings::get('default_tax_id', 1)]) ?? 20),
            ],
            'perms' => [
                'discount'   => can('pos.discount'),
                'override'   => can('pos.price_override'),
                'belowMin'   => can('pos.below_min_price'),
                'credit'     => can('pos.credit_sale'),
                'newProduct' => can('products.create'),
                'openCash'   => can('cash.open'),
                'newCustomer'=> can('customers.create'),
            ],
        ], 'pos');
    }

    /** Catalogue pour la grille du POS (avec prix du client sélectionné). */
    public function products(): void
    {
        $q = Request::string('q');
        $category = Request::string('category');
        $customerId = Request::int('customer_id') ?: (int) Settings::get('walk_in_customer_id', 1);
        $items = [];
        $exact = null;

        if ($category !== 'packs') {
            $where = ['p.deleted_at IS NULL', 'p.is_active = 1'];
            $params = [];
            if ($q !== '') {
                $where[] = '(p.name LIKE :a OR p.code LIKE :b OR p.barcode = :c OR p.name_fr LIKE :d)';
                $params += ['a' => "%{$q}%", 'b' => "%{$q}%", 'c' => $q, 'd' => "%{$q}%"];
            }
            if ($category === 'quick') {
                $where[] = 'p.is_quick = 1';
            } elseif (ctype_digit($category) && (int) $category > 0) {
                $where[] = '(p.category_id = :cat OR c.parent_id = :cat2)';
                $params += ['cat' => (int) $category, 'cat2' => (int) $category];
            }
            $rows = $this->db->fetchAll(
                'SELECT p.id, p.code, p.barcode, p.name, p.image, p.track_stock, p.stock_quantity, p.product_type,
                        u.symbol, u.is_area, u.allows_decimal, c.color
                   FROM products p JOIN units u ON u.id = p.unit_id LEFT JOIN categories c ON c.id = p.category_id
                  WHERE ' . implode(' AND ', $where) . '
                  ORDER BY ' . ($q !== '' ? '(p.barcode = :e) DESC, (p.code = :f) DESC, ' : '') . 'p.is_quick DESC, p.name LIMIT 60',
                $q !== '' ? $params + ['e' => $q, 'f' => $q] : $params
            );
            $prices = PricingService::forProducts(array_column($rows, 'id'), $customerId);
            foreach ($rows as $r) {
                $pr = $prices[(int) $r['id']] ?? null;
                if (!$pr) {
                    continue;
                }
                $item = [
                    'type' => 'product', 'id' => (int) $r['id'], 'code' => $r['code'], 'name' => $r['name'],
                    'image' => $r['image'] ? asset($r['image']) : null, 'color' => $r['color'],
                    'unit' => $r['symbol'], 'is_area' => (bool) $r['is_area'], 'decimal' => (bool) $r['allows_decimal'],
                    'track_stock' => (bool) $r['track_stock'], 'stock' => (float) $r['stock_quantity'],
                    'price' => $pr['price'], 'min_price' => $pr['min_price'], 'discount' => $pr['discount_percent'],
                    'tax_rate' => $pr['tax_rate'], 'allow_override' => $pr['allow_override'], 'source' => $pr['source'],
                ];
                if ($q !== '' && ($r['barcode'] === $q || strcasecmp($r['code'], $q) === 0)) {
                    $exact = $item;
                }
                $items[] = $item;
            }
        }
        if ($category === 'packs' || ($q !== '' && $category === '')) {
            $params = [];
            $where = 'pk.deleted_at IS NULL AND pk.is_active = 1 AND (pk.available_from IS NULL OR pk.available_from <= CURDATE()) AND (pk.available_until IS NULL OR pk.available_until >= CURDATE())';
            if ($q !== '') {
                $where .= ' AND (pk.name LIKE :a OR pk.code LIKE :b)';
                $params = ['a' => "%{$q}%", 'b' => "%{$q}%"];
            }
            foreach ($this->db->fetchAll("SELECT pk.id, pk.code, pk.name, pk.image FROM packs pk WHERE {$where} ORDER BY pk.name LIMIT 30", $params) as $r) {
                $pr = PricingService::forPack((int) $r['id']);
                if ($pr) {
                    $items[] = ['type' => 'pack', 'id' => (int) $r['id'], 'code' => $r['code'], 'name' => $r['name'],
                        'image' => $r['image'] ? asset($r['image']) : null, 'color' => '#111111', 'unit' => 'pack', 'is_area' => false,
                        'decimal' => false, 'track_stock' => false, 'stock' => 0, 'price' => $pr['price'], 'min_price' => 0,
                        'discount' => 0, 'tax_rate' => $pr['tax_rate'], 'allow_override' => true, 'source' => 'pack'];
                }
            }
        }
        Response::json(['success' => true, 'items' => $items, 'exact' => $exact]);
    }

    /** Prix des produits du panier pour un client (changement de client). */
    public function prices(): void
    {
        $data = Request::json();
        $customerId = (int) ($data['customer_id'] ?? 0) ?: (int) Settings::get('walk_in_customer_id', 1);
        $prices = PricingService::forProducts((array) ($data['product_ids'] ?? []), $customerId);
        Response::json(['success' => true, 'prices' => array_map(static fn($p) => [
            'price' => $p['price'], 'discount' => $p['discount_percent'], 'min_price' => $p['min_price'],
            'tax_rate' => $p['tax_rate'], 'source' => $p['source'], 'allow_override' => $p['allow_override'],
        ], $prices)]);
    }

    /** Informations commerciales du client sélectionné. */
    public function customer(int $id): void
    {
        $c = $this->db->fetch(
            'SELECT c.id, c.code, c.full_name, c.company_name, c.phone, c.is_walk_in, c.credit_limit, c.loyalty_points,
                    g.name AS group_name, g.discount_percent, pl.name AS price_list
               FROM customers c LEFT JOIN customer_groups g ON g.id = c.group_id
               LEFT JOIN price_lists pl ON pl.id = COALESCE(c.price_list_id, g.price_list_id, 1)
              WHERE c.id = ? AND c.deleted_at IS NULL AND c.is_active = 1',
            [$id]
        );
        if (!$c) {
            Response::json(['success' => false, 'message' => 'Client introuvable.'], 404);
        }
        $balance = CustomerService::balance($id);
        $limit = (float) $c['credit_limit'];
        Response::json(['success' => true, 'customer' => [
            'id' => (int) $c['id'], 'code' => $c['code'], 'name' => CustomerService::displayName($c), 'phone' => $c['phone'],
            'walk_in' => (bool) $c['is_walk_in'], 'group' => $c['group_name'], 'group_discount' => (float) ($c['discount_percent'] ?? 0),
            'price_list' => $c['price_list'] ?? 'Détail', 'balance' => $balance, 'credit_limit' => $limit,
            'credit_available' => $limit > 0 ? max(0, $limit - max(0, $balance)) : 0, 'points' => (int) $c['loyalty_points'],
        ]]);
    }

    public function checkout(): void
    {
        try {
            $r = SaleService::checkout(Request::json());
        } catch (\DomainException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 422);
        }
        Response::json(['success' => true, 'sale' => $r + [
            'receipt_url' => url('sales/' . $r['id'] . '/receipt'),
            'a4_url'      => url('sales/' . $r['id'] . '/print'),
            'show_url'    => url('sales/' . $r['id']),
        ]]);
    }

    public function suspend(): void
    {
        try {
            $id = SaleService::suspend(Request::json());
        } catch (\DomainException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 422);
        }
        Response::json(['success' => true, 'id' => $id]);
    }

    /** Ventes en attente (tous les postes). */
    public function drafts(): void
    {
        $rows = $this->db->fetchAll(
            "SELECT s.id, s.suspend_label, s.total, s.sale_date, s.customer_id, u.full_name AS user_name,
                    COALESCE(NULLIF(c.company_name, ''), c.full_name) AS customer_name,
                    (SELECT COUNT(*) FROM sale_items si WHERE si.sale_id = s.id) AS line_count
               FROM sales s JOIN customers c ON c.id = s.customer_id LEFT JOIN users u ON u.id = s.user_id
              WHERE s.status = 'suspended' ORDER BY s.sale_date DESC LIMIT 50"
        );
        Response::json(['success' => true, 'drafts' => array_map(static fn($r) => [
            'id' => (int) $r['id'], 'label' => $r['suspend_label'], 'total' => (float) $r['total'], 'date' => format_date($r['sale_date'], true),
            'customer' => $r['customer_name'], 'user' => $r['user_name'], 'lines' => (int) $r['line_count'],
        ], $rows)]);
    }

    /** Recharge une vente en attente dans le panier (elle sera supprimée à l'encaissement). */
    public function resume(int $id): void
    {
        $sale = $this->db->fetch("SELECT * FROM sales WHERE id = ? AND status = 'suspended'", [$id]);
        if (!$sale) {
            Response::json(['success' => false, 'message' => 'Vente en attente introuvable.'], 404);
        }
        $items = [];
        foreach ($this->db->fetchAll(
            'SELECT si.*, p.name AS product_name, p.code AS product_code, u.symbol, u.is_area, u.allows_decimal, p.min_selling_price, p.allow_price_override,
                    pk.name AS pack_name, pk.code AS pack_code
               FROM sale_items si LEFT JOIN products p ON p.id = si.product_id LEFT JOIN units u ON u.id = COALESCE(p.unit_id, si.unit_id)
               LEFT JOIN packs pk ON pk.id = si.pack_id WHERE si.sale_id = ? ORDER BY si.sort_order',
            [$id]
        ) as $r) {
            $type = $r['product_id'] ? 'product' : ($r['pack_id'] ? 'pack' : 'custom');
            $items[] = [
                'type' => $type, 'product_id' => $r['product_id'] ? (int) $r['product_id'] : null, 'pack_id' => $r['pack_id'] ? (int) $r['pack_id'] : null,
                'name' => $type === 'product' ? $r['product_name'] : ($type === 'pack' ? $r['pack_name'] : $r['description']),
                'code' => $r['product_code'] ?? $r['pack_code'] ?? '',
                // Seuls les articles libres gardent leur désignation ; les autres reprennent celle du catalogue
                'description' => $type === 'custom' ? $r['description'] : null,
                'quantity' => (float) $r['quantity'], 'width' => $r['width'] !== null ? (float) $r['width'] : null, 'height' => $r['height'] !== null ? (float) $r['height'] : null,
                'unit_price' => (float) $r['unit_price'], 'catalog_price' => (float) $r['original_price'], 'discount_percent' => (float) $r['discount_percent'],
                'tax_rate' => (float) $r['tax_rate'], 'unit' => $r['symbol'] ?? 'u', 'unit_id' => $r['unit_id'] ? (int) $r['unit_id'] : null,
                'is_area' => (bool) ($r['is_area'] ?? false), 'decimal' => (bool) ($r['allows_decimal'] ?? true), 'notes' => $r['notes'],
                'allow_override' => $type !== 'product' || (bool) $r['allow_price_override'],
            ];
        }
        Response::json(['success' => true, 'draft' => [
            'id' => (int) $sale['id'], 'customer_id' => (int) $sale['customer_id'], 'label' => $sale['suspend_label'], 'notes' => $sale['notes'],
            'discount_type' => $sale['discount_type'], 'discount_value' => (float) $sale['discount_value'], 'items' => $items,
        ]]);
    }

    public function deleteDraft(int $id): void
    {
        $n = $this->db->query("DELETE FROM sales WHERE id = ? AND status = 'suspended'", [$id])->rowCount();
        if ($n) {
            \App\Core\AuditLog::log('delete', 'pos', 'sale', $id, 'Vente en attente supprimée');
        }
        Response::json(['success' => (bool) $n]);
    }
}
