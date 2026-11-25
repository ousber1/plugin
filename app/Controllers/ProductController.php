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
use App\Core\Settings;
use App\Core\Uploader;
use App\Core\Validator;
use App\Services\StockService;

/**
 * Catalogue : produits et services (impression, design, pose, marketing…),
 * tarifs par liste de prix, nomenclature matières, image, stock initial,
 * duplication, import / export CSV, recherche JSON.
 */
final class ProductController extends Controller
{
    public const TYPES = [
        'print_job'     => 'Travail d\'impression',
        'product'       => 'Produit (revente)',
        'finished_good' => 'Produit fini / personnalisé',
        'service'       => 'Service',
        'design'        => 'Design graphique',
        'installation'  => 'Installation / pose',
        'digital'       => 'Marketing digital',
    ];
    public const SERVICE_TYPES = ['service', 'design', 'installation', 'digital'];

    private const SORTS = ['name' => 'p.name', 'code' => 'p.code', 'price' => 'p.selling_price', 'stock' => 'p.stock_quantity', 'category' => 'c.name', 'created' => 'p.created_at'];

    /* ================================================================
       Liste
       ================================================================ */

    private function filters(): array
    {
        return [
            'q'        => Request::string('q'),
            'kind'     => in_array(Request::string('kind'), ['products', 'services'], true) ? Request::string('kind') : '',
            'type'     => isset(self::TYPES[Request::string('type')]) ? Request::string('type') : '',
            'category' => Request::int('category'),
            'status'   => Request::string('status', 'active'),
            'stock'    => Request::string('stock'),
            'sort'     => array_key_exists(Request::string('sort'), self::SORTS) ? Request::string('sort') : 'name',
            'dir'      => Request::string('dir') === 'desc' ? 'DESC' : 'ASC',
        ];
    }

    private function where(array $f): array
    {
        $where = ['p.deleted_at IS NULL'];
        $params = [];
        if ($f['q'] !== '') {
            $or = [];
            foreach (['p.name', 'p.name_fr', 'p.code', 'p.barcode', 'p.description'] as $i => $col) {
                $or[] = "{$col} LIKE :q{$i}";
                $params['q' . $i] = '%' . $f['q'] . '%';
            }
            $where[] = '(' . implode(' OR ', $or) . ')';
        }
        $services = "('" . implode("','", self::SERVICE_TYPES) . "')";
        if ($f['kind'] === 'services') {
            $where[] = "p.product_type IN {$services}";
        } elseif ($f['kind'] === 'products') {
            $where[] = "p.product_type NOT IN {$services}";
        }
        if ($f['type'] !== '') {
            $where[] = 'p.product_type = :type';
            $params['type'] = $f['type'];
        }
        if ($f['category'] > 0) {
            $where[] = '(p.category_id = :cat OR c.parent_id = :cat2)';
            $params['cat'] = $f['category'];
            $params['cat2'] = $f['category'];
        }
        if ($f['status'] === 'active') {
            $where[] = 'p.is_active = 1';
        } elseif ($f['status'] === 'inactive') {
            $where[] = 'p.is_active = 0';
        }
        if ($f['stock'] === 'low') {
            $where[] = 'p.track_stock = 1 AND p.stock_quantity <= p.min_stock';
        } elseif ($f['stock'] === 'out') {
            $where[] = 'p.track_stock = 1 AND p.stock_quantity <= 0';
        }
        return [implode(' AND ', $where), $params];
    }

    public function index(): void
    {
        $f = $this->filters();
        [$where, $params] = $this->where($f);
        $from = 'FROM products p LEFT JOIN categories c ON c.id = p.category_id JOIN units u ON u.id = p.unit_id LEFT JOIN taxes t ON t.id = p.tax_id';
        $p = Paginator::fromRequest((int) $this->db->fetchColumn("SELECT COUNT(*) {$from} WHERE {$where}", $params));
        $products = $this->db->fetchAll(
            "SELECT p.*, c.name AS category_name, c.color AS category_color, u.symbol AS unit_symbol, t.rate AS tax_rate
               {$from} WHERE {$where} ORDER BY " . self::SORTS[$f['sort']] . " {$f['dir']}, p.id DESC LIMIT {$p->limit()} OFFSET {$p->offset()}",
            $params
        );
        $this->view('products/index', [
            'title' => $f['kind'] === 'services' ? 'Services' : ($f['kind'] === 'products' ? 'Produits' : 'Produits & services'),
            'products' => $products, 'p' => $p, 'f' => $f, 'categories' => $this->categories(),
            'counts' => $this->db->fetch(
                "SELECT SUM(product_type NOT IN ('" . implode("','", self::SERVICE_TYPES) . "')) AS products,
                        SUM(product_type IN ('" . implode("','", self::SERVICE_TYPES) . "')) AS services,
                        SUM(track_stock = 1 AND stock_quantity <= min_stock) AS low
                   FROM products WHERE deleted_at IS NULL AND is_active = 1"
            ),
        ]);
    }

