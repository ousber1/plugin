<?php

declare(strict_types=1);

namespace App\Services\Finance;

/**
 * French amount in words for invoices ("Arrêtée la présente facture à la somme de …").
 * Follows the 1990 spelling reform (hyphens everywhere) and the usual plural rules of vingt / cent.
 */
final class AmountInWords
{
    private const UNITS = ['zéro', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf', 'dix', 'onze', 'douze', 'treize', 'quatorze', 'quinze', 'seize'];
    private const TENS = [2 => 'vingt', 3 => 'trente', 4 => 'quarante', 5 => 'cinquante', 6 => 'soixante'];

    public static function fr(string $amount): string
    {
        [$int, $dec] = array_pad(explode('.', \App\Helpers\Money::normalize($amount)), 2, '00');
        $words = self::number((int) $int) . ' ' . ((int) $int > 1 ? 'dirhams' : 'dirham');
        if ((int) $dec > 0) {
            $words .= ' et ' . self::number((int) $dec) . ' ' . ((int) $dec > 1 ? 'centimes' : 'centime');
        }
        return $words;
    }

    public static function number(int $n): string
    {
        if ($n < 0) {
            return 'moins-' . self::number(-$n);
        }
        if ($n < 17) {
            return self::UNITS[$n];
        }
        if ($n < 100) {
            return self::belowHundred($n);
        }
        if ($n < 1000) {
            return self::belowThousand($n, true);
        }
        $parts = [];
        foreach ([1000000000 => 'milliard', 1000000 => 'million'] as $value => $word) {
            if ($n >= $value) {
                $q = intdiv($n, $value);
                $parts[] = self::number($q) . '-' . $word . ($q > 1 ? 's' : '');
                $n %= $value;
            }
        }
        if ($n >= 1000) {
            $q = intdiv($n, 1000);
            $parts[] = ($q === 1 ? '' : self::belowThousand($q, false) . '-') . 'mille';
            $n %= 1000;
        }
        if ($n > 0) {
            $parts[] = self::belowThousand($n, true);
        }
        return implode('-', $parts);
    }

    private static function belowHundred(int $n): string
    {
        if ($n < 17) {
            return self::UNITS[$n];
        }
        if ($n < 20) {
            return 'dix-' . self::UNITS[$n - 10];
        }
        $t = intdiv($n, 10);
        $u = $n % 10;
        if ($t === 7 || $t === 9) {
            $base = $t === 7 ? 'soixante' : 'quatre-vingt';
            return $base . ($u === 1 && $t === 7 ? '-et-' : '-') . self::belowHundred(10 + $u);
        }
        if ($t === 8) {
            return $u === 0 ? 'quatre-vingts' : 'quatre-vingt-' . self::UNITS[$u];
        }
        $w = self::TENS[$t];
        return $u === 0 ? $w : ($u === 1 ? $w . '-et-un' : $w . '-' . self::UNITS[$u]);
    }

    /** $final: "cents" takes an s only when it ends the number (deux-cents / deux-cent-mille). */
    private static function belowThousand(int $n, bool $final): string
    {
        $h = intdiv($n, 100);
        $r = $n % 100;
        $out = '';
        if ($h > 0) {
            $out = ($h > 1 ? self::UNITS[$h] . '-' : '') . 'cent' . ($h > 1 && $r === 0 && $final ? 's' : '');
        }
        if ($r > 0) {
            $tail = self::belowHundred($r);
            if (!$final && $r === 80) {
                $tail = 'quatre-vingt';
            }
            $out .= ($out !== '' ? '-' : '') . $tail;
        }
        return $out;
    }
}
