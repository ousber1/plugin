<?php

declare(strict_types=1);

namespace App\Services\Seo;

use App\Core\Database;

/**
 * 301/302 redirects for old URLs, and the "404 monitor" that lists the missing pages visitors and search engines
 * still ask for. Redirects only apply to URLs that would otherwise be "not found": a live page is never hijacked.
 */
final class RedirectService
{
    /** Normalised path: leading slash, no query string, no trailing slash (except "/"), decoded, lower-case. */
    public static function normalize(string $path): string
    {
        $path = (string) parse_url(trim($path), PHP_URL_PATH);
        $path = rawurldecode($path);
        $path = '/' . trim($path, '/');
        return mb_strtolower($path);
    }

    public static function hash(string $path): string
    {
        return sha1(self::normalize($path));
    }

    /** Target accepted: a path on this site or an https URL; never the source itself. */
    public static function validTarget(string $source, string $target): bool
    {
        $target = trim($target);
        if (!preg_match('#^(/[^\s]*|https://[^\s/]+(/[^\s]*)?)$#', $target) || str_starts_with($target, '//')) {
            return false;
        }
        return !str_starts_with($target, '/') || self::normalize($target) !== self::normalize($source);
    }

    /** @return array{target:string,status:int}|null */
    public static function match(string $path): ?array
    {
        try {
            $db = Database::instance();
            $r = $db->fetch('SELECT id, target_url, status_code FROM seo_redirects WHERE source_hash = ? AND is_active = 1', [self::hash($path)]);
            if ($r === null) {
                return null;
            }
            $db->query('UPDATE seo_redirects SET hits = hits + 1, last_hit_at = NOW() WHERE id = ?', [(int) $r['id']]);
            return ['target' => (string) $r['target_url'], 'status' => in_array((int) $r['status_code'], [301, 302, 307, 308], true) ? (int) $r['status_code'] : 301];
        } catch (\Throwable) {
            return null; // table missing before the v1.1 update
        }
    }

    /** Record a 404 (public GET pages only: no admin, assets, uploads or bots probing PHP files). */
    public static function logNotFound(string $path, ?string $referrer): void
    {
        if (!setting('seo_log_404', true)) {
            return;
        }
        $admin = '/' . trim((string) config('app.admin_path', 'admin'), '/');
        $norm = self::normalize($path);
        if (strlen($norm) > 400 || str_starts_with($norm, $admin) || preg_match('#^/(assets|uploads|vendor|install|webhooks|builder)/|\.(php\d?|asp|aspx|env|git|sql|bak|ini|cgi)$|/wp-|^/\.#i', $norm)) {
            return;
        }
        $ref = $referrer !== null ? mb_substr($referrer, 0, 500) : null;
        try {
            Database::instance()->query(
                'INSERT INTO seo_not_found (path, path_hash, last_referrer) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE hits = hits + 1, last_seen_at = NOW(), last_referrer = COALESCE(VALUES(last_referrer), last_referrer)',
                [$norm, sha1($norm), $ref]
            );
        } catch (\Throwable) {
            // logging must never break the 404 page
        }
    }

    /** Retention: forget 404s nobody asked for in 180 days. */
    public static function purge(int $days = 180): int
    {
        try {
            return Database::instance()->query('DELETE FROM seo_not_found WHERE last_seen_at < NOW() - INTERVAL ? DAY', [$days])->rowCount();
        } catch (\Throwable) {
            return 0;
        }
    }
}
