<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\AuditLog;
use App\Core\Auth;
use App\Core\Database;
use App\Core\DocumentNumber;
use App\Core\Settings;

/**
 * Factures : brouillons, validation, acomptes, soldes, facturation d'un ticket,
 * suivi des paiements et des retards, annulation par avoir.
 *
 * Règles comptables :
 *  - un brouillon n'a pas de numéro et reste modifiable / supprimable ;
 *  - la validation attribue le numéro (séquence sans trou), fige les coordonnées
 *    du client et de l'entreprise et verrouille le document (is_locked) ;
 *  - une facture validée n'est plus jamais modifiée ni supprimée : on la corrige
 *    ou l'annule par un avoir (traçable) ;
 *  - une facture de solde reprend les lignes du devis et déduit chaque acompte
 *    par une ligne négative au même taux de TVA.
 */
final class InvoiceService
{
    public const TYPES = ['standard' => 'Facture', 'deposit' => 'Facture d\'acompte', 'balance' => 'Facture de solde'];

    /* ------------------------------------------------------------------
       Brouillons
       ------------------------------------------------------------------ */

    /** Crée ou met à jour un brouillon. Retourne l'id. */
    public static function saveDraft(?int $id, array $input): int
    {
        $db = Database::instance();
        $data = DocumentService::readInput($input);
        $customer = DocumentService::requireCustomer($data['customer_id']);
        if ($id) {
            $inv = $db->fetch('SELECT * FROM invoices WHERE id = ?', [$id]);
            if (!$inv || $inv['status'] !== 'draft') {
                throw new \DomainException('Seule une facture brouillon peut être modifiée. Une facture validée se corrige par un avoir.');
            }
        }
        $doc = DocumentService::compute($data['items'], (int) $customer['id'], $data['discount_type'], $data['discount_value'], $data['fees']);
        $t = $doc['totals'];
        $row = [
            'customer_id' => (int) $customer['id'], 'issue_date' => $data['issue_date'],
            'due_date' => $data['due_date'] ?? date('Y-m-d', strtotime($data['issue_date'] . ' +' . (int) Settings::get('invoice_due_days', 30) . ' days')),
            'subtotal' => $t['subtotal'], 'discount_type' => $data['discount_type'],
            'discount_value' => $data['discount_type'] === 'none' ? 0 : $data['discount_value'], 'discount_total' => $t['discount_total'],
            'design_fees' => $t['fees']['design_fees'], 'installation_fees' => $t['fees']['installation_fees'], 'transport_fees' => $t['fees']['transport_fees'],
            'tax_total' => $t['tax_total'], 'total' => $t['total'], 'balance_due' => $t['total'], 'cost_total' => $t['cost_total'],
            'payment_terms' => $data['payment_terms'], 'notes' => $data['notes'], 'internal_notes' => $data['internal_notes'],
        ];
        return $db->transaction(function (Database $db) use ($id, $row, $doc, $input): int {
            if ($id) {
                $db->update('invoices', $row, 'id = :id', ['id' => $id]);
            } else {
                $row += [
                    'invoice_type'   => in_array($input['invoice_type'] ?? '', array_keys(self::TYPES), true) ? $input['invoice_type'] : 'standard',
                    'quotation_id'   => !empty($input['quotation_id']) ? (int) $input['quotation_id'] : null,
                    'sales_order_id' => !empty($input['sales_order_id']) ? (int) $input['sales_order_id'] : null,
                    'user_id'        => Auth::id(), 'status' => 'draft',
                ];
                $id = $db->insert('invoices', $row);
            }
            DocumentService::saveItems('invoice_items', 'invoice_id', $id, $doc['lines']);
            return $id;
        });
    }

