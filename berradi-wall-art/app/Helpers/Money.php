<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Exact money arithmetic using integer minor units (centimes).
 * DB stores DECIMAL(12,2); PHP never uses floats for financial calculations.
 */
final class Money
{
    /**
     * Parse "1250.5", "1 250,50", 1250 into integer cents.
     */
    public static function toCents(string|int|float|null $amount): int
    {
        if ($amount === null || $amount === '') {
            return 0;
        }
        if (is_int($amount)) {
            return $amount * 100;
        }
        if (is_float($amount)) {
            // only for legacy inputs; convert through string with fixed precision
            $amount = number_format($amount, 2, '.', '');
        }
        $s = str_replace(["\u{00A0}", ' ', "\u{202F}"], '', trim($amount));
        // "1.250,50" or "1250,50" => use comma as decimal separator
        if (str_contains($s, ',') && (!str_contains($s, '.') || strrpos($s, ',') > strrpos($s, '.'))) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        } else {
            $s = str_replace(',', '', $s);
        }
        if (!preg_match('/^(-)?(\d*)(?:\.(\d*))?$/', $s, $m) || ($m[2] === '' && ($m[3] ?? '') === '')) {
            throw new \InvalidArgumentException('Invalid money amount: ' . $amount);
        }
        $neg = $m[1] === '-';
        $int = $m[2] === '' ? 0 : (int) $m[2];
        $frac = $m[3] ?? '';
        $frac2 = (int) str_pad(substr($frac, 0, 2), 2, '0');
        $cents = $int * 100 + $frac2;
        // round half up on 3rd decimal
        if (strlen($frac) > 2 && (int) $frac[2] >= 5) {
            $cents++;
        }
        return $neg ? -$cents : $cents;
    }

    /** Integer cents to DB decimal string "1250.50". */
    public static function fromCents(int $cents): string
    {
        $neg = $cents < 0;
        $abs = abs($cents);
        return ($neg ? '-' : '') . intdiv($abs, 100) . '.' . str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT);
    }

    /** Normalize any amount to decimal string. */
    public static function normalize(string|int|float|null $amount): string
    {
        return self::fromCents(self::toCents($amount));
    }

    public static function add(string|int ...$amounts): string
    {
        $sum = 0;
        foreach ($amounts as $a) {
            $sum += self::toCents($a);
        }
        return self::fromCents($sum);
    }

    public static function sub(string|int $a, string|int $b): string
    {
        return self::fromCents(self::toCents($a) - self::toCents($b));
    }

    /** Multiply amount by integer quantity. */
    public static function mul(string|int $amount, int $qty): string
    {
        return self::fromCents(self::toCents($amount) * $qty);
    }

    /**
     * Amount × decimal quantity (up to 2 decimals, e.g. 2.5 m²), rounded half up to the cent.
     */
    public static function mulQty(string|int $amount, string|int|float $qty): string
    {
        $q = str_replace(',', '.', trim((string) $qty));
        if (!preg_match('/^\d{1,8}(\.\d{1,2})?$/', $q)) {
            throw new \InvalidArgumentException('Invalid quantity');
        }
        [$i, $d] = array_pad(explode('.', $q, 2), 2, '');
        $hundredths = (int) $i * 100 + (int) str_pad($d, 2, '0');
        return self::fromCents(self::roundDiv(self::toCents($amount) * $hundredths, 100));
    }

    /**
     * Percentage of an amount, e.g. TVA 20% => percent("100.00", "20") = "20.00". Rounds half up.
     * Rate may have up to 4 decimals ("7.5", "20.0000").
     */
    public static function percent(string|int $amount, string|int|float $rate): string
    {
        $cents = self::toCents($amount);
        $rateBp = self::rateToBasisPoints($rate); // 20% => 200000 (4 decimals)
        $num = $cents * $rateBp;
        $den = 1000000;
        return self::fromCents(self::roundDiv($num, $den));
    }

    /** Convert a TTC amount to HT for a given rate: ht = ttc / (1 + rate). */
    public static function htFromTtc(string|int $ttc, string|int|float $rate): string
    {
        $cents = self::toCents($ttc);
        $bp = self::rateToBasisPoints($rate);
        return self::fromCents(self::roundDiv($cents * 1000000, 1000000 + $bp));
    }

    private static function rateToBasisPoints(string|int|float $rate): int
    {
        $s = is_float($rate) ? number_format($rate, 4, '.', '') : str_replace(',', '.', (string) $rate);
        if (!preg_match('/^(\d+)(?:\.(\d{0,4})\d*)?$/', trim($s), $m)) {
            throw new \InvalidArgumentException('Invalid rate: ' . $rate);
        }
        return (int) $m[1] * 10000 + (int) str_pad($m[2] ?? '', 4, '0');
    }

    private static function roundDiv(int $num, int $den): int
    {
        $neg = ($num < 0) !== ($den < 0);
        $num = abs($num);
        $den = abs($den);
        $q = intdiv($num, $den);
        if (($num % $den) * 2 >= $den) {
            $q++;
        }
        return $neg ? -$q : $q;
    }

    public static function compare(string|int $a, string|int $b): int
    {
        return self::toCents($a) <=> self::toCents($b);
    }

    public static function isZero(string|int|null $a): bool
    {
        return self::toCents($a) === 0;
    }

    /** Display: "1 250,00 DH" (fr) / "1 250,00 د.م." if configured. */
    public static function format(string|int|float|null $amount, bool $withSymbol = true): string
    {
        $cents = self::toCents($amount ?? 0);
        $neg = $cents < 0;
        $abs = abs($cents);
        $int = number_format(intdiv($abs, 100), 0, '', "\u{202F}");
        $str = ($neg ? '-' : '') . $int . ',' . str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT);
        if (!$withSymbol) {
            return $str;
        }
        $symbol = (string) setting('currency_symbol', 'DH');
        $position = (string) setting('currency_position', 'after');
        return $position === 'before' ? $symbol . ' ' . $str : $str . ' ' . $symbol;
    }
}