    /* ================================================================
       Fiche
       ================================================================ */

    public function show(int $id): void
    {
        $product = $this->db->fetch(
            'SELECT p.*, c.name AS category_name, u.name AS unit_name, u.symbol AS unit_symbol, t.name AS tax_name, t.rate AS tax_rate,
                    m.name AS machine_name, mat.name AS material_name, s.name AS supplier_name, s.id AS supplier_ref
               FROM products p
               LEFT JOIN categories c ON c.id = p.category_id JOIN units u ON u.id = p.unit_id
               LEFT JOIN taxes t ON t.id = p.tax_id LEFT JOIN machines m ON m.id = p.machine_id
               LEFT JOIN materials mat ON mat.id = p.material_id LEFT JOIN suppliers s ON s.id = p.supplier_id
              WHERE p.id = ? AND p.deleted_at IS NULL',
            [$id]
        );
        if (!$product) {
            $this->fail('Produit introuvable.', 'products');
        }
        $year = date('Y-01-01');
        $this->view('products/show', [
            'title'       => $product['name'],
            'breadcrumbs' => [['Catalogue', 'products'], [$product['name'], null]],
            'product'     => $product,
            'prices'      => $this->db->fetchAll('SELECT pl.id, pl.name, pp.price FROM price_lists pl LEFT JOIN product_prices pp ON pp.price_list_id = pl.id AND pp.product_id = ? AND pp.min_quantity = 1 WHERE pl.is_active = 1 ORDER BY pl.id', [$id]),
            'bom'         => $this->bom($id),
            'packs'       => $this->db->fetchAll('SELECT pk.id, pk.code, pk.name, pi.quantity FROM pack_items pi JOIN packs pk ON pk.id = pi.pack_id WHERE pi.product_id = ? AND pk.deleted_at IS NULL', [$id]),
            'movements'   => $this->db->fetchAll('SELECT sm.*, u.full_name FROM stock_movements sm LEFT JOIN users u ON u.id = sm.user_id WHERE sm.product_id = ? ORDER BY sm.id DESC LIMIT 15', [$id]),
            'sales'       => $this->db->fetch(
                "SELECT COALESCE(SUM(l.quantity), 0) AS qty, COALESCE(SUM(l.revenue_ht), 0) AS revenue, COALESCE(SUM(l.cost), 0) AS cost
                   FROM " . \App\Services\Analytics::soldLinesSql() . " l WHERE l.product_id = ? AND l.doc_date >= ?",
                [$id, $year]
            ),
        ]);
    }

    /* ================================================================
       Création / modification
       ================================================================ */

    public function create(): void
    {
        $copy = null;
        if (Request::int('duplicate') > 0) {
            $copy = $this->db->fetch('SELECT * FROM products WHERE id = ? AND deleted_at IS NULL', [Request::int('duplicate')]);
        }
        $type = Request::string('type');
        $this->form(null, $copy ? array_merge($copy, ['code' => '', 'barcode' => '', 'name' => $copy['name'] . ' (copie)', 'image' => null])
            : ['product_type' => isset(self::TYPES[$type]) ? $type : 'print_job'], $copy ? (int) $copy['id'] : null);
    }

    public function edit(int $id): void
    {
        $product = $this->findOrFail('products', $id, 'products', 'Produit');
        $this->form($product, $product, $id);
    }