    /** Facture brouillon reprenant toutes les lignes d'un devis. */
    public static function fromQuotation(int $quotationId): int
    {
        $db = Database::instance();
        $q = $db->fetch('SELECT * FROM quotations WHERE id = ? AND deleted_at IS NULL', [$quotationId]);
        if (!$q) {
            throw new \DomainException('Devis introuvable.');
        }
        if ((int) $db->fetchColumn("SELECT COUNT(*) FROM invoices WHERE quotation_id = ? AND invoice_type = 'deposit' AND status NOT IN ('draft','cancelled')", [$quotationId]) > 0) {
            throw new \DomainException('Des acomptes ont été facturés sur ce devis : établissez une facture de solde.');
        }
        return $db->transaction(function () use ($q): int {
            $id = self::saveDraft(null, self::inputFromDoc('quotation_items', 'quotation_id', $q) + ['quotation_id' => $q['id'], 'invoice_type' => 'standard']);
            QuotationService::markConverted((int) $q['id'], 'invoice', $id);
            return $id;
        });
    }

    /**
     * Facture d'acompte : une ligne par taux de TVA du devis, égale au pourcentage
     * demandé de la base HT correspondante.
     */
    public static function deposit(int $quotationId, float $percent): int
    {
        $db = Database::instance();
        if ($percent <= 0 || $percent >= 100) {
            throw new \DomainException('Le pourcentage d\'acompte doit être compris entre 1 et 99 %.');
        }
        $q = $db->fetch('SELECT * FROM quotations WHERE id = ? AND deleted_at IS NULL', [$quotationId]);
        if (!$q || in_array($q['status'], ['refused', 'expired'], true)) {
            throw new \DomainException('Devis introuvable, refusé ou expiré.');
        }
        $already = (float) $db->fetchColumn("SELECT COALESCE(SUM(total), 0) FROM invoices WHERE quotation_id = ? AND invoice_type = 'deposit' AND status NOT IN ('cancelled')", [$quotationId]);
        $bases = [];
        foreach ($db->fetchAll('SELECT tax_rate, SUM(line_subtotal) AS base FROM quotation_items WHERE quotation_id = ? GROUP BY tax_rate', [$quotationId]) as $r) {
            $bases[(string) (float) $r['tax_rate']] = (float) $r['base'];
        }
        $fees = (float) $q['design_fees'] + (float) $q['installation_fees'] + (float) $q['transport_fees'];
        if ($fees > 0) {
            $rate = (string) (float) ($db->fetchColumn('SELECT rate FROM taxes WHERE id = ?', [(int) Settings::get('default_tax_id', 1)]) ?? 0);
            $bases[$rate] = ($bases[$rate] ?? 0) + $fees;
        }
        $items = [];
        foreach ($bases as $rate => $base) {
            $amount = round($base * $percent / 100, 2);
            if ($amount > 0) {
                $items[] = ['type' => 'custom', 'description' => 'Acompte de ' . number_fr($percent, 0) . ' % sur le devis ' . $q['number'] . (count($bases) > 1 ? ' (TVA ' . number_fr((float) $rate, 0) . ' %)' : ''),
                    'quantity' => 1, 'unit_price' => $amount, 'discount_percent' => 0, 'tax_rate' => (float) $rate];
            }
        }
        if (!$items) {
            throw new \DomainException('Le devis ne contient aucun montant.');
        }
        $estimated = array_sum(array_map(static fn($i) => $i['unit_price'] * (1 + $i['tax_rate'] / 100), $items));
        if ($already + $estimated > (float) $q['total'] + 0.05) {
            throw new \DomainException('Le total des acomptes dépasserait le montant du devis (' . money($q['total']) . ', déjà facturé ' . money($already) . ').');
        }
        return $db->transaction(function (Database $db) use ($q, $items): int {
            $id = self::saveDraft(null, [
                'customer_id' => $q['customer_id'], 'items' => $items, 'quotation_id' => $q['id'], 'invoice_type' => 'deposit',
                'payment_terms' => 'Acompte payable à la commande.', 'notes' => $q['title'] ? 'Objet : ' . $q['title'] : null,
                'due_date' => date('Y-m-d'),
            ]);
            if (!in_array($q['status'], ['accepted', 'converted'], true)) {
                QuotationService::setStatus((int) $q['id'], 'accepted', 'Acompte facturé');
            }
            return $id;
        });
    }

