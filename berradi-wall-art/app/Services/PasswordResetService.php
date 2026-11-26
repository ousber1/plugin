<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Core\Request;

/**
 * Password recovery for staff ("user") and customer accounts.
 * Tokens are random, stored hashed (sha256), single-use, and expire.
 * The request flow never reveals whether an e-mail exists.
 */
final class PasswordResetService
{
    /** @param 'user'|'customer' $guard */
    public static function request(string $guard, string $email, Request $request): void
    {
        $email = mb_strtolower(trim($email));
        $key = RateLimiter::key('reset_' . $guard, $email);
        if (RateLimiter::tooManyAttempts($key, $request->ip(), 3, 60)) {
            return; // silently ignore abuse
        }
        RateLimiter::hit($key, $request->ip());

        $db = Database::instance();
        $account = $guard === 'user'
            ? $db->fetch('SELECT id, name, email, locale FROM users WHERE email = ? AND is_active = 1', [$email])
            : $db->fetch("SELECT id, CONCAT(first_name, ' ', last_name) AS name, email, locale FROM customers WHERE email = ? AND is_guest = 0 AND is_active = 1 AND deleted_at IS NULL", [$email]);
        if ($account === null) {
            return;
        }
        $token = self::createToken($guard, (int) $account['id'], $request->ip());
        $routeName = $guard === 'user' ? 'admin.password.reset' : 'customer.password.reset';
        $link = absolute_url(app()->router()->url($routeName, ['token' => $token]));
        Mailer::sendTemplate((string) $account['email'], __('auth.reset_subject'), 'password_reset', [
            'name' => $account['name'],
            'link' => $link,
            'minutes' => (int) Config::get('app.security.password_reset_minutes', 60),
        ]);
        ActivityLogger::log('auth.reset_requested', $guard, (int) $account['id']);
    }

    public static function createToken(string $guard, int $accountId, ?string $ip = null): string
    {
        $db = Database::instance();
        // invalidate previous open tokens
        $db->query('UPDATE password_resets SET used_at = NOW() WHERE guard = ? AND account_id = ? AND used_at IS NULL', [$guard, $accountId]);
        $token = bin2hex(random_bytes(32));
        $minutes = (int) Config::get('app.security.password_reset_minutes', 60);
        $db->insert('password_resets', [
            'guard' => $guard,
            'account_id' => $accountId,
            'token_hash' => hash('sha256', $token),
            'expires_at' => date('Y-m-d H:i:s', time() + $minutes * 60),
            'ip' => $ip,
        ]);
        return $token;
    }

    /** @return array<string,mixed>|null valid reset row */
    public static function find(string $guard, string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }
        return Database::instance()->fetch(
            'SELECT * FROM password_resets WHERE token_hash = ? AND guard = ? AND used_at IS NULL AND expires_at > NOW()',
            [hash('sha256', $token), $guard]
        );
    }

    public static function reset(string $guard, string $token, string $newPassword): bool
    {
        $row = self::find($guard, $token);
        if ($row === null) {
            return false;
        }
        $db = Database::instance();
        $db->transaction(function (Database $db) use ($row, $guard, $newPassword): void {
            $hash = Auth::hashPassword($newPassword);
            if ($guard === 'user') {
                $db->update('users', ['password_hash' => $hash, 'password_changed_at' => date('Y-m-d H:i:s')], ['id' => (int) $row['account_id']]);
            } else {
                $db->update('customers', ['password_hash' => $hash], ['id' => (int) $row['account_id']]);
            }
            $db->update('password_resets', ['used_at' => date('Y-m-d H:i:s')], ['id' => (int) $row['id']]);
        });
        ActivityLogger::log('auth.password_reset', $guard, (int) $row['account_id']);
        return true;
    }
}