    private function form(?array $product, array $values, ?int $sourceId): void
    {
        $prices = [];
        $bom = [];
        if ($sourceId) {
            foreach ($this->db->fetchAll('SELECT price_list_id, price FROM product_prices WHERE product_id = ? AND min_quantity = 1', [$sourceId]) as $r) {
                $prices[(int) $r['price_list_id']] = $r['price'];
            }
            $bom = $this->bom($sourceId);
        }
        $this->view('products/form', [
            'title'      => $product ? 'Modifier : ' . $product['name'] : 'Nouveau produit / service',
            'breadcrumbs'=> $product ? [['Catalogue', 'products'], [$product['name'], 'products/' . $product['id']], ['Modifier', null]] : [['Catalogue', 'products'], ['Nouveau', null]],
            'product'    => $product,
            'values'     => $values,
            'prices'     => $prices,
            'bom'        => $bom,
            'categories' => $this->categories(),
            'units'      => $this->db->fetchAll('SELECT id, name, symbol FROM units WHERE is_active = 1 ORDER BY sort_order'),
            'taxes'      => $this->db->fetchAll('SELECT id, name, rate FROM taxes WHERE is_active = 1 OR id = ? ORDER BY rate DESC', [(int) ($values['tax_id'] ?? 0)]),
            'machines'   => $this->db->fetchAll('SELECT id, name FROM machines WHERE deleted_at IS NULL AND is_active = 1 ORDER BY name'),
            'priceLists' => $this->db->fetchAll('SELECT id, name FROM price_lists WHERE is_active = 1 AND id <> 1 ORDER BY id'),
            'supplier'   => !empty($values['supplier_id']) ? $this->db->fetch('SELECT id, code, name FROM suppliers WHERE id = ?', [$values['supplier_id']]) : null,
            'material'   => !empty($values['material_id']) ? $this->db->fetch('SELECT id, code, name FROM materials WHERE id = ?', [$values['material_id']]) : null,
        ]);
    }

    public function store(): void
    {
        $data = $this->validated(null);
        $initial = can('stock.movements') ? Request::decimal('initial_stock') : 0.0;
        $image = $this->uploadImage('products/create');
        try {
            $id = $this->db->transaction(function () use (&$data, $initial, $image): int {
                $data['code'] = $data['code'] ?: DocumentNumber::next('product');
                $data['created_by'] = auth_user()['id'];
                $data['image'] = $image;
                $id = $this->db->insert('products', $data);
                $this->savePricesAndBom($id);
                if ($data['track_stock'] && $initial > 0) {
                    StockService::move('product', $id, $initial, 'adjustment', (float) $data['purchase_price'], 'product', $id, 'Stock initial');
                }
                return $id;
            });
        } catch (\DomainException $e) {
            Uploader::deletePublic($image);
            $this->fail($e->getMessage(), 'products/create');
        }
        AuditLog::log('create', 'products', 'product', $id, 'Produit créé : ' . $data['code'] . ' ' . $data['name'], null, $data);
        Session::flash('success', '« ' . $data['name'] . ' » ajouté au catalogue.');
        $this->redirect(Request::post('redirect') === 'new' ? 'products/create' : 'products/' . $id);
    }

    public function update(int $id): void
    {
        $before = $this->findOrFail('products', $id, 'products', 'Produit');
        $data = $this->validated($id);
        if ($data['code'] === '') {
            unset($data['code']);
        }
        if (!$data['track_stock'] && (float) $before['stock_quantity'] != 0.0) {
            $this->fail('Ce produit a encore ' . number_fr($before['stock_quantity'], 3) . ' en stock : ramenez le stock à zéro (ajustement) avant de désactiver le suivi.', 'products/' . $id . '/edit');
        }
        $image = $this->uploadImage('products/' . $id . '/edit');
        if ($image) {
            $data['image'] = $image;
        } elseif (Request::post('remove_image')) {
            $data['image'] = null;
        }
        $this->db->transaction(function () use ($id, $data): void {
            $this->db->update('products', $data, 'id = :id', ['id' => $id]);
            $this->savePricesAndBom($id);
        });
        if (array_key_exists('image', $data)) {
            Uploader::deletePublic($before['image']);
        }
        [$old, $new] = AuditLog::diff($before, $data);
        AuditLog::log('update', 'products', 'product', $id, 'Produit modifié : ' . $before['code'], $old, $new);
        Session::flash('success', 'Produit mis à jour.');
        $this->redirect('products/' . $id);
    }

