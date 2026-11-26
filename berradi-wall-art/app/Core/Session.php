<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Hardened PHP session wrapper + flash data.
 */
final class Session
{
    private static bool $started = false;
    /** @var array<string,mixed> in-memory store when running under CLI / tests */
    private static array $memory = [];
    private static bool $cli = false;

    public static function start(Request $request): void
    {
        if (self::$started) {
            return;
        }
        if (PHP_SAPI === 'cli') {
            self::$cli = true;
            self::$started = true;
            self::ageFlash();
            return;
        }
        $secureSetting = (string) Config::get('app.session.secure', 'auto');
        $secure = $secureSetting === 'auto' ? $request->isSecure() : filter_var($secureSetting, FILTER_VALIDATE_BOOL);
        $lifetime = (int) Config::get('app.session.lifetime', 120) * 60;

        $savePath = (string) Config::get('app.session.path', '');
        if ($savePath !== '' && is_dir($savePath) && is_writable($savePath)) {
            session_save_path($savePath);
        }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.gc_maxlifetime', (string) $lifetime);
        session_name((string) Config::get('app.session.name', 'bwa_session'));
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => ($request->basePath() ?: '') . '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
        self::$started = true;

        // idle timeout
        $now = time();
        if (isset($_SESSION['_last_activity']) && $now - (int) $_SESSION['_last_activity'] > $lifetime) {
            $_SESSION = [];
            session_regenerate_id(true);
        }
        $_SESSION['_last_activity'] = $now;
        self::ageFlash();
    }

    /** @return array<string,mixed> */
    private static function &store(): array
    {
        if (self::$cli) {
            return self::$memory;
        }
        return $_SESSION;
    }

    private static function ageFlash(): void
    {
        $s = &self::store();
        $s['_flash_old'] = $s['_flash_new'] ?? [];
        $s['_flash_new'] = [];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $s = &self::store();
        return $s[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        $s = &self::store();
        $s[$key] = $value;
    }

    public static function has(string $key): bool
    {
        $s = &self::store();
        return isset($s[$key]);
    }

    public static function forget(string $key): void
    {
        $s = &self::store();
        unset($s[$key]);
    }

    public static function pull(string $key, mixed $default = null): mixed
    {
        $v = self::get($key, $default);
        self::forget($key);
        return $v;
    }

    public static function flash(string $key, mixed $value): void
    {
        $s = &self::store();
        $s['_flash_new'][$key] = $value;
    }

    public static function getFlash(string $key, mixed $default = null): mixed
    {
        $s = &self::store();
        return $s['_flash_new'][$key] ?? $s['_flash_old'][$key] ?? $default;
    }

    /** Keep flash data for one more request. */
    public static function reflash(): void
    {
        $s = &self::store();
        $s['_flash_new'] = array_merge($s['_flash_old'] ?? [], $s['_flash_new'] ?? []);
    }

    public static function regenerate(): void
    {
        if (!self::$cli && session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function invalidate(): void
    {
        $s = &self::store();
        $s = [];
        if (!self::$cli && session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function id(): string
    {
        return self::$cli ? 'cli-session' : (string) session_id();
    }

    /** Reset (tests only). */
    public static function resetForTesting(): void
    {
        self::$memory = [];
        self::$started = false;
    }
}
