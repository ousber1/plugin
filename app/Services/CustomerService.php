<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Compte client : solde, statistiques et relevé.
 *
 * Convention du compte (débit = ce que le client doit, crédit = ce qu'il a réglé) :
 *   Débit  : factures validées non annulées (TTC) + tickets POS terminés non facturés (TTC)
 *            + remboursements versés au client
 *   Crédit : paiements reçus + avoirs validés
 * Solde = Débit − Crédit (positif = le client doit de l'argent).
 */
final class CustomerService
{
    /** Écritures du compte client (sous-requête). Colonnes : entry_date, kind, ref, label, debit, credit, link */
    private static function entriesSql(): string
    {
        return "(
            SELECT i.issue_date AS entry_date, 'invoice' AS kind, i.number AS ref,
                   CONCAT('Facture ', i.number) AS label, i.total AS debit, 0 AS credit, i.id AS doc_id
              FROM invoices i WHERE i.customer_id = :c1 AND i.status NOT IN ('draft','cancelled')
            UNION ALL
            SELECT DATE(s.sale_date), 'sale', s.number, CONCAT('Ticket ', s.number), s.total - s.refunded_amount, 0, s.id
              FROM sales s WHERE s.customer_id = :c2 AND s.status IN ('completed','partially_refunded','refunded') AND s.invoice_id IS NULL
            UNION ALL
            SELECT DATE(p.payment_date), IF(p.direction = 'refund', 'refund', 'payment'), p.number,
                   CONCAT(IF(p.direction = 'refund', 'Remboursement ', 'Paiement '), p.number),
                   IF(p.direction = 'refund', p.amount, 0), IF(p.direction = 'refund', 0, p.amount), p.id
              FROM payments p WHERE p.customer_id = :c3 AND p.status = 'valid'
            UNION ALL
            SELECT c.issue_date, 'credit_note', c.number, CONCAT('Avoir ', c.number), 0, c.total, c.id
              FROM credit_notes c WHERE c.customer_id = :c4 AND c.status IN ('validated','applied','refunded')
        )";
    }

    private static function params(int $customerId): array
    {
        return ['c1' => $customerId, 'c2' => $customerId, 'c3' => $customerId, 'c4' => $customerId];
    }

    /** Solde du compte client (positif = montant dû). */
    public static function balance(int $customerId): float
    {
        return (float) Database::instance()->fetchColumn(
            'SELECT COALESCE(SUM(debit - credit), 0) FROM ' . self::entriesSql() . ' e',
            self::params($customerId)
        );
    }

    /** Indicateurs de la fiche client. */
    public static function stats(int $customerId): array
    {
        $db = Database::instance();
        $totals = $db->fetch(
            'SELECT COALESCE(SUM(debit), 0) AS debit, COALESCE(SUM(credit), 0) AS credit,
                    COALESCE(SUM(CASE WHEN kind IN ("invoice","sale") THEN debit END), 0) AS purchases,
                    COALESCE(SUM(CASE WHEN kind = "payment" THEN credit END), 0) AS paid,
                    MAX(CASE WHEN kind IN ("invoice","sale") THEN entry_date END) AS last_purchase
               FROM ' . self::entriesSql() . ' e',
            self::params($customerId)
        );
        $invoices = $db->fetch(
            "SELECT COUNT(*) AS total,
                    SUM(status IN ('validated','unpaid','partial','overdue')) AS open_count,
                    COALESCE(SUM(CASE WHEN status IN ('validated','unpaid','partial','overdue') THEN balance_due END), 0) AS outstanding,
                    SUM(status IN ('validated','unpaid','partial','overdue') AND due_date < CURDATE()) AS overdue
               FROM invoices WHERE customer_id = ? AND status NOT IN ('draft','cancelled')",
            [$customerId]
        );
        return [
            'purchases'     => (float) $totals['purchases'],
            'paid'          => (float) $totals['paid'],
            'balance'       => (float) $totals['debit'] - (float) $totals['credit'],
            'last_purchase' => $totals['last_purchase'],
            'invoices'      => (int) $invoices['total'],
            'open_invoices' => (int) $invoices['open_count'],
            'outstanding'   => (float) $invoices['outstanding'],
            'overdue'       => (int) $invoices['overdue'],
            'quotations'    => (int) $db->fetchColumn('SELECT COUNT(*) FROM quotations WHERE customer_id = ? AND deleted_at IS NULL', [$customerId]),
            'sales'         => (int) $db->fetchColumn("SELECT COUNT(*) FROM sales WHERE customer_id = ? AND status IN ('completed','partially_refunded','refunded')", [$customerId]),
        ];
    }

    /**
     * Relevé de compte sur une période : solde d'ouverture, écritures, solde de clôture.
     */
    public static function statement(int $customerId, string $from, string $to): array
    {
        $db = Database::instance();
        $opening = (float) $db->fetchColumn(
            'SELECT COALESCE(SUM(debit - credit), 0) FROM ' . self::entriesSql() . ' e WHERE entry_date < :from',
            self::params($customerId) + ['from' => $from]
        );
        $rows = $db->fetchAll(
            'SELECT * FROM ' . self::entriesSql() . ' e WHERE entry_date BETWEEN :from AND :to ORDER BY entry_date, kind, ref',
            self::params($customerId) + ['from' => $from, 'to' => $to]
        );
        $running = $opening;
        $debit = 0.0;
        $credit = 0.0;
        foreach ($rows as &$r) {
            $running += (float) $r['debit'] - (float) $r['credit'];
            $r['balance'] = $running;
            $debit += (float) $r['debit'];
            $credit += (float) $r['credit'];
        }
        unset($r);
        return ['opening' => $opening, 'rows' => $rows, 'debit' => $debit, 'credit' => $credit, 'closing' => $running];
    }

    /** Nom d'affichage : raison sociale ou nom complet. */
    public static function displayName(array $c): string
    {
        return trim((string) ($c['company_name'] ?? '')) !== '' ? (string) $c['company_name'] : (string) $c['full_name'];
    }
}
