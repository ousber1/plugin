<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Session;
use App\Services\CustomerService;
use App\Services\DocumentService;
use App\Services\InvoiceService;
use App\Services\QuotationService;

/**
 * Devis : liste, création / modification, suivi des statuts, envoi,
 * conversions (facture, acompte, solde, bon de commande, vente au comptoir).
 */
final class QuotationController extends Controller
{
    private function filters(): array
    {
        return [
            'q' => Request::string('q'), 'status' => Request::string('status'), 'from' => Request::string('from'), 'to' => Request::string('to'),
            'customer' => Request::int('customer'),
        ];
    }

    public function index(): void
    {
        QuotationService::refreshExpired();
        $f = $this->filters();
        $where = ['q.deleted_at IS NULL'];
        $params = [];
        if ($f['q'] !== '') {
            $where[] = '(q.number LIKE :a OR q.title LIKE :b OR c.full_name LIKE :c OR c.company_name LIKE :d OR c.phone LIKE :e)';
            $params += ['a' => "%{$f['q']}%", 'b' => "%{$f['q']}%", 'c' => "%{$f['q']}%", 'd' => "%{$f['q']}%", 'e' => "%{$f['q']}%"];
        }
        if (isset(status_map('quotation')[$f['status']])) {
            $where[] = 'q.status = :st';
            $params['st'] = $f['status'];
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['from'])) {
            $where[] = 'q.issue_date >= :from';
            $params['from'] = $f['from'];
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['to'])) {
            $where[] = 'q.issue_date <= :to';
            $params['to'] = $f['to'];
        }
        if ($f['customer'] > 0) {
            $where[] = 'q.customer_id = :cid';
            $params['cid'] = $f['customer'];
        }
        $w = implode(' AND ', $where);
        $from = 'FROM quotations q JOIN customers c ON c.id = q.customer_id LEFT JOIN users u ON u.id = q.user_id';
        $p = Paginator::fromRequest((int) $this->db->fetchColumn("SELECT COUNT(*) {$from} WHERE {$w}", $params));
        $stats = $this->db->fetch(
            "SELECT COUNT(*) AS n, COALESCE(SUM(q.total), 0) AS total,
                    COALESCE(SUM(CASE WHEN q.status IN ('sent','pending','draft') THEN q.total END), 0) AS open_total,
                    SUM(q.status IN ('accepted','converted')) AS won, SUM(q.status IN ('accepted','converted','refused','expired')) AS decided
               {$from} WHERE {$w}",
            $params
        );
        $this->view('quotations/index', [
            'title' => 'Devis', 'f' => $f, 'p' => $p, 'stats' => $stats,
            'rows' => $this->db->fetchAll(
                "SELECT q.*, COALESCE(NULLIF(c.company_name, ''), c.full_name) AS customer_name, c.phone, u.full_name AS user_name
                   {$from} WHERE {$w} ORDER BY q.issue_date DESC, q.id DESC LIMIT {$p->limit()} OFFSET {$p->offset()}",
                $params
            ),
        ]);
    }

    private function formData(): array
    {
        return [
            'taxes' => $this->db->fetchAll('SELECT id, name, rate FROM taxes WHERE is_active = 1 ORDER BY rate DESC'),
            'units' => $this->db->fetchAll('SELECT id, symbol FROM units WHERE is_active = 1 ORDER BY sort_order'),
            'scripts' => [asset('js/document-form.js')],
        ];
    }

    public function create(): void
    {
        $customer = null;
        if ($cid = Request::int('customer')) {
            $customer = $this->db->fetch('SELECT * FROM customers WHERE id = ? AND deleted_at IS NULL AND is_walk_in = 0', [$cid]) ?: null;
        }
        $this->view('quotations/form', ['title' => 'Nouveau devis', 'doc' => [], 'items' => [], 'customer' => $customer] + $this->formData());
    }

    public function store(): void
    {
        try {
            $id = QuotationService::save(null, $this->input());
        } catch (\DomainException $e) {
            $this->fail($e->getMessage(), 'quotations/create');
        }
        Session::flash('success', 'Devis enregistré. Vérifiez l\'aperçu puis envoyez-le au client.');
        $this->redirect('quotations/' . $id);
    }

    public function edit(int $id): void
    {
        $q = $this->findOrFail('quotations', $id, 'quotations', 'Devis');
        if (in_array($q['status'], ['accepted', 'converted'], true)) {
            Session::flash('warning', 'Ce devis est accepté ou converti : il n\'est plus modifiable. Dupliquez-le pour établir une nouvelle offre.');
            $this->redirect('quotations/' . $id);
        }
        $this->view('quotations/form', [
            'title' => 'Modifier le devis ' . $q['number'], 'doc' => $q,
            'items' => DocumentService::itemsForForm('quotation_items', 'quotation_id', $id),
            'customer' => $this->db->fetch('SELECT * FROM customers WHERE id = ?', [$q['customer_id']]),
        ] + $this->formData());
    }

    public function update(int $id): void
    {
        $this->findOrFail('quotations', $id, 'quotations', 'Devis');
        try {
            QuotationService::save($id, $this->input());
        } catch (\DomainException $e) {
            $this->fail($e->getMessage(), 'quotations/' . $id . '/edit');
        }
        Session::flash('success', 'Devis mis à jour.');
        $this->redirect('quotations/' . $id);
    }

    /** Données du formulaire (lignes transmises en JSON par l'éditeur). */
    private function input(): array
    {
        $in = $_POST;
        $items = json_decode((string) ($in['items_json'] ?? ''), true);
        $in['items'] = is_array($items) ? $items : [];
        return $in;
    }

    public function show(int $id): void
    {
        $q = $this->findOrFail('quotations', $id, 'quotations', 'Devis');
        if (in_array($q['status'], ['sent', 'pending'], true) && $q['valid_until'] && $q['valid_until'] < date('Y-m-d')) {
            QuotationService::refreshExpired();
            $q = $this->db->fetch('SELECT * FROM quotations WHERE id = ?', [$id]);
        }
        $customer = $this->db->fetch('SELECT * FROM customers WHERE id = ?', [$q['customer_id']]);
        $invoices = $this->db->fetchAll("SELECT id, number, invoice_type, status, total, balance_due, issue_date FROM invoices WHERE quotation_id = ? ORDER BY id", [$id]);
        $depositsBilled = 0.0;
        foreach ($invoices as $inv) {
            if ($inv['invoice_type'] === 'deposit' && $inv['status'] !== 'cancelled') {
                $depositsBilled += (float) $inv['total'];
            }
        }
        $this->view('quotations/show', [
            'title' => 'Devis ' . $q['number'], 'q' => $q, 'customer' => $customer, 'customerName' => CustomerService::displayName($customer),
            'invoices' => $invoices, 'depositsBilled' => round($depositsBilled, 2),
            'orders' => $this->db->fetchAll('SELECT id, number, status, total FROM sales_orders WHERE quotation_id = ? AND deleted_at IS NULL', [$id]),
            'sales' => $this->db->fetchAll("SELECT id, number, status, total FROM sales WHERE quotation_id = ? AND status NOT IN ('draft')", [$id]),
            'history' => $this->db->fetchAll('SELECT h.*, u.full_name FROM quotation_history h LEFT JOIN users u ON u.id = h.user_id WHERE h.quotation_id = ? ORDER BY h.id DESC', [$id]),
            'send' => DocumentController::sendDefaults('quotation', $q),
            'cost' => (float) $q['cost_total'],
            'statuses' => array_intersect_key(status_map('quotation'), array_flip(['draft', 'sent', 'pending', 'accepted', 'refused'])),
            'views' => (int) $this->db->fetchColumn("SELECT COALESCE(SUM(views), 0) FROM document_shares WHERE doc_type = 'quotation' AND doc_id = ?", [$id]),
        ]);
    }

    public function status(int $id): void
    {
        $this->findOrFail('quotations', $id, 'quotations', 'Devis');
        try {
            QuotationService::setStatus($id, Request::string('status'), Request::string('note') ?: null);
        } catch (\DomainException $e) {
            $this->fail($e->getMessage(), 'quotations/' . $id);
        }
        Session::flash('success', 'Statut du devis mis à jour.');
        $this->redirect('quotations/' . $id);
    }

    public function duplicate(int $id): void
    {
        try {
            $new = QuotationService::duplicate($id);
        } catch (\DomainException $e) {
            $this->fail($e->getMessage(), 'quotations/' . $id);
        }
        Session::flash('success', 'Devis dupliqué : vous pouvez modifier la copie.');
        $this->redirect('quotations/' . $new . '/edit');
    }

    public function delete(int $id): void
    {
        try {
            QuotationService::delete($id);
        } catch (\DomainException $e) {
            $this->fail($e->getMessage(), 'quotations/' . $id);
        }
        Session::flash('success', 'Devis supprimé.');
        $this->redirect('quotations');
    }

    /** Conversions : facture, acompte, solde, bon de commande, vente au comptoir. */
    public function convert(int $id, string $target): void
    {
        $this->findOrFail('quotations', $id, 'quotations', 'Devis');
        $needs = ['invoice' => 'invoices.create', 'deposit' => 'invoices.create', 'balance' => 'invoices.create', 'order' => 'quotations.convert', 'pos' => 'pos.access'];
        if (!isset($needs[$target])) {
            $this->redirect('quotations/' . $id);
        }
        $this->authorize($needs[$target]);
        try {
            switch ($target) {
                case 'invoice':
                    $inv = InvoiceService::fromQuotation($id);
                    Session::flash('success', 'Facture brouillon créée depuis le devis. Vérifiez-la puis validez-la pour lui attribuer son numéro.');
                    $this->redirect('invoices/' . $inv);
                case 'deposit':
                    $inv = InvoiceService::deposit($id, (float) str_replace(',', '.', Request::string('percent')));
                    Session::flash('success', 'Facture d\'acompte créée (brouillon). Validez-la pour l\'envoyer au client.');
                    $this->redirect('invoices/' . $inv);
                case 'balance':
                    $inv = InvoiceService::balance($id);
                    Session::flash('success', 'Facture de solde créée (brouillon) : les acomptes facturés sont déduits.');
                    $this->redirect('invoices/' . $inv);
                case 'order':
                    $so = QuotationService::toSalesOrder($id);
                    Session::flash('success', 'Bon de commande créé depuis le devis.');
                    $this->redirect('sales-orders/' . $so);
                case 'pos':
                    $draft = QuotationService::toPosDraft($id);
                    Session::flash('success', 'Le devis est chargé au point de vente : encaissez-le pour finaliser la vente.');
                    $this->redirect('pos', ['resume' => $draft]);
            }
        } catch (\DomainException $e) {
            $this->fail($e->getMessage(), 'quotations/' . $id);
        }
    }
}
