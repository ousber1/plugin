<?php

declare(strict_types=1);

namespace App\Services\System;

use App\Core\Database;
use App\Core\Request;

/**
 * Configuration checks shown on the Maintenance page. Each item: key, state (ok | warn | fail), detail.
 * They only read the configuration; nothing is changed automatically.
 *
 * @phpstan-type Check array{key:string,state:string,detail:string}
 */
final class SecurityCheck
{
    /** @return list<array{key:string,state:string,detail:string}> */
    public static function run(?Request $request = null): array
    {
        $request ??= Request::current();
        $c = [];
        $add = function (string $key, string $state, string $detail = '') use (&$c): void {
            $c[] = ['key' => $key, 'state' => $state, 'detail' => $detail];
        };
        $secure = $request?->isSecure() ?? false;
        $add('https', $secure ? 'ok' : 'fail');
        $appUrl = (string) config('app.url', '');
        $add('app_url', $appUrl !== '' && str_starts_with($appUrl, 'https://') ? 'ok' : ($appUrl !== '' ? 'warn' : 'fail'), $appUrl);
        $add('debug', (bool) config('app.debug') ? 'fail' : 'ok');
        $add('display_errors', in_array(strtolower((string) ini_get('display_errors')), ['1', 'on', 'stdout'], true) && (bool) config('app.debug') ? 'warn' : 'ok');
        $add('installer', is_file(storage_path('installed.lock')) ? 'ok' : 'fail');
        $env = base_path('.env');
        if (is_file($env)) {
            $perm = fileperms($env) & 0777;
            $add('env_perms', ($perm & 0004) ? 'warn' : 'ok', sprintf('%o', $perm));
        }
        $add('outside_public', !str_starts_with(realpath(base_path()) ?: '', (realpath(public_path()) ?: '#') . DIRECTORY_SEPARATOR) ? 'ok' : 'fail');
        $add('admin_path', trim((string) config('app.admin_path', 'admin'), '/') === 'admin' ? 'warn' : 'ok', '/' . trim((string) config('app.admin_path', 'admin'), '/'));
        $add('php', version_compare(PHP_VERSION, '8.2.0', '>=') ? 'ok' : 'fail', PHP_VERSION);
        $key = (string) config('app.key', '');
        $add('app_key', strlen($key) >= 32 ? 'ok' : 'fail');
        $db = Database::instance();
        $admins = (int) $db->value("SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id WHERE r.slug = 'super_admin' AND u.is_active = 1");
        $add('super_admins', $admins >= 1 && $admins <= 3 ? 'ok' : 'warn', (string) $admins);
        $stale = (int) $db->value('SELECT COUNT(*) FROM users WHERE is_active = 1 AND (last_login_at IS NULL OR last_login_at < NOW() - INTERVAL 90 DAY) AND created_at < NOW() - INTERVAL 30 DAY');
        $add('stale_accounts', $stale === 0 ? 'ok' : 'warn', (string) $stale);
        $failed = (int) $db->value('SELECT COUNT(*) FROM login_attempts WHERE successful = 0 AND created_at > NOW() - INTERVAL 1 DAY');
        $add('failed_logins', $failed < 50 ? 'ok' : 'warn', (string) $failed);
        $cron = (string) setting('last_cron_run', '');
        $add('cron', $cron !== '' && strtotime($cron) > time() - 7200 ? 'ok' : 'fail', $cron);
        $latest = BackupService::list()[0] ?? null;
        $add('backup', $latest !== null && $latest['time'] > time() - 2 * 86400 ? 'ok' : 'warn', $latest !== null ? date('Y-m-d H:i', $latest['time']) : '');
        $add('mail', (string) setting('mail_driver', 'mail') === 'smtp' && (string) setting('mail_host', '') !== '' ? 'ok' : 'warn', (string) setting('mail_driver', 'mail'));
        $add('demo', (int) $db->value("SELECT COUNT(*) FROM products WHERE sku LIKE 'DEMO-%' AND deleted_at IS NULL") === 0 ? 'ok' : 'warn');
        return $c;
    }
}
