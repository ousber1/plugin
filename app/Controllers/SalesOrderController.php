<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\AuditLog;
use App\Core\Controller;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Session;
use App\Services\CustomerService;
use App\Services\InvoiceService;

/** Bons de commande client (issus des devis acceptés) : suivi et facturation. */
final class SalesOrderController extends Controller
{
    private const FLOW = [
        'pending' => ['confirmed', 'cancelled'], 'confirmed' => ['in_production', 'ready', 'cancelled'],
        'in_production' => ['ready', 'cancelled'], 'ready' => ['delivered', 'cancelled'], 'delivered' => ['completed'],
        'completed' => [], 'cancelled' => [],
    ];

    public function index(): void
    {
        $q = Request::string('q');
        $status = Request::string('status');
        $where = ['so.deleted_at IS NULL'];
        $params = [];
        if ($q !== '') {
            $where[] = '(so.number LIKE :a OR c.full_name LIKE :b OR c.company_name LIKE :c)';
            $params += ['a' => "%{$q}%", 'b' => "%{$q}%", 'c' => "%{$q}%"];
        }
        if (isset(status_map('sales_order')[$status])) {
            $where[] = 'so.status = :st';
            $params['st'] = $status;
        }
        $w = implode(' AND ', $where);
        $from = 'FROM sales_orders so JOIN customers c ON c.id = so.customer_id LEFT JOIN quotations q ON q.id = so.quotation_id';
        $p = Paginator::fromRequest((int) $this->db->fetchColumn("SELECT COUNT(*) {$from} WHERE {$w}", $params));
        $this->view('sales_orders/index', [
            'title' => 'Bons de commande', 'q' => $q, 'status' => $status, 'p' => $p,
            'rows' => $this->db->fetchAll(
                "SELECT so.*, q.number AS quotation_number, COALESCE(NULLIF(c.company_name, ''), c.full_name) AS customer_name,
                        (SELECT COUNT(*) FROM invoices i WHERE i.sales_order_id = so.id AND i.status <> 'cancelled') AS invoiced
                   {$from} WHERE {$w} ORDER BY so.id DESC LIMIT {$p->limit()} OFFSET {$p->offset()}",
                $params
            ),
        ]);
    }

    public function show(int $id): void
    {
        $so = $this->findOrFail('sales_orders', $id, 'sales-orders', 'Bon de commande');
        $this->view('sales_orders/show', [
            'title' => 'Bon de commande ' . $so['number'], 'so' => $so,
            'customerName' => CustomerService::displayName($this->db->fetch('SELECT * FROM customers WHERE id = ?', [$so['customer_id']])),
            'quotation' => $so['quotation_id'] ? $this->db->fetch('SELECT id, number FROM quotations WHERE id = ?', [$so['quotation_id']]) : null,
            'invoices' => $this->db->fetchAll('SELECT id, number, invoice_type, status, total FROM invoices WHERE sales_order_id = ? OR (quotation_id = ? AND ? IS NOT NULL) ORDER BY id', [$id, $so['quotation_id'], $so['quotation_id']]),
            'next' => self::FLOW[$so['status']] ?? [],
            'send' => DocumentController::sendDefaults('sales_order', $so),
        ]);
    }

    public function status(int $id): void
    {
        $so = $this->findOrFail('sales_orders', $id, 'sales-orders', 'Bon de commande');
        $to = Request::string('status');
        if (!in_array($to, self::FLOW[$so['status']] ?? [], true)) {
            $this->fail('Changement de statut impossible.', 'sales-orders/' . $id);
        }
        $this->db->update('sales_orders', ['status' => $to], 'id = :id', ['id' => $id]);
        AuditLog::log('status', 'quotations', 'sales_order', $id, 'Bon de commande ' . $so['number'] . ' : ' . $so['status'] . ' → ' . $to);
        Session::flash('success', 'Statut mis à jour : ' . status_map('sales_order')[$to][0] . '.');
        $this->redirect('sales-orders/' . $id);
    }

    /** Facture brouillon reprenant le bon de commande (remise et frais du devis d'origine). */
    public function invoice(int $id): void
    {
        $so = $this->findOrFail('sales_orders', $id, 'sales-orders', 'Bon de commande');
        if ($so['status'] === 'cancelled') {
            $this->fail('Ce bon de commande est annulé.', 'sales-orders/' . $id);
        }
        if ((int) $this->db->fetchColumn("SELECT COUNT(*) FROM invoices WHERE (sales_order_id = ? OR (quotation_id = ? AND ? IS NOT NULL)) AND status <> 'cancelled' AND invoice_type <> 'deposit'", [$id, $so['quotation_id'], $so['quotation_id']]) > 0) {
            $this->fail('Ce bon de commande est déjà facturé.', 'sales-orders/' . $id);
        }
        $q = $so['quotation_id'] ? $this->db->fetch('SELECT * FROM quotations WHERE id = ?', [$so['quotation_id']]) : null;
        try {
            if ($q && (int) $this->db->fetchColumn("SELECT COUNT(*) FROM invoices WHERE quotation_id = ? AND invoice_type = 'deposit' AND status NOT IN ('draft','cancelled')", [$q['id']]) > 0) {
                $invId = InvoiceService::balance((int) $q['id']);
                $this->db->update('invoices', ['sales_order_id' => $id], 'id = :id', ['id' => $invId]);
            } else {
                $input = InvoiceService::inputFromDoc('sales_order_items', 'sales_order_id', $q ? ['id' => $id] + $q : $so);
                if (!$q && (float) $so['fees_total'] > 0) {
                    $rate = (float) ($this->db->fetchColumn('SELECT rate FROM taxes WHERE id = ?', [(int) setting('default_tax_id', 1)]) ?? 0);
                    $input['items'][] = ['type' => 'custom', 'description' => 'Frais (design, installation, transport)', 'quantity' => 1, 'unit_price' => (float) $so['fees_total'], 'tax_rate' => $rate];
                }
                $invId = InvoiceService::saveDraft(null, $input + ['sales_order_id' => $id, 'quotation_id' => $so['quotation_id'], 'invoice_type' => 'standard']);
            }
        } catch (\DomainException $e) {
            $this->fail($e->getMessage(), 'sales-orders/' . $id);
        }
        Session::flash('success', 'Facture brouillon créée depuis le bon de commande.');
        $this->redirect('invoices/' . $invId);
    }
}
