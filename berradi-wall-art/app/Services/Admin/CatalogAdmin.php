<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Core\Database;
use App\Helpers\Money;
use App\Helpers\Str;
use App\Services\ActivityLogger;
use App\Services\Builder\PricingService;
use App\Services\ImageUploadService;
use App\Services\TranslationService;

/**
 * Write-side of the catalogue for the administration (products, images, variants, categories, collections).
 */
final class CatalogAdmin
{
    public const PRODUCT_AR_FIELDS = ['title', 'short_description', 'description', 'seo_title', 'seo_description', 'option1_name', 'option2_name'];
    public const TAXONOMY_AR_FIELDS = ['name', 'description', 'seo_title', 'seo_description'];

    /** Unique slug in a table (appends -2, -3… when taken). */
    public static function uniqueSlug(string $table, string $wanted, ?int $ignoreId = null): string
    {
        $base = Str::slug($wanted);
        $slug = $base;
        $i = 2;
        while (Database::instance()->table($table)->where('slug', $slug)->where('id', '!=', $ignoreId ?? 0)->exists()) {
            $slug = substr($base, 0, 170) . '-' . $i++;
        }
        return $slug;
    }

    public static function uniqueSku(string $table, string $wanted, ?int $ignoreId = null): string
    {
        $base = strtoupper(substr((string) preg_replace('/[^A-Za-z0-9-]+/', '-', $wanted), 0, 50)) ?: 'BWA';
        $sku = $base;
        $i = 2;
        while (Database::instance()->table($table)->where('sku', $sku)->where('id', '!=', $ignoreId ?? 0)->exists()) {
            $sku = $base . '-' . $i++;
        }
        return $sku;
    }

    /**
     * Store a catalogue image + a 400px thumbnail. @param array<string,mixed> $file
     * @return array{path:string,thumb:?string}
     */
    public static function storeImage(array $file, string $folder, int $maxPx = 1800): array
    {
        $path = ImageUploadService::storePublic($file, $folder, $maxPx);
        $abs = public_path('uploads/' . $path);
        $thumb = preg_replace('/(\.[a-z]+)$/', '_t$1', $path);
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($abs);
        $ok = $thumb !== null && ImageUploadService::reencode($abs, public_path('uploads/' . $thumb), $mime, 400, 82);
        return ['path' => $path, 'thumb' => $ok ? $thumb : null];
    }

