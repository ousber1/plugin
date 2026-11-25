<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\AuditLog;
use App\Core\Controller;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Session;
use App\Services\CashService;

/**
 * Caisse : ouverture, dépôts / retraits / transferts, clôture avec comptage,
 * historique des sessions et rapport Z.
 */
final class CashController extends Controller
{
    public const MOVEMENT_TYPES = [
        'sale' => 'Vente', 'payment' => 'Règlement client', 'refund' => 'Remboursement / annulation', 'expense' => 'Dépense',
        'supplier_payment' => 'Paiement fournisseur', 'deposit' => 'Dépôt (apport)', 'withdrawal' => 'Retrait',
        'transfer_in' => 'Transfert entrant', 'transfer_out' => 'Transfert (banque / coffre)', 'adjustment' => 'Ajustement',
    ];

    public function index(): void
    {
        $session = CashService::openSession();
        $data = ['title' => 'Caisse', 'session' => $session, 'denominations' => CashService::DENOMINATIONS];
        if ($session) {
            $data['summary'] = CashService::summary((int) $session['id']);
            $data['movements'] = $this->movements((int) $session['id'], 100);
        }
        $data['history'] = $this->db->fetchAll(
            "SELECT cs.*, uo.full_name AS opened_name, uc.full_name AS closed_name FROM cash_sessions cs
               LEFT JOIN users uo ON uo.id = cs.opened_by LEFT JOIN users uc ON uc.id = cs.closed_by
              WHERE cs.status = 'closed' ORDER BY cs.closed_at DESC LIMIT 10"
        );
        $this->view('cash/index', $data);
    }

    public function open(): void
    {
        $back = Request::post('back') === 'pos' ? 'pos' : 'cash';
        try {
            $id = CashService::open(Request::decimal('opening_balance'), Request::string('notes') ?: null);
        } catch (\DomainException $e) {
            $this->fail($e->getMessage(), $back);
        }
        AuditLog::log('cash_open', 'cash', 'cash_session', $id, 'Ouverture de caisse, fond : ' . money(Request::decimal('opening_balance')));
        Session::flash('success', 'Caisse ouverte avec un fond de ' . money(Request::decimal('opening_balance')) . '.');
        $this->redirect($back);
    }

    /** Dépôt, retrait ou transfert manuel. */
    public function movement(): void
    {
        $session = CashService::openSession();
        if (!$session) {
            $this->fail('La caisse est fermée.', 'cash');
        }
        $type = Request::string('type');
        $amount = round(Request::decimal('amount'), 2);
        $description = Request::string('description');
        if (!in_array($type, ['deposit', 'withdrawal', 'transfer_out'], true) || $amount <= 0) {
            $this->fail('Type ou montant invalide.', 'cash');
        }
        if (mb_strlen($description) < 3) {
            $this->fail('Indiquez le motif du mouvement.', 'cash');
        }
        $signed = $type === 'deposit' ? $amount : -$amount;
        if ($signed < 0 && CashService::expectedCash((int) $session['id']) + $signed < -0.004) {
            $this->fail('Montant supérieur aux espèces théoriquement présentes (' . money(CashService::expectedCash((int) $session['id'])) . ').', 'cash');
        }
        $id = CashService::movement((int) $session['id'], $type, $signed, null, 'manual', null, $description);
        AuditLog::log('cash_' . $type, 'cash', 'cash_movement', $id, self::MOVEMENT_TYPES[$type] . ' : ' . money($amount) . ' — ' . $description);
        Session::flash('success', self::MOVEMENT_TYPES[$type] . ' de ' . money($amount) . ' enregistré.');
        $this->redirect('cash');
    }

