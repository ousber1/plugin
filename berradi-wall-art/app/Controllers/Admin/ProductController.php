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
use App\Services\Admin\CatalogAdmin;
use App\Services\CatalogService;
use App\Services\UploadException;

final class ProductController extends Controller
{
    private const SORTS = ['created_at' => 'p.created_at', 'title' => 'p.title', 'selling_price' => 'p.selling_price', 'stock_quantity' => 'p.stock_quantity', 'views' => 'p.views', 'sort_order' => 'p.sort_order'];

    public function index(): Response
    {
        $db = Database::instance();
        $f = [
            'q' => $this->request->str('q'), 'category' => $this->request->int('category'), 'collection' => $this->request->int('collection'),
            'type' => $this->request->str('type'), 'status' => $this->request->str('status'), 'flag' => $this->request->str('flag'),
            'sort' => $this->request->str('sort', 'created_at'), 'dir' => $this->request->str('dir', 'desc'),
        ];
        $where = ['p.deleted_at IS NULL'];
        $params = [];
        if ($f['q'] !== '') {
            $where[] = '(p.title LIKE :q1 OR p.sku LIKE :q2 OR EXISTS (SELECT 1 FROM product_variants v WHERE v.product_id = p.id AND v.sku LIKE :q3))';
            $like = '%' . addcslashes($f['q'], '%_\\') . '%';
            $params += ['q1' => $like, 'q2' => $like, 'q3' => $like];
        }
        if ($f['category'] > 0) {
            $where[] = 'p.category_id = :cat';
            $params['cat'] = $f['category'];
        }
        if ($f['collection'] > 0) {
            $where[] = 'p.collection_id = :col';
            $params['col'] = $f['collection'];
        }
        if (in_array($f['type'], CatalogService::TYPES, true)) {
            $where[] = 'p.product_type = :type';
            $params['type'] = $f['type'];
        }
        if ($f['status'] === 'active' || $f['status'] === 'inactive') {
            $where[] = 'p.is_active = ' . ($f['status'] === 'active' ? 1 : 0);
        }
        $where[] = match ($f['flag']) {
            'featured' => 'p.is_featured = 1',
            'best_seller' => 'p.is_best_seller = 1',
            'new' => 'p.is_new = 1',
            'promo' => 'p.promo_price IS NOT NULL',
            'low_stock' => 'p.track_stock = 1 AND p.stock_quantity <= 5',
            'customizable' => 'p.is_customizable = 1',
            default => '1 = 1',
        };
        $sortCol = self::SORTS[$f['sort']] ?? 'p.created_at';
        $dir = strtolower($f['dir']) === 'asc' ? 'ASC' : 'DESC';
        $sqlWhere = ' WHERE ' . implode(' AND ', $where);
        $total = (int) $db->value('SELECT COUNT(*) FROM products p' . $sqlWhere, $params);
        $per = 25;
        $page = max(1, $this->request->int('page', 1));
        $rows = $db->fetchAll(
            "SELECT p.id, p.title, p.slug, p.sku, p.product_type, p.main_image, p.selling_price, p.promo_price, p.cost_price, p.track_stock, p.stock_quantity,
                    p.is_active, p.is_featured, p.is_best_seller, p.is_new, p.is_customizable, p.views, c.name AS category_name,
                    (SELECT COUNT(*) FROM product_variants v WHERE v.product_id = p.id) AS variant_count,
                    (SELECT MIN(price) FROM product_variants v WHERE v.product_id = p.id AND v.is_active = 1) AS min_variant
             FROM products p LEFT JOIN categories c ON c.id = p.category_id" . $sqlWhere . " ORDER BY {$sortCol} {$dir}, p.id DESC LIMIT {$per} OFFSET " . (($page - 1) * $per),
            $params
        );
        return $this->view('admin/products/index', [
            'products' => new \App\Core\Paginator($rows, $total, $per, $page),
            'filters' => $f,
            'categories' => $db->fetchAll('SELECT id, name FROM categories ORDER BY sort_order, name'),
            'collections' => $db->fetchAll('SELECT id, name FROM collections ORDER BY sort_order, name'),
        ]);
    }

