<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Core\Database;
use App\Services\TranslationService;

/**
 * Data of the storefront mega menus, built from the real catalogue (nothing hard-coded):
 *  - the three universes with their categories (photo tiles from the category image or its best product);
 *  - recipients and occasions that have products.
 * One query for the categories, two for the tags; kept for the request.
 */
final class Navigation
{
    /** Parent category of each universe (seeded); wall art = every other top-level category. */
    public const UNIVERSE_PARENTS = ['gift' => 'personalized-gifts', 'laser' => 'laser-engraving-cutting'];

    /** @var array<string,mixed>|null */
    private static ?array $menu = null;

    /** @return array<string,mixed> */
    public static function menu(): array
    {
        if (self::$menu !== null) {
            return self::$menu;
        }
        try {
            $cats = Database::instance()->fetchAll(
                "SELECT c.id, c.slug, c.name, c.parent_id, c.image, c.sort_order,
                        (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND p.is_active = 1 AND p.deleted_at IS NULL) AS n,
                        (SELECT p.main_image FROM products p WHERE p.category_id = c.id AND p.is_active = 1 AND p.deleted_at IS NULL AND p.main_image IS NOT NULL
                         ORDER BY p.is_featured DESC, p.is_best_seller DESC, p.id DESC LIMIT 1) AS product_image
                 FROM categories c WHERE c.is_active = 1 ORDER BY c.sort_order, c.name"
            );
        } catch (\Throwable) {
            return self::$menu = ['wall' => self::empty(), 'gift' => self::empty(), 'laser' => self::empty(), 'recipients' => [], 'occasions' => []];
        }
        $cats = TranslationService::apply('category', $cats, ['name']);
        $bySlug = array_column($cats, null, 'slug');
        $parentIds = [];
        foreach (self::UNIVERSE_PARENTS as $family => $slug) {
            $parentIds[$family] = isset($bySlug[$slug]) ? (int) $bySlug[$slug]['id'] : null;
        }
        $groups = ['wall' => [], 'gift' => [], 'laser' => []];
        foreach ($cats as $c) {
            $item = [
                'name' => (string) $c['name'], 'url' => route('shop.category', ['slug' => $c['slug']]), 'count' => (int) $c['n'],
                'image' => $c['image'] ?: $c['product_image'] ?: null,
            ];
            $parent = $c['parent_id'] !== null ? (int) $c['parent_id'] : null;
            $family = array_search($parent, $parentIds, true);
            if ($family !== false) {
                $groups[$family][] = $item;
            } elseif ($parent === null && !in_array((int) $c['id'], array_filter($parentIds), true)) {
                $groups['wall'][] = $item;
            }
        }
        $out = [];
        foreach ($groups as $family => $items) {
            $withProducts = array_values(array_filter($items, fn ($i) => $i['count'] > 0));
            $out[$family] = [
                // tiles: categories that have products and a photo; text list: categories that have products
                // (all of them while the universe is still empty, so the menu is never blank)
                'tiles' => array_slice(array_values(array_filter($withProducts, fn ($i) => $i['image'] !== null)), 0, 6),
                'categories' => $withProducts !== [] ? $withProducts : $items,
            ];
        }
        $out['recipients'] = array_values(array_filter(Tags::all('recipient'), fn ($t) => $t['product_count'] > 0));
        $out['occasions'] = array_values(array_filter(Tags::all('occasion'), fn ($t) => $t['product_count'] > 0));
        return self::$menu = $out;
    }

    /** @return array{tiles:list<mixed>,categories:list<mixed>} */
    private static function empty(): array
    {
        return ['tiles' => [], 'categories' => []];
    }

    public static function forget(): void
    {
        self::$menu = null;
    }
}
