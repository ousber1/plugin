<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csv;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Session;
use App\Core\Settings;
use App\Services\CustomerService;
use App\Services\SaleCalculator;
use App\Services\SaleService;

/**
 * Historique des ventes, détail, tickets (58/80 mm), bon de vente A4,
 * annulation et retours.
 */
final class SaleController extends Controller
{
    private function filters(): array
    {
        $date = static fn(string $k): string => preg_match('/^\d{4}-\d{2}-\d{2}$/', Request::string($k)) ? Request::string($k) : '';
        return [
            'q'       => Request::string('q'),
            'status'  => array_key_exists(Request::string('status'), status_map('sale')) ? Request::string('status') : '',
            'from'    => $date('from'),
            'to'      => $date('to'),
            'user'    => Request::int('user'),
            'method'  => Request::int('method'),
            'session' => Request::int('session'),
            'credit'  => Request::string('credit') === '1',
        ];
    }

    private function where(array $f): array
    {
        $where = ["s.status <> 'draft'", "s.status <> 'suspended'"];
        $params = [];
        if ($f['q'] !== '') {
            $where[] = '(s.number LIKE :a OR c.full_name LIKE :b OR c.company_name LIKE :c OR c.phone LIKE :d)';
            $params += ['a' => "%{$f['q']}%", 'b' => "%{$f['q']}%", 'c' => "%{$f['q']}%", 'd' => "%{$f['q']}%"];
        }
        foreach ([['status', 's.status = :status'], ['user', 's.user_id = :user'], ['session', 's.cash_session_id = :session']] as [$k, $sql]) {
            if ($f[$k]) {
                $where[] = $sql;
                $params[$k] = $f[$k];
            }
        }
        if ($f['from'] !== '') {
            $where[] = 's.sale_date >= :from';
            $params['from'] = $f['from'] . ' 00:00:00';
        }
        if ($f['to'] !== '') {
            $where[] = 's.sale_date <= :to';
            $params['to'] = $f['to'] . ' 23:59:59';
        }
        if ($f['method'] > 0) {
            $where[] = "EXISTS (SELECT 1 FROM payments p WHERE p.sale_id = s.id AND p.payment_method_id = :method AND p.status = 'valid')";
            $params['method'] = $f['method'];
        }
        if ($f['credit']) {
            $where[] = "s.total - s.paid_amount > 0.004 AND s.status IN ('completed','partially_refunded')";
        }
        return [implode(' AND ', $where), $params];
    }

    public function index(): void
    {
        $f = $this->filters();
        [$where, $params] = $this->where($f);
        $from = 'FROM sales s JOIN customers c ON c.id = s.customer_id LEFT JOIN users u ON u.id = s.user_id';
        $p = Paginator::fromRequest((int) $this->db->fetchColumn("SELECT COUNT(*) {$from} WHERE {$where}", $params));
        $totals = $this->db->fetch(
            "SELECT COUNT(*) AS n, COALESCE(SUM(CASE WHEN s.status <> 'cancelled' THEN s.total - s.refunded_amount END), 0) AS total,
                    COALESCE(SUM(CASE WHEN s.status <> 'cancelled' THEN GREATEST(s.total - s.paid_amount, 0) END), 0) AS credit
               {$from} WHERE {$where}",
            $params
        );
        $this->view('sales/index', [
            'title' => 'Ventes', 'f' => $f, 'p' => $p, 'totals' => $totals,
            'sales' => $this->db->fetchAll(
                "SELECT s.*, COALESCE(NULLIF(c.company_name, ''), c.full_name) AS customer_name, c.is_walk_in, u.full_name AS user_name,
                        (SELECT GROUP_CONCAT(DISTINCT pm.name SEPARATOR ', ') FROM payments py JOIN payment_methods pm ON pm.id = py.payment_method_id
                          WHERE py.sale_id = s.id AND py.direction = 'in') AS methods
                   {$from} WHERE {$where} ORDER BY s.sale_date DESC, s.id DESC LIMIT {$p->limit()} OFFSET {$p->offset()}",
                $params
            ),
            'users' => $this->db->fetchAll('SELECT id, full_name FROM users ORDER BY full_name'),
            'methods' => $this->db->fetchAll('SELECT id, name FROM payment_methods WHERE is_credit = 0 ORDER BY sort_order'),
        ]);
    }

