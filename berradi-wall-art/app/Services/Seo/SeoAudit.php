<?php

declare(strict_types=1);

namespace App\Services\Seo;

use App\Core\Database;

/**
 * SEO audit of the catalogue: what Google shows (title ≈ 60 characters, description ≈ 155) and what helps ranking
 * (unique titles, image alt texts, Arabic version, readable URL). Each item gets a score and the list of fixes,
 * the worst first. Read-only.
 */
final class SeoAudit
{
    public const TITLE_MIN = 30;
    public const TITLE_MAX = 60;
    public const DESC_MIN = 70;
    public const DESC_MAX = 160;

    /** @return array{items:list<array<string,mixed>>,summary:array<string,int>} */
    public static function run(): array
    {
        $db = Database::instance();
        $items = [];
        $ar = [];
        foreach ($db->fetchAll("SELECT entity_type, entity_id, field, value FROM translations WHERE locale = 'ar' AND entity_type IN ('product','category','collection') AND value <> ''") as $t) {
            $ar[$t['entity_type']][(int) $t['entity_id']][$t['field']] = $t['value'];
        }
        $products = $db->fetchAll(
            "SELECT p.id, p.title, p.slug, p.seo_title, p.seo_description, p.short_description, p.description, p.main_image,
                    (SELECT COUNT(*) FROM product_images i WHERE i.product_id = p.id AND (i.alt IS NULL OR i.alt = '')) AS no_alt
             FROM products p WHERE p.is_active = 1 AND p.deleted_at IS NULL ORDER BY p.title"
        );
        $titles = [];
        foreach ($products as $p) {
            $titles[mb_strtolower(self::effectiveTitle($p))][] = (int) $p['id'];
        }
        foreach ($products as $p) {
            $issues = self::common($p, 'product', $ar['product'][(int) $p['id']] ?? []);
            if (count($titles[mb_strtolower(self::effectiveTitle($p))]) > 1) {
                $issues[] = 'duplicate_title';
            }
            if ((int) $p['no_alt'] > 0) {
                $issues[] = 'missing_alt';
            }
            if (empty($p['main_image'])) {
                $issues[] = 'no_image';
            }
            $items[] = self::item('product', $p, $issues, 9);
        }
        foreach (['category' => 'categories', 'collection' => 'collections'] as $type => $table) {
            foreach ($db->fetchAll("SELECT id, name AS title, slug, seo_title, seo_description, description, NULL AS short_description FROM {$table} WHERE is_active = 1 ORDER BY name") as $c) {
                $items[] = self::item($type, $c, self::common($c, $type, $ar[$type][(int) $c['id']] ?? [], 'name'), 6);
            }
        }
        usort($items, fn ($a, $b) => [$a['score'], $a['title']] <=> [$b['score'], $b['title']]);
        $n = count($items);
        $perfect = count(array_filter($items, fn ($i) => $i['issues'] === []));
        return [
            'items' => $items,
            'summary' => [
                'items' => $n,
                'perfect' => $perfect,
                'score' => $n > 0 ? (int) round(array_sum(array_column($items, 'score')) / $n) : 100,
                'issues' => array_sum(array_map(fn ($i) => count($i['issues']), $items)),
            ],
        ];
    }

    /** Title Google will show (the site name is appended by the layout). @param array<string,mixed> $row */
    public static function effectiveTitle(array $row): string
    {
        return trim((string) (($row['seo_title'] ?? '') !== '' && $row['seo_title'] !== null ? $row['seo_title'] : $row['title']));
    }

    /** @param array<string,mixed> $row */
    public static function effectiveDescription(array $row): string
    {
        foreach (['seo_description', 'short_description', 'description'] as $f) {
            $v = trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) ($row[$f] ?? ''))));
            if ($v !== '') {
                return $v;
            }
        }
        return '';
    }

    /**
     * @param array<string,mixed>  $row
     * @param array<string,string> $ar
     * @return list<string>
     */
    private static function common(array $row, string $type, array $ar, string $nameField = 'title'): array
    {
        $issues = [];
        $title = self::effectiveTitle($row);
        $len = mb_strlen($title);
        if ($len < self::TITLE_MIN) {
            $issues[] = 'title_short';
        } elseif ($len > self::TITLE_MAX) {
            $issues[] = 'title_long';
        }
        $explicitDesc = trim((string) ($row['seo_description'] ?? ''));
        $dlen = mb_strlen($explicitDesc);
        if ($explicitDesc === '') {
            $issues[] = 'no_meta_description';
        } elseif ($dlen < self::DESC_MIN) {
            $issues[] = 'desc_short';
        } elseif ($dlen > self::DESC_MAX) {
            $issues[] = 'desc_long';
        }
        if (trim((string) ($ar[$nameField] ?? '')) === '') {
            $issues[] = 'no_ar_title';
        }
        if (trim((string) ($ar['seo_description'] ?? $ar['description'] ?? '')) === '') {
            $issues[] = 'no_ar_description';
        }
        if (mb_strlen((string) $row['slug']) > 75) {
            $issues[] = 'slug_long';
        }
        if ($type !== 'product' && mb_strlen(strip_tags((string) ($row['description'] ?? ''))) < 80) {
            $issues[] = 'thin_content';
        }
        return $issues;
    }

    /**
     * @param array<string,mixed> $row
     * @param list<string>        $issues
     * @return array<string,mixed>
     */
    private static function item(string $type, array $row, array $issues, int $checks): array
    {
        return [
            'type' => $type,
            'id' => (int) $row['id'],
            'title' => (string) $row['title'],
            'slug' => (string) $row['slug'],
            'seo_title' => self::effectiveTitle($row),
            'seo_description' => self::effectiveDescription($row),
            'issues' => $issues,
            'score' => max(0, (int) round(100 - count($issues) * 100 / $checks)),
            'url' => match ($type) {
                'product' => '/product/' . $row['slug'],
                'category' => '/shop/category/' . $row['slug'],
                default => '/collections/' . $row['slug'],
            },
        ];
    }
}
