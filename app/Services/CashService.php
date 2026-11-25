<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\DocumentNumber;
use App\Core\Settings;

/**
 * Caisse : sessions (ouverture / clôture), mouvements, solde théorique et écart.
 *
 * cash_movements enregistre TOUS les encaissements de la session (tous modes) avec
 * leur mode de paiement ; seuls les modes « espèces » (affects_cash) et les
 * mouvements sans mode (dépôts, retraits) entrent dans le solde théorique du tiroir.
 */
final class CashService
{
    /** Billets et pièces en circulation au Maroc (comptage à la clôture). */
    public const DENOMINATIONS = ['200', '100', '50', '20', '10', '5', '2', '1', '0.5', '0.2', '0.1'];

    public static function registerId(): int
    {
        return (int) Settings::get('default_cash_register_id', 1);
    }

    /** Session ouverte de la caisse (ou null). */
    public static function openSession(?int $registerId = null): ?array
    {
        return Database::instance()->fetch(
            "SELECT cs.*, u.full_name AS opened_by_name, cr.name AS register_name
               FROM cash_sessions cs JOIN cash_registers cr ON cr.id = cs.cash_register_id LEFT JOIN users u ON u.id = cs.opened_by
              WHERE cs.cash_register_id = ? AND cs.status = 'open' LIMIT 1",
            [$registerId ?? self::registerId()]
        );
    }

    public static function open(float $openingBalance, ?string $notes = null): int
    {
        if ($openingBalance < 0) {
            throw new \DomainException('Le fond de caisse ne peut pas être négatif.');
        }
        $db = Database::instance();
        return $db->transaction(function (Database $db) use ($openingBalance, $notes): int {
            $registerId = self::registerId();
            if (!$db->fetchColumn('SELECT id FROM cash_registers WHERE id = ? AND is_active = 1', [$registerId])) {
                throw new \DomainException('Caisse introuvable ou inactive (Paramètres › Point de vente).');
            }
            if ($db->fetchColumn("SELECT id FROM cash_sessions WHERE cash_register_id = ? AND status = 'open' FOR UPDATE", [$registerId])) {
                throw new \DomainException('La caisse est déjà ouverte.');
            }
            try {
                return $db->insert('cash_sessions', [
                    'cash_register_id' => $registerId,
                    'number'           => DocumentNumber::next('cash_session'),
                    'status'           => 'open',
                    'opened_by'        => Auth::id(),
                    'opened_at'        => date('Y-m-d H:i:s'),
                    'opening_balance'  => round($openingBalance, 2),
                    'opening_notes'    => $notes !== null ? mb_substr($notes, 0, 500) : null,
                    'open_flag'        => 1,
                ]);
            } catch (\PDOException $e) {
                // Index unique (caisse, open_flag) : ouverture simultanée par deux postes
                throw new \DomainException('La caisse vient d\'être ouverte par un autre poste.');
            }
        });
    }

    /**
     * Enregistre un mouvement de caisse (montant signé : + entrée, − sortie).
     */
    public static function movement(int $sessionId, string $type, float $amount, ?int $methodId = null, ?string $referenceType = null, ?int $referenceId = null, ?string $description = null): int
    {
        return Database::instance()->insert('cash_movements', [
            'cash_session_id'   => $sessionId,
            'type'              => $type,
            'payment_method_id' => $methodId,
            'amount'            => round($amount, 2),
            'reference_type'    => $referenceType,
            'reference_id'      => $referenceId,
            'description'       => $description !== null ? mb_substr($description, 0, 255) : null,
            'user_id'           => Auth::id(),
        ]);
    }

    /** Solde théorique d'espèces dans le tiroir. */
    public static function expectedCash(int $sessionId): float
    {
        $db = Database::instance();
        $opening = (float) $db->fetchColumn('SELECT opening_balance FROM cash_sessions WHERE id = ?', [$sessionId]);
        $moves = (float) $db->fetchColumn(
            'SELECT COALESCE(SUM(cm.amount), 0) FROM cash_movements cm LEFT JOIN payment_methods pm ON pm.id = cm.payment_method_id
              WHERE cm.cash_session_id = ? AND (cm.payment_method_id IS NULL OR pm.affects_cash = 1)',
            [$sessionId]
        );
        return round($opening + $moves, 2);
    }