    /** Facture de solde : lignes du devis − acomptes validés (lignes négatives par taux). */
    public static function balance(int $quotationId): int
    {
        $db = Database::instance();
        $q = $db->fetch('SELECT * FROM quotations WHERE id = ? AND deleted_at IS NULL', [$quotationId]);
        if (!$q) {
            throw new \DomainException('Devis introuvable.');
        }
        $deposits = $db->fetchAll("SELECT * FROM invoices WHERE quotation_id = ? AND invoice_type = 'deposit' AND status NOT IN ('draft','cancelled') ORDER BY id", [$quotationId]);
        if (!$deposits) {
            throw new \DomainException('Aucune facture d\'acompte validée sur ce devis : établissez une facture normale.');
        }
        if ((int) $db->fetchColumn("SELECT COUNT(*) FROM invoices WHERE quotation_id = ? AND invoice_type = 'balance' AND status <> 'cancelled'", [$quotationId]) > 0) {
            throw new \DomainException('Une facture de solde existe déjà pour ce devis.');
        }
        $input = self::inputFromDoc('quotation_items', 'quotation_id', $q);
        // La remise globale du devis ne porte que sur les lignes positives (SaleCalculator) : les déductions restent intactes
        foreach ($deposits as $d) {
            foreach ($db->fetchAll('SELECT tax_rate, SUM(line_subtotal) AS base FROM invoice_items WHERE invoice_id = ? GROUP BY tax_rate', [$d['id']]) as $r) {
                $input['items'][] = ['type' => 'custom', 'description' => 'Déduction acompte facture ' . $d['number'] . ' du ' . format_date($d['issue_date']),
                    'quantity' => 1, 'unit_price' => -(float) $r['base'], 'discount_percent' => 0, 'tax_rate' => (float) $r['tax_rate']];
            }
        }
        return $db->transaction(function (Database $db) use ($q, $input, $deposits): int {
            $id = self::saveDraft(null, $input + ['quotation_id' => $q['id'], 'invoice_type' => 'balance']);
            $sum = 0.0;
            foreach ($deposits as $d) {
                $db->insert('invoice_deposits', ['balance_invoice_id' => $id, 'deposit_invoice_id' => $d['id'], 'amount' => $d['total']]);
                $sum += (float) $d['total'];
            }
            $db->update('invoices', ['deposits_deducted' => round($sum, 2)], 'id = :id', ['id' => $id]);
            QuotationService::markConverted((int) $q['id'], 'invoice', $id);
            return $id;
        });
    }

    /** Données de formulaire reconstruites depuis un devis / bon de commande. */
    public static function inputFromDoc(string $table, string $fk, array $doc): array
    {
        return [
            'customer_id' => $doc['customer_id'], 'items' => DocumentService::itemsForForm($table, $fk, (int) $doc['id']),
            'discount_type' => $doc['discount_type'] ?? 'none', 'discount_value' => $doc['discount_value'] ?? 0,
            'fees' => ['design_fees' => $doc['design_fees'] ?? 0, 'installation_fees' => $doc['installation_fees'] ?? 0, 'transport_fees' => $doc['transport_fees'] ?? 0],
            'payment_terms' => $doc['payment_terms'] ?? null, 'notes' => $doc['notes'] ?? null,
        ];
    }

    /* ------------------------------------------------------------------
       Ticket de caisse → facture (validée immédiatement, paiements imputés)
       ------------------------------------------------------------------ */