    private function validated(?int $id): array
    {
        $in = [
            'code'              => mb_strtoupper(Request::string('code')),
            'barcode'           => Request::string('barcode'),
            'name'              => Request::string('name'),
            'name_fr'           => Request::string('name_fr'),
            'product_type'      => Request::string('product_type'),
            'category_id'       => Request::int('category_id'),
            'description'       => Request::string('description'),
            'unit_id'           => Request::int('unit_id'),
            'tax_id'            => Request::int('tax_id'),
        ];
        $back = $id ? 'products/' . $id . '/edit' : 'products/create';
        $v = Validator::make($in, [
            'code'         => 'nullable|alpha_dash|maxlen:40|unique:products,code' . ($id ? ',' . $id : ''),
            'barcode'      => 'nullable|alpha_dash|maxlen:60',
            'name'         => 'required|maxlen:190',
            'name_fr'      => 'nullable|maxlen:190',
            'product_type' => 'required|in:' . implode(',', array_keys(self::TYPES)),
            'unit_id'      => 'required|exists:units,id',
            'description'  => 'nullable|maxlen:5000',
        ], ['code' => 'Code', 'barcode' => 'Code-barres', 'name' => 'Nom', 'name_fr' => 'Nom en français',
            'product_type' => 'Type', 'unit_id' => 'Unité', 'description' => 'Description']);
        if ($v->fails()) {
            $this->fail((string) $v->first(), $back);
        }
        if ($in['barcode'] !== '' && (int) $this->db->fetchColumn('SELECT COUNT(*) FROM products WHERE barcode = ? AND deleted_at IS NULL AND id <> ?', [$in['barcode'], (int) $id])) {
            $this->fail('Ce code-barres est déjà utilisé par un autre produit.', $back);
        }
        $isService = in_array($in['product_type'], self::SERVICE_TYPES, true);

        $purchase = Request::decimal('purchase_price');
        $cost = Request::decimal('estimated_cost');
        $selling = Request::decimal('selling_price');
        $min = Request::decimal('min_selling_price');
        foreach ([$purchase, $cost, $selling, $min] as $n) {
            if ($n < 0) {
                $this->fail('Les prix et coûts ne peuvent pas être négatifs.', $back);
            }
        }
        if ($min > $selling && $selling > 0) {
            $this->fail('Le prix minimum ne peut pas dépasser le prix de vente.', $back);
        }
        $unitCost = $cost > 0 ? $cost : $purchase;
        $in += [
            'purchase_price'       => round($purchase, 4),
            'estimated_cost'       => round($cost, 4),
            'selling_price'        => round($selling, 2),
            'min_selling_price'    => round($min, 2),
            // Marge calculée uniquement si un coût est connu (sinon elle serait faussement de 100 %)
            'margin_percent'       => $selling > 0 && $unitCost > 0 ? round(($selling - $unitCost) / $selling * 100, 2) : 0,
            'track_stock'          => !$isService && Request::post('track_stock') ? 1 : 0,
            'min_stock'            => max(0, round(Request::decimal('min_stock'), 3)),
            'machine_id'           => Request::int('machine_id') ?: null,
            'material_id'          => Request::int('material_id') ?: null,
            'supplier_id'          => Request::int('supplier_id') ?: null,
            'allow_price_override' => Request::post('allow_price_override') ? 1 : 0,
            'is_quick'             => Request::post('is_quick') ? 1 : 0,
            'is_active'            => Request::post('is_active') ? 1 : 0,
        ];
        foreach (['category_id' => 'categories', 'tax_id' => 'taxes', 'machine_id' => 'machines', 'material_id' => 'materials', 'supplier_id' => 'suppliers'] as $k => $table) {
            if ($in[$k] && !$this->db->fetchColumn("SELECT id FROM {$table} WHERE id = ?", [$in[$k]])) {
                $in[$k] = null;
            }
            $in[$k] = $in[$k] ?: null;
        }
        if ($in['tax_id'] === null) {
            $in['tax_id'] = (int) Settings::get('default_tax_id', 1);
        }
        foreach (['barcode', 'name_fr', 'description'] as $k) {
            $in[$k] = $in[$k] !== '' ? $in[$k] : null;
        }
        return $in;
    }

