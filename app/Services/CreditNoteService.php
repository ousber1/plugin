<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\AuditLog;
use App\Core\Auth;
use App\Core\Database;
use App\Core\DocumentNumber;

/**
 * Avoirs : établis à partir des lignes d'une facture validée.
 *
 * Utilisation du montant :
 *  - « apply »  : imputé sur la facture d'origine (réduit son reste dû) ;
 *                 l'excédent éventuel reste au crédit du compte client ;
 *  - « refund » : remboursé au client (paiement sortant, caisse si espèces) ;
 *  - « keep »   : conservé au crédit du compte client (déduit de ses prochains achats).
 * L'avoir est numéroté et verrouillé dès sa création (document fiscal).
 */
final class CreditNoteService
{
    /**
     * @param array<int,float> $quantities invoice_item_id => quantité créditée
     */
    public static function create(int $invoiceId, array $quantities, string $reason, bool $restock, string $mode, ?int $refundMethodId = null, bool $system = false): int
    {
        if (!$system && !Auth::can('invoices.credit_note')) {
            throw new \DomainException('Vous n\'êtes pas autorisé à émettre un avoir.');
        }
        if (mb_strlen(trim($reason)) < 3) {
            throw new \DomainException('Indiquez le motif de l\'avoir.');
        }
        if (!in_array($mode, ['apply', 'refund', 'keep'], true)) {
            throw new \DomainException('Utilisation de l\'avoir invalide.');
        }
        $db = Database::instance();
        return $db->transaction(function (Database $db) use ($invoiceId, $quantities, $reason, $restock, $mode, $refundMethodId): int {
            $inv = $db->fetch('SELECT * FROM invoices WHERE id = ? FOR UPDATE', [$invoiceId]);
            if (!$inv || in_array($inv['status'], ['draft', 'cancelled'], true)) {
                throw new \DomainException('Un avoir ne peut être établi que sur une facture validée.');
            }
            // Quantités déjà créditées par ligne
            $credited = [];
            foreach ($db->fetchAll(
                "SELECT cni.invoice_item_id, SUM(cni.quantity) AS q FROM credit_note_items cni JOIN credit_notes c ON c.id = cni.credit_note_id
                  WHERE c.invoice_id = ? AND c.status <> 'cancelled' GROUP BY cni.invoice_item_id",
                [$invoiceId]
            ) as $r) {
                $credited[(int) $r['invoice_item_id']] = (float) $r['q'];
            }
            $lines = [];
            $sub = $tax = 0.0;
            foreach ($db->fetchAll('SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY sort_order', [$invoiceId]) as $it) {
                $qty = round((float) ($quantities[(int) $it['id']] ?? 0), 3);
                if ($qty <= 0) {
                    continue;
                }
                $available = round((float) $it['quantity'] - ($credited[(int) $it['id']] ?? 0), 3);
                if ($qty > $available + 0.0005) {
                    throw new \DomainException('Quantité créditée supérieure au reste facturable pour « ' . $it['description'] . ' » (' . number_fr($available, 3) . ').');
                }
                $ratio = (float) $it['quantity'] != 0.0 ? $qty / (float) $it['quantity'] : 0;
                $ls = round((float) $it['line_subtotal'] * $ratio, 2);
                $lt = round((float) $it['line_tax'] * $ratio, 2);
                $lines[] = ['it' => $it, 'qty' => $qty, 'sub' => $ls, 'tax' => $lt];
                $sub += $ls;
                $tax += $lt;
            }
            // Frais de la facture : crédités au prorata lors d'un avoir total
            $itemsTotal = (float) $db->fetchColumn('SELECT COALESCE(SUM(line_total), 0) FROM invoice_items WHERE invoice_id = ?', [$invoiceId]);
            $fees = (float) $inv['design_fees'] + (float) $inv['installation_fees'] + (float) $inv['transport_fees'];
            $full = count($lines) > 0 && abs(($sub + $tax) - $itemsTotal) < 0.01 && !$credited;
            if (!$lines) {
                throw new \DomainException('Sélectionnez au moins une ligne à créditer.');
            }
            $total = round($sub + $tax, 2);
            if ($full && $fees > 0) {
                // Avoir total : on reprend exactement le total de la facture (frais et TVA compris)
                $sub = round((float) $inv['total'] - (float) $inv['tax_total'], 2);
                $tax = (float) $inv['tax_total'];
                $total = (float) $inv['total'];
            }
            $id = $db->insert('credit_notes', [
                'number' => DocumentNumber::next('credit_note'), 'invoice_id' => $invoiceId, 'customer_id' => $inv['customer_id'],
                'user_id' => Auth::id(), 'status' => 'validated', 'issue_date' => date('Y-m-d'), 'reason' => mb_substr($reason, 0, 255),
                'subtotal' => round($sub, 2), 'tax_total' => round($tax, 2), 'total' => $total, 'restock' => $restock ? 1 : 0,
                'customer_snapshot' => json_encode(DocumentService::customerSnapshot((int) $inv['customer_id']), JSON_UNESCAPED_UNICODE),
                'is_locked' => 1, 'validated_at' => date('Y-m-d H:i:s'), 'validated_by' => Auth::id(),
            ]);
            foreach ($lines as $i => $l) {
                $it = $l['it'];
                $db->insert('credit_note_items', [
                    'credit_note_id' => $id, 'invoice_item_id' => $it['id'], 'product_id' => $it['product_id'], 'description' => $it['description'],
                    'quantity' => $l['qty'], 'unit_id' => $it['unit_id'], 'unit_price' => $it['unit_price'], 'tax_rate' => $it['tax_rate'],
                    'line_subtotal' => $l['sub'], 'line_tax' => $l['tax'], 'line_total' => round($l['sub'] + $l['tax'], 2), 'sort_order' => $i,
                ]);
                if ($restock && $it['product_id'] && (int) $db->fetchColumn('SELECT track_stock FROM products WHERE id = ?', [$it['product_id']]) === 1) {
                    StockService::move('product', (int) $it['product_id'], $l['qty'], 'return', null, 'credit_note', $id, 'Avoir');
                }
            }
            if ($full && $fees > 0) {
                $feeSub = round($sub - array_sum(array_column($lines, 'sub')), 2);
                $feeTax = round($tax - array_sum(array_column($lines, 'tax')), 2);
                $db->insert('credit_note_items', [
                    'credit_note_id' => $id, 'description' => 'Frais (design, installation, transport)', 'quantity' => 1,
                    'unit_price' => $feeSub, 'tax_rate' => $feeSub > 0 ? round($feeTax / $feeSub * 100, 3) : 0,
                    'line_subtotal' => $feeSub, 'line_tax' => $feeTax, 'line_total' => round($feeSub + $feeTax, 2), 'sort_order' => 999,
                ]);
            }

            if ($mode === 'apply') {
                InvoiceService::recalc($invoiceId);
                $balance = (float) $db->fetchColumn('SELECT balance_due FROM invoices WHERE id = ?', [$invoiceId]);
                $apply = round(min($total, $balance), 2);
                if ($apply > 0) {
                    $db->insert('credit_note_allocations', ['credit_note_id' => $id, 'invoice_id' => $invoiceId, 'amount' => $apply, 'created_by' => Auth::id()]);
                }
                $db->update('credit_notes', ['applied_amount' => $apply, 'status' => $apply >= $total - 0.004 ? 'applied' : 'validated'], 'id = :id', ['id' => $id]);
                InvoiceService::recalc($invoiceId);
            } elseif ($mode === 'refund') {
                if (!Auth::can('payments.refund')) {
                    throw new \DomainException('Vous n\'êtes pas autorisé à rembourser un client.');
                }
                PaymentService::refundCreditNote($id, (int) $refundMethodId);
            }
            AuditLog::log('create', 'invoices', 'credit_note', $id, 'Avoir de ' . money($total) . ' sur la facture ' . $inv['number'] . ' (' . $mode . ') : ' . $reason);
            return $id;
        });
    }
}
