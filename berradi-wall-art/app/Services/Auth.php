<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Request;
use App\Core\Session;

/**
 * Authentication for two guards:
 *  - "admin"    => staff accounts (table users, RBAC)
 *  - "customer" => storefront accounts (table customers)
 */
final class Auth
{
    private const ADMIN_KEY = '_auth_admin';
    private const CUSTOMER_KEY = '_auth_customer';

    /** @var array<string,mixed>|null|false false = not loaded yet */
    private static array|null|false $user = false;
    /** @var array<string,mixed>|null|false */
    private static array|null|false $customer = false;
    /** @var list<string>|null */
    private static ?array $permissions = null;

    // ------------------------------------------------------------------ staff

    /**
     * @return array{ok:bool,error?:string,retry_after?:int}
     */
    public static function attemptAdmin(string $email, string $password, Request $request): array
    {
        return self::attempt('admin', $email, $password, $request);
    }

    /** @return array<string,mixed>|null */
    public static function user(): ?array
    {
        if (self::$user !== false) {
            return self::$user;
        }
        self::$user = null;
        $data = Session::get(self::ADMIN_KEY);
        if (!is_array($data) || !isset($data['id'], $data['fp'])) {
            return null;
        }
        if (!hash_equals((string) $data['fp'], self::fingerprint())) {
            Session::forget(self::ADMIN_KEY);
            return null;
        }
        $row = Database::instance()->fetch(
            'SELECT u.id, u.role_id, u.name, u.email, u.phone, u.locale, u.is_active, u.last_login_at,
                    u.password_changed_at, r.slug AS role_slug, r.name AS role_name
             FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?',
            [(int) $data['id']]
        );
        // invalidate sessions when deactivated or password changed after login
        if ($row === null || (int) $row['is_active'] !== 1
            || ($row['password_changed_at'] !== null && strtotime((string) $row['password_changed_at']) > (int) ($data['at'] ?? 0))) {
            Session::forget(self::ADMIN_KEY);
            return null;
        }
        self::$user = $row;
        return $row;
    }

    public static function isSuperAdmin(): bool
    {
        return (self::user()['role_slug'] ?? null) === 'super_admin';
    }

    /** @return list<string> */
    public static function permissions(): array
    {
        if (self::$permissions !== null) {
            return self::$permissions;
        }
        $user = self::user();
        if ($user === null) {
            return [];
        }
        self::$permissions = array_map('strval', array_column(Database::instance()->fetchAll(
            'SELECT p.slug FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id WHERE rp.role_id = ?',
            [(int) $user['role_id']]
        ), 'slug'));
        return self::$permissions;
    }

    public static function can(string $permission): bool
    {
        if (self::user() === null) {
            return false;
        }
        if (self::isSuperAdmin()) {
            return true;
        }
        return in_array($permission, self::permissions(), true);
    }

    public static function logoutAdmin(): void
    {
        if (self::user() !== null) {
            ActivityLogger::log('auth.logout', 'user', (int) self::user()['id']);
        }
        Session::forget(self::ADMIN_KEY);
        self::$user = null;
        self::$permissions = null;
        Session::regenerate();
        Csrf::rotate();
    }

    // --------------------------------------------------------------- customer

    /** @return array{ok:bool,error?:string,retry_after?:int} */
    public static function attemptCustomer(string $email, string $password, Request $request): array
    {
        return self::attempt('customer', $email, $password, $request);
    }

    /** @return array<string,mixed>|null */
    public static function customer(): ?array
    {
        if (self::$customer !== false) {
            return self::$customer;
        }
        self::$customer = null;
        $data = Session::get(self::CUSTOMER_KEY);
        if (!is_array($data) || !isset($data['id'], $data['fp']) || !hash_equals((string) $data['fp'], self::fingerprint())) {
            return null;
        }
        $row = Database::instance()->fetch(
            'SELECT id, first_name, last_name, email, phone, company_name, ice, locale, marketing_opt_in, is_active, created_at
             FROM customers WHERE id = ? AND is_guest = 0 AND deleted_at IS NULL',
            [(int) $data['id']]
        );
        if ($row === null || (int) $row['is_active'] !== 1) {
            Session::forget(self::CUSTOMER_KEY);
            return null;
        }
        self::$customer = $row;
        return $row;
    }