    /** Prix par liste (Pro, Gros, Contrat) et nomenclature matières. */
    private function savePricesAndBom(int $id): void
    {
        $prices = (array) ($_POST['prices'] ?? []);
        foreach ($this->db->fetchAll('SELECT id FROM price_lists WHERE id <> 1') as $pl) {
            $raw = $prices[$pl['id']] ?? '';
            $value = Csv::number(is_string($raw) ? $raw : '');
            if ($value === null || $value < 0) {
                $this->db->query('DELETE FROM product_prices WHERE product_id = ? AND price_list_id = ? AND min_quantity = 1', [$id, $pl['id']]);
            } else {
                $this->db->query(
                    'INSERT INTO product_prices (product_id, price_list_id, price, min_quantity) VALUES (?, ?, ?, 1)
                     ON DUPLICATE KEY UPDATE price = VALUES(price)',
                    [$id, $pl['id'], round($value, 2)]
                );
            }
        }
        $this->db->query('DELETE FROM product_materials WHERE product_id = ?', [$id]);
        $bom = (array) ($_POST['bom'] ?? []);
        $seen = [];
        foreach ((array) ($bom['material_id'] ?? []) as $i => $mid) {
            $mid = (int) $mid;
            $qty = Csv::number((string) ($bom['quantity'][$i] ?? ''));
            $waste = Csv::number((string) ($bom['waste'][$i] ?? '')) ?? 0;
            if ($mid <= 0 || $qty === null || $qty <= 0 || isset($seen[$mid])) {
                continue;
            }
            if (!$this->db->fetchColumn('SELECT id FROM materials WHERE id = ? AND deleted_at IS NULL', [$mid])) {
                continue;
            }
            $seen[$mid] = true;
            $this->db->insert('product_materials', ['product_id' => $id, 'material_id' => $mid, 'quantity' => round($qty, 4), 'waste_percent' => max(0, min(100, round($waste, 2)))]);
        }
    }

    private function uploadImage(string $back): ?string
    {
        if (!Uploader::has('image')) {
            return null;
        }
        try {
            return Uploader::publicImage('image', 'products', 3072, 900);
        } catch (\Throwable $e) {
            $this->fail('Image : ' . $e->getMessage(), $back);
        }
    }

    public function delete(int $id): void
    {
        $p = $this->findOrFail('products', $id, 'products', 'Produit');
        if ((float) $p['stock_quantity'] > 0) {
            $this->fail('Ce produit a encore ' . number_fr($p['stock_quantity'], 3) . ' en stock : sortez ou ajustez le stock avant de le supprimer.', 'products/' . $id);
        }
        $inPacks = (int) $this->db->fetchColumn('SELECT COUNT(*) FROM pack_items pi JOIN packs pk ON pk.id = pi.pack_id WHERE pi.product_id = ? AND pk.deleted_at IS NULL', [$id]);
        if ($inPacks) {
            $this->fail("Ce produit fait partie de {$inPacks} pack(s) : retirez-le des packs d'abord.", 'products/' . $id);
        }
        // Suppression logique : les documents existants gardent leur ligne ; le code est libéré
        $suffix = '-DEL' . $id;
        $this->db->update('products', [
            'deleted_at' => date('Y-m-d H:i:s'), 'is_active' => 0, 'barcode' => null,
            'code' => mb_substr($p['code'], 0, 40 - strlen($suffix)) . $suffix,
        ], 'id = :id', ['id' => $id]);
        AuditLog::log('delete', 'products', 'product', $id, 'Produit supprimé : ' . $p['code'] . ' ' . $p['name']);
        Session::flash('success', 'Produit supprimé du catalogue. Les documents existants ne sont pas modifiés.');
        $this->redirect('products');
    }

    /* ================================================================
       Recherche JSON (POS, devis, packs, prix négociés)
       ================================================================ */

