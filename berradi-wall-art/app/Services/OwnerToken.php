<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Request;

/**
 * Anonymous ownership of private uploads and designs (guests do not need an account).
 * A random token lives in an HttpOnly cookie; only its SHA-256 hash is stored in the database.
 */
final class OwnerToken
{
    private const COOKIE = 'bwa_owner';
    private const DAYS = 30;
    private static ?string $raw = null;

    /** Raw token for this browser (created on first use). */
    public static function raw(): string
    {
        if (self::$raw !== null) {
            return self::$raw;
        }
        $req = Request::current();
        $cookie = $req?->cookie(self::COOKIE);
        if (is_string($cookie) && preg_match('/^[a-f0-9]{64}$/', $cookie)) {
            self::$raw = $cookie;
        } else {
            self::$raw = bin2hex(random_bytes(32));
            if (PHP_SAPI !== 'cli' && !headers_sent()) {
                setcookie(self::COOKIE, self::$raw, [
                    'expires' => time() + self::DAYS * 86400,
                    'path' => (($req?->basePath() ?: '') . '/'),
                    'secure' => (bool) $req?->isSecure(),
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
            }
        }
        return self::$raw;
    }

    public static function hash(): string
    {
        return hash('sha256', self::raw());
    }

    /** Tests only. */
    public static function set(?string $raw): void
    {
        self::$raw = $raw;
    }

    /**
     * After login/registration, attach the guest's uploads and designs to the customer account
     * so they are not lost and remain private to that customer.
     */
    public static function claimFor(int $customerId): void
    {
        $hash = self::hash();
        $db = \App\Core\Database::instance();
        $db->query('UPDATE uploaded_images SET customer_id = ? WHERE owner_token = ? AND customer_id IS NULL', [$customerId, $hash]);
        $db->query('UPDATE custom_designs SET customer_id = ? WHERE owner_token = ? AND customer_id IS NULL', [$customerId, $hash]);
    }
}