    public static function loginCustomer(int $customerId): void
    {
        Session::regenerate();
        Csrf::rotate();
        Session::put(self::CUSTOMER_KEY, ['id' => $customerId, 'fp' => self::fingerprint(), 'at' => time()]);
        self::$customer = false;
        Database::instance()->update('customers', ['last_login_at' => date('Y-m-d H:i:s')], ['id' => $customerId]);
        // photos and designs created as a guest in this browser now belong to the account
        OwnerToken::claimFor($customerId);
    }

    public static function logoutCustomer(): void
    {
        Session::forget(self::CUSTOMER_KEY);
        self::$customer = null;
        Session::regenerate();
        Csrf::rotate();
    }

    // ----------------------------------------------------------------- shared

    /** @return array{ok:bool,error?:string,retry_after?:int} */
    private static function attempt(string $guard, string $email, string $password, Request $request): array
    {
        $email = mb_strtolower(trim($email));
        $max = (int) Config::get('app.security.login_max_attempts', 5);
        $decay = (int) Config::get('app.security.login_decay_minutes', 15);
        $key = RateLimiter::key('login_' . $guard, $email);
        $ip = $request->ip();

        if (RateLimiter::tooManyAttempts($key, $ip, $max, $decay)) {
            return ['ok' => false, 'error' => 'throttled', 'retry_after' => RateLimiter::availableIn($key, $decay)];
        }

        $db = Database::instance();
        $row = $guard === 'admin'
            ? $db->fetch('SELECT id, password_hash, is_active FROM users WHERE email = ?', [$email])
            : $db->fetch('SELECT id, password_hash, is_active FROM customers WHERE email = ? AND is_guest = 0 AND deleted_at IS NULL', [$email]);

        // constant-ish time: verify against a dummy hash when account does not exist
        $hash = $row['password_hash'] ?? '$2y$10$fwq1bCWr94fveIxJwiWBneJHbTC.tTj5zuqpsVr7PeM51PHgXLCQS';
        $valid = password_verify($password, (string) $hash) && $row !== null;

        if (!$valid) {
            RateLimiter::hit($key, $ip);
            ActivityLogger::log('auth.failed', $guard === 'admin' ? 'user' : 'customer', $row ? (int) $row['id'] : null, 'Failed login for ' . $email);
            return ['ok' => false, 'error' => 'invalid'];
        }
        if ((int) $row['is_active'] !== 1) {
            return ['ok' => false, 'error' => 'inactive'];
        }

        if (password_needs_rehash((string) $row['password_hash'], PASSWORD_DEFAULT)) {
            $table = $guard === 'admin' ? 'users' : 'customers';
            $db->update($table, ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], ['id' => (int) $row['id']]);
        }
        RateLimiter::recordSuccess($key, $ip);

        if ($guard === 'admin') {
            Session::regenerate();
            Csrf::rotate();
            Session::put(self::ADMIN_KEY, ['id' => (int) $row['id'], 'fp' => self::fingerprint(), 'at' => time()]);
            self::$user = false;
            self::$permissions = null;
            $db->update('users', ['last_login_at' => date('Y-m-d H:i:s'), 'last_login_ip' => $ip], ['id' => (int) $row['id']]);
            ActivityLogger::log('auth.login', 'user', (int) $row['id']);
        } else {
            self::loginCustomer((int) $row['id']);
            ActivityLogger::log('auth.login', 'customer', (int) $row['id']);
        }
        return ['ok' => true];
    }

    /** Binds the session to the browser (user agent) to make stolen session IDs less useful. */
    private static function fingerprint(): string
    {
        $ua = Request::current()?->userAgent() ?? 'cli';
        return hash('sha256', $ua . '|' . Config::get('app.key', ''));
    }

    /** Re-stamp the admin session after the user changed their own password (keeps them logged in). */
    public static function touchAdminSession(): void
    {
        $data = Session::get(self::ADMIN_KEY);
        if (is_array($data)) {
            $data['at'] = time() + 1;
            Session::put(self::ADMIN_KEY, $data);
        }
        self::$user = false;
    }

    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    /** Reset in-memory caches (tests / after profile changes). */
    public static function refresh(): void
    {
        self::$user = false;
        self::$customer = false;
        self::$permissions = null;
    }
}