    public function export(): void
    {
        [$where, $params] = $this->where($this->filters());
        $rows = $this->db->fetchAll(
            "SELECT s.number, s.sale_date, s.status, s.subtotal, s.discount_total, s.tax_total, s.total, s.paid_amount, s.refunded_amount,
                    COALESCE(NULLIF(c.company_name, ''), c.full_name) AS customer_name, u.full_name AS user_name
               FROM sales s JOIN customers c ON c.id = s.customer_id LEFT JOIN users u ON u.id = s.user_id
              WHERE {$where} ORDER BY s.sale_date DESC LIMIT 20000",
            $params
        );
        foreach ($rows as &$r) {
            $r['status'] = status_map('sale')[$r['status']][0] ?? $r['status'];
            foreach (['subtotal', 'discount_total', 'tax_total', 'total', 'paid_amount', 'refunded_amount'] as $k) {
                $r[$k] = (float) $r[$k];
            }
            $r['due'] = max(0, round($r['total'] - $r['paid_amount'], 2));
        }
        unset($r);
        Csv::download('ventes-' . date('Y-m-d') . '.csv', [
            'number' => 'Ticket', 'sale_date' => 'Date', 'customer_name' => 'Client', 'status' => 'Statut', 'subtotal' => 'Sous-total HT',
            'discount_total' => 'Remise HT', 'tax_total' => 'TVA', 'total' => 'Total TTC', 'paid_amount' => 'Payé', 'due' => 'Reste dû',
            'refunded_amount' => 'Remboursé', 'user_name' => 'Vendeur',
        ], $rows);
    }

    private function load(int $id): array
    {
        $sale = $this->db->fetch(
            "SELECT s.*, c.code AS customer_code, c.full_name, c.company_name, c.phone, c.address, c.city, c.ice, c.is_walk_in,
                    u.full_name AS user_name, cu.full_name AS cancelled_name, cs.number AS session_number
               FROM sales s JOIN customers c ON c.id = s.customer_id LEFT JOIN users u ON u.id = s.user_id
               LEFT JOIN users cu ON cu.id = s.cancelled_by LEFT JOIN cash_sessions cs ON cs.id = s.cash_session_id
              WHERE s.id = ? AND s.status NOT IN ('draft','suspended')",
            [$id]
        );
        if (!$sale) {
            $this->fail('Vente introuvable.', 'sales');
        }
        $items = $this->db->fetchAll(
            'SELECT si.*, u.symbol, ou.full_name AS override_name FROM sale_items si LEFT JOIN units u ON u.id = si.unit_id
               LEFT JOIN users ou ON ou.id = si.price_overridden_by WHERE si.sale_id = ? ORDER BY si.sort_order, si.id',
            [$id]
        );
        $payments = $this->db->fetchAll(
            'SELECT p.*, pm.name AS method, pm.affects_cash FROM payments p JOIN payment_methods pm ON pm.id = p.payment_method_id
              WHERE p.sale_id = ? ORDER BY p.id',
            [$id]
        );
        // Ventilation de TVA reconstituée à partir des lignes enregistrées
        $vat = [];
        foreach ($items as $it) {
            $k = number_format((float) $it['tax_rate'], 3);
            $vat[$k] ??= ['rate' => (float) $it['tax_rate'], 'base' => 0.0, 'tax' => 0.0];
            $vat[$k]['base'] += (float) $it['line_subtotal'];
            $vat[$k]['tax'] += (float) $it['line_tax'];
        }
        krsort($vat);
        return [$sale, $items, $payments, array_values($vat)];
    }

    public function show(int $id): void
    {
        [$sale, $items, $payments, $vat] = $this->load($id);
        $session = \App\Services\CashService::openSession();
        $this->view('sales/show', [
            'title' => 'Vente ' . $sale['number'], 'breadcrumbs' => [['Ventes', 'sales'], [$sale['number'], null]],
            'sale' => $sale, 'items' => $items, 'payments' => $payments, 'vat' => $vat,
            'returns' => $this->db->fetchAll(
                'SELECT r.*, u.full_name, pm.name AS method FROM sale_returns r LEFT JOIN users u ON u.id = r.user_id
                   LEFT JOIN payment_methods pm ON pm.id = r.refund_method_id WHERE r.sale_id = ? ORDER BY r.id',
                [$id]
            ),
            'movements' => $this->db->fetchAll(
                "SELECT sm.*, COALESCE(p.name, m.name) AS item_name, u.full_name FROM stock_movements sm
                   LEFT JOIN products p ON p.id = sm.product_id LEFT JOIN materials m ON m.id = sm.material_id LEFT JOIN users u ON u.id = sm.user_id
                  WHERE (sm.reference_type IN ('sale','sale_cancel') AND sm.reference_id = ?)
                     OR (sm.reference_type = 'sale_return' AND sm.reference_id IN (SELECT id FROM sale_returns WHERE sale_id = ?))
                  ORDER BY sm.id",
                [$id, $id]
            ),
            'canCancel' => can('pos.cancel') && $sale['status'] === 'completed' && !$sale['invoice_id'] && $session && (int) $session['id'] === (int) $sale['cash_session_id'],
            'canReturn' => can('pos.refund') && in_array($sale['status'], ['completed', 'partially_refunded'], true) && !$sale['invoice_id'],
        ]);
    }

