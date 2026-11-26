<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;

/**
 * Symmetric encryption for secrets stored in the database (SMTP password, payment credentials).
 * AES-256-GCM with the application key (APP_KEY).
 */
final class Crypt
{
    private const CIPHER = 'aes-256-gcm';

    private static function key(): string
    {
        $hex = (string) Config::get('app.key', '');
        if (!preg_match('/^[0-9a-f]{64}$/i', $hex)) {
            throw new \RuntimeException('APP_KEY is missing or invalid (64 hex characters expected).');
        }
        return (string) hex2bin($hex);
    }

    public static function generateKey(): string
    {
        return bin2hex(random_bytes(32));
    }

    public static function encrypt(string $plain): string
    {
        if ($plain === '') {
            return '';
        }
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) {
            throw new \RuntimeException('Encryption failed');
        }
        return 'v1:' . base64_encode($iv . $tag . $cipher);
    }

    public static function decrypt(string $payload): string
    {
        if ($payload === '') {
            return '';
        }
        if (!str_starts_with($payload, 'v1:')) {
            throw new \RuntimeException('Unknown encrypted payload format');
        }
        $raw = base64_decode(substr($payload, 3), true);
        if ($raw === false || strlen($raw) < 29) {
            throw new \RuntimeException('Corrupted encrypted payload');
        }
        $plain = openssl_decrypt(substr($raw, 28), self::CIPHER, self::key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
        if ($plain === false) {
            throw new \RuntimeException('Decryption failed (wrong APP_KEY?)');
        }
        return $plain;
    }
}
