<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Core\Database;
use App\Services\TranslationService;

/**
 * Recipients ("pour Maman", "pour lui"…) and occasions (anniversaire, mariage, Aïd…) attached to products.
 */
final class Tags
{
    public const TYPES = ['recipient', 'occasion'];

    /** @var array<string,list<array<string,mixed>>> */
    private static array $cache = [];

    /**
     * Active tags of a type, translated, with the number of visible products (menus hide empty ones on request).
     *
     * @return list<array{id:int,slug:string,name:string,icon:?string,product_count:int}>
     */
    public static function all(string $type, bool $activeOnly = true): array
    {
        $key = $type . ($activeOnly ? '1' : '0') . locale();
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }
        try {
            $rows = Database::instance()->fetchAll(
                'SELECT t.id, t.slug, t.name, t.icon, t.is_active, t.sort_order,
                        (SELECT COUNT(*) FROM product_tag_map m JOIN products p ON p.id = m.product_id
                         WHERE m.tag_id = t.id AND p.is_active = 1 AND p.deleted_at IS NULL) AS product_count
                 FROM product_tags t WHERE t.tag_type = ?' . ($activeOnly ? ' AND t.is_active = 1' : '') . ' ORDER BY t.sort_order, t.name',
                [$type]
            );
        } catch (\Throwable) {
            return []; // before the v1.3 update
        }
        $rows = TranslationService::apply('product_tag', $rows, ['name']);
        return self::$cache[$key] = array_map(fn ($r) => [
            'id' => (int) $r['id'], 'slug' => (string) $r['slug'], 'name' => (string) $r['name'], 'icon' => $r['icon'],
            'is_active' => (int) $r['is_active'], 'sort_order' => (int) $r['sort_order'], 'product_count' => (int) $r['product_count'],
        ], $rows);
    }

    /** @return array<string,mixed>|null */
    public static function find(string $type, string $slug): ?array
    {
        foreach (self::all($type) as $t) {
            if ($t['slug'] === $slug) {
                return $t;
            }
        }
        return null;
    }

    /** @return list<int> */
    public static function forProduct(int $productId): array
    {
        try {
            return array_map('intval', array_column(Database::instance()->fetchAll('SELECT tag_id FROM product_tag_map WHERE product_id = ?', [$productId]), 'tag_id'));
        } catch (\Throwable) {
            return [];
        }
    }

    /** @param list<int> $tagIds */
    public static function saveForProduct(int $productId, array $tagIds): void
    {
        $db = Database::instance();
        $valid = $tagIds === [] ? [] : array_map('intval', array_column($db->fetchAll(
            'SELECT id FROM product_tags WHERE id IN (' . implode(',', array_fill(0, count($tagIds), '?')) . ')',
            array_values($tagIds)
        ), 'id'));
        $db->transaction(function (Database $db) use ($productId, $valid): void {
            $db->query('DELETE FROM product_tag_map WHERE product_id = ?', [$productId]);
            foreach (array_unique($valid) as $id) {
                $db->insert('product_tag_map', ['product_id' => $productId, 'tag_id' => $id]);
            }
        });
        self::$cache = [];
    }

    /** Attach tags by slug (templates). @param list<string> $recipients @param list<string> $occasions */
    public static function attachSlugs(int $productId, array $recipients, array $occasions): void
    {
        $db = Database::instance();
        foreach (['recipient' => $recipients, 'occasion' => $occasions] as $type => $slugs) {
            foreach ($slugs as $slug) {
                $id = $db->value('SELECT id FROM product_tags WHERE tag_type = ? AND slug = ?', [$type, $slug]);
                if ($id !== null) {
                    $db->query('INSERT IGNORE INTO product_tag_map (product_id, tag_id) VALUES (?, ?)', [$productId, (int) $id]);
                }
            }
        }
        self::$cache = [];
    }

    public static function forget(): void
    {
        self::$cache = [];
    }
}