    public static function fromSale(int $saleId): int
    {
        $db = Database::instance();
        return $db->transaction(function (Database $db) use ($saleId): int {
            $sale = $db->fetch('SELECT s.*, c.is_walk_in FROM sales s JOIN customers c ON c.id = s.customer_id WHERE s.id = ? FOR UPDATE', [$saleId]);
            if (!$sale || !in_array($sale['status'], ['completed', 'partially_refunded'], true)) {
                throw new \DomainException('Seule une vente terminée peut être facturée.');
            }
            if ($sale['invoice_id']) {
                throw new \DomainException('Ce ticket a déjà été facturé.');
            }
            if ((int) $sale['is_walk_in'] === 1) {
                throw new \DomainException('Le ticket est au nom du client comptoir : affectez d\'abord la vente à un client enregistré (bouton « Changer de client »).');
            }
            if ((float) $sale['refunded_amount'] > 0) {
                throw new \DomainException('Ce ticket a fait l\'objet d\'un retour : la facture ne peut plus reprendre la vente à l\'identique.');
            }
            $id = $db->insert('invoices', [
                'invoice_type' => 'standard', 'customer_id' => $sale['customer_id'], 'user_id' => Auth::id(), 'sale_id' => $saleId,
                'status' => 'draft', 'issue_date' => date('Y-m-d'), 'due_date' => date('Y-m-d'),
                'subtotal' => $sale['subtotal'], 'discount_type' => $sale['discount_type'], 'discount_value' => $sale['discount_value'],
                'discount_total' => $sale['discount_total'], 'tax_total' => $sale['tax_total'], 'total' => $sale['total'],
                'balance_due' => $sale['total'], 'cost_total' => $sale['cost_total'],
                'notes' => 'Facture du ticket ' . $sale['number'] . ' du ' . format_date($sale['sale_date'], true) . '.',
            ]);
            foreach ($db->fetchAll('SELECT * FROM sale_items WHERE sale_id = ? ORDER BY sort_order', [$saleId]) as $i => $it) {
                $db->insert('invoice_items', [
                    'invoice_id' => $id, 'product_id' => $it['product_id'], 'pack_id' => $it['pack_id'], 'description' => $it['description'],
                    'width' => $it['width'], 'height' => $it['height'], 'dimension_unit' => $it['dimension_unit'], 'quantity' => $it['quantity'],
                    'unit_id' => $it['unit_id'], 'unit_price' => $it['unit_price'], 'discount_percent' => $it['discount_percent'],
                    'tax_rate' => $it['tax_rate'], 'line_subtotal' => $it['line_subtotal'], 'line_tax' => $it['line_tax'],
                    'line_total' => $it['line_total'], 'unit_cost' => $it['unit_cost'], 'sort_order' => $i,
                ]);
            }
            // Les paiements du ticket sont imputés sur la facture
            foreach ($db->fetchAll("SELECT id, amount FROM payments WHERE sale_id = ? AND direction = 'in' AND status = 'valid'", [$saleId]) as $p) {
                $db->insert('payment_allocations', ['payment_id' => $p['id'], 'invoice_id' => $id, 'amount' => $p['amount']]);
            }
            $db->update('sales', ['invoice_id' => $id], 'id = :id', ['id' => $saleId]);
            self::validate($id);
            return $id;
        });
    }

    /* ------------------------------------------------------------------
       Validation, recalcul, retards
       ------------------------------------------------------------------ */

    public static function validate(int $id): string
    {
        $db = Database::instance();
        return $db->transaction(function (Database $db) use ($id): string {
            $inv = $db->fetch('SELECT * FROM invoices WHERE id = ? FOR UPDATE', [$id]);
            if (!$inv || $inv['status'] !== 'draft') {
                throw new \DomainException('Cette facture est déjà validée.');
            }
            if ((int) $db->fetchColumn('SELECT COUNT(*) FROM invoice_items WHERE invoice_id = ?', [$id]) === 0) {
                throw new \DomainException('Une facture sans ligne ne peut pas être validée.');
            }
            if ((float) $inv['total'] < 0) {
                throw new \DomainException('Le total de la facture est négatif : utilisez un avoir.');
            }
            $customer = $db->fetch('SELECT is_walk_in FROM customers WHERE id = ?', [$inv['customer_id']]);
            if (!$customer || (int) $customer['is_walk_in'] === 1) {
                throw new \DomainException('Une facture doit être au nom d\'un client enregistré.');
            }
            $issue = $inv['issue_date'] < date('Y-m-d', strtotime('-31 days')) ? date('Y-m-d') : $inv['issue_date'];
            $number = DocumentNumber::next('invoice', new \DateTimeImmutable($issue));
            $db->update('invoices', [
                'number' => $number, 'status' => 'validated', 'issue_date' => $issue,
                'customer_snapshot' => json_encode(DocumentService::customerSnapshot((int) $inv['customer_id']), JSON_UNESCAPED_UNICODE),
                'business_snapshot' => json_encode(DocumentService::businessSnapshot(), JSON_UNESCAPED_UNICODE),
                'is_locked' => 1, 'validated_at' => date('Y-m-d H:i:s'), 'validated_by' => Auth::id(),
            ], 'id = :id', ['id' => $id]);
            self::recalc($id);
            AuditLog::log('validate', 'invoices', 'invoice', $id, (self::TYPES[$inv['invoice_type']] ?? 'Facture') . ' ' . $number . ' validée : ' . money($inv['total']));
            return $number;
        });
    }

