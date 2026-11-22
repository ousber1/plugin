<?php

namespace App\Support;

class Locales
{
    public const DEFAULT = 'ar';

    public static function codes(): array
    {
        return array_keys(config('academy.locales'));
    }

    public static function all(): array
    {
        return config('academy.locales');
    }

    public static function dir(?string $locale = null): string
    {
        return config('academy.locales.'.($locale ?? app()->getLocale()).'.dir', 'rtl');
    }

    public static function isSupported(?string $locale): bool
    {
        return $locale !== null && array_key_exists($locale, config('academy.locales'));
    }
}
