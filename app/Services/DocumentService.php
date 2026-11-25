<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Settings;

/**
 * Moteur commun des documents commerciaux (devis, bons de commande, factures).
 *
 * - Lignes validées par SaleService::buildLines() : mêmes règles que le POS
 *   (prix client, remises, prix minimum, permissions, travaux au m²).
 * - Frais de design, d'installation et de transport : montants HT ajoutés après
 *   la remise globale, soumis au taux de TVA par défaut.
 * - Totaux calculés par SaleCalculator (arrondi par ligne, remise répartie).
 */
final class DocumentService
{
    public const FEES = ['design_fees' => 'Frais de design', 'installation_fees' => 'Frais d\'installation / pose', 'transport_fees' => 'Frais de transport / livraison'];

    /**
     * @return array{lines: array, totals: array}
     */
    public static function compute(array $items, int $customerId, string $discountType, float $discountValue, array $fees): array
    {
        $lines = SaleService::buildLines($items, $customerId, true);
        $calc = SaleCalculator::compute($lines, $discountType, $discountValue);
        SaleService::checkGlobalDiscount($discountType, $discountValue, $calc['discount_base']);

        $rate = Settings::get('tax_enabled', true)
            ? (float) (Database::instance()->fetchColumn('SELECT rate FROM taxes WHERE id = ?', [(int) Settings::get('default_tax_id', 1)]) ?? 0)
            : 0.0;
        $feeTotal = 0.0;
        $feeTax = 0.0;
        $cleanFees = [];
        foreach (array_keys(self::FEES) as $k) {
            $v = round(max(0.0, (float) ($fees[$k] ?? 0)), 2);
            $cleanFees[$k] = $v;
            $feeTotal += $v;
        }
        if ($feeTotal > 0) {
            $feeTax = round($feeTotal * $rate / 100, 2);
            $key = null;
            foreach ($calc['tax_breakdown'] as $i => $b) {
                if (abs($b['rate'] - $rate) < 0.0005) {
                    $key = $i;
                }
            }
            if ($key === null) {
                $calc['tax_breakdown'][] = ['rate' => $rate, 'base' => 0.0, 'tax' => 0.0];
                $key = array_key_last($calc['tax_breakdown']);
            }
            $calc['tax_breakdown'][$key]['base'] = round($calc['tax_breakdown'][$key]['base'] + $feeTotal, 2);
            $calc['tax_breakdown'][$key]['tax'] = round($calc['tax_breakdown'][$key]['tax'] + $feeTax, 2);
        }
        $cost = 0.0;
        foreach ($calc['lines'] as $l) {
            $cost += $l['unit_cost'] * $l['quantity'];
        }
        $grand = round($calc['net_total'] + $feeTotal + $calc['tax_total'] + $feeTax, 2);
        if ($grand < 0) {
            throw new \DomainException('Le total du document ne peut pas être négatif.');
        }
        return [
            'lines'  => $calc['lines'],
            'totals' => [
                'subtotal'       => $calc['subtotal'],
                'discount_total' => $calc['discount_total'],
                'fees'           => $cleanFees,
                'fees_total'     => round($feeTotal, 2),
                'tax_total'      => round($calc['tax_total'] + $feeTax, 2),
                'total'          => round($calc['net_total'] + $feeTotal + $calc['tax_total'] + $feeTax, 2),
                'net_total'      => round($calc['net_total'] + $feeTotal, 2),
                'cost_total'     => round($cost, 2),
                'tax_breakdown'  => $calc['tax_breakdown'],
            ],
        ];
    }

    /** Enregistre les lignes d'un document (table *_items). */
    public static function saveItems(string $table, string $fk, int $docId, array $lines): void
    {
        $db = Database::instance();
        Database::assertIdentifier($table);
        Database::assertIdentifier($fk);
        $db->query("DELETE FROM `{$table}` WHERE `{$fk}` = ?", [$docId]);
        foreach (array_values($lines) as $i => $l) {
            $db->insert($table, [
                $fk => $docId, 'product_id' => $l['product_id'], 'pack_id' => $l['pack_id'],
                'description' => mb_substr($l['description'], 0, 2000), 'width' => $l['width'], 'height' => $l['height'],
                'dimension_unit' => $l['dimension_unit'], 'quantity' => $l['quantity'], 'unit_id' => $l['unit_id'],
                'unit_price' => $l['unit_price'], 'discount_percent' => $l['discount_percent'], 'tax_rate' => $l['tax_rate'],
                'line_subtotal' => $l['line_subtotal'], 'line_tax' => $l['line_tax'], 'line_total' => $l['line_total'],
                'unit_cost' => round((float) $l['unit_cost'], 4), 'sort_order' => $i,
            ]);
        }
    }