    public function lookup(): void
    {
        $q = Request::string('q');
        $onlyStock = Request::string('stock') === '1';
        if ($q === '') {
            Response::json(['success' => true, 'items' => []]);
        }
        $rows = $this->db->fetchAll(
            'SELECT p.id, p.code, p.barcode, p.name, p.selling_price, p.stock_quantity, p.track_stock,
                    IF(p.estimated_cost > 0, p.estimated_cost, p.purchase_price) AS unit_cost, u.symbol, t.rate
               FROM products p JOIN units u ON u.id = p.unit_id LEFT JOIN taxes t ON t.id = p.tax_id
              WHERE p.deleted_at IS NULL AND p.is_active = 1' . ($onlyStock ? ' AND p.track_stock = 1' : '') . '
                AND (p.name LIKE :a OR p.code LIKE :b OR p.barcode = :c OR p.name_fr LIKE :d)
              ORDER BY (p.barcode = :e) DESC, (p.code = :f) DESC, p.name LIMIT 20',
            ['a' => "%{$q}%", 'b' => "%{$q}%", 'c' => $q, 'd' => "%{$q}%", 'e' => $q, 'f' => $q]
        );
        $showCost = can('products.view_cost');
        Response::json(['success' => true, 'items' => array_map(static fn($r) => [
            'id' => (int) $r['id'], 'code' => $r['code'], 'name' => $r['name'],
            'price' => (float) $r['selling_price'], 'unit' => $r['symbol'], 'tax_rate' => (float) ($r['rate'] ?? 0),
            'cost' => $showCost ? (float) $r['unit_cost'] : null,
            'detail' => money($r['selling_price']) . ' / ' . $r['symbol'] . ($r['track_stock'] ? ' · stock ' . number_fr($r['stock_quantity'], 2) : ''),
        ], $rows)]);
    }

    /* ================================================================
       Import / export CSV
       ================================================================ */

    private static function csvColumns(): array
    {
        return [
            'code' => 'Code', 'name' => 'Nom', 'name_fr' => 'Nom FR', 'type_label' => 'Type', 'category_name' => 'Catégorie',
            'unit_code' => 'Unité', 'purchase_price' => 'Prix achat', 'estimated_cost' => 'Coût estimé', 'selling_price' => 'Prix vente',
            'min_selling_price' => 'Prix minimum', 'price_2' => 'Prix professionnel', 'price_3' => 'Prix gros', 'price_4' => 'Prix contrat',
            'tax_rate' => 'TVA', 'barcode' => 'Code-barres', 'track_stock' => 'Suivi stock', 'stock_quantity' => 'Stock',
            'min_stock' => 'Stock minimum', 'description' => 'Description', 'is_active' => 'Actif',
        ];
    }

    public function export(): void
    {
        [$where, $params] = $this->where($this->filters());
        $rows = $this->db->fetchAll(
            "SELECT p.*, c.name AS category_name, u.code AS unit_code, t.rate AS tax_rate,
                    (SELECT price FROM product_prices WHERE product_id = p.id AND price_list_id = 2 AND min_quantity = 1) AS price_2,
                    (SELECT price FROM product_prices WHERE product_id = p.id AND price_list_id = 3 AND min_quantity = 1) AS price_3,
                    (SELECT price FROM product_prices WHERE product_id = p.id AND price_list_id = 4 AND min_quantity = 1) AS price_4
               FROM products p LEFT JOIN categories c ON c.id = p.category_id JOIN units u ON u.id = p.unit_id LEFT JOIN taxes t ON t.id = p.tax_id
              WHERE {$where} ORDER BY p.code",
            $params
        );
        $showCost = can('products.view_cost');
        foreach ($rows as &$r) {
            $r['type_label'] = self::TYPES[$r['product_type']] ?? $r['product_type'];
            foreach (['selling_price', 'min_selling_price', 'tax_rate', 'stock_quantity', 'min_stock', 'price_2', 'price_3', 'price_4', 'purchase_price', 'estimated_cost'] as $k) {
                $r[$k] = $r[$k] === null ? '' : (float) $r[$k];
            }
            if (!$showCost) {
                $r['purchase_price'] = $r['estimated_cost'] = '';
            }
            $r['track_stock'] = $r['track_stock'] ? 'oui' : 'non';
            $r['is_active'] = $r['is_active'] ? 'oui' : 'non';
        }
        unset($r);
        AuditLog::log('export', 'products', null, null, 'Export CSV de ' . count($rows) . ' produit(s)');
        Csv::download('catalogue-' . date('Y-m-d') . '.csv', self::csvColumns(), $rows);
    }