    public function close(): void
    {
        $session = CashService::openSession();
        if (!$session) {
            $this->fail('La caisse est déjà fermée.', 'cash');
        }
        $denoms = [];
        foreach ((array) ($_POST['denom'] ?? []) as $k => $v) {
            $denoms[(string) $k] = (int) $v;
        }
        $countedRaw = trim((string) Request::post('counted', ''));
        $counted = $countedRaw === '' ? null : Request::decimal('counted');
        try {
            $r = CashService::close((int) $session['id'], $counted, $denoms, Request::string('notes') ?: null);
        } catch (\DomainException $e) {
            $this->fail($e->getMessage(), 'cash');
        }
        AuditLog::log('cash_close', 'cash', 'cash_session', (int) $session['id'], sprintf('Clôture %s : attendu %s, compté %s, écart %s',
            $session['number'], money($r['expected']), money($r['counted']), money($r['difference'])));
        $msg = 'Caisse clôturée. Écart : ' . money($r['difference']) . '.';
        if ($r['suspended']) {
            $msg .= ' ' . $r['suspended'] . ' vente(s) en attente restent disponibles au POS.';
        }
        Session::flash(abs($r['difference']) < 0.01 ? 'success' : 'warning', $msg);
        $this->redirect('cash/sessions/' . $session['id']);
    }

    public function sessions(): void
    {
        $p = Paginator::fromRequest((int) $this->db->fetchColumn('SELECT COUNT(*) FROM cash_sessions'));
        $this->view('cash/sessions', [
            'title' => 'Historique de caisse', 'breadcrumbs' => [['Caisse', 'cash'], ['Historique', null]], 'p' => $p,
            'sessions' => $this->db->fetchAll(
                "SELECT cs.*, uo.full_name AS opened_name, uc.full_name AS closed_name, cr.name AS register_name,
                        (SELECT COALESCE(SUM(total), 0) FROM sales s WHERE s.cash_session_id = cs.id AND s.status IN ('completed','partially_refunded','refunded')) AS sales_total
                   FROM cash_sessions cs JOIN cash_registers cr ON cr.id = cs.cash_register_id
                   LEFT JOIN users uo ON uo.id = cs.opened_by LEFT JOIN users uc ON uc.id = cs.closed_by
                  ORDER BY cs.id DESC LIMIT {$p->limit()} OFFSET {$p->offset()}"
            ),
        ]);
    }

    /** Rapport de session (rapport Z à la clôture), imprimable. */
    public function show(int $id): void
    {
        $session = $this->db->fetch(
            'SELECT cs.*, uo.full_name AS opened_name, uc.full_name AS closed_name, cr.name AS register_name
               FROM cash_sessions cs JOIN cash_registers cr ON cr.id = cs.cash_register_id
               LEFT JOIN users uo ON uo.id = cs.opened_by LEFT JOIN users uc ON uc.id = cs.closed_by WHERE cs.id = ?',
            [$id]
        );
        if (!$session) {
            $this->fail('Session de caisse introuvable.', 'cash/sessions');
        }
        $this->view('cash/report', [
            'title' => 'Rapport de caisse ' . $session['number'],
            'session' => $session,
            'summary' => CashService::summary($id),
            'movements' => $this->movements($id, 2000),
            'byUser' => $this->db->fetchAll(
                "SELECT u.full_name, COUNT(*) AS n, SUM(s.total) AS total FROM sales s LEFT JOIN users u ON u.id = s.user_id
                  WHERE s.cash_session_id = ? AND s.status IN ('completed','partially_refunded','refunded') GROUP BY s.user_id, u.full_name",
                [$id]
            ),
            'types' => self::MOVEMENT_TYPES,
        ], 'print');
    }

    private function movements(int $sessionId, int $limit): array
    {
        return $this->db->fetchAll(
            'SELECT cm.*, pm.name AS method, pm.affects_cash, u.full_name FROM cash_movements cm
               LEFT JOIN payment_methods pm ON pm.id = cm.payment_method_id LEFT JOIN users u ON u.id = cm.user_id
              WHERE cm.cash_session_id = ? ORDER BY cm.id DESC LIMIT ' . $limit,
            [$sessionId]
        );
    }
}
