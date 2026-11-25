<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Chiffrement symétrique (AES-256-GCM) des secrets stockés en base
 * (mot de passe SMTP…), avec la clé applicative de config/config.php.
 */
final class Crypto
{
    private const PREFIX = 'enc:v1:';

    private static function key(): string
    {
        $key = (string) Config::get('app.key', '');
        if (strlen($key) < 32) {
            throw new \RuntimeException('Clé applicative absente ou trop courte (config/config.php › app.key).');
        }
        return hash('sha256', $key, true);
    }

    public static function encrypt(string $plain): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) {
            throw new \RuntimeException('Échec du chiffrement.');
        }
        return self::PREFIX . base64_encode($iv . $tag . $cipher);
    }

    /** Déchiffre ; une valeur non chiffrée (ancienne) est renvoyée telle quelle. */
    public static function decrypt(string $value): string
    {
        if (!str_starts_with($value, self::PREFIX)) {
            return $value;
        }
        $raw = base64_decode(substr($value, strlen(self::PREFIX)), true);
        if ($raw === false || strlen($raw) < 29) {
            return '';
        }
        $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
        return $plain === false ? '' : $plain;
    }
}
