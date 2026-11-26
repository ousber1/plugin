<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Database-backed rate limiter (works on shared hosting, no Redis/APCu needed).
 * Used for login throttling, password reset requests and generic POST throttling.
 */
final class RateLimiter
{
    public static function key(string $scope, string $identifier): string
    {
        return mb_substr($scope . ':' . mb_strtolower(trim($identifier)), 0, 191);
    }

    public static function attempts(string $key, string $ip, int $decayMinutes): int
    {
        return self::countFor($key, $ip, $decayMinutes);
    }

    /** Failed attempts counted per key (account) and per IP, whichever is higher. */
    private static function countFor(string $key, string $ip, int $decayMinutes): int
    {
        $db = Database::instance();
        $byKey = (int) $db->value(
            'SELECT COUNT(*) FROM login_attempts WHERE successful = 0 AND throttle_key = ? AND created_at >= (NOW() - INTERVAL ? MINUTE)',
            [$key, $decayMinutes]
        );
        $scope = explode(':', $key)[0];
        $byIp = (int) $db->value(
            'SELECT COUNT(*) FROM login_attempts WHERE successful = 0 AND ip = ? AND throttle_key LIKE ? AND created_at >= (NOW() - INTERVAL ? MINUTE)',
            [$ip, $scope . ':%', $decayMinutes]
        );
        // an IP may try a few different accounts, but is limited to 3x the per-account allowance
        return max($byKey, intdiv($byIp, 3));
    }

    public static function tooManyAttempts(string $key, string $ip, int $max, int $decayMinutes): bool
    {
        return self::attempts($key, $ip, $decayMinutes) >= $max;
    }

    public static function hit(string $key, string $ip): void
    {
        Database::instance()->insert('login_attempts', [
            'throttle_key' => $key,
            'ip' => $ip,
            'successful' => 0,
        ]);
    }

    public static function clear(string $key): void
    {
        Database::instance()->query('DELETE FROM login_attempts WHERE throttle_key = ? AND successful = 0', [$key]);
    }

    public static function recordSuccess(string $key, string $ip): void
    {
        self::clear($key);
        Database::instance()->insert('login_attempts', ['throttle_key' => $key, 'ip' => $ip, 'successful' => 1]);
    }

    /** Seconds until the oldest counted failure expires. */
    public static function availableIn(string $key, int $decayMinutes): int
    {
        $oldest = Database::instance()->value(
            'SELECT MIN(created_at) FROM login_attempts WHERE successful = 0 AND throttle_key = ? AND created_at >= (NOW() - INTERVAL ? MINUTE)',
            [$key, $decayMinutes]
        );
        if ($oldest === null) {
            return $decayMinutes * 60;
        }
        return max(1, strtotime((string) $oldest) + $decayMinutes * 60 - time());
    }

    /** Housekeeping (cron). */
    public static function prune(int $days = 7): int
    {
        return Database::instance()->query('DELETE FROM login_attempts WHERE created_at < (NOW() - INTERVAL ? DAY)', [$days])->rowCount();
    }
}
