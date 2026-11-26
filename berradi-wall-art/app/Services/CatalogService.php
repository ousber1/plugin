<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Paginator;
use App\Helpers\Money;

/**
 * Read-side of the catalogue for the storefront: listings with filters/sort/search,
 * product detail, related products, categories and collections (with FR/AR content).
 *
 * Pricing rules
 *  - product price  = promo_price when set and inside [promo_starts_at, promo_ends_at], else selling_price
 *  - variant price  = variant promo_price under the same product promo window, else variant price
 *  - a product with active variants is displayed "from" its cheapest variant
 */
final class CatalogService
{
    public const SORTS = ['featured', 'newest', 'price_asc', 'price_desc', 'popular', 'name'];
    public const TYPES = ['canvas', 'framed', 'poster', 'photo_print', 'multi_panel', 'limited'];
    public const FLAGS = ['featured', 'best_seller', 'new', 'promo', 'customizable'];

    private const PRODUCT_FIELDS = ['title', 'short_description', 'description', 'seo_title', 'seo_description'];
    private const CATEGORY_FIELDS = ['name', 'description', 'seo_title', 'seo_description'];
    private const COLLECTION_FIELDS = ['name', 'description', 'seo_title', 'seo_description'];

    /** SQL condition: product promo window is open (alias given). */
    private static function promoWindow(string $a): string
    {
        return "({$a}.promo_starts_at IS NULL OR {$a}.promo_starts_at <= NOW()) AND ({$a}.promo_ends_at IS NULL OR {$a}.promo_ends_at >= NOW())";
    }

    private static function effectiveSql(): string
    {
        return 'CASE WHEN p.promo_price IS NOT NULL AND p.promo_price < p.selling_price AND ' . self::promoWindow('p') . ' THEN p.promo_price ELSE p.selling_price END';
    }

    private static function displaySql(): string
    {
        return 'COALESCE(v.min_price, ' . self::effectiveSql() . ')';
    }

    private static function baseSelect(): string
    {
        $eff = self::effectiveSql();
        $win = self::promoWindow('pp');
        return "SELECT p.id, p.slug, p.sku, p.title, p.short_description, p.product_type, p.main_image, p.selling_price,
                       p.is_new, p.is_best_seller, p.is_featured, p.is_customizable, p.track_stock, p.stock_quantity,
                       p.category_id, p.collection_id, p.created_at, p.views, p.sort_order,
                       {$eff} AS effective_price,
                       v.min_price AS variant_min_price, v.min_regular AS variant_min_regular, COALESCE(v.variant_count, 0) AS variant_count,
                       " . self::displaySql() . " AS display_price,
                       COALESCE(v.min_regular, p.selling_price) AS compare_price,
                       c.name AS category_name, c.slug AS category_slug,
                       COALESCE(r.avg_rating, 0) AS avg_rating, COALESCE(r.review_count, 0) AS review_count
                FROM products p
                LEFT JOIN categories c ON c.id = p.category_id
                LEFT JOIN (
                    SELECT pv.product_id,
                           MIN(CASE WHEN pv.promo_price IS NOT NULL AND pv.promo_price < pv.price AND {$win} THEN pv.promo_price ELSE pv.price END) AS min_price,
                           MIN(pv.price) AS min_regular,
                           COUNT(*) AS variant_count
                    FROM product_variants pv JOIN products pp ON pp.id = pv.product_id
                    WHERE pv.is_active = 1
                    GROUP BY pv.product_id
                ) v ON v.product_id = p.id
                LEFT JOIN (
                    SELECT product_id, AVG(rating) AS avg_rating, COUNT(*) AS review_count
                    FROM reviews WHERE is_approved = 1 AND product_id IS NOT NULL GROUP BY product_id
                ) r ON r.product_id = p.id";
    }

