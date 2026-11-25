<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\AuditLog;
use App\Core\Auth;
use App\Core\Database;
use App\Core\DocumentNumber;

/**
 * Règlements clients (hors encaissement immédiat au POS).
 *
 * Un règlement est ventilé :
 *  - sur des factures (payment_allocations) ;
 *  - sur des tickets POS vendus à crédit (une ligne de paiement par ticket, sale_id) ;
 *  - le reste éventuel est une avance (unallocated_amount), imputable plus tard.
 * Toutes les lignes d'un même règlement partagent le même payment_group
 * et figurent sur un seul reçu.
 */
final class PaymentService
{
    /** Factures et tickets à crédit restant dus pour un client. */
    public static function openItems(int $customerId): array
    {
        $db = Database::instance();
        InvoiceService::refreshOverdue();
        $invoices = $db->fetchAll(
            "SELECT id, number, invoice_type, issue_date, due_date, total, paid_amount, credited_amount, balance_due, status
               FROM invoices WHERE customer_id = ? AND status IN ('validated','unpaid','partial','overdue') AND balance_due > 0.004
              ORDER BY COALESCE(due_date, issue_date), id",
            [$customerId]
        );
        $sales = $db->fetchAll(
            "SELECT s.id, s.number, s.sale_date, s.total, s.paid_amount, s.refunded_amount,
                    ROUND(s.total - s.refunded_amount - s.paid_amount
                          + COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.sale_id = s.id AND p.direction = 'refund' AND p.status = 'valid'), 0), 2) AS due
               FROM sales s
              WHERE s.customer_id = ? AND s.invoice_id IS NULL AND s.status IN ('completed','partially_refunded')
             HAVING due > 0.004 ORDER BY s.sale_date",
            [$customerId]
        );
        $advances = $db->fetchAll(
            "SELECT p.id, p.number, p.payment_date, p.unallocated_amount, pm.name AS method FROM payments p JOIN payment_methods pm ON pm.id = p.payment_method_id
              WHERE p.customer_id = ? AND p.status = 'valid' AND p.direction = 'in' AND p.unallocated_amount > 0.004 ORDER BY p.payment_date",
            [$customerId]
        );
        return ['invoices' => $invoices, 'sales' => $sales, 'advances' => $advances];
    }

    /**
     * Enregistre un règlement.
     * @param array<int,float> $invoiceAlloc invoice_id => montant
     * @param array<int,float> $saleAlloc    sale_id => montant
     * @return array{group: string, ids: int[], numbers: string[], advance: float}
     */
    public static function register(int $customerId, float $amount, int $methodId, string $date, ?string $reference, ?string $notes, array $invoiceAlloc, array $saleAlloc): array
    {
        if (!Auth::can('payments.create')) {
            throw new \DomainException('Vous n\'êtes pas autorisé à enregistrer un paiement.');
        }
        $db = Database::instance();
        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw new \DomainException('Le montant doit être supérieur à zéro.');
        }
        $customer = $db->fetch('SELECT * FROM customers WHERE id = ? AND deleted_at IS NULL', [$customerId]);
        if (!$customer) {
            throw new \DomainException('Client introuvable.');
        }
        $method = $db->fetch('SELECT * FROM payment_methods WHERE id = ? AND is_active = 1 AND is_credit = 0', [$methodId]);
        if (!$method) {
            throw new \DomainException('Mode de paiement invalide.');
        }
        $reference = $reference !== null ? mb_substr(trim($reference), 0, 100) : null;
        if ((int) $method['requires_reference'] === 1 && !$reference) {
            throw new \DomainException('Référence obligatoire pour un paiement par « ' . $method['name'] . ' ».');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date > date('Y-m-d')) {
            throw new \DomainException('Date de paiement invalide (pas de date future).');
        }
        $session = CashService::openSession();
        if ((int) $method['affects_cash'] === 1 && !$session) {
            throw new \DomainException('La caisse est fermée : ouvrez-la pour encaisser des espèces.');
        }

        return $db->transaction(function (Database $db) use ($customer, $customerId, $amount, $method, $date, $reference, $notes, $invoiceAlloc, $saleAlloc, $session): array {
            $open = self::openItems($customerId);
            $invDue = array_column($open['invoices'], 'balance_due', 'id');
            $saleDue = array_column($open['sales'], 'due', 'id');
            $allocated = 0.0;
            $inv = [];
            foreach ($invoiceAlloc as $id => $v) {
                $v = round((float) $v, 2);
                if ($v <= 0) {
                    continue;
                }
                if (!isset($invDue[$id]) || $v > (float) $invDue[$id] + 0.004) {
                    throw new \DomainException('Montant imputé supérieur au reste dû d\'une facture.');
                }
                $inv[(int) $id] = $v;
                $allocated += $v;
            }
            $sal = [];
            foreach ($saleAlloc as $id => $v) {
                $v = round((float) $v, 2);
                if ($v <= 0) {
                    continue;
                }
                if (!isset($saleDue[$id]) || $v > (float) $saleDue[$id] + 0.004) {
                    throw new \DomainException('Montant imputé supérieur au reste dû d\'un ticket.');
                }
                $sal[(int) $id] = $v;
                $allocated += $v;
            }
            $allocated = round($allocated, 2);
            if ($allocated > $amount + 0.004) {
                throw new \DomainException('La somme des imputations (' . money($allocated) . ') dépasse le montant reçu (' . money($amount) . ').');
            }
            $advance = round($amount - $allocated, 2);
            if ($advance > 0.004 && (int) $customer['is_walk_in'] === 1) {
                throw new \DomainException('Le client comptoir ne peut pas avoir d\'avance : imputez la totalité du montant.');
            }

            $group = bin2hex(random_bytes(8));
            $ids = [];
            $numbers = [];
            $insert = function (float $value, ?int $saleId, float $unallocated) use ($db, $group, $customerId, $method, $session, $date, $reference, $notes, &$ids, &$numbers): int {
                $number = DocumentNumber::next('receipt');
                $pid = $db->insert('payments', [
                    'number' => $number, 'payment_group' => $group, 'direction' => 'in', 'customer_id' => $customerId,
                    'payment_method_id' => (int) $method['id'], 'cash_session_id' => $session['id'] ?? null, 'sale_id' => $saleId,
                    'amount' => round($value, 2), 'unallocated_amount' => round($unallocated, 2),
                    'payment_date' => $date === date('Y-m-d') ? date('Y-m-d H:i:s') : $date . ' 12:00:00',
                    'reference' => $reference ?: null, 'notes' => $notes ? mb_substr($notes, 0, 500) : null, 'user_id' => Auth::id(),
                ]);
                if ($session) {
                    CashService::movement((int) $session['id'], 'payment', $value, (int) $method['id'], 'payment', $pid, 'Règlement ' . $number);
                }
                $ids[] = $pid;
                $numbers[] = $number;
                return $pid;
            };
            foreach ($sal as $saleId => $v) {
                $insert($v, $saleId, 0.0);
                $db->query('UPDATE sales SET paid_amount = paid_amount + ? WHERE id = ?', [$v, $saleId]);
            }
            $invTotal = round(array_sum($inv), 2);
            if ($invTotal > 0 || $advance > 0.004) {
                $pid = $insert($invTotal + max(0, $advance), null, max(0, $advance));
                foreach ($inv as $invoiceId => $v) {
                    $db->insert('payment_allocations', ['payment_id' => $pid, 'invoice_id' => $invoiceId, 'amount' => $v]);
                    InvoiceService::recalc($invoiceId);
                }
            }
            AuditLog::log('payment', 'payments', 'payment', $ids[0] ?? null, 'Règlement ' . implode(', ', $numbers) . ' : ' . money($amount) . ' (' . $method['name'] . ')'
                . ($advance > 0.004 ? ', avance ' . money($advance) : ''));
            NotificationService::create('customer_payment', 'Paiement reçu : ' . money($amount), CustomerService::displayName($customer) . ' — ' . $method['name'] . '.',
                'payments/' . ($ids[0] ?? 0), 'success');
            return ['group' => $group, 'ids' => $ids, 'numbers' => $numbers, 'advance' => max(0, $advance)];
        });
    }

    /** Impute une avance existante sur une facture. */
    public static function allocateAdvance(int $paymentId, int $invoiceId, float $amount): void
    {
        $db = Database::instance();
        $db->transaction(function (Database $db) use ($paymentId, $invoiceId, $amount): void {
            $p = $db->fetch("SELECT * FROM payments WHERE id = ? AND status = 'valid' AND direction = 'in' FOR UPDATE", [$paymentId]);
            $inv = $db->fetch("SELECT * FROM invoices WHERE id = ? AND status IN ('validated','unpaid','partial','overdue') FOR UPDATE", [$invoiceId]);
            $amount = round($amount, 2);
            if (!$p || !$inv || (int) $p['customer_id'] !== (int) $inv['customer_id']) {
                throw new \DomainException('Avance ou facture invalide.');
            }
            if ($amount <= 0 || $amount > (float) $p['unallocated_amount'] + 0.004 || $amount > (float) $inv['balance_due'] + 0.004) {
                throw new \DomainException('Montant supérieur à l\'avance disponible ou au reste dû.');
            }
            $db->query('INSERT INTO payment_allocations (payment_id, invoice_id, amount) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE amount = amount + VALUES(amount)', [$paymentId, $invoiceId, $amount]);
            $db->query('UPDATE payments SET unallocated_amount = unallocated_amount - ? WHERE id = ?', [$amount, $paymentId]);
            InvoiceService::recalc($invoiceId);
            AuditLog::log('allocate', 'payments', 'payment', $paymentId, 'Avance ' . $p['number'] . ' imputée sur ' . $inv['number'] . ' : ' . money($amount));
        });
    }

    /** Annulation d'un paiement (et de ses imputations). */
    public static function cancel(int $paymentId, string $reason): void
    {
        if (!Auth::can('payments.cancel')) {
            throw new \DomainException('Vous n\'êtes pas autorisé à annuler un paiement.');
        }
        if (mb_strlen(trim($reason)) < 3) {
            throw new \DomainException('Indiquez le motif de l\'annulation.');
        }
        $db = Database::instance();
        $db->transaction(function (Database $db) use ($paymentId, $reason): void {
            $p = $db->fetch('SELECT p.*, pm.affects_cash, pm.name AS method FROM payments p JOIN payment_methods pm ON pm.id = p.payment_method_id WHERE p.id = ? FOR UPDATE', [$paymentId]);
            if (!$p || $p['status'] !== 'valid') {
                throw new \DomainException('Paiement introuvable ou déjà annulé.');
            }
            if ($p['direction'] !== 'in') {
                throw new \DomainException('Un remboursement ne s\'annule pas : enregistrez un nouveau paiement.');
            }
            $session = CashService::openSession();
            if ((int) $p['affects_cash'] === 1 && !$session) {
                throw new \DomainException('La caisse est fermée : ouvrez-la pour contre-passer un paiement en espèces.');
            }
            $invoices = array_column($db->fetchAll('SELECT invoice_id FROM payment_allocations WHERE payment_id = ?', [$paymentId]), 'invoice_id');
            $db->query('DELETE FROM payment_allocations WHERE payment_id = ?', [$paymentId]);
            if ($p['sale_id']) {
                $db->query('UPDATE sales SET paid_amount = GREATEST(0, paid_amount - ?) WHERE id = ?', [$p['amount'], $p['sale_id']]);
            }
            $db->update('payments', ['status' => 'cancelled', 'unallocated_amount' => 0, 'cancelled_by' => Auth::id(),
                'cancelled_at' => date('Y-m-d H:i:s'), 'cancel_reason' => mb_substr($reason, 0, 255)], 'id = :id', ['id' => $paymentId]);
            if ($session) {
                CashService::movement((int) $session['id'], 'refund', -(float) $p['amount'], (int) $p['payment_method_id'], 'payment', $paymentId, 'Annulation règlement ' . $p['number']);
            }
            foreach ($invoices as $invoiceId) {
                InvoiceService::recalc((int) $invoiceId);
            }
            AuditLog::log('cancel', 'payments', 'payment', $paymentId, 'Paiement ' . $p['number'] . ' annulé (' . money($p['amount']) . ') : ' . $reason);
        });
    }

    /** Remboursement d'un avoir au client. */
    public static function refundCreditNote(int $creditNoteId, int $methodId): int
    {
        $db = Database::instance();
        $cn = $db->fetch('SELECT * FROM credit_notes WHERE id = ? FOR UPDATE', [$creditNoteId]);
        $method = $db->fetch('SELECT * FROM payment_methods WHERE id = ? AND is_active = 1 AND is_credit = 0', [$methodId]);
        if (!$cn || !$method) {
            throw new \DomainException('Avoir ou mode de remboursement invalide.');
        }
        $amount = round((float) $cn['total'] - (float) $cn['applied_amount'] - (float) $cn['refunded_amount'], 2);
        if ($amount <= 0) {
            throw new \DomainException('Cet avoir est déjà entièrement utilisé.');
        }
        $session = CashService::openSession();
        if ((int) $method['affects_cash'] === 1 && !$session) {
            throw new \DomainException('La caisse est fermée : ouvrez-la pour rembourser en espèces.');
        }
        $number = DocumentNumber::next('receipt');
        $pid = $db->insert('payments', [
            'number' => $number, 'direction' => 'refund', 'customer_id' => $cn['customer_id'], 'payment_method_id' => $methodId,
            'cash_session_id' => $session['id'] ?? null, 'credit_note_id' => $creditNoteId, 'amount' => $amount,
            'payment_date' => date('Y-m-d H:i:s'), 'notes' => 'Remboursement avoir ' . $cn['number'], 'user_id' => Auth::id(),
        ]);
        if ($session) {
            CashService::movement((int) $session['id'], 'refund', -$amount, $methodId, 'credit_note', $creditNoteId, 'Remboursement avoir ' . $cn['number']);
        }
        $db->update('credit_notes', ['refunded_amount' => round((float) $cn['refunded_amount'] + $amount, 2), 'status' => 'refunded'], 'id = :id', ['id' => $creditNoteId]);
        AuditLog::log('refund', 'payments', 'payment', $pid, 'Avoir ' . $cn['number'] . ' remboursé : ' . money($amount) . ' (' . $method['name'] . ')');
        return $pid;
    }
}
