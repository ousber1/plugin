<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Core\Database;
use App\Helpers\Money;

/**
 * Shipping price & delivery estimate for Morocco.
 * Resolution: city rate (exact city in an active zone) → that zone's thresholds;
 * unknown city → "Reste du Maroc"-type fallback: global flat rate + global free threshold from settings.
 */
final class ShippingService
{
    /** Normalize a city name for matching ("Fès" == "fes" == " FES "). */
    public static function normalize(string $city): string
    {
        $c = mb_strtolower(trim($city));
        $t = function_exists('transliterator_transliterate') ? transliterator_transliterate('Any-Latin; Latin-ASCII', $c) : @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $c);
        $c = is_string($t) && $t !== '' ? strtolower($t) : $c;
        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', $c));
    }

    /** @return list<string> cities known by the shipping table (for the checkout autocomplete) */
    public static function cities(): array
    {
        return array_map('strval', array_column(Database::instance()->fetchAll(
            'SELECT DISTINCT r.city FROM shipping_rates r JOIN shipping_zones z ON z.id = r.shipping_zone_id
             WHERE r.is_active = 1 AND z.is_active = 1 ORDER BY r.city'
        ), 'city'));
    }

    /**
     * @param string $subtotal amount used for the free-shipping threshold (after discount)
     * @return array{price:string,free:bool,zone_id:?int,rate_id:?int,zone:?string,min_days:int,max_days:int,date_min:string,date_max:string,threshold:?string,known_city:bool}
     */
    public static function quote(string $city, string $subtotal, bool $freeShippingCoupon = false): array
    {
        $norm = self::normalize($city);
        $match = null;
        if ($norm !== '') {
            foreach (Database::instance()->fetchAll(
                'SELECT r.id AS rate_id, r.city, r.price, r.free_shipping_threshold AS rate_free, r.min_days AS rate_min, r.max_days AS rate_max,
                        z.id AS zone_id, z.name AS zone, z.free_shipping_threshold AS zone_free, z.min_days, z.max_days
                 FROM shipping_rates r JOIN shipping_zones z ON z.id = r.shipping_zone_id
                 WHERE r.is_active = 1 AND z.is_active = 1'
            ) as $r) {
                if (self::normalize((string) $r['city']) === $norm) {
                    $match = $r;
                    break;
                }
            }
        }
        if ($match !== null) {
            $price = (string) $match['price'];
            $threshold = $match['rate_free'] ?? $match['zone_free'];
            $min = (int) ($match['rate_min'] ?? $match['min_days']);
            $max = (int) ($match['rate_max'] ?? $match['max_days']);
        } else {
            $price = (string) setting('shipping_flat_rate', '40.00');
            $threshold = setting('shipping_free_threshold', '') ?: null;
            $min = (int) setting('shipping_min_days', 2);
            $max = (int) setting('shipping_max_days', 5);
        }
        $free = $freeShippingCoupon || ($threshold !== null && $threshold !== '' && Money::compare($subtotal, (string) $threshold) >= 0);
        $production = (int) setting('production_days', 3);
        return [
            'price' => $free ? '0.00' : Money::normalize($price),
            'free' => $free,
            'zone_id' => $match !== null ? (int) $match['zone_id'] : null,
            'rate_id' => $match !== null ? (int) $match['rate_id'] : null,
            'zone' => $match['zone'] ?? null,
            'min_days' => $production + $min,
            'max_days' => $production + max($min, $max),
            'date_min' => self::addBusinessDays($production + $min),
            'date_max' => self::addBusinessDays($production + max($min, $max)),
            'threshold' => $threshold !== null && $threshold !== '' ? Money::normalize((string) $threshold) : null,
            'known_city' => $match !== null,
        ];
    }

    /** Working days in Morocco: Monday–Saturday (Sunday skipped). */
    public static function addBusinessDays(int $days, ?string $from = null): string
    {
        $ts = strtotime($from ?? date('Y-m-d'));
        while ($days > 0) {
            $ts = strtotime('+1 day', $ts);
            if ((int) date('N', $ts) !== 7) {
                $days--;
            }
        }
        return date('Y-m-d', $ts);
    }
}