    /** Delete an image file only when no other record still uses it (duplicated products share files). */
    public static function releaseImage(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }
        $db = Database::instance();
        $uses = (int) $db->value('SELECT COUNT(*) FROM product_images WHERE path = ? OR thumb_path = ?', [$path, $path])
            + (int) $db->value('SELECT COUNT(*) FROM products WHERE main_image = ?', [$path])
            + (int) $db->value('SELECT COUNT(*) FROM categories WHERE image = ?', [$path])
            + (int) $db->value('SELECT COUNT(*) FROM collections WHERE image = ?', [$path]);
        if ($uses === 0) {
            ImageUploadService::deletePublic($path);
        }
    }

    /**
     * Create or update a product from validated data.
     *
     * @param array<string,mixed> $d
     * @param array<string,mixed> $ar Arabic translations
     * @param list<int> $materials allowed print supports (empty = all)
     * @param list<int> $frames allowed frames (empty = all)
     * @param list<int> $related
     */
    public static function saveProduct(?int $id, array $d, array $ar, array $materials, array $frames, array $related): int
    {
        $db = Database::instance();
        return $db->transaction(function (Database $db) use ($id, $d, $ar, $materials, $frames, $related): int {
            $d['slug'] = self::uniqueSlug('products', (string) (($d['slug'] ?? '') ?: $d['title']), $id);
            $d['sku'] = self::uniqueSku('products', (string) (($d['sku'] ?? '') ?: 'BWA-' . substr(Str::slug((string) $d['title']), 0, 20)), $id);
            if ($id === null) {
                $id = $db->insert('products', $d);
                ActivityLogger::log('product.created', 'product', $id, (string) $d['title']);
            } else {
                $db->update('products', $d, ['id' => $id]);
                ActivityLogger::log('product.updated', 'product', $id, (string) $d['title']);
            }
            TranslationService::save('product', $id, 'ar', $ar);
            \App\Services\Seo\SitemapService::forget();
            $db->delete('product_material_map', ['product_id' => $id]);
            foreach (array_unique($materials) as $m) {
                $db->query('INSERT IGNORE INTO product_material_map (product_id, product_material_id) SELECT ?, id FROM product_materials WHERE id = ?', [$id, $m]);
            }
            $db->delete('product_frame_map', ['product_id' => $id]);
            foreach (array_unique($frames) as $f) {
                $db->query('INSERT IGNORE INTO product_frame_map (product_id, frame_id) SELECT ?, id FROM frames WHERE id = ?', [$id, $f]);
            }
            $db->delete('related_products', ['product_id' => $id]);
            foreach (array_values(array_unique($related)) as $i => $r) {
                if ($r !== $id) {
                    $db->query('INSERT IGNORE INTO related_products (product_id, related_product_id, sort_order) SELECT ?, id, ? FROM products WHERE id = ? AND deleted_at IS NULL', [$id, $i, $r]);
                }
            }
            return $id;
        });
    }

    /** Copy a product (inactive draft) with images, variants, options, relations and translations. */
    public static function duplicate(int $id): int
    {
        $db = Database::instance();
        $p = $db->fetch('SELECT * FROM products WHERE id = ? AND deleted_at IS NULL', [$id]);
        if ($p === null) {
            throw new \RuntimeException('Product not found');
        }
        return $db->transaction(function (Database $db) use ($p, $id): int {
            $copy = $p;
            unset($copy['id'], $copy['created_at'], $copy['updated_at'], $copy['deleted_at']);
            $copy['title'] = mb_substr($p['title'] . ' (copie)', 0, 191);
            $copy['slug'] = self::uniqueSlug('products', $p['slug'] . '-copie');
            $copy['sku'] = self::uniqueSku('products', $p['sku'] . '-C');
            $copy['is_active'] = 0;
            $copy['views'] = 0;
            $copy['is_best_seller'] = 0;
            $newId = $db->insert('products', $copy);
            foreach ($db->fetchAll('SELECT path, thumb_path, alt, sort_order FROM product_images WHERE product_id = ?', [$id]) as $img) {
                $db->insert('product_images', $img + ['product_id' => $newId]);
            }
            $variantMap = [];
            foreach ($db->fetchAll('SELECT * FROM product_variants WHERE product_id = ?', [$id]) as $v) {
                $oldVid = (int) $v['id'];
                unset($v['id'], $v['created_at'], $v['updated_at']);
                $v['product_id'] = $newId;
                $v['sku'] = self::uniqueSku('product_variants', $v['sku'] . '-C');
                $variantMap[$oldVid] = $db->insert('product_variants', $v);
                $db->query("INSERT INTO translations (entity_type, entity_id, locale, field, value) SELECT entity_type, ?, locale, field, value FROM translations WHERE entity_type = 'product_variant' AND entity_id = ?", [$variantMap[$oldVid], $oldVid]);
            }
            // gifts & laser: personalisation fields (with their Arabic texts), price breaks, bill of materials, tags
            foreach ($db->fetchAll('SELECT * FROM product_personalization_fields WHERE product_id = ?', [$id]) as $f) {
                $oldFid = (int) $f['id'];
                unset($f['id'], $f['created_at'], $f['updated_at']);
                $newFid = $db->insert('product_personalization_fields', ['product_id' => $newId] + $f);
                $db->query("INSERT INTO translations (entity_type, entity_id, locale, field, value) SELECT entity_type, ?, locale, field, value FROM translations WHERE entity_type = 'personalization_field' AND entity_id = ?", [$newFid, $oldFid]);
            }
            $db->query('INSERT INTO product_price_tiers (product_id, min_qty, discount_percent) SELECT ?, min_qty, discount_percent FROM product_price_tiers WHERE product_id = ?', [$newId, $id]);
            foreach ($db->fetchAll('SELECT * FROM product_components WHERE product_id = ?', [$id]) as $c) {
                $db->insert('product_components', ['product_id' => $newId, 'product_variant_id' => $c['product_variant_id'] !== null ? ($variantMap[(int) $c['product_variant_id']] ?? null) : null,
                    'material_id' => (int) $c['material_id'], 'quantity' => $c['quantity']]);
            }
            $db->query('INSERT INTO product_tag_map (product_id, tag_id) SELECT ?, tag_id FROM product_tag_map WHERE product_id = ?', [$newId, $id]);
            $db->query('INSERT INTO product_material_map (product_id, product_material_id, price_adjustment) SELECT ?, product_material_id, price_adjustment FROM product_material_map WHERE product_id = ?', [$newId, $id]);
            $db->query('INSERT INTO product_frame_map (product_id, frame_id) SELECT ?, frame_id FROM product_frame_map WHERE product_id = ?', [$newId, $id]);
            $db->query('INSERT INTO product_option_map (product_id, customization_option_id) SELECT ?, customization_option_id FROM product_option_map WHERE product_id = ?', [$newId, $id]);
            $db->query('INSERT INTO related_products (product_id, related_product_id, sort_order) SELECT ?, related_product_id, sort_order FROM related_products WHERE product_id = ?', [$newId, $id]);
            $db->query("INSERT INTO translations (entity_type, entity_id, locale, field, value) SELECT entity_type, ?, locale, field, value FROM translations WHERE entity_type = 'product' AND entity_id = ?", [$newId, $id]);
            ActivityLogger::log('product.duplicated', 'product', $newId, 'from #' . $id);
            return $newId;
        });
    }

    /**
     * Soft delete: hidden everywhere, kept for order history; slug/SKU are freed for reuse.
     */
    public static function softDelete(int $id): bool
    {
        $db = Database::instance();
        $ok = $db->query(
            "UPDATE products SET deleted_at = NOW(), is_active = 0, slug = CONCAT(LEFT(slug, 160), '-deleted-', id), sku = CONCAT(LEFT(sku, 40), '-DEL-', id)
             WHERE id = ? AND deleted_at IS NULL",
            [$id]
        )->rowCount() === 1;
        if ($ok) {
            $db->query('DELETE FROM cart_items WHERE product_id = ? AND custom_design_id IS NULL', [$id]);
            ActivityLogger::log('product.deleted', 'product', $id);
            \App\Services\Seo\SitemapService::forget();
        }
        return $ok;
    }

    /** Recompute product display prices from its variants (from-price) when variants exist. */
    public static function syncFromVariants(int $productId): void
    {
        $db = Database::instance();
        $min = $db->value('SELECT MIN(price) FROM product_variants WHERE product_id = ? AND is_active = 1', [$productId]);
        if ($min !== null) {
            $db->update('products', ['selling_price' => Money::normalize((string) $min)], ['id' => $productId]);
        }
    }

    /**
     * Create variants for every combination of sizes × supports × frames (frame null = no frame),
     * with a suggested price from the builder pricing engine. Existing combinations are skipped.
     *
     * @param list<array{w:float,h:float}> $sizes
     * @param list<int> $materials
     * @param list<?int> $frames
     * @return int number created
     */
    public static function generateVariants(int $productId, array $sizes, array $materials, array $frames, int $panels = 1, int $roundTo = 9): int
    {
        $db = Database::instance();
        $p = $db->fetch('SELECT id, sku FROM products WHERE id = ?', [$productId]);
        if ($p === null) {
            return 0;
        }
        $layout = (string) ($db->value("SELECT code FROM customization_options WHERE option_group = 'panel_layout' AND panels = ? AND is_active = 1 LIMIT 1", [$panels]) ?? 'single');
        $count = 0;
        $order = (int) $db->value('SELECT COALESCE(MAX(sort_order), 0) FROM product_variants WHERE product_id = ?', [$productId]);
        $hasDefault = (int) $db->value('SELECT COUNT(*) FROM product_variants WHERE product_id = ? AND is_default = 1', [$productId]) > 0;
        foreach ($sizes as $s) {
            foreach ($materials as $m) {
                $mat = $db->fetch('SELECT id, code, name, supports_frame FROM product_materials WHERE id = ?', [$m]);
                if ($mat === null) {
                    continue;
                }
                foreach ($frames as $f) {
                    if ($f !== null && (int) $mat['supports_frame'] !== 1) {
                        continue;
                    }
                    $exists = (int) $db->value(
                        'SELECT COUNT(*) FROM product_variants WHERE product_id = ? AND width_cm = ? AND height_cm = ? AND product_material_id = ? AND frame_id <=> ?',
                        [$productId, number_format($s['w'], 1, '.', ''), number_format($s['h'], 1, '.', ''), $m, $f]
                    );
                    if ($exists > 0) {
                        continue;
                    }
                    $q = PricingService::quote(['material_id' => (int) $m, 'frame_id' => $f, 'layout' => $layout, 'panel_width_cm' => $s['w'], 'panel_height_cm' => $s['h']]);
                    if (!$q['ok']) {
                        continue; // size outside this support's limits
                    }
                    $price = Money::toCents($q['unit_price']);
                    if ($roundTo === 9) {
                        $dh = (int) ceil($price / 100);
                        $price = ((int) (ceil(($dh + 1) / 10) * 10) - 1) * 100;
                    }
                    $frameName = $f !== null ? (string) $db->value('SELECT name FROM frames WHERE id = ?', [$f]) : null;
                    $sizeLabel = cm($s['w']) . '×' . cm($s['h']) . ' cm';
                    $db->insert('product_variants', [
                        'product_id' => $productId,
                        'sku' => self::uniqueSku('product_variants', $p['sku'] . '-' . (int) $s['w'] . 'X' . (int) $s['h'] . '-' . strtoupper(substr((string) $mat['code'], 0, 3)) . ($f !== null ? '-F' . $f : '')),
                        'name' => $sizeLabel . ' · ' . $mat['name'] . ($frameName ? ' · ' . $frameName : ''),
                        'width_cm' => number_format($s['w'], 1, '.', ''),
                        'height_cm' => number_format($s['h'], 1, '.', ''),
                        'product_material_id' => (int) $m,
                        'frame_id' => $f,
                        'panels' => $panels,
                        'price' => Money::fromCents($price),
                        'cost_price' => $q['unit_cost'],
                        'is_default' => !$hasDefault && $count === 0 ? 1 : 0,
                        'sort_order' => ++$order,
                    ]);
                    $count++;
                }
            }
        }
        if ($count > 0) {
            self::syncFromVariants($productId);
            ActivityLogger::log('product.variants_generated', 'product', $productId, (string) $count);
        }
        return $count;
    }

    /** Arabic translation values for the edit form. @return array<string,string> */
    public static function arValues(string $type, ?int $id): array
    {
        return $id === null ? [] : TranslationService::get($type, $id, 'ar');
    }
}