    /**
     * Lignes d'un document converties au format du formulaire (pour modification,
     * duplication ou conversion vers un autre document).
     */
    public static function itemsForForm(string $table, string $fk, int $docId): array
    {
        Database::assertIdentifier($table);
        Database::assertIdentifier($fk);
        $rows = Database::instance()->fetchAll(
            "SELECT i.*, p.name AS product_name, p.code AS product_code, pk.name AS pack_name, pk.code AS pack_code,
                    u.symbol, u.is_area, u.allows_decimal
               FROM `{$table}` i LEFT JOIN products p ON p.id = i.product_id LEFT JOIN packs pk ON pk.id = i.pack_id
               LEFT JOIN units u ON u.id = COALESCE(p.unit_id, i.unit_id)
              WHERE i.`{$fk}` = ? ORDER BY i.sort_order, i.id",
            [$docId]
        );
        $out = [];
        foreach ($rows as $r) {
            $type = $r['product_id'] ? 'product' : ($r['pack_id'] ? 'pack' : 'custom');
            $qty = (float) $r['quantity'];
            $isArea = $type === 'product' && (int) ($r['is_area'] ?? 0) === 1 && $r['width'] !== null && $r['height'] !== null;
            $description = (string) $r['description'];
            if ($isArea) {
                // Quantité stockée = surface ; on retrouve le nombre d'exemplaires et la désignation d'origine
                $unitArea = (float) $r['width'] * (float) $r['height'] / 10000;
                $qty = $unitArea > 0 ? max(1, round($qty / $unitArea)) : 1;
                $description = (string) preg_replace('/ — [\d\s,.]+ × [\d\s,.]+ cm( × [\d\s,.]+)?$/u', '', $description);
            }
            $out[] = [
                'type' => $type, 'product_id' => $r['product_id'] ? (int) $r['product_id'] : null, 'pack_id' => $r['pack_id'] ? (int) $r['pack_id'] : null,
                'name' => $type === 'product' ? $r['product_name'] : ($type === 'pack' ? $r['pack_name'] : $description),
                'code' => $r['product_code'] ?? $r['pack_code'] ?? '',
                'description' => $description, 'quantity' => $qty,
                'width' => $isArea ? (float) $r['width'] : null, 'height' => $isArea ? (float) $r['height'] : null,
                'unit_price' => (float) $r['unit_price'], 'discount_percent' => (float) $r['discount_percent'],
                'tax_rate' => (float) $r['tax_rate'], 'unit' => $r['symbol'] ?? '', 'unit_id' => $r['unit_id'] ? (int) $r['unit_id'] : null,
                'is_area' => $isArea, 'decimal' => (bool) ($r['allows_decimal'] ?? true),
            ];
        }
        return $out;
    }

    /** Coordonnées du client figées sur le document au moment de sa validation. */
    public static function customerSnapshot(int $customerId): array
    {
        $c = Database::instance()->fetch('SELECT code, full_name, company_name, phone, email, address, city, ice, if_number, rc FROM customers WHERE id = ?', [$customerId]);
        return $c ?: [];
    }

    /** Coordonnées de l'entreprise figées sur la facture. */
    public static function businessSnapshot(): array
    {
        $keys = ['business_name', 'business_activity', 'business_address', 'business_city', 'business_phone', 'business_email', 'business_website',
            'business_ice', 'business_if', 'business_rc', 'business_patente', 'business_cnss', 'business_capital', 'bank_name', 'bank_rib', 'bank_swift'];
        $out = [];
        foreach ($keys as $k) {
            $out[$k] = (string) Settings::get($k, '');
        }
        return $out;
    }

    /** Champs communs envoyés par les formulaires de documents. */
    public static function readInput(array $in): array
    {
        $date = static fn($v) => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
        $type = (string) ($in['discount_type'] ?? 'none');
        return [
            'customer_id'    => (int) ($in['customer_id'] ?? 0),
            'items'          => is_array($in['items'] ?? null) ? $in['items'] : [],
            'discount_type'  => in_array($type, ['none', 'percent', 'amount'], true) ? $type : 'none',
            'discount_value' => max(0.0, round((float) ($in['discount_value'] ?? 0), 2)),
            'fees'           => array_intersect_key(is_array($in['fees'] ?? null) ? $in['fees'] : [], self::FEES),
            'issue_date'     => $date($in['issue_date'] ?? null) ?? date('Y-m-d'),
            'due_date'       => $date($in['due_date'] ?? null),
            'valid_until'    => $date($in['valid_until'] ?? null),
            'delivery_date'  => $date($in['delivery_date'] ?? null),
            'production_days'=> ($in['production_days'] ?? '') === '' ? null : max(0, min(365, (int) $in['production_days'])),
            'title'          => mb_substr(trim((string) ($in['title'] ?? '')), 0, 190) ?: null,
            'payment_terms'  => mb_substr(trim((string) ($in['payment_terms'] ?? '')), 0, 2000) ?: null,
            'notes'          => mb_substr(trim((string) ($in['notes'] ?? '')), 0, 5000) ?: null,
            'internal_notes' => mb_substr(trim((string) ($in['internal_notes'] ?? '')), 0, 5000) ?: null,
        ];
    }

    /** Client valide (non supprimé). Le client comptoir est refusé pour les documents nominatifs. */
    public static function requireCustomer(int $customerId, bool $allowWalkIn = false): array
    {
        $c = Database::instance()->fetch('SELECT * FROM customers WHERE id = ? AND deleted_at IS NULL', [$customerId]);
        if (!$c) {
            throw new \DomainException('Choisissez un client.');
        }
        if (!$allowWalkIn && (int) $c['is_walk_in'] === 1) {
            throw new \DomainException('Un devis ou une facture doit être établi au nom d\'un client enregistré (pas le client comptoir).');
        }
        return $c;
    }
}
