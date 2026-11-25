<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Paramètres métier stockés en base (table settings), chargés une fois par requête.
 */
final class Settings
{
    private static ?array $cache = null;

    private static function load(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        self::$cache = [];
        try {
            foreach (Database::instance()->fetchAll('SELECT setting_key, setting_value, value_type FROM settings') as $row) {
                self::$cache[$row['setting_key']] = self::cast($row['setting_value'], $row['value_type']);
            }
        } catch (\Throwable $e) {
            Logger::error('Chargement des paramètres impossible : ' . $e->getMessage());
        }
        return self::$cache;
    }

    private static function cast(?string $value, string $type): mixed
    {
        return match ($type) {
            'int'     => (int) $value,
            'decimal' => (float) $value,
            'bool'    => $value === '1' || $value === 'true',
            'json'    => json_decode((string) $value, true) ?? [],
            default   => (string) $value,
        };
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = self::load();
        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    public static function all(): array
    {
        return self::load();
    }

    public static function group(string $group): array
    {
        $rows = Database::instance()->fetchAll(
            'SELECT setting_key, setting_value, value_type FROM settings WHERE setting_group = ?',
            [$group]
        );
        $out = [];
        foreach ($rows as $row) {
            $out[$row['setting_key']] = self::cast($row['setting_value'], $row['value_type']);
        }
        return $out;
    }

    /** Crée ou met à jour un paramètre. */
    public static function set(string $key, mixed $value, string $group = 'general', ?string $type = null): void
    {
        $type ??= match (true) {
            is_bool($value)  => 'bool',
            is_int($value)   => 'int',
            is_float($value) => 'decimal',
            is_array($value) => 'json',
            default          => 'string',
        };
        $stored = match ($type) {
            'bool'  => $value ? '1' : '0',
            'json'  => json_encode($value, JSON_UNESCAPED_UNICODE),
            default => (string) $value,
        };
        Database::instance()->query(
            'INSERT INTO settings (setting_group, setting_key, setting_value, value_type) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
            [$group, $key, $stored, $type]
        );
        if (self::$cache !== null) {
            self::$cache[$key] = self::cast($stored, $type);
        }
    }

    public static function flush(): void
    {
        self::$cache = null;
    }
}
