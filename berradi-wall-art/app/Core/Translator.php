<?php

declare(strict_types=1);

namespace App\Core;

/**
 * File-based UI translations: resources/lang/{locale}/{group}.php
 * Key format "group.key" (e.g. "auth.login"). Falls back to French.
 */
final class Translator
{
    public const SUPPORTED = ['fr', 'ar'];
    public const RTL = ['ar'];

    private static string $path = '';
    private static string $locale = 'fr';
    private static string $fallback = 'fr';
    /** @var array<string,array<string,array<string,mixed>>> */
    private static array $loaded = [];

    public static function init(string $path, string $locale, string $fallback = 'fr'): void
    {
        self::$path = rtrim($path, '/');
        self::$fallback = $fallback;
        self::setLocale($locale);
    }

    public static function setLocale(string $locale): void
    {
        self::$locale = in_array($locale, self::SUPPORTED, true) ? $locale : self::$fallback;
    }

    public static function locale(): string
    {
        return self::$locale;
    }

    public static function isRtl(?string $locale = null): bool
    {
        return in_array($locale ?? self::$locale, self::RTL, true);
    }

    public static function dir(): string
    {
        return self::isRtl() ? 'rtl' : 'ltr';
    }

    /** @return array<string,mixed> */
    private static function group(string $locale, string $group): array
    {
        if (!isset(self::$loaded[$locale][$group])) {
            $file = self::$path . '/' . $locale . '/' . $group . '.php';
            $data = is_file($file) ? require $file : [];
            self::$loaded[$locale][$group] = is_array($data) ? $data : [];
        }
        return self::$loaded[$locale][$group];
    }

    /** @param array<string,string|int|float> $replace */
    public static function get(string $key, array $replace = [], ?string $locale = null): string
    {
        $locale ??= self::$locale;
        if (!str_contains($key, '.')) {
            return $key;
        }
        [$group, $item] = explode('.', $key, 2);
        $line = self::lookup(self::group($locale, $group), $item);
        if ($line === null && $locale !== self::$fallback) {
            $line = self::lookup(self::group(self::$fallback, $group), $item);
        }
        if ($line === null) {
            return $key;
        }
        foreach ($replace as $k => $v) {
            $line = str_replace(':' . $k, (string) $v, $line);
        }
        return $line;
    }

    public static function has(string $key, ?string $locale = null): bool
    {
        if (!str_contains($key, '.')) {
            return false;
        }
        [$group, $item] = explode('.', $key, 2);
        return self::lookup(self::group($locale ?? self::$locale, $group), $item) !== null;
    }

    /** @param array<string,mixed> $data */
    private static function lookup(array $data, string $item): ?string
    {
        $value = $data;
        foreach (explode('.', $item) as $seg) {
            if (!is_array($value) || !array_key_exists($seg, $value)) {
                return null;
            }
            $value = $value[$seg];
        }
        return is_string($value) ? $value : null;
    }

    /** @return array<string,mixed> whole group (used to export JS strings) */
    public static function all(string $group, ?string $locale = null): array
    {
        return array_replace_recursive(self::group(self::$fallback, $group), self::group($locale ?? self::$locale, $group));
    }
}