    public function template(): void
    {
        Csv::download('modele-import-catalogue.csv', self::csvColumns(), [[
            'code' => '', 'name' => 'Bâche 440 g quadri', 'name_fr' => '', 'type_label' => 'Travail d\'impression', 'category_name' => 'Grand format',
            'unit_code' => 'm2', 'purchase_price' => '', 'estimated_cost' => 35.0, 'selling_price' => 90.0, 'min_selling_price' => 70.0,
            'price_2' => 80.0, 'price_3' => 75.0, 'price_4' => '', 'tax_rate' => 20.0, 'barcode' => '', 'track_stock' => 'non',
            'stock_quantity' => '', 'min_stock' => '', 'description' => 'Impression éco-solvant, œillets en option', 'is_active' => 'oui',
        ]]);
    }

    public function importForm(): void
    {
        $this->view('products/import', ['title' => 'Importer le catalogue', 'report' => Session::get('_import_report'),
            'breadcrumbs' => [['Catalogue', 'products'], ['Importer', null]]]);
        Session::forget('_import_report');
    }

    /**
     * Import : mise à jour par code, création sinon. Le stock n'est jamais écrasé :
     * la colonne « Stock » sert uniquement de stock initial à la création (mouvement tracé).
     */
    public function import(): void
    {
        try {
            [, $rows] = Csv::parseUpload('file');
        } catch (\RuntimeException $e) {
            $this->fail($e->getMessage(), 'products/import');
        }
        $maps = [
            'types' => [], 'categories' => [], 'units' => [], 'taxes' => [],
        ];
        foreach (self::TYPES as $k => $l) {
            $maps['types'][Csv::normalize($l)] = $k;
            $maps['types'][$k] = $k;
        }
        $maps['types']['produit'] = 'product';
        $maps['types']['impression'] = 'print_job';
        foreach ($this->db->fetchAll('SELECT id, name FROM categories WHERE deleted_at IS NULL') as $c) {
            $maps['categories'][Csv::normalize($c['name'])] = (int) $c['id'];
        }
        foreach ($this->db->fetchAll('SELECT id, code, name, symbol FROM units') as $u) {
            foreach ([$u['code'], $u['name'], $u['symbol']] as $k) {
                $maps['units'][Csv::normalize($k)] = (int) $u['id'];
            }
        }
        $maps['units']['m'] = $maps['units']['m'] ?? null;
        foreach ($this->db->fetchAll('SELECT id, rate FROM taxes') as $t) {
            $maps['taxes'][(string) (float) $t['rate']] = (int) $t['id'];
        }
        $defaultUnit = $maps['units']['piece'] ?? 1;
        $created = $updated = 0;
        $errors = [];
        foreach ($rows as $i => $r) {
            $line = $i + 2;
            $name = mb_substr(trim($r['nom'] ?? ''), 0, 190);
            if ($name === '') {
                $errors[] = "Ligne {$line} : nom obligatoire.";
                continue;
            }
            $type = $maps['types'][Csv::normalize($r['type'] ?? '')] ?? 'product';
            $selling = Csv::number($r['prix_vente'] ?? '') ?? 0.0;
            $purchase = Csv::number($r['prix_achat'] ?? '') ?? 0.0;
            $cost = Csv::number($r['cout_estime'] ?? '') ?? 0.0;
            $min = Csv::number($r['prix_minimum'] ?? '') ?? 0.0;
            if ($selling < 0 || $purchase < 0 || $cost < 0 || $min < 0) {
                $errors[] = "Ligne {$line} : prix négatif.";
                continue;
            }
            $taxRate = Csv::number($r['tva'] ?? '');
            $data = [
                'name' => $name, 'name_fr' => mb_substr($r['nom_fr'] ?? '', 0, 190) ?: null, 'product_type' => $type,
                'category_id' => $maps['categories'][Csv::normalize($r['categorie'] ?? '')] ?? null,
                'unit_id' => $maps['units'][Csv::normalize($r['unite'] ?? '')] ?? $defaultUnit,
                'purchase_price' => round($purchase, 4), 'estimated_cost' => round($cost, 4),
                'selling_price' => round($selling, 2), 'min_selling_price' => round(min($min, $selling ?: $min), 2),
                'margin_percent' => $selling > 0 && ($cost ?: $purchase) > 0 ? round(($selling - ($cost ?: $purchase)) / $selling * 100, 2) : 0,
                'tax_id' => $taxRate !== null ? ($maps['taxes'][(string) $taxRate] ?? (int) Settings::get('default_tax_id', 1)) : (int) Settings::get('default_tax_id', 1),
                'barcode' => preg_match('/^[A-Za-z0-9_\-.]{1,60}$/', $r['code_barres'] ?? '') ? $r['code_barres'] : null,
                'track_stock' => !in_array($type, self::SERVICE_TYPES, true) && Csv::bool($r['suivi_stock'] ?? '', false) ? 1 : 0,
                'min_stock' => max(0, Csv::number($r['stock_minimum'] ?? '') ?? 0),
                'description' => ($r['description'] ?? '') ?: null,
                'is_active' => Csv::bool($r['actif'] ?? '') ? 1 : 0,
            ];
            $code = mb_strtoupper(trim($r['code'] ?? ''));
            if ($code !== '' && !preg_match('/^[A-Z0-9_\-.]{1,40}$/', $code)) {
                $errors[] = "Ligne {$line} : code « {$code} » invalide.";
                continue;
            }
            try {
                $this->db->transaction(function () use ($code, $data, $r, &$created, &$updated): void {
                    $existing = $code !== '' ? $this->db->fetch('SELECT id, track_stock, stock_quantity FROM products WHERE code = ? AND deleted_at IS NULL', [$code]) : null;
                    if ($existing) {
                        if (!$data['track_stock'] && (float) $existing['stock_quantity'] != 0.0) {
                            $data['track_stock'] = 1;
                        }
                        $this->db->update('products', $data, 'id = :id', ['id' => $existing['id']]);
                        $id = (int) $existing['id'];
                        $updated++;
                    } else {
                        $data['code'] = $code !== '' ? $code : DocumentNumber::next('product');
                        $data['created_by'] = auth_user()['id'];
                        $id = $this->db->insert('products', $data);
                        $initial = Csv::number($r['stock'] ?? '') ?? 0;
                        if ($data['track_stock'] && $initial > 0) {
                            StockService::move('product', $id, $initial, 'adjustment', (float) $data['purchase_price'], 'import', null, 'Stock initial (import CSV)');
                        }
                        $created++;
                    }
                    foreach ([2 => 'prix_professionnel', 3 => 'prix_gros', 4 => 'prix_contrat'] as $listId => $col) {
                        $price = Csv::number($r[$col] ?? '');
                        if ($price !== null && $price >= 0) {
                            $this->db->query('INSERT INTO product_prices (product_id, price_list_id, price, min_quantity) VALUES (?, ?, ?, 1) ON DUPLICATE KEY UPDATE price = VALUES(price)', [$id, $listId, round($price, 2)]);
                        }
                    }
                });
            } catch (\Throwable $e) {
                $errors[] = "Ligne {$line} : " . ($e instanceof \DomainException ? $e->getMessage() : 'enregistrement impossible (code ou code-barres en double ?).');
            }
        }
        AuditLog::log('import', 'products', null, null, "Import CSV catalogue : {$created} créé(s), {$updated} mis à jour, " . count($errors) . ' erreur(s)');
        Session::set('_import_report', ['created' => $created, 'updated' => $updated, 'errors' => array_slice($errors, 0, 100), 'error_count' => count($errors)]);
        $this->redirect('products/import');
    }

    /* ---------------------------------------------------------------- */

    private function bom(int $productId): array
    {
        return $this->db->fetchAll(
            'SELECT pm.*, m.code, m.name, m.unit_cost, u.symbol
               FROM product_materials pm JOIN materials m ON m.id = pm.material_id JOIN units u ON u.id = m.unit_id
              WHERE pm.product_id = ? ORDER BY m.name',
            [$productId]
        );
    }

    private function categories(): array
    {
        return $this->db->fetchAll(
            'SELECT c.id, c.name, c.parent_id, p.name AS parent_name FROM categories c LEFT JOIN categories p ON p.id = c.parent_id
              WHERE c.deleted_at IS NULL AND c.is_active = 1 ORDER BY COALESCE(p.sort_order, c.sort_order), COALESCE(c.parent_id, c.id), c.parent_id IS NOT NULL, c.sort_order'
        );
    }
}
