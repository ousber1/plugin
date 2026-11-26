<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;

/**
 * Key/value application settings stored in the `settings` table, cached per request.
 */
final class SettingsService
{
    /** @var array<string,array{value:?string,type:string,group:string}>|null */
    private static ?array $cache = null;

    private static function load(): void
    {
        if (self::$cache !== null) {
            return;
        }
        self::$cache = [];
        foreach (Database::instance()->fetchAll('SELECT setting_key, setting_value, value_type, setting_group FROM settings') as $row) {
            self::$cache[$row['setting_key']] = [
                'value' => $row['setting_value'],
                'type' => $row['value_type'],
                'group' => $row['setting_group'],
            ];
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::load();
        if (!isset(self::$cache[$key])) {
            return $default;
        }
        $row = self::$cache[$key];
        $value = $row['value'];
        if ($value === null) {
            return $default;
        }
        return match ($row['type']) {
            'int' => (int) $value,
            'bool' => $value === '1',
            'json' => json_decode($value, true) ?? $default,
            'encrypted' => self::safeDecrypt($value),
            default => $value,
        };
    }

    private static function safeDecrypt(string $value): string
    {
        try {
            return Crypt::decrypt($value);
        } catch (\Throwable $e) {
            Logger::warning('Could not decrypt a setting: ' . $e->getMessage());
            return '';
        }
    }

    /** @return array<string,mixed> all decoded values of a group (encrypted values are NOT returned) */
    public static function group(string $group): array
    {
        self::load();
        $out = [];
        foreach (self::$cache as $key => $row) {
            if ($row['group'] === $group) {
                $out[$key] = $row['type'] === 'encrypted' ? '' : self::get($key);
            }
        }
        return $out;
    }

    public static function type(string $key): ?string
    {
        self::load();
        return self::$cache[$key]['type'] ?? null;
    }

    public static function set(string $key, mixed $value, ?string $group = null, ?string $type = null): void
    {
        self::load();
        $type ??= self::$cache[$key]['type'] ?? 'string';
        $group ??= self::$cache[$key]['group'] ?? 'general';
        $stored = match ($type) {
            'bool' => $value ? '1' : '0',
            'json' => json_encode($value, JSON_UNESCAPED_UNICODE),
            'int' => (string) (int) $value,
            'encrypted' => Crypt::encrypt((string) $value),
            default => $value === null ? null : (string) $value,
        };
        Database::instance()->query(
            'INSERT INTO settings (setting_key, setting_value, setting_group, value_type) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
            [$key, $stored, $group, $type]
        );
        self::$cache[$key] = ['value' => $stored, 'type' => $type, 'group' => $group];
    }

    /** @param array<string,mixed> $values */
    public static function setMany(array $values): void
    {
        Database::instance()->transaction(function () use ($values): void {
            foreach ($values as $k => $v) {
                self::set($k, $v);
            }
        });
    }

    public static function flush(): void
    {
        self::$cache = null;
    }
}