    /**
     * Storefront listing.
     *
     * @param array{q?:string,category?:string,collection?:int|string,type?:string,min?:string,max?:string,flag?:string,sort?:string} $f
     */
    public static function listing(array $f, int $perPage = 24, ?int $page = null): Paginator
    {
        [$where, $params] = self::filters($f);
        $sort = in_array($f['sort'] ?? '', self::SORTS, true) ? $f['sort'] : 'featured';
        $order = match ($sort) {
            'newest' => 'p.created_at DESC, p.id DESC',
            'price_asc' => 'display_price ASC, p.id DESC',
            'price_desc' => 'display_price DESC, p.id DESC',
            'popular' => 'p.views DESC, p.id DESC',
            'name' => 'p.title ASC',
            default => 'p.is_featured DESC, p.is_best_seller DESC, p.sort_order ASC, p.created_at DESC',
        };
        $db = Database::instance();
        $total = (int) $db->value(
            'SELECT COUNT(*) FROM products p LEFT JOIN (SELECT pv.product_id, MIN(CASE WHEN pv.promo_price IS NOT NULL AND pv.promo_price < pv.price AND '
            . self::promoWindow('pp') . ' THEN pv.promo_price ELSE pv.price END) AS min_price FROM product_variants pv JOIN products pp ON pp.id = pv.product_id WHERE pv.is_active = 1 GROUP BY pv.product_id) v ON v.product_id = p.id'
            . $where,
            $params
        );
        $perPage = max(1, min(60, $perPage));
        $page ??= max(1, (int) ($_GET['page'] ?? 1));
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);
        $rows = $db->fetchAll(
            self::baseSelect() . $where . " ORDER BY {$order} LIMIT {$perPage} OFFSET " . (($page - 1) * $perPage),
            $params
        );
        return new Paginator(self::decorate($rows), $total, $perPage, $page);
    }

    /**
     * @param array<string,mixed> $f
     * @return array{0:string,1:array<string,mixed>}
     */
    private static function filters(array $f): array
    {
        $w = ['p.is_active = 1', 'p.deleted_at IS NULL'];
        $params = [];
        $q = trim((string) ($f['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . addcslashes(mb_substr($q, 0, 100), '%_\\') . '%';
            $w[] = "(p.title LIKE :q1 OR p.sku LIKE :q2 OR p.short_description LIKE :q3
                    OR EXISTS (SELECT 1 FROM translations t WHERE t.entity_type = 'product' AND t.entity_id = p.id AND t.field = 'title' AND t.value LIKE :q4))";
            $params += ['q1' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like];
        }
        if (!empty($f['category'])) {
            $ids = self::categoryTreeIds((string) $f['category']);
            if ($ids === []) {
                $w[] = '1 = 0';
            } else {
                $ph = [];
                foreach ($ids as $i => $id) {
                    $ph[] = ':cat' . $i;
                    $params['cat' . $i] = $id;
                }
                $w[] = 'p.category_id IN (' . implode(',', $ph) . ')';
            }
        }
        if (!empty($f['collection'])) {
            $w[] = 'p.collection_id = :col';
            $params['col'] = (int) $f['collection'];
        }
        if (!empty($f['type']) && in_array($f['type'], self::TYPES, true)) {
            $w[] = 'p.product_type = :ptype';
            $params['ptype'] = $f['type'];
        }
        foreach (['min' => '>=', 'max' => '<='] as $key => $op) {
            $val = trim((string) ($f[$key] ?? ''));
            if ($val !== '' && preg_match('/^\d{1,7}([.,]\d{1,2})?$/', $val)) {
                $w[] = self::displaySql() . " {$op} :{$key}price";
                $params[$key . 'price'] = Money::normalize($val);
            }
        }
        $flag = (string) ($f['flag'] ?? '');
        $w[] = match ($flag) {
            'featured' => 'p.is_featured = 1',
            'best_seller' => 'p.is_best_seller = 1',
            'new' => 'p.is_new = 1',
            'customizable' => 'p.is_customizable = 1',
            // on sale: product promo active, or any variant promo active (same promo window)
            'promo' => '(' . self::effectiveSql() . ' < p.selling_price OR EXISTS (SELECT 1 FROM product_variants pv2 WHERE pv2.product_id = p.id AND pv2.is_active = 1 AND pv2.promo_price IS NOT NULL AND pv2.promo_price < pv2.price AND ' . self::promoWindow('p') . '))',
            default => '1 = 1',
        };
        return [' WHERE ' . implode(' AND ', $w), $params];
    }

    /** Category slug → ids of the category and all its descendants. @return list<int> */
    public static function categoryTreeIds(string $slug): array
    {
        $all = Database::instance()->fetchAll('SELECT id, parent_id, slug FROM categories WHERE is_active = 1');
        $root = null;
        foreach ($all as $c) {
            if ($c['slug'] === $slug) {
                $root = (int) $c['id'];
            }
        }
        if ($root === null) {
            return [];
        }
        $ids = [$root];
        $changed = true;
        while ($changed) {
            $changed = false;
            foreach ($all as $c) {
                if ($c['parent_id'] !== null && in_array((int) $c['parent_id'], $ids, true) && !in_array((int) $c['id'], $ids, true)) {
                    $ids[] = (int) $c['id'];
                    $changed = true;
                }
            }
        }
        return $ids;
    }

    /** @return list<array<string,mixed>> */
    public static function byFlag(string $flag, int $limit = 8, string $sort = 'featured'): array
    {
        return self::listing(['flag' => $flag, 'sort' => $sort], $limit, 1)->items;
    }

    /**
     * Add translated title/category, promo flags and percent off.
     *
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    public static function decorate(array $rows): array
    {
        if ($rows === []) {
            return [];
        }
        $rows = TranslationService::apply('product', $rows, ['title', 'short_description']);
        $catRows = array_values(array_filter(array_map(fn ($r) => $r['category_id'] !== null ? ['id' => (int) $r['category_id'], 'name' => $r['category_name']] : null, $rows)));
        $catNames = [];
        foreach (TranslationService::apply('category', $catRows, ['name']) as $c) {
            $catNames[$c['id']] = $c['name'];
        }
        foreach ($rows as &$r) {
            if ($r['category_id'] !== null) {
                $r['category_name'] = $catNames[(int) $r['category_id']] ?? $r['category_name'];
            }
            $r['display_price'] = Money::normalize((string) $r['display_price']);
            $r['compare_price'] = Money::normalize((string) $r['compare_price']);
            $r['on_sale'] = Money::compare($r['display_price'], $r['compare_price']) < 0;
            $r['discount_percent'] = $r['on_sale'] && Money::toCents($r['compare_price']) > 0
                ? (int) round(100 - Money::toCents($r['display_price']) * 100 / Money::toCents($r['compare_price']))
                : 0;
            $r['from_price'] = (int) $r['variant_count'] > 1;
            $r['avg_rating'] = round((float) $r['avg_rating'], 1);
            $r['out_of_stock'] = (int) $r['track_stock'] === 1 && (int) $r['stock_quantity'] <= 0;
        }
        unset($r);
        return $rows;
    }

    /** Full product for the detail page. @return array<string,mixed>|null */
    public static function product(string $slug): ?array
    {
        $db = Database::instance();
        $row = $db->fetch(self::baseSelect() . ' WHERE p.slug = :slug AND p.is_active = 1 AND p.deleted_at IS NULL', ['slug' => $slug]);
        if ($row === null) {
            return null;
        }
        $extra = $db->fetch(
            'SELECT description, seo_title, seo_description, promo_price, promo_ends_at, promo_starts_at, base_price,
                    min_panels, max_panels, allow_custom_size, collection_id
             FROM products WHERE id = ?',
            [(int) $row['id']]
        ) ?? [];
        $product = self::decorate([array_merge($row, $extra)])[0];
        $product = TranslationService::applyOne('product', $product, self::PRODUCT_FIELDS);
        $promoOpen = $product['promo_price'] !== null
            && ($product['promo_starts_at'] === null || strtotime((string) $product['promo_starts_at']) <= time())
            && ($product['promo_ends_at'] === null || strtotime((string) $product['promo_ends_at']) >= time());

        $product['images'] = $db->fetchAll('SELECT id, path, thumb_path, alt FROM product_images WHERE product_id = ? ORDER BY sort_order, id', [(int) $product['id']]);

        $variants = $db->fetchAll(
            'SELECT v.id, v.sku, v.name, v.width_cm, v.height_cm, v.panels, v.price, v.promo_price, v.stock_quantity, v.is_default,
                    v.product_material_id, m.name AS material_name, v.frame_id, f.name AS frame_name, f.color_hex AS frame_color
             FROM product_variants v
             LEFT JOIN product_materials m ON m.id = v.product_material_id
             LEFT JOIN frames f ON f.id = v.frame_id
             WHERE v.product_id = ? AND v.is_active = 1
             ORDER BY v.sort_order, v.price, v.id',
            [(int) $product['id']]
        );
        foreach ($variants as &$v) {
            $v['effective_price'] = $promoOpen && $v['promo_price'] !== null && Money::compare((string) $v['promo_price'], (string) $v['price']) < 0
                ? Money::normalize((string) $v['promo_price'])
                : Money::normalize((string) $v['price']);
            $v['regular_price'] = Money::normalize((string) $v['price']);
            $v['size_label'] = cm($v['width_cm']) . ' × ' . cm($v['height_cm']) . ' cm';
            $v['out_of_stock'] = (int) $product['track_stock'] === 1 && (int) $v['stock_quantity'] <= 0;
        }
        unset($v);
        $product['variants'] = $variants;

        $product['category'] = $product['category_id'] !== null ? self::category((int) $product['category_id']) : null;
        $product['collection'] = $product['collection_id'] !== null ? self::collectionById((int) $product['collection_id']) : null;
        $product['reviews'] = $db->fetchAll(
            'SELECT author_name, author_city, rating, title, body, is_verified_purchase, created_at
             FROM reviews WHERE product_id = ? AND is_approved = 1 ORDER BY created_at DESC LIMIT 20',
            [(int) $product['id']]
        );
        return $product;
    }

    public static function incrementViews(int $productId): void
    {
        Database::instance()->query('UPDATE products SET views = views + 1 WHERE id = ?', [$productId]);
    }

    /**
     * Manually linked related products first, then same category, then same collection.
     *
     * @param array<string,mixed> $product
     * @return list<array<string,mixed>>
     */
    public static function related(array $product, int $limit = 4): array
    {
        $db = Database::instance();
        $id = (int) $product['id'];
        $linked = array_map('intval', array_column($db->fetchAll('SELECT related_product_id FROM related_products WHERE product_id = ? ORDER BY sort_order LIMIT ' . $limit, [$id]), 'related_product_id'));
        $ids = $linked;
        if (count($ids) < $limit) {
            $exclude = array_merge([$id], $ids);
            $ph = implode(',', array_fill(0, count($exclude), '?'));
            $more = $db->fetchAll(
                "SELECT id FROM products WHERE is_active = 1 AND deleted_at IS NULL AND id NOT IN ({$ph})
                 AND (category_id <=> ? OR collection_id <=> ?) ORDER BY (category_id <=> ?) DESC, is_best_seller DESC, views DESC LIMIT " . ($limit - count($ids)),
                array_merge($exclude, [$product['category_id'], $product['collection_id'] ?? null, $product['category_id']])
            );
            $ids = array_merge($ids, array_map('intval', array_column($more, 'id')));
        }
        if ($ids === []) {
            return [];
        }
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $rows = self::decorate($db->fetchAll(self::baseSelect() . " WHERE p.id IN ({$ph}) AND p.is_active = 1 AND p.deleted_at IS NULL", $ids));
        usort($rows, fn ($a, $b) => array_search((int) $a['id'], $ids, true) <=> array_search((int) $b['id'], $ids, true));
        return $rows;
    }

    // ------------------------------------------------------------ categories

    /** @return list<array<string,mixed>> active categories with active product counts */
    public static function categories(bool $featuredOnly = false): array
    {
        $rows = Database::instance()->fetchAll(
            'SELECT c.id, c.parent_id, c.slug, c.name, c.description, c.image, c.is_featured, c.sort_order,
                    (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND p.is_active = 1 AND p.deleted_at IS NULL) AS product_count
             FROM categories c WHERE c.is_active = 1' . ($featuredOnly ? ' AND c.is_featured = 1' : '') . '
             ORDER BY c.sort_order, c.name'
        );
        return TranslationService::apply('category', $rows, ['name', 'description']);
    }

    /** @return array<string,mixed>|null */
    public static function categoryBySlug(string $slug): ?array
    {
        $row = Database::instance()->fetch('SELECT * FROM categories WHERE slug = ? AND is_active = 1', [$slug]);
        return $row ? TranslationService::applyOne('category', $row, self::CATEGORY_FIELDS) : null;
    }

    /** @return array<string,mixed>|null */
    public static function category(int $id): ?array
    {
        $row = Database::instance()->fetch('SELECT id, slug, name FROM categories WHERE id = ?', [$id]);
        return $row ? TranslationService::applyOne('category', $row, ['name']) : null;
    }

    // ----------------------------------------------------------- collections

    private static function collectionVisible(): string
    {
        return 'is_active = 1 AND (starts_at IS NULL OR starts_at <= NOW()) AND (ends_at IS NULL OR ends_at >= NOW())';
    }

    /** @return list<array<string,mixed>> */
    public static function collections(bool $featuredOnly = false, int $limit = 0): array
    {
        $rows = Database::instance()->fetchAll(
            'SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.collection_id = c.id AND p.is_active = 1 AND p.deleted_at IS NULL) AS product_count
             FROM collections c WHERE ' . self::collectionVisible() . ($featuredOnly ? ' AND is_featured = 1' : '')
            . ' ORDER BY sort_order, name' . ($limit > 0 ? ' LIMIT ' . $limit : '')
        );
        return TranslationService::apply('collection', $rows, ['name', 'description']);
    }

    /** @return array<string,mixed>|null */
    public static function collectionBySlug(string $slug): ?array
    {
        $row = Database::instance()->fetch('SELECT * FROM collections WHERE slug = ? AND ' . self::collectionVisible(), [$slug]);
        return $row ? TranslationService::applyOne('collection', $row, self::COLLECTION_FIELDS) : null;
    }

    /** @return array<string,mixed>|null */
    public static function collectionById(int $id): ?array
    {
        $row = Database::instance()->fetch('SELECT id, slug, name, is_limited FROM collections WHERE id = ? AND ' . self::collectionVisible(), [$id]);
        return $row ? TranslationService::applyOne('collection', $row, ['name']) : null;
    }

    // ---------------------------------------------------------- home content

    /** @return list<array<string,mixed>> */
    public static function banners(string $position, int $limit = 5): array
    {
        $rows = Database::instance()->fetchAll(
            'SELECT * FROM banners WHERE position = ? AND is_active = 1
             AND (starts_at IS NULL OR starts_at <= NOW()) AND (ends_at IS NULL OR ends_at >= NOW())
             ORDER BY sort_order, id LIMIT ' . $limit,
            [$position]
        );
        return TranslationService::apply('banner', $rows, ['title', 'subtitle', 'button_text']);
    }

    /** @return list<array<string,mixed>> approved featured reviews (real customer reviews only) */
    public static function featuredReviews(int $limit = 6): array
    {
        return Database::instance()->fetchAll(
            'SELECT r.author_name, r.author_city, r.rating, r.title, r.body, r.is_verified_purchase, p.title AS product_title, p.slug AS product_slug
             FROM reviews r LEFT JOIN products p ON p.id = r.product_id
             WHERE r.is_approved = 1 AND r.is_featured = 1 ORDER BY r.created_at DESC LIMIT ' . $limit
        );
    }

    /** @return array{avg:float,count:int} */
    public static function reviewStats(): array
    {
        $r = Database::instance()->fetch('SELECT AVG(rating) AS a, COUNT(*) AS c FROM reviews WHERE is_approved = 1');
        return ['avg' => round((float) ($r['a'] ?? 0), 1), 'count' => (int) ($r['c'] ?? 0)];
    }

    /** @return list<array<string,mixed>> customer installations (published with consent only) */
    public static function gallery(int $limit = 8): array
    {
        $rows = Database::instance()->fetchAll(
            'SELECT id, title, city, image FROM gallery_items WHERE is_active = 1 AND customer_consent = 1 ORDER BY sort_order, id DESC LIMIT ' . $limit
        );
        return TranslationService::apply('gallery', $rows, ['title']);
    }

    /** @return list<array<string,mixed>> */
    public static function faqs(int $limit = 50): array
    {
        $rows = Database::instance()->fetchAll('SELECT id, question, answer, category FROM faqs WHERE is_active = 1 ORDER BY sort_order, id LIMIT ' . $limit);
        return TranslationService::apply('faq', $rows, ['question', 'answer']);
    }

    /** @return array{min:string,max:string} */
    public static function priceBounds(): array
    {
        $r = Database::instance()->fetch(
            'SELECT MIN(' . self::displaySql() . ') AS mn, MAX(' . self::displaySql() . ') AS mx FROM products p
             LEFT JOIN (SELECT pv.product_id, MIN(CASE WHEN pv.promo_price IS NOT NULL AND pv.promo_price < pv.price AND ' . self::promoWindow('pp') . ' THEN pv.promo_price ELSE pv.price END) AS min_price
                        FROM product_variants pv JOIN products pp ON pp.id = pv.product_id WHERE pv.is_active = 1 GROUP BY pv.product_id) v ON v.product_id = p.id
             WHERE p.is_active = 1 AND p.deleted_at IS NULL'
        );
        return ['min' => Money::normalize((string) ($r['mn'] ?? '0')), 'max' => Money::normalize((string) ($r['mx'] ?? '0'))];
    }

    /**
     * Current selling price and snapshot data of a product (+ variant) for the cart / order.
     * Returns null when the product or variant is not purchasable.
     *
     * @return array<string,mixed>|null
     */
    public static function purchasable(int $productId, ?int $variantId): ?array
    {
        $db = Database::instance();
        $p = $db->fetch(
            'SELECT id, slug, sku, title, product_type, main_image, category_id, selling_price, promo_price, promo_starts_at, promo_ends_at,
                    cost_price, track_stock, stock_quantity, is_customizable,
                    (SELECT COUNT(*) FROM product_variants WHERE product_id = products.id AND is_active = 1) AS variant_count
             FROM products WHERE id = ? AND is_active = 1 AND deleted_at IS NULL',
            [$productId]
        );
        if ($p === null) {
            return null;
        }
        $p = TranslationService::applyOne('product', $p, ['title']);
        $promoOpen = $p['promo_price'] !== null
            && ($p['promo_starts_at'] === null || strtotime((string) $p['promo_starts_at']) <= time())
            && ($p['promo_ends_at'] === null || strtotime((string) $p['promo_ends_at']) >= time());
        $out = [
            'product_id' => (int) $p['id'], 'variant_id' => null, 'slug' => $p['slug'], 'sku' => $p['sku'], 'title' => $p['title'],
            'product_type' => $p['product_type'], 'image' => $p['main_image'], 'category_id' => $p['category_id'] !== null ? (int) $p['category_id'] : null,
            'variant_name' => null, 'width_cm' => null, 'height_cm' => null, 'material_name' => null, 'frame_name' => null, 'panels' => 1,
            'track_stock' => (int) $p['track_stock'] === 1, 'stock' => (int) $p['stock_quantity'], 'is_customizable' => (int) $p['is_customizable'] === 1,
        ];
        if ((int) $p['variant_count'] > 0) {
            if ($variantId === null) {
                return null; // a size/material must be chosen
            }
            $v = $db->fetch(
                'SELECT v.*, m.name AS material_name, f.name AS frame_name FROM product_variants v
                 LEFT JOIN product_materials m ON m.id = v.product_material_id LEFT JOIN frames f ON f.id = v.frame_id
                 WHERE v.id = ? AND v.product_id = ? AND v.is_active = 1',
                [$variantId, $productId]
            );
            if ($v === null) {
                return null;
            }
            $price = $promoOpen && $v['promo_price'] !== null && Money::compare((string) $v['promo_price'], (string) $v['price']) < 0 ? (string) $v['promo_price'] : (string) $v['price'];
            return array_merge($out, [
                'variant_id' => (int) $v['id'], 'sku' => $v['sku'], 'variant_name' => $v['name'], 'width_cm' => (float) $v['width_cm'], 'height_cm' => (float) $v['height_cm'],
                'material_name' => $v['material_name'], 'frame_name' => $v['frame_name'], 'panels' => (int) $v['panels'],
                'unit_price' => Money::normalize($price), 'regular_price' => Money::normalize((string) $v['price']), 'unit_cost' => Money::normalize((string) $v['cost_price']),
                'stock' => $out['track_stock'] ? min((int) $p['stock_quantity'], (int) $v['stock_quantity']) : 0,
            ]);
        }
        $price = $promoOpen && Money::compare((string) $p['promo_price'], (string) $p['selling_price']) < 0 ? (string) $p['promo_price'] : (string) $p['selling_price'];
        return array_merge($out, [
            'unit_price' => Money::normalize($price), 'regular_price' => Money::normalize((string) $p['selling_price']), 'unit_cost' => Money::normalize((string) $p['cost_price']),
        ]);
    }
}
