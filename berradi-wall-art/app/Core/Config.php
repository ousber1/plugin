<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Loads PHP config files from /config and gives dot-notation access.
 */
final class Config
{
    /** @var array<string,mixed> */
    private static array $items = [];
    private static string $path = '';

    public static function init(string $configPath): void
    {
        self::$path = rtrim($configPath, '/');
        self::$items = [];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $file = array_shift($segments);
        if (!array_key_exists($file, self::$items)) {
            $path = self::$path . '/' . $file . '.php';
            self::$items[$file] = is_file($path) ? (require $path) : [];
        }
        $value = self::$items[$file];
        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        return $value;
    }

    public static function set(string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $file = array_shift($segments);
        self::get($file); // ensure loaded
        $ref = &self::$items[$file];
        foreach ($segments as $segment) {
            if (!isset($ref[$segment]) || !is_array($ref[$segment])) {
                $ref[$segment] = [];
            }
            $ref = &$ref[$segment];
        }
        $ref = $value;
    }
}
