<?php

declare(strict_types=1);

namespace App\Helpers;

final class Str
{
    public static function slug(string $text, string $sep = '-'): string
    {
        $text = trim($text);
        if (function_exists('transliterator_transliterate')) {
            $t = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $text);
            if (is_string($t)) {
                $text = $t;
            }
        } else {
            $conv = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
            $text = strtolower($conv !== false ? $conv : $text);
        }
        $text = preg_replace('/[^a-z0-9]+/', $sep, strtolower($text)) ?? '';
        $text = trim($text, $sep);
        return $text !== '' ? substr($text, 0, 180) : 'item-' . bin2hex(random_bytes(3));
    }

    public static function random(int $length = 32): string
    {
        return substr(bin2hex(random_bytes((int) ceil($length / 2))), 0, $length);
    }

    public static function limit(string $text, int $limit = 100, string $end = '…'): string
    {
        return mb_strlen($text) <= $limit ? $text : rtrim(mb_substr($text, 0, $limit)) . $end;
    }

    public static function uuid(): string
    {
        $d = random_bytes(16);
        $d[6] = chr(ord($d[6]) & 0x0f | 0x40);
        $d[8] = chr(ord($d[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d), 4));
    }

    public static function initials(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name)) ?: [];
        $out = '';
        foreach (array_slice($parts, 0, 2) as $p) {
            $out .= mb_strtoupper(mb_substr($p, 0, 1));
        }
        return $out !== '' ? $out : '?';
    }
}
