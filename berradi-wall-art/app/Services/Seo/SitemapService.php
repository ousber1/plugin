<?php

declare(strict_types=1);

namespace App\Services\Seo;

use App\Core\Database;
use App\Services\SeoService;

/**
 * sitemap.xml: public pages, active categories / collections / products, each with its French and Arabic
 * URL (xhtml:link alternates) and product images. Cached in storage/cache for one hour.
 */
final class SitemapService
{
    private const CACHE = 'cache/sitemap.xml';
    private const TTL = 3600;

    /** @return list<array{path:string,lastmod:?string,priority:string,changefreq:string,images?:list<string>}> */
    public static function entries(): array
    {
        $db = Database::instance();
        $out = [];
        $static = [
            ['/', '1.0', 'daily'], ['/shop', '0.9', 'daily'], ['/wall-art', '0.9', 'daily'], ['/personalized-gifts', '0.9', 'daily'], ['/laser-engraving', '0.9', 'daily'], ['/custom-wall-art', '0.9', 'weekly'], ['/collections', '0.8', 'weekly'],
            ['/best-sellers', '0.7', 'weekly'], ['/new-arrivals', '0.7', 'weekly'], ['/offers', '0.6', 'daily'],
            ['/about', '0.5', 'monthly'], ['/contact', '0.5', 'monthly'], ['/faq', '0.5', 'monthly'], ['/terms', '0.3', 'yearly'],
        ];
        $latest = (string) ($db->value('SELECT MAX(COALESCE(updated_at, created_at)) FROM products WHERE is_active = 1 AND deleted_at IS NULL') ?? '');
        $hidden = SeoPages::noindexPaths();
        foreach ($static as [$path, $prio, $freq]) {
            if (in_array($path, $hidden, true)) {
                continue; // set to noindex in SEO → Pages
            }
            $out[] = ['path' => $path, 'lastmod' => in_array($path, ['/', '/shop'], true) && $latest !== '' ? $latest : null, 'priority' => $prio, 'changefreq' => $freq];
        }
        foreach ($db->fetchAll('SELECT slug, COALESCE(updated_at, created_at) AS m FROM categories WHERE is_active = 1 ORDER BY sort_order, id') as $c) {
            $out[] = ['path' => '/shop/category/' . $c['slug'], 'lastmod' => $c['m'], 'priority' => '0.8', 'changefreq' => 'weekly'];
        }
        foreach ($db->fetchAll('SELECT slug, COALESCE(updated_at, created_at) AS m FROM collections WHERE is_active = 1 AND (ends_at IS NULL OR ends_at >= NOW()) ORDER BY sort_order, id') as $c) {
            $out[] = ['path' => '/collections/' . $c['slug'], 'lastmod' => $c['m'], 'priority' => '0.7', 'changefreq' => 'weekly'];
        }
        foreach ($db->fetchAll(
            "SELECT p.id, p.slug, p.main_image, COALESCE(p.updated_at, p.created_at) AS m,
                    (SELECT GROUP_CONCAT(path ORDER BY sort_order SEPARATOR '|') FROM product_images WHERE product_id = p.id) AS imgs
             FROM products p WHERE p.is_active = 1 AND p.deleted_at IS NULL ORDER BY p.id"
        ) as $p) {
            $imgs = array_values(array_unique(array_filter(array_merge([(string) $p['main_image']], explode('|', (string) $p['imgs'])))));
            $out[] = ['path' => '/product/' . $p['slug'], 'lastmod' => $p['m'], 'priority' => '0.8', 'changefreq' => 'weekly',
                'images' => array_map(fn ($i) => absolute_url('uploads/' . ltrim($i, '/')), array_slice($imgs, 0, 10))];
        }
        return $out;
    }

    public static function xml(bool $fresh = false): string
    {
        $file = storage_path(self::CACHE);
        if (!$fresh && is_file($file) && filemtime($file) > time() - self::TTL) {
            return (string) file_get_contents($file);
        }
        $x = new \XMLWriter();
        $x->openMemory();
        $x->startDocument('1.0', 'UTF-8');
        $x->startElement('urlset');
        $x->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $x->writeAttribute('xmlns:xhtml', 'http://www.w3.org/1999/xhtml');
        $x->writeAttribute('xmlns:image', 'http://www.google.com/schemas/sitemap-image/1.1');
        foreach (self::entries() as $e) {
            $alt = SeoService::alternates($e['path']);
            foreach (\App\Core\Translator::SUPPORTED as $lang) {
                $x->startElement('url');
                $x->writeElement('loc', $alt[$lang]);
                foreach ($alt as $hl => $href) {
                    $x->startElement('xhtml:link');
                    $x->writeAttribute('rel', 'alternate');
                    $x->writeAttribute('hreflang', $hl);
                    $x->writeAttribute('href', $href);
                    $x->endElement();
                }
                if (!empty($e['lastmod'])) {
                    $x->writeElement('lastmod', date('Y-m-d', (int) strtotime((string) $e['lastmod'])));
                }
                $x->writeElement('changefreq', $e['changefreq']);
                $x->writeElement('priority', $e['priority']);
                foreach ($e['images'] ?? [] as $img) {
                    $x->startElement('image:image');
                    $x->writeElement('image:loc', $img);
                    $x->endElement();
                }
                $x->endElement();
            }
        }
        $x->endElement();
        $x->endDocument();
        $xml = $x->outputMemory();
        @file_put_contents($file, $xml, LOCK_EX);
        return $xml;
    }

    public static function forget(): void
    {
        $file = storage_path(self::CACHE);
        if (is_file($file)) {
            @unlink($file);
        }
    }

    public static function robots(): string
    {
        $lines = ['User-agent: *'];
        foreach (['/install', '/account', '/cart', '/checkout', '/order/', '/track/', '/builder/', '/artwork/', '/quote/', '/invoice/', '/newsletter/', '/lang/'] as $p) {
            $lines[] = 'Disallow: ' . $p;
        }
        // the administration path is deliberately not listed (it may be a private word); its pages send X-Robots-Tag: noindex
        $lines[] = 'Allow: /uploads/';
        // owner's additional rules (SEO → General), validated line by line when saved
        foreach (self::extraRobotsLines((string) setting('seo_robots_extra', '')) as $l) {
            $lines[] = $l;
        }
        $lines[] = '';
        $lines[] = 'Sitemap: ' . absolute_url('sitemap.xml');
        return implode("\n", $lines) . "\n";
    }

    /** Only well-formed robots.txt directives are kept. @return list<string> */
    public static function extraRobotsLines(string $text): array
    {
        $out = [];
        foreach (preg_split('/\R/', $text) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (preg_match('#^(User-agent|Disallow|Allow|Crawl-delay|Sitemap)\s*:\s*[^\s].{0,250}$#i', $line)) {
                $out[] = $line;
            }
        }
        return array_slice($out, 0, 100);
    }
}