    /** Synthèse d'une session : par mode de paiement et par type de mouvement. */
    public static function summary(int $sessionId): array
    {
        $db = Database::instance();
        $byMethod = $db->fetchAll(
            "SELECT COALESCE(pm.name, 'Espèces (mouvements)') AS method, COALESCE(pm.affects_cash, 1) AS affects_cash,
                    SUM(CASE WHEN cm.amount > 0 THEN cm.amount ELSE 0 END) AS inflow,
                    SUM(CASE WHEN cm.amount < 0 THEN -cm.amount ELSE 0 END) AS outflow,
                    SUM(cm.amount) AS net, COUNT(*) AS operations
               FROM cash_movements cm LEFT JOIN payment_methods pm ON pm.id = cm.payment_method_id
              WHERE cm.cash_session_id = ? GROUP BY cm.payment_method_id, pm.name, pm.affects_cash ORDER BY MIN(pm.sort_order)",
            [$sessionId]
        );
        $byType = [];
        foreach ($db->fetchAll('SELECT type, SUM(amount) AS total, COUNT(*) AS n FROM cash_movements WHERE cash_session_id = ? GROUP BY type', [$sessionId]) as $r) {
            $byType[$r['type']] = ['total' => (float) $r['total'], 'count' => (int) $r['n']];
        }
        $sales = $db->fetch(
            "SELECT COUNT(*) AS n, COALESCE(SUM(total), 0) AS total, COALESCE(SUM(GREATEST(total - paid_amount, 0)), 0) AS credit
               FROM sales WHERE cash_session_id = ? AND status IN ('completed','partially_refunded','refunded')",
            [$sessionId]
        );
        $cancelled = (int) $db->fetchColumn("SELECT COUNT(*) FROM sales WHERE cash_session_id = ? AND status = 'cancelled'", [$sessionId]);
        return [
            'by_method' => $byMethod,
            'by_type'   => $byType,
            'sales'     => ['count' => (int) $sales['n'], 'total' => (float) $sales['total'], 'credit' => max(0.0, (float) $sales['credit']), 'cancelled' => $cancelled],
            'expected'  => self::expectedCash($sessionId),
        ];
    }

    /**
     * Clôture : comptage réel (total ou détail des billets/pièces) et écart.
     * @param array<string,int> $denominations
     */
    public static function close(int $sessionId, ?float $counted, array $denominations, ?string $notes): array
    {
        $db = Database::instance();
        return $db->transaction(function (Database $db) use ($sessionId, $counted, $denominations, $notes): array {
            $session = $db->fetch('SELECT * FROM cash_sessions WHERE id = ? FOR UPDATE', [$sessionId]);
            if (!$session || $session['status'] !== 'open') {
                throw new \DomainException('Cette session de caisse n\'est pas ouverte.');
            }
            $suspended = (int) $db->fetchColumn("SELECT COUNT(*) FROM sales WHERE cash_session_id = ? AND status = 'suspended'", [$sessionId]);
            $clean = [];
            $fromNotes = 0.0;
            foreach (self::DENOMINATIONS as $d) {
                $n = max(0, (int) ($denominations[$d] ?? 0));
                if ($n > 0) {
                    $clean[$d] = $n;
                    $fromNotes += $n * (float) $d;
                }
            }
            $counted ??= round($fromNotes, 2);
            if ($counted < 0) {
                throw new \DomainException('Le montant compté ne peut pas être négatif.');
            }
            $expected = self::expectedCash($sessionId);
            $difference = round($counted - $expected, 2);
            $db->update('cash_sessions', [
                'status'           => 'closed',
                'closed_by'        => Auth::id(),
                'closed_at'        => date('Y-m-d H:i:s'),
                'expected_balance' => $expected,
                'counted_balance'  => round($counted, 2),
                'difference'       => $difference,
                'denominations'    => $clean ? json_encode($clean) : null,
                'closing_notes'    => $notes !== null ? mb_substr($notes, 0, 500) : null,
                'open_flag'        => null,
            ], 'id = :id', ['id' => $sessionId]);
            if (abs($difference) >= 0.01) {
                NotificationService::create('cash_closing', 'Écart de caisse : ' . money($difference),
                    'Session ' . $session['number'] . ' — attendu ' . money($expected) . ', compté ' . money($counted) . '.',
                    'cash/sessions/' . $sessionId, 'warning');
            } else {
                NotificationService::create('cash_closing', 'Caisse clôturée sans écart', 'Session ' . $session['number'] . '.', 'cash/sessions/' . $sessionId, 'success');
            }
            return ['expected' => $expected, 'counted' => $counted, 'difference' => $difference, 'suspended' => $suspended];
        });
    }
}