    /** Recalcule montant payé, avoirs imputés, reste dû et statut. */
    public static function recalc(int $id): void
    {
        $db = Database::instance();
        $inv = $db->fetch('SELECT id, status, total, due_date FROM invoices WHERE id = ?', [$id]);
        if (!$inv || in_array($inv['status'], ['draft', 'cancelled'], true)) {
            return;
        }
        $paid = (float) $db->fetchColumn(
            "SELECT COALESCE(SUM(pa.amount), 0) FROM payment_allocations pa JOIN payments p ON p.id = pa.payment_id WHERE pa.invoice_id = ? AND p.status = 'valid'",
            [$id]
        );
        $credited = (float) $db->fetchColumn(
            "SELECT COALESCE(SUM(a.amount), 0) FROM credit_note_allocations a JOIN credit_notes c ON c.id = a.credit_note_id WHERE a.invoice_id = ? AND c.status <> 'cancelled'",
            [$id]
        );
        $balance = round((float) $inv['total'] - $paid - $credited, 2);
        if ($balance <= 0.004) {
            $status = 'paid';
        } elseif ($inv['due_date'] !== null && $inv['due_date'] < date('Y-m-d')) {
            $status = 'overdue';
        } elseif ($paid + $credited > 0.004) {
            $status = 'partial';
        } else {
            $status = 'unpaid';
        }
        $db->update('invoices', ['paid_amount' => round($paid, 2), 'credited_amount' => round($credited, 2),
            'balance_due' => max(0, $balance), 'status' => $status], 'id = :id', ['id' => $id]);
    }

    /** Passe en « En retard » les factures échues non soldées et notifie (une fois). */
    public static function refreshOverdue(): void
    {
        $db = Database::instance();
        $rows = $db->fetchAll(
            "SELECT i.id, i.number, i.balance_due, i.due_date, COALESCE(NULLIF(c.company_name, ''), c.full_name) AS customer
               FROM invoices i JOIN customers c ON c.id = i.customer_id
              WHERE i.status IN ('validated','unpaid','partial') AND i.due_date < CURDATE() AND i.balance_due > 0.004"
        );
        foreach ($rows as $r) {
            $db->update('invoices', ['status' => 'overdue'], 'id = :id', ['id' => $r['id']]);
            NotificationService::create('invoice_overdue', 'Facture en retard : ' . $r['number'],
                $r['customer'] . ' — reste dû ' . money($r['balance_due']) . ', échue le ' . format_date($r['due_date']) . '.',
                'invoices/' . $r['id'], 'danger', null, null, 'invoice_overdue:' . $r['id'], 'invoice', (int) $r['id']);
        }
    }

    /* ------------------------------------------------------------------
       Suppression (brouillon) et annulation (facture validée → avoir)
       ------------------------------------------------------------------ */

