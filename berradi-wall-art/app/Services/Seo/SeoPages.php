<?php

declare(strict_types=1);

namespace App\Services\Seo;

use App\Core\Database;

/**
 * Title / description / noindex overrides for the fixed public pages, per language.
 * Products, categories and collections have their own SEO fields on their edit forms.
 */
final class SeoPages
{
    /** canonical path => label translation key */
    public const PAGES = [
        '/' => 'nav.home',
        '/shop' => 'nav.shop',
        '/collections' => 'nav.collections',
        '/custom-wall-art' => 'builder.title',
        '/best-sellers' => 'nav.best_sellers',
        '/new-arrivals' => 'nav.new_arrivals',
        '/offers' => 'nav.offers',
        '/about' => 'nav.about',
        '/contact' => 'nav.contact',
        '/faq' => 'pages.faq',
        '/terms' => 'pages.terms',
    ];

    /** @var array<string,array<string,array<string,mixed>>>|null locale => path => row */
    private static ?array $cache = null;

    /** @return array{meta_title:?string,meta_description:?string,noindex:int}|null */
    public static function override(string $path, string $locale): ?array
    {
        if (!isset(self::PAGES[$path])) {
            return null;
        }
        if (self::$cache === null) {
            self::$cache = [];
            try {
                foreach (Database::instance()->fetchAll('SELECT page_key, locale, meta_title, meta_description, noindex FROM seo_pages') as $r) {
                    self::$cache[$r['locale']][$r['page_key']] = $r;
                }
            } catch (\Throwable) {
                self::$cache = []; // before the v1.1 migration
            }
        }
        return self::$cache[$locale][$path] ?? null;
    }

    /** @param array<string,array{meta_title:?string,meta_description:?string,noindex:bool}> $byLocale */
    public static function save(string $path, array $byLocale): void
    {
        if (!isset(self::PAGES[$path])) {
            throw new \InvalidArgumentException('Unknown page');
        }
        $db = Database::instance();
        foreach ($byLocale as $locale => $v) {
            $title = trim((string) ($v['meta_title'] ?? ''));
            $desc = trim((string) ($v['meta_description'] ?? ''));
            if ($title === '' && $desc === '' && empty($v['noindex'])) {
                $db->query('DELETE FROM seo_pages WHERE page_key = ? AND locale = ?', [$path, $locale]);
                continue;
            }
            $db->query(
                'INSERT INTO seo_pages (page_key, locale, meta_title, meta_description, noindex) VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE meta_title = VALUES(meta_title), meta_description = VALUES(meta_description), noindex = VALUES(noindex)',
                [$path, $locale, $title !== '' ? $title : null, $desc !== '' ? $desc : null, !empty($v['noindex']) ? 1 : 0]
            );
        }
        self::$cache = null;
        SitemapService::forget();
    }

    /** Paths the owner set to noindex in every language (left out of the sitemap). @return list<string> */
    public static function noindexPaths(): array
    {
        try {
            $rows = Database::instance()->fetchAll('SELECT page_key, MIN(noindex) AS n FROM seo_pages GROUP BY page_key');
        } catch (\Throwable) {
            return [];
        }
        $out = [];
        foreach ($rows as $r) {
            if ((int) $r['n'] === 1) {
                $out[] = (string) $r['page_key'];
            }
        }
        return $out;
    }

    public static function reset(): void
    {
        self::$cache = null;
    }
}
