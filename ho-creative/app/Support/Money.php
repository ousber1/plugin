<?php

namespace App\Support;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Decimal-safe money helpers. All amounts are handled as BigDecimal and
 * persisted as strings with 2 decimals (HALF_UP), never as floats.
 */
final class Money
{
    public static function of(mixed $v): BigDecimal
    {
        if ($v instanceof BigDecimal) {
            return $v;
        }
        if ($v === null || $v === '') {
            return BigDecimal::zero();
        }
        if (is_float($v)) {
            $v = number_format($v, 6, '.', '');
        }

        return BigDecimal::of(str_replace([' ', ','], ['', '.'], (string) $v));
    }

    public static function round(mixed $v, int $scale = 2): BigDecimal
    {
        return self::of($v)->toScale($scale, RoundingMode::HalfUp);
    }

    /** Rounded 2-decimal string suitable for DECIMAL columns. */
    public static function str(mixed $v, int $scale = 2): string
    {
        return (string) self::round($v, $scale);
    }

    public static function sum(iterable $values): BigDecimal
    {
        $t = BigDecimal::zero();
        foreach ($values as $v) {
            $t = $t->plus(self::of($v));
        }

        return $t;
    }

    public static function max(mixed $a, mixed $b): BigDecimal
    {
        return self::of($a)->isGreaterThan(self::of($b)) ? self::of($a) : self::of($b);
    }

    public static function min(mixed $a, mixed $b): BigDecimal
    {
        return self::of($a)->isLessThan(self::of($b)) ? self::of($a) : self::of($b);
    }

    public static function percent(mixed $base, mixed $pct): BigDecimal
    {
        return self::of($base)->multipliedBy(self::of($pct))->dividedBy(100, 6, RoundingMode::HalfUp);
    }

    /**
     * Human format, e.g. "1 234,50 DH" (fr) / "1 234,50 د.م." (ar).
     * Western digits are used in both languages, as is common in Morocco.
     */
    public static function format(mixed $v, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $d = self::round($v);
        $neg = $d->isNegative();
        [$int, $dec] = explode('.', (string) $d->abs());
        $int = preg_replace('/\B(?=(\d{3})+(?!\d))/', "\u{202F}", $int);
        $symbol = $locale === 'ar' ? 'د.م.' : 'DH';

        return ($neg ? '-' : '').$int.','.$dec."\u{00A0}".$symbol;
    }
}
