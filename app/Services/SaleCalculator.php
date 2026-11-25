<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Calcul des montants d'une vente / d'un document (fonction pure, sans base).
 * Le même algorithme est reproduit dans assets/js/pos.js pour l'affichage ;
 * le serveur recalcule toujours et seul son résultat est enregistré.
 *
 * Règles :
 *  - montant de ligne HT = quantité × prix unitaire HT × (1 − remise ligne %) — arrondi au centime
 *  - la remise globale (en % ou en montant HT) est répartie au prorata des lignes,
 *    le reliquat d'arrondi étant porté par la ligne la plus importante
 *  - TVA calculée par ligne sur le montant HT après remise globale
 *  - total TTC = Σ HT + Σ TVA
 */
final class SaleCalculator
{
    /**
     * @param array<int, array{quantity: float, unit_price: float, discount_percent: float, tax_rate: float}> $lines
     * @return array{lines: array, subtotal: float, discount_total: float, net_total: float, tax_total: float, total: float, tax_breakdown: array}
     */
    public static function compute(array $lines, string $discountType = 'none', float $discountValue = 0.0): array
    {
        $subtotal = 0.0;
        foreach ($lines as $i => $l) {
            $gross = round((float) $l['quantity'] * (float) $l['unit_price'] * (1 - (float) $l['discount_percent'] / 100), 2);
            $lines[$i]['gross'] = $gross;
            $subtotal += $gross;
        }
        $subtotal = round($subtotal, 2);

        $discount = match ($discountType) {
            'percent' => round($subtotal * max(0.0, min(100.0, $discountValue)) / 100, 2),
            'amount'  => round(max(0.0, min($subtotal, $discountValue)), 2),
            default   => 0.0,
        };

        // Répartition proportionnelle de la remise globale
        $allocated = 0.0;
        $largest = null;
        foreach ($lines as $i => $l) {
            $share = $subtotal > 0 ? round($discount * $l['gross'] / $subtotal, 2) : 0.0;
            $lines[$i]['global_discount'] = $share;
            $allocated += $share;
            if ($largest === null || $l['gross'] > $lines[$largest]['gross']) {
                $largest = $i;
            }
        }
        if ($largest !== null) {
            $lines[$largest]['global_discount'] = round($lines[$largest]['global_discount'] + ($discount - $allocated), 2);
        }

        $taxTotal = 0.0;
        $net = 0.0;
        $breakdown = [];
        foreach ($lines as $i => $l) {
            $lineNet = round($l['gross'] - $l['global_discount'], 2);
            $lineTax = round($lineNet * (float) $l['tax_rate'] / 100, 2);
            $lines[$i]['line_subtotal'] = $lineNet;
            $lines[$i]['line_tax'] = $lineTax;
            $lines[$i]['line_total'] = round($lineNet + $lineTax, 2);
            $net += $lineNet;
            $taxTotal += $lineTax;
            $key = number_format((float) $l['tax_rate'], 3, '.', '');
            $breakdown[$key] ??= ['rate' => (float) $l['tax_rate'], 'base' => 0.0, 'tax' => 0.0];
            $breakdown[$key]['base'] = round($breakdown[$key]['base'] + $lineNet, 2);
            $breakdown[$key]['tax'] = round($breakdown[$key]['tax'] + $lineTax, 2);
        }
        krsort($breakdown);
        return [
            'lines'          => $lines,
            'subtotal'       => $subtotal,
            'discount_total' => $discount,
            'net_total'      => round($net, 2),
            'tax_total'      => round($taxTotal, 2),
            'total'          => round($net + $taxTotal, 2),
            'tax_breakdown'  => array_values($breakdown),
        ];
    }
}