    /** @return array<string,mixed> */
    private function formData(?array $product): array
    {
        $db = Database::instance();
        $id = $product !== null ? (int) $product['id'] : null;
        return [
            'product' => $product,
            'ar' => CatalogAdmin::arValues('product', $id),
            'categories' => $db->fetchAll('SELECT id, name, parent_id FROM categories ORDER BY sort_order, name'),
            'collections' => $db->fetchAll('SELECT id, name FROM collections ORDER BY sort_order, name'),
            'materials' => $db->fetchAll('SELECT id, code, name, supports_frame, min_width_cm, max_width_cm FROM product_materials ORDER BY sort_order'),
            'frames' => $db->fetchAll('SELECT id, name, color_hex FROM frames ORDER BY sort_order'),
            'sizes' => $db->fetchAll("SELECT code, name, width_cm, height_cm FROM customization_options WHERE option_group = 'size' AND is_active = 1 ORDER BY sort_order"),
            'selMaterials' => $id ? array_map('intval', array_column($db->fetchAll('SELECT product_material_id FROM product_material_map WHERE product_id = ?', [$id]), 'product_material_id')) : [],
            'selFrames' => $id ? array_map('intval', array_column($db->fetchAll('SELECT frame_id FROM product_frame_map WHERE product_id = ?', [$id]), 'frame_id')) : [],
            'related' => $id ? array_map('intval', array_column($db->fetchAll('SELECT related_product_id FROM related_products WHERE product_id = ? ORDER BY sort_order', [$id]), 'related_product_id')) : [],
            'allProducts' => $db->fetchAll('SELECT id, title, sku FROM products WHERE deleted_at IS NULL' . ($id ? ' AND id <> ' . $id : '') . ' ORDER BY title LIMIT 1000'),
            'images' => $id ? $db->fetchAll('SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order, id', [$id]) : [],
            'variants' => $id ? $db->fetchAll('SELECT * FROM product_variants WHERE product_id = ? ORDER BY sort_order, id', [$id]) : [],
            'orderCount' => $id ? (int) $db->value('SELECT COUNT(*) FROM order_items WHERE product_id = ?', [$id]) : 0,
        ];
    }

    public function create(): Response
    {
        return $this->view('admin/products/form', $this->formData(null));
    }

    /** @return array<string,mixed> */
    private function find(string $id): array
    {
        $p = Database::instance()->fetch('SELECT * FROM products WHERE id = ? AND deleted_at IS NULL', [(int) $id]);
        if ($p === null) {
            throw new HttpException(404);
        }
        return $p;
    }

    public function edit(string $id): Response
    {
        return $this->view('admin/products/form', $this->formData($this->find($id)));
    }