    public static function deleteDraft(int $id): void
    {
        $db = Database::instance();
        $inv = $db->fetch('SELECT * FROM invoices WHERE id = ?', [$id]);
        if (!$inv || $inv['status'] !== 'draft') {
            throw new \DomainException('Seul un brouillon peut être supprimé.');
        }
        $db->transaction(function (Database $db) use ($id, $inv): void {
            $db->query('DELETE FROM invoice_deposits WHERE balance_invoice_id = ?', [$id]);
            $db->query('DELETE FROM payment_allocations WHERE invoice_id = ?', [$id]);
            $db->query('UPDATE sales SET invoice_id = NULL WHERE invoice_id = ?', [$id]);
            $db->query('DELETE FROM invoices WHERE id = ?', [$id]);
            if ($inv['quotation_id']) {
                $db->query("UPDATE quotations SET status = 'accepted', converted_type = NULL, converted_id = NULL WHERE id = ? AND converted_type = 'invoice' AND converted_id = ?", [$inv['quotation_id'], $id]);
            }
        });
        AuditLog::log('delete', 'invoices', 'invoice', $id, 'Brouillon de facture supprimé');
    }

    /** Annulation d'une facture validée : avoir total automatique, traçable. */
    public static function cancel(int $id, string $reason): int
    {
        if (!Auth::can('invoices.cancel')) {
            throw new \DomainException('Vous n\'êtes pas autorisé à annuler une facture.');
        }
        if (mb_strlen(trim($reason)) < 3) {
            throw new \DomainException('Indiquez le motif de l\'annulation.');
        }
        $db = Database::instance();
        $inv = $db->fetch('SELECT * FROM invoices WHERE id = ?', [$id]);
        if (!$inv || in_array($inv['status'], ['draft', 'cancelled'], true)) {
            throw new \DomainException('Cette facture ne peut pas être annulée.');
        }
        // Les paiements du ticket d'origine restent attachés au ticket (ils ne bloquent pas l'annulation)
        $foreignPaid = (float) $db->fetchColumn(
            "SELECT COALESCE(SUM(pa.amount), 0) FROM payment_allocations pa JOIN payments p ON p.id = pa.payment_id
              WHERE pa.invoice_id = ? AND p.status = 'valid' AND (p.sale_id IS NULL OR p.sale_id <> ?)",
            [$id, (int) ($inv['sale_id'] ?? 0)]
        );
        if ($foreignPaid > 0.004) {
            throw new \DomainException('Des paiements sont imputés sur cette facture (' . money($foreignPaid) . ') : annulez-les ou émettez un avoir partiel.');
        }
        if ((float) $inv['credited_amount'] > 0.004) {
            throw new \DomainException('Un avoir est déjà imputé sur cette facture : émettez un avoir pour le reste.');
        }
        return $db->transaction(function (Database $db) use ($id, $inv, $reason): int {
            if ($inv['sale_id']) {
                // Facture d'un ticket : le ticket redevient une vente non facturée avec ses propres paiements
                $db->query('DELETE pa FROM payment_allocations pa JOIN payments p ON p.id = pa.payment_id WHERE pa.invoice_id = ? AND p.sale_id = ?', [$id, $inv['sale_id']]);
                self::recalc($id);
            }
            $quantities = [];
            foreach ($db->fetchAll('SELECT id, quantity FROM invoice_items WHERE invoice_id = ?', [$id]) as $it) {
                $quantities[(int) $it['id']] = (float) $it['quantity'];
            }
            $cnId = CreditNoteService::create($id, $quantities, 'Annulation de la facture : ' . $reason, false, 'apply', null, true);
            $db->update('invoices', ['status' => 'cancelled', 'balance_due' => 0, 'cancelled_at' => date('Y-m-d H:i:s'),
                'cancelled_by' => Auth::id(), 'cancel_reason' => mb_substr($reason, 0, 255)], 'id = :id', ['id' => $id]);
            if ($inv['sale_id']) {
                $db->query('UPDATE sales SET invoice_id = NULL WHERE id = ?', [$inv['sale_id']]);
            }
            AuditLog::log('cancel', 'invoices', 'invoice', $id, 'Facture ' . $inv['number'] . ' annulée par avoir : ' . $reason);
            return $cnId;
        });
    }
}
