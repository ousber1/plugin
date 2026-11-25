<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Définition unique du chiffre d'affaires, réutilisée par le tableau de bord
 * et les rapports (Phase 7) pour garantir des chiffres identiques partout.
 *
 * Chiffre d'affaires HT =
 *   + tickets POS terminés NON facturés (total HT, au prorata des remboursements)
 *   + factures validées non annulées (facture, acompte, solde — la facture de
 *     solde porte des lignes négatives de déduction des acomptes)
 *   − avoirs validés (HT)
 * Un ticket transformé en facture n'est compté qu'une fois (via la facture).
 */
final class Analytics
{
    /**
     * Sous-requête : une ligne par document de vente.
     * Colonnes : doc_date (DATE), revenue_ht, cost, customer_id, source, doc_id
     */
    public static function revenueSql(): string
    {
        return "(
            SELECT DATE(s.sale_date) AS doc_date,
                   (s.total - s.tax_total) * (CASE WHEN s.total > 0 THEN GREATEST(0, 1 - s.refunded_amount / s.total) ELSE 1 END) AS revenue_ht,
                   s.cost_total * (CASE WHEN s.total > 0 THEN GREATEST(0, 1 - s.refunded_amount / s.total) ELSE 1 END) AS cost,
                   s.customer_id, 'sale' AS source, s.id AS doc_id
              FROM sales s
             WHERE s.status IN ('completed','partially_refunded','refunded') AND s.invoice_id IS NULL
            UNION ALL
            SELECT i.issue_date, i.total - i.tax_total, i.cost_total, i.customer_id, 'invoice', i.id
              FROM invoices i
             WHERE i.status NOT IN ('draft','cancelled')
            UNION ALL
            SELECT c.issue_date, -c.subtotal, 0, c.customer_id, 'credit_note', c.id
              FROM credit_notes c
             WHERE c.status IN ('validated','applied','refunded')
        )";
    }

    /**
     * Sous-requête des lignes vendues (POS non facturé + factures validées).
     * Colonnes : doc_date, product_id, description, quantity, revenue_ht, cost
     */
    public static function soldLinesSql(): string
    {
        return "(
            SELECT DATE(s.sale_date) AS doc_date, si.product_id, si.description,
                   (si.quantity - si.returned_quantity) AS quantity,
                   si.line_subtotal * (CASE WHEN si.quantity > 0 THEN (si.quantity - si.returned_quantity) / si.quantity ELSE 0 END) AS revenue_ht,
                   si.unit_cost * (si.quantity - si.returned_quantity) AS cost
              FROM sale_items si JOIN sales s ON s.id = si.sale_id
             WHERE s.status IN ('completed','partially_refunded','refunded') AND s.invoice_id IS NULL
            UNION ALL
            SELECT i.issue_date, ii.product_id, ii.description, ii.quantity, ii.line_subtotal, ii.unit_cost * ii.quantity
              FROM invoice_items ii JOIN invoices i ON i.id = ii.invoice_id
             WHERE i.status NOT IN ('draft','cancelled')
        )";
    }

    /** [CA HT, coût des ventes] sur une période (dates incluses). */
    public static function revenueBetween(string $from, string $to): array
    {
        $row = Database::instance()->fetch(
            'SELECT COALESCE(SUM(revenue_ht),0) AS revenue, COALESCE(SUM(cost),0) AS cost
               FROM ' . self::revenueSql() . ' r WHERE r.doc_date BETWEEN ? AND ?',
            [$from, $to]
        );
        return [(float) $row['revenue'], (float) $row['cost']];
    }

    public static function expensesBetween(string $from, string $to): float
    {
        return (float) Database::instance()->fetchColumn(
            'SELECT COALESCE(SUM(amount - tax_amount),0) FROM expenses WHERE deleted_at IS NULL AND expense_date BETWEEN ? AND ?',
            [$from, $to]
        );
    }

    /**
     * Bénéfice estimé = CA HT − coût des ventes − dépenses HT.
     * Les achats de matières ne sont pas déduits deux fois : ils entrent dans
     * le coût des ventes via le coût unitaire enregistré sur chaque ligne.
     */
    public static function profitBetween(string $from, string $to): float
    {
        [$revenue, $cost] = self::revenueBetween($from, $to);
        return $revenue - $cost - self::expensesBetween($from, $to);
    }
}