    /** Ticket de caisse thermique 58 / 80 mm. */
    public function receipt(int $id): void
    {
        [$sale, $items, $payments, $vat] = $this->load($id);
        $width = (int) Request::get('w', Settings::get('receipt_width', 80));
        $this->view('sales/receipt', [
            'title' => 'Ticket ' . $sale['number'], 'sale' => $sale, 'items' => $items, 'payments' => $payments, 'vat' => $vat,
            'width' => in_array($width, [58, 80], true) ? $width : 80, 'autoPrint' => Request::get('print') === '1',
        ], null);
    }

    /** Bon de vente A4 (la facture officielle est générée par le module Factures, Phase 5). */
    public function printA4(int $id): void
    {
        [$sale, $items, $payments, $vat] = $this->load($id);
        $this->view('sales/a4', ['title' => 'Bon de vente ' . $sale['number'], 'sale' => $sale, 'items' => $items, 'payments' => $payments, 'vat' => $vat], 'print');
    }

    public function cancel(int $id): void
    {
        try {
            SaleService::cancel($id, Request::string('reason'));
        } catch (\DomainException $e) {
            $this->fail($e->getMessage(), 'sales/' . $id);
        }
        Session::flash('success', 'Vente annulée : paiements contre-passés et stock réintégré.');
        $this->redirect('sales/' . $id);
    }

    public function returnForm(int $id): void
    {
        [$sale, $items, $payments] = $this->load($id);
        if (!in_array($sale['status'], ['completed', 'partially_refunded'], true) || $sale['invoice_id']) {
            $this->fail('Cette vente ne peut pas faire l\'objet d\'un retour.', 'sales/' . $id);
        }
        $paidOut = (float) $this->db->fetchColumn("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE sale_id = ? AND direction = 'refund' AND status = 'valid'", [$id]);
        $this->view('sales/return', [
            'title' => 'Retour sur ' . $sale['number'], 'breadcrumbs' => [['Ventes', 'sales'], [$sale['number'], 'sales/' . $id], ['Retour', null]],
            'sale' => $sale, 'items' => $items, 'refundable' => max(0, round((float) $sale['paid_amount'] - $paidOut, 2)),
            'methods' => $this->db->fetchAll('SELECT id, name, is_credit, affects_cash FROM payment_methods WHERE is_active = 1 ORDER BY sort_order'),
            'sessionOpen' => (bool) \App\Services\CashService::openSession(),
        ]);
    }

    public function returnStore(int $id): void
    {
        $qty = [];
        foreach ((array) ($_POST['qty'] ?? []) as $itemId => $v) {
            $n = Csv::number(is_string($v) ? $v : '');
            if ($n !== null && $n > 0) {
                $qty[(int) $itemId] = $n;
            }
        }
        $restock = [];
        foreach ((array) ($_POST['restock'] ?? []) as $itemId => $v) {
            $restock[(int) $itemId] = (bool) $v;
        }
        try {
            $r = SaleService::processReturn($id, $qty, $restock, Request::int('method_id'), Request::string('reason'));
        } catch (\DomainException $e) {
            $this->fail($e->getMessage(), 'sales/' . $id . '/return');
        }
        Session::flash('success', 'Retour ' . $r['number'] . ' enregistré : ' . money($r['total']) . '.');
        $this->redirect('sales/' . $id);
    }

    /** Montants TTC d'une ligne avant remise globale (affichage ticket). */
    public static function lineGross(array $it): float
    {
        $c = SaleCalculator::compute([[
            'quantity' => (float) $it['quantity'], 'unit_price' => (float) $it['unit_price'],
            'discount_percent' => (float) $it['discount_percent'], 'tax_rate' => (float) $it['tax_rate'],
        ]]);
        return $c['total'];
    }

    public static function customerName(array $sale): string
    {
        return CustomerService::displayName($sale);
    }
}