    /** @return array{0:array<string,mixed>,1:array<string,string>,2:list<int>,3:list<int>,4:list<int>} */
    private function validated(?int $id): array
    {
        $this->request->normalizeDecimals(['base_price', 'selling_price', 'promo_price', 'cost_price']);
        $d = $this->validate([
            'title' => 'required|max:191',
            'slug' => 'nullable|slug|max:180',
            'sku' => 'nullable|max:50|regex:/^[A-Za-z0-9-]+$/',
            'category_id' => 'nullable|integer|exists:categories,id',
            'collection_id' => 'nullable|integer|exists:collections,id',
            'product_type' => 'required|in:' . implode(',', CatalogService::TYPES),
            'short_description' => 'nullable|max:500',
            'description' => 'nullable|max:20000',
            'base_price' => 'nullable|decimal:2|min:0',
            'selling_price' => 'required|decimal:2|min:0',
            'promo_price' => 'nullable|decimal:2|min:0',
            'promo_starts_at' => 'nullable|date',
            'promo_ends_at' => 'nullable|date',
            'cost_price' => 'nullable|decimal:2|min:0',
            'stock_quantity' => 'nullable|integer|between:0,1000000',
            'min_panels' => 'required|integer|between:1,5',
            'max_panels' => 'required|integer|between:1,5',
            'sort_order' => 'nullable|integer|between:-10000,10000',
            'seo_title' => 'nullable|max:191',
            'seo_description' => 'nullable|max:320',
            'ar_title' => 'nullable|max:191',
            'ar_short_description' => 'nullable|max:500',
            'ar_description' => 'nullable|max:20000',
            'ar_seo_title' => 'nullable|max:191',
            'ar_seo_description' => 'nullable|max:320',
        ], [
            'title' => __('admin.p_title'), 'selling_price' => __('admin.p_selling_price'), 'promo_price' => __('admin.p_promo_price'),
            'product_type' => __('shop.product_type'), 'sku' => __('product.sku'),
        ]);
        if ($d['promo_price'] !== null && $d['promo_price'] !== '' && Money::compare((string) $d['promo_price'], (string) $d['selling_price']) >= 0) {
            throw new ValidationException(['promo_price' => __('admin.promo_lower')], $this->request->all());
        }
        if (!empty($d['promo_starts_at']) && !empty($d['promo_ends_at']) && strtotime((string) $d['promo_ends_at']) <= strtotime((string) $d['promo_starts_at'])) {
            throw new ValidationException(['promo_ends_at' => __('admin.dates_order')], $this->request->all());
        }
        if ((int) $d['max_panels'] < (int) $d['min_panels']) {
            throw new ValidationException(['max_panels' => __('admin.panels_order')], $this->request->all());
        }
        $dt = fn ($v) => $v !== null && $v !== '' ? date('Y-m-d H:i:s', (int) strtotime((string) $v)) : null;
        $row = [
            'title' => $d['title'], 'slug' => $d['slug'], 'sku' => $d['sku'] !== null && $d['sku'] !== '' ? strtoupper((string) $d['sku']) : null,
            'category_id' => $d['category_id'] !== null && $d['category_id'] !== '' ? (int) $d['category_id'] : null,
            'collection_id' => $d['collection_id'] !== null && $d['collection_id'] !== '' ? (int) $d['collection_id'] : null,
            'product_type' => $d['product_type'], 'short_description' => $d['short_description'], 'description' => $d['description'],
            'base_price' => Money::normalize((string) ($d['base_price'] ?: $d['selling_price'])),
            'selling_price' => Money::normalize((string) $d['selling_price']),
            'promo_price' => $d['promo_price'] !== null && $d['promo_price'] !== '' ? Money::normalize((string) $d['promo_price']) : null,
            'promo_starts_at' => $dt($d['promo_starts_at']), 'promo_ends_at' => $dt($d['promo_ends_at']),
            'cost_price' => Money::normalize((string) ($d['cost_price'] ?: '0')),
            'track_stock' => $this->request->bool('track_stock') ? 1 : 0, 'stock_quantity' => (int) ($d['stock_quantity'] ?? 0),
            'is_customizable' => $this->request->bool('is_customizable') ? 1 : 0, 'allow_custom_size' => $this->request->bool('allow_custom_size') ? 1 : 0,
            'min_panels' => (int) $d['min_panels'], 'max_panels' => (int) $d['max_panels'],
            'is_featured' => $this->request->bool('is_featured') ? 1 : 0, 'is_best_seller' => $this->request->bool('is_best_seller') ? 1 : 0,
            'is_new' => $this->request->bool('is_new') ? 1 : 0, 'is_active' => $this->request->bool('is_active') ? 1 : 0,
            'sort_order' => (int) ($d['sort_order'] ?? 0), 'seo_title' => $d['seo_title'], 'seo_description' => $d['seo_description'],
        ];
        $ar = [];
        foreach (CatalogAdmin::PRODUCT_AR_FIELDS as $f) {
            $ar[$f] = $d['ar_' . $f] ?? null;
        }
        $ints = function (string $key): array {
            $v = $this->request->input($key, []);
            return is_array($v) ? array_values(array_filter(array_map('intval', $v), fn ($i) => $i > 0)) : [];
        };
        return [$row, $ar, $ints('materials'), $ints('frames'), $ints('related')];
    }

    public function store(): Response
    {
        [$row, $ar, $m, $f, $r] = $this->validated(null);
        $id = CatalogAdmin::saveProduct(null, $row, $ar, $m, $f, $r);
        $this->storeUploadedImages($id);
        $this->flash('success', __('admin.product_created'));
        return $this->redirectRoute('admin.products.edit', ['id' => $id]);
    }

    public function update(string $id): Response
    {
        $p = $this->find($id);
        [$row, $ar, $m, $f, $r] = $this->validated((int) $p['id']);
        CatalogAdmin::saveProduct((int) $p['id'], $row, $ar, $m, $f, $r);
        $this->storeUploadedImages((int) $p['id']);
        $this->flash('success', __('common.saved'));
        return $this->redirect(route('admin.products.edit', ['id' => $p['id']]) . '#' . $this->request->str('tab', 'general'));
    }

