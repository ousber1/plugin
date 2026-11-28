<?php

declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    private const KEY = '_csrf_token';

    public static function token(): string
    {
        $token = Session::get(self::KEY);
        if (!is_string($token) || strlen($token) !== 64) {
            $token = bin2hex(random_bytes(32));
            Session::put(self::KEY, $token);
        }
        return $token;
    }

    public static function verify(?string $token): bool
    {
        $stored = Session::get(self::KEY);
        return is_string($stored) && is_string($token) && $token !== '' && hash_equals($stored, $token);
    }

    public static function rotate(): void
    {
        Session::put(self::KEY, bin2hex(random_bytes(32)));
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_token" value="' . self::token() . '">';
    }
}
