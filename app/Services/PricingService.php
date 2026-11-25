<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Settings;

/**
 * Prix de vente applicable à un client (source unique, POS et devis).
 *
 * Ordre de priorité du prix unitaire HT :
 *   1. prix négocié du client (customer_prices, non expiré)
 *   2. prix de la liste de prix du client (ou de son groupe)
 *   3. prix de vente détail du produit
 * La remise du groupe de clients est proposée comme remise de ligne par défaut.
 * Si les prix du catalogue sont saisis TTC (paramètre), ils sont convertis en HT.
 */
final class PricingService
{
    /** Contexte tarifaire d'un client : liste de prix et remise de groupe. */
    public static function customerContext(int $customerId): array
    {
        $row = Database::instance()->fetch(
            'SELECT c.id, c.price_list_id, c.is_walk_in, c.credit_limit, g.price_list_id AS group_price_list, g.discount_percent
               FROM customers c LEFT JOIN customer_groups g ON g.id = c.group_id
              WHERE c.id = ? AND c.deleted_at IS NULL',
            [$customerId]
        );
        if (!$row) {
            return ['price_list_id' => 1, 'discount_percent' => 0.0];
        }
        return [
            'price_list_id'    => (int) ($row['price_list_id'] ?: ($row['group_price_list'] ?: 1)),
            'discount_percent' => (float) ($row['discount_percent'] ?? 0),
        ];
    }

    /**
     * Prix HT de plusieurs produits pour un client.
     * @param int[] $productIds
     * @return array<int, array{price: float, source: string, discount_percent: float, tax_rate: float, min_price: float, cost: float, allow_override: bool, catalog_price: float}>
     */
    public static function forProducts(array $productIds, int $customerId): array
    {
        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds))));
        if (!$productIds) {
            return [];
        }
        $db = Database::instance();
        $ctx = self::customerContext($customerId);
        $in = implode(',', array_fill(0, count($productIds), '?'));
        $products = $db->fetchAll(
            "SELECT p.id, p.selling_price, p.min_selling_price, p.allow_price_override,
                    IF(p.estimated_cost > 0, p.estimated_cost, p.purchase_price) AS unit_cost,
                    COALESCE(t.rate, 0) AS tax_rate,
                    (SELECT pp.price FROM product_prices pp WHERE pp.product_id = p.id AND pp.price_list_id = ? AND pp.min_quantity = 1) AS list_price,
                    (SELECT cp.price FROM customer_prices cp WHERE cp.product_id = p.id AND cp.customer_id = ? AND (cp.valid_until IS NULL OR cp.valid_until >= CURDATE())) AS customer_price
               FROM products p LEFT JOIN taxes t ON t.id = p.tax_id
              WHERE p.id IN ({$in})",
            array_merge([$ctx['price_list_id'], $customerId], $productIds)
        );
        $ttc = (bool) Settings::get('prices_include_tax', false);
        $taxEnabled = (bool) Settings::get('tax_enabled', true);
        $out = [];
        foreach ($products as $p) {
            $rate = $taxEnabled ? (float) $p['tax_rate'] : 0.0;
            if ($p['customer_price'] !== null) {
                [$price, $source, $discount] = [(float) $p['customer_price'], 'customer', 0.0];
            } elseif ($p['list_price'] !== null && $ctx['price_list_id'] !== 1) {
                [$price, $source, $discount] = [(float) $p['list_price'], 'price_list', $ctx['discount_percent']];
            } else {
                [$price, $source, $discount] = [(float) $p['selling_price'], 'retail', $ctx['discount_percent']];
            }
            $toHt = static fn(float $v): float => $ttc && $rate > 0 ? round($v / (1 + $rate / 100), 4) : $v;
            $out[(int) $p['id']] = [
                'price'            => $toHt($price),
                'catalog_price'    => $toHt((float) $p['selling_price']),
                'min_price'        => $toHt((float) $p['min_selling_price']),
                'source'           => $source,
                'discount_percent' => $discount,
                'tax_rate'         => $rate,
                'cost'             => (float) $p['unit_cost'],
                'allow_override'   => (bool) $p['allow_price_override'],
            ];
        }
        return $out;
    }

    /** Prix HT d'un pack (prix forfaitaire) et coût de ses composants. */
    public static function forPack(int $packId): ?array
    {
        $row = Database::instance()->fetch(
            "SELECT pk.price, COALESCE(t.rate, 0) AS tax_rate,
                    (SELECT COALESCE(SUM(pi.quantity * IF(p.estimated_cost > 0, p.estimated_cost, p.purchase_price)), 0)
                       FROM pack_items pi JOIN products p ON p.id = pi.product_id WHERE pi.pack_id = pk.id) AS unit_cost
               FROM packs pk LEFT JOIN taxes t ON t.id = pk.tax_id
              WHERE pk.id = ? AND pk.deleted_at IS NULL AND pk.is_active = 1
                AND (pk.available_from IS NULL OR pk.available_from <= CURDATE())
                AND (pk.available_until IS NULL OR pk.available_until >= CURDATE())",
            [$packId]
        );
        if (!$row) {
            return null;
        }
        $rate = Settings::get('tax_enabled', true) ? (float) $row['tax_rate'] : 0.0;
        $price = (float) $row['price'];
        if (Settings::get('prices_include_tax', false) && $rate > 0) {
            $price = round($price / (1 + $rate / 100), 4);
        }
        return ['price' => $price, 'tax_rate' => $rate, 'cost' => (float) $row['unit_cost']];
    }
}