    /** Images sent with the main form or the gallery form. */
    private function storeUploadedImages(int $productId): int
    {
        $files = $this->request->file('images');
        if ($files === null || !is_array($files['name'] ?? null)) {
            return 0;
        }
        $db = Database::instance();
        $n = 0;
        $errors = [];
        $order = (int) $db->value('SELECT COALESCE(MAX(sort_order), -1) FROM product_images WHERE product_id = ?', [$productId]);
        foreach (array_keys($files['name']) as $i) {
            if ((int) $files['error'][$i] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $one = ['name' => $files['name'][$i], 'tmp_name' => $files['tmp_name'][$i], 'error' => $files['error'][$i], 'size' => $files['size'][$i]];
            try {
                $img = CatalogAdmin::storeImage($one, 'products');
            } catch (UploadException $e) {
                $errors[] = $files['name'][$i] . ' : ' . $e->getMessage();
                continue;
            }
            $db->insert('product_images', ['product_id' => $productId, 'path' => $img['path'], 'thumb_path' => $img['thumb'], 'alt' => null, 'sort_order' => ++$order]);
            $n++;
        }
        if ($n > 0 && (string) $db->value('SELECT main_image FROM products WHERE id = ?', [$productId]) === '') {
            $first = $db->value('SELECT path FROM product_images WHERE product_id = ? ORDER BY sort_order, id LIMIT 1', [$productId]);
            $db->update('products', ['main_image' => $first], ['id' => $productId]);
        }
        if ($errors) {
            $this->flash('warning', implode(' · ', $errors));
        }
        return $n;
    }

    public function uploadImages(string $id): Response
    {
        $p = $this->find($id);
        $n = $this->storeUploadedImages((int) $p['id']);
        if ($n > 0) {
            ActivityLogger::log('product.images_added', 'product', (int) $p['id'], (string) $n);
            $this->flash('success', __('admin.images_added', ['n' => $n]));
        }
        return $this->redirect(route('admin.products.edit', ['id' => $p['id']]) . '#images');
    }

    public function imageAction(string $id, string $imageId): Response
    {
        $p = $this->find($id);
        $db = Database::instance();
        $img = $db->fetch('SELECT * FROM product_images WHERE id = ? AND product_id = ?', [(int) $imageId, (int) $p['id']]);
        if ($img === null) {
            throw new HttpException(404);
        }
        switch ($this->request->str('action')) {
            case 'main':
                $db->update('products', ['main_image' => $img['path']], ['id' => (int) $p['id']]);
                break;
            case 'up':
            case 'down':
                $list = $db->fetchAll('SELECT id FROM product_images WHERE product_id = ? ORDER BY sort_order, id', [(int) $p['id']]);
                $ids = array_map(fn ($r) => (int) $r['id'], $list);
                $pos = array_search((int) $img['id'], $ids, true);
                $swap = $this->request->str('action') === 'up' ? $pos - 1 : $pos + 1;
                if ($pos !== false && isset($ids[$swap])) {
                    [$ids[$pos], $ids[$swap]] = [$ids[$swap], $ids[$pos]];
                }
                foreach ($ids as $i => $iid) {
                    $db->update('product_images', ['sort_order' => $i], ['id' => $iid]);
                }
                break;
            case 'alt':
                $db->update('product_images', ['alt' => mb_substr($this->request->str('alt'), 0, 191)], ['id' => (int) $img['id']]);
                break;
            case 'delete':
                $db->delete('product_images', ['id' => (int) $img['id']]);
                if ($p['main_image'] === $img['path']) {
                    $next = $db->value('SELECT path FROM product_images WHERE product_id = ? ORDER BY sort_order, id LIMIT 1', [(int) $p['id']]);
                    $db->update('products', ['main_image' => $next], ['id' => (int) $p['id']]);
                }
                CatalogAdmin::releaseImage($img['path']);
                CatalogAdmin::releaseImage($img['thumb_path']);
                break;
            default:
                throw new HttpException(400);
        }
        return $this->redirect(route('admin.products.edit', ['id' => $p['id']]) . '#images');
    }

    /** Save the whole variant table at once. */
    public function saveVariants(string $id): Response
    {
        $p = $this->find($id);
        $db = Database::instance();
        $rows = $this->request->input('variants', []);
        $default = $this->request->int('default_variant');
        $errors = [];
        $db->transaction(function (Database $db) use ($p, $rows, $default, &$errors): void {
            foreach (is_array($rows) ? $rows : [] as $vid => $v) {
                $variant = $db->fetch('SELECT id FROM product_variants WHERE id = ? AND product_id = ?', [(int) $vid, (int) $p['id']]);
                if ($variant === null || !is_array($v)) {
                    continue;
                }
                if (!empty($v['delete'])) {
                    $db->delete('product_variants', ['id' => (int) $vid]);
                    continue;
                }
                $price = (string) ($v['price'] ?? '');
                $promo = trim((string) ($v['promo_price'] ?? ''));
                if (!preg_match('/^\d{1,8}([.,]\d{1,2})?$/', $price) || ($promo !== '' && !preg_match('/^\d{1,8}([.,]\d{1,2})?$/', $promo))) {
                    $errors[] = (string) ($v['sku'] ?? $vid);
                    continue;
                }
                if ($promo !== '' && Money::compare($promo, $price) >= 0) {
                    $promo = '';
                }
                $db->update('product_variants', [
                    'name' => mb_substr(trim((string) ($v['name'] ?? '')), 0, 150) ?: 'Variante',
                    'price' => Money::normalize($price),
                    'promo_price' => $promo !== '' ? Money::normalize($promo) : null,
                    'cost_price' => Money::normalize((string) (($v['cost_price'] ?? '') ?: '0')),
                    'stock_quantity' => max(0, (int) ($v['stock_quantity'] ?? 0)),
                    'is_active' => !empty($v['is_active']) ? 1 : 0,
                    'is_default' => (int) $vid === $default ? 1 : 0,
                    'sort_order' => (int) ($v['sort_order'] ?? 0),
                ], ['id' => (int) $vid]);
            }
            CatalogAdmin::syncFromVariants((int) $p['id']);
        });
        ActivityLogger::log('product.variants_saved', 'product', (int) $p['id']);
        $this->flash($errors ? 'warning' : 'success', $errors ? __('admin.variant_errors', ['list' => implode(', ', $errors)]) : __('common.saved'));
        return $this->redirect(route('admin.products.edit', ['id' => $p['id']]) . '#variants');
    }

    public function addVariant(string $id): Response
    {
        $p = $this->find($id);
        $this->request->normalizeDecimals(['v_width', 'v_height', 'v_price']);
        $d = $this->validate([
            'v_width' => 'required|decimal:1|between:5,500', 'v_height' => 'required|decimal:1|between:5,500',
            'v_material' => 'required|integer|exists:product_materials,id', 'v_frame' => 'nullable|integer|exists:frames,id',
            'v_price' => 'required|decimal:2|min:0', 'v_panels' => 'nullable|integer|between:1,5',
        ], ['v_width' => __('builder.width'), 'v_height' => __('builder.height'), 'v_price' => __('admin.p_selling_price')]);
        $db = Database::instance();
        $mat = (string) $db->value('SELECT name FROM product_materials WHERE id = ?', [(int) $d['v_material']]);
        $frame = !empty($d['v_frame']) ? (string) $db->value('SELECT name FROM frames WHERE id = ?', [(int) $d['v_frame']]) : null;
        $db->insert('product_variants', [
            'product_id' => (int) $p['id'],
            'sku' => CatalogAdmin::uniqueSku('product_variants', $p['sku'] . '-' . (int) $d['v_width'] . 'X' . (int) $d['v_height']),
            'name' => cm($d['v_width']) . '×' . cm($d['v_height']) . ' cm · ' . $mat . ($frame ? ' · ' . $frame : ''),
            'width_cm' => number_format((float) $d['v_width'], 1, '.', ''), 'height_cm' => number_format((float) $d['v_height'], 1, '.', ''),
            'product_material_id' => (int) $d['v_material'], 'frame_id' => !empty($d['v_frame']) ? (int) $d['v_frame'] : null,
            'panels' => (int) ($d['v_panels'] ?? 1), 'price' => Money::normalize((string) $d['v_price']),
            'is_default' => (int) $db->value('SELECT COUNT(*) FROM product_variants WHERE product_id = ?', [(int) $p['id']]) === 0 ? 1 : 0,
            'sort_order' => (int) $db->value('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM product_variants WHERE product_id = ?', [(int) $p['id']]),
        ]);
        CatalogAdmin::syncFromVariants((int) $p['id']);
        $this->flash('success', __('common.created'));
        return $this->redirect(route('admin.products.edit', ['id' => $p['id']]) . '#variants');
    }

    public function generateVariants(string $id): Response
    {
        $p = $this->find($id);
        $db = Database::instance();
        $codes = (array) $this->request->input('g_sizes', []);
        $sizes = [];
        foreach ($db->fetchAll("SELECT code, width_cm, height_cm FROM customization_options WHERE option_group = 'size'") as $s) {
            if (in_array($s['code'], $codes, true)) {
                $w = (float) $s['width_cm'];
                $h = (float) $s['height_cm'];
                $sizes[] = $this->request->bool('g_landscape') ? ['w' => max($w, $h), 'h' => min($w, $h)] : ['w' => $w, 'h' => $h];
            }
        }
        $materials = array_values(array_filter(array_map('intval', (array) $this->request->input('g_materials', []))));
        $frames = array_map(fn ($v) => $v === 'none' ? null : (int) $v, array_values((array) $this->request->input('g_frames', [])));
        if ($sizes === [] || $materials === [] || $frames === []) {
            $this->flash('danger', __('admin.generate_need'));
            return $this->redirect(route('admin.products.edit', ['id' => $p['id']]) . '#variants');
        }
        $n = CatalogAdmin::generateVariants((int) $p['id'], $sizes, $materials, $frames, max(1, min(5, $this->request->int('g_panels', 1))));
        $this->flash('success', __('admin.variants_generated', ['n' => $n]));
        return $this->redirect(route('admin.products.edit', ['id' => $p['id']]) . '#variants');
    }

    public function duplicate(string $id): Response
    {
        $p = $this->find($id);
        $newId = CatalogAdmin::duplicate((int) $p['id']);
        $this->flash('success', __('admin.product_duplicated'));
        return $this->redirectRoute('admin.products.edit', ['id' => $newId]);
    }

    public function destroy(string $id): Response
    {
        $p = $this->find($id);
        CatalogAdmin::softDelete((int) $p['id']);
        $this->flash('success', __('admin.product_deleted'));
        return $this->redirectRoute('admin.products.index');
    }

    public function bulk(): Response
    {
        $ids = array_values(array_filter(array_map('intval', (array) $this->request->input('ids', []))));
        $action = $this->request->str('bulk_action');
        if ($ids === []) {
            $this->flash('warning', __('admin.bulk_none'));
            return $this->back();
        }
        $db = Database::instance();
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $n = 0;
        switch ($action) {
            case 'activate':
            case 'deactivate':
                $n = $db->query("UPDATE products SET is_active = ? WHERE deleted_at IS NULL AND id IN ({$ph})", array_merge([$action === 'activate' ? 1 : 0], $ids))->rowCount();
                break;
            case 'feature':
            case 'unfeature':
                $n = $db->query("UPDATE products SET is_featured = ? WHERE deleted_at IS NULL AND id IN ({$ph})", array_merge([$action === 'feature' ? 1 : 0], $ids))->rowCount();
                break;
            case 'category':
                $cat = $this->request->int('bulk_category');
                $catId = $cat > 0 && $db->value('SELECT id FROM categories WHERE id = ?', [$cat]) !== null ? $cat : null;
                $n = $db->query("UPDATE products SET category_id = ? WHERE deleted_at IS NULL AND id IN ({$ph})", array_merge([$catId], $ids))->rowCount();
                break;
            case 'delete':
                foreach ($ids as $id) {
                    $n += CatalogAdmin::softDelete($id) ? 1 : 0;
                }
                break;
            default:
                $this->flash('danger', __('admin.bulk_invalid'));
                return $this->back();
        }
        ActivityLogger::log('product.bulk_' . $action, 'product', null, implode(',', $ids));
        $this->flash('success', __('admin.bulk_done', ['n' => $n]));
        return $this->back();
    }
}
