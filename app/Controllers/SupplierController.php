<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\AuditLog;
use App\Core\Controller;
use App\Core\Csv;
use App\Core\DocumentNumber;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;

/**
 * Fournisseurs : fiche, historique d'achats, dettes, prix des matières et comparaison.
 * (Commandes, factures et paiements fournisseurs : saisie en Phase 7.)
 */
final class SupplierController extends Controller
{
    private const SORTS = ['name' => 's.name', 'code' => 's.code', 'city' => 's.city', 'created' => 's.created_at'];

    private function filters(): array
    {
        return [
            'q'      => Request::string('q'),
            'city'   => Request::string('city'),
            'status' => Request::string('status', 'active'),
            'sort'   => array_key_exists(Request::string('sort'), self::SORTS) ? Request::string('sort') : 'name',
            'dir'    => Request::string('dir') === 'desc' ? 'DESC' : 'ASC',
        ];
    }

    private function where(array $f): array
    {
        $where = ['s.deleted_at IS NULL'];
        $params = [];
        if ($f['q'] !== '') {
            $or = [];
            foreach (['s.name', 's.company', 's.code', 's.phone', 's.email', 's.ice'] as $i => $col) {
                $or[] = "{$col} LIKE :q{$i}";
                $params['q' . $i] = '%' . $f['q'] . '%';
            }
            $where[] = '(' . implode(' OR ', $or) . ')';
        }
        if ($f['city'] !== '') {
            $where[] = 's.city = :city';
            $params['city'] = $f['city'];
        }
        if ($f['status'] === 'active') {
            $where[] = 's.is_active = 1';
        } elseif ($f['status'] === 'inactive') {
            $where[] = 's.is_active = 0';
        }
        return [implode(' AND ', $where), $params];
    }

    /** Dette fournisseur = factures fournisseurs non soldées. */
    private const DEBT_SQL = "(SELECT COALESCE(SUM(pi.total - pi.paid_amount), 0) FROM purchase_invoices pi WHERE pi.supplier_id = s.id AND pi.status IN ('unpaid','partial'))";

    public function index(): void
    {
        $f = $this->filters();
        [$where, $params] = $this->where($f);
        $p = Paginator::fromRequest((int) $this->db->fetchColumn("SELECT COUNT(*) FROM suppliers s WHERE {$where}", $params));
        $suppliers = $this->db->fetchAll(
            "SELECT s.*, " . self::DEBT_SQL . " AS debt,
                    (SELECT COUNT(*) FROM materials m WHERE m.supplier_id = s.id AND m.deleted_at IS NULL) AS materials_count
               FROM suppliers s WHERE {$where} ORDER BY " . self::SORTS[$f['sort']] . " {$f['dir']}
              LIMIT {$p->limit()} OFFSET {$p->offset()}",
            $params
        );
        $this->view('suppliers/index', [
            'title' => 'Fournisseurs', 'suppliers' => $suppliers, 'p' => $p, 'f' => $f,
            'cities' => array_column($this->db->fetchAll("SELECT DISTINCT city FROM suppliers WHERE city IS NOT NULL AND city <> '' AND deleted_at IS NULL ORDER BY city"), 'city'),
        ]);
    }

    public function export(): void
    {
        [$where, $params] = $this->where($this->filters());
        $rows = $this->db->fetchAll("SELECT s.*, " . self::DEBT_SQL . " AS debt FROM suppliers s WHERE {$where} ORDER BY s.code", $params);
        foreach ($rows as &$r) {
            $r['debt'] = (float) $r['debt'];
            $r['is_active'] = $r['is_active'] ? 'oui' : 'non';
        }
        unset($r);
        AuditLog::log('export', 'suppliers', null, null, 'Export CSV de ' . count($rows) . ' fournisseur(s)');
        Csv::download('fournisseurs-' . date('Y-m-d') . '.csv', [
            'code' => 'Code', 'name' => 'Nom', 'company' => 'Société', 'phone' => 'Téléphone', 'whatsapp' => 'WhatsApp',
            'email' => 'Email', 'address' => 'Adresse', 'city' => 'Ville', 'ice' => 'ICE', 'if_number' => 'IF', 'rc' => 'RC',
            'payment_terms_days' => 'Délai de paiement (j)', 'debt' => 'Dette', 'notes' => 'Notes', 'is_active' => 'Actif',
        ], $rows);
    }

    public function lookup(): void
    {
        $q = Request::string('q');
        $rows = $q === '' ? [] : $this->db->fetchAll(
            'SELECT id, code, name, company, city FROM suppliers WHERE deleted_at IS NULL AND is_active = 1
              AND (name LIKE :a OR company LIKE :b OR code LIKE :c) ORDER BY name LIMIT 15',
            ['a' => "%{$q}%", 'b' => "%{$q}%", 'c' => "%{$q}%"]
        );
        Response::json(['success' => true, 'items' => array_map(static fn($r) => [
            'id' => (int) $r['id'], 'code' => $r['code'], 'name' => $r['company'] ?: $r['name'], 'detail' => trim(($r['company'] ? $r['name'] : '') . ' ' . ($r['city'] ?? '')),
        ], $rows)]);
    }

    public function create(): void
    {
        $this->view('suppliers/form', ['title' => 'Nouveau fournisseur', 'supplier' => null]);
    }

    public function edit(int $id): void
    {
        $s = $this->findOrFail('suppliers', $id, 'suppliers', 'Fournisseur');
        $this->view('suppliers/form', ['title' => 'Modifier le fournisseur', 'supplier' => $s,
            'breadcrumbs' => [['Fournisseurs', 'suppliers'], [$s['name'], 'suppliers/' . $id], ['Modifier', null]]]);
    }

    public function store(): void
    {
        $data = $this->validated(null);
        $id = $this->db->transaction(function () use (&$data): int {
            $data['code'] = $data['code'] ?: DocumentNumber::next('supplier');
            $data['created_by'] = auth_user()['id'];
            return $this->db->insert('suppliers', $data);
        });
        AuditLog::log('create', 'suppliers', 'supplier', $id, 'Fournisseur créé : ' . $data['code'] . ' ' . $data['name'], null, $data);
        Session::flash('success', 'Fournisseur « ' . $data['name'] . ' » créé.');
        $this->redirect('suppliers/' . $id);
    }

    public function update(int $id): void
    {
        $before = $this->findOrFail('suppliers', $id, 'suppliers', 'Fournisseur');
        $data = $this->validated($id);
        if ($data['code'] === '') {
            unset($data['code']);
        }
        $this->db->update('suppliers', $data, 'id = :id', ['id' => $id]);
        [$old, $new] = AuditLog::diff($before, $data);
        AuditLog::log('update', 'suppliers', 'supplier', $id, 'Fournisseur modifié : ' . $before['code'], $old, $new);
        Session::flash('success', 'Fournisseur mis à jour.');
        $this->redirect('suppliers/' . $id);
    }

    private function validated(?int $id): array
    {
        $in = [
            'code' => mb_strtoupper(Request::string('code')), 'name' => Request::string('name'), 'company' => Request::string('company'),
            'phone' => Request::string('phone'), 'whatsapp' => Request::string('whatsapp'), 'email' => mb_strtolower(Request::string('email')),
            'address' => Request::string('address'), 'city' => Request::string('city'), 'ice' => preg_replace('/\s+/', '', Request::string('ice')) ?? '',
            'if_number' => Request::string('if_number'), 'rc' => Request::string('rc'), 'payment_terms_days' => Request::string('payment_terms_days'),
            'notes' => Request::string('notes'),
        ];
        $v = Validator::make($in, [
            'code' => 'nullable|alpha_dash|maxlen:30|unique:suppliers,code' . ($id ? ',' . $id : ''),
            'name' => 'required|maxlen:150', 'company' => 'nullable|maxlen:190', 'phone' => 'nullable|phone', 'whatsapp' => 'nullable|phone',
            'email' => 'nullable|email|maxlen:190', 'address' => 'nullable|maxlen:255', 'city' => 'nullable|maxlen:100', 'ice' => 'nullable|ice',
            'if_number' => 'nullable|maxlen:20', 'rc' => 'nullable|maxlen:30', 'payment_terms_days' => 'nullable|integer|min:0|max:365', 'notes' => 'nullable|maxlen:5000',
        ], ['code' => 'Code', 'name' => 'Nom', 'company' => 'Société', 'phone' => 'Téléphone', 'whatsapp' => 'WhatsApp', 'email' => 'E-mail',
            'address' => 'Adresse', 'city' => 'Ville', 'if_number' => 'IF', 'rc' => 'RC', 'payment_terms_days' => 'Délai de paiement', 'notes' => 'Notes']);
        if ($v->fails()) {
            $this->fail((string) $v->first(), $id ? 'suppliers/' . $id . '/edit' : 'suppliers/create');
        }
        foreach (['company', 'phone', 'whatsapp', 'email', 'address', 'city', 'ice', 'if_number', 'rc', 'notes'] as $k) {
            $in[$k] = $in[$k] !== '' ? $in[$k] : null;
        }
        $in['payment_terms_days'] = (int) $in['payment_terms_days'];
        $in['is_active'] = Request::post('is_active') ? 1 : 0;
        return $in;
    }

    public function delete(int $id): void
    {
        $s = $this->findOrFail('suppliers', $id, 'suppliers', 'Fournisseur');
        $debt = (float) $this->db->fetchColumn("SELECT COALESCE(SUM(total - paid_amount), 0) FROM purchase_invoices WHERE supplier_id = ? AND status IN ('unpaid','partial')", [$id]);
        if ($debt >= 0.01) {
            $this->fail('Ce fournisseur a une dette de ' . money($debt) . ' : réglez-la avant de le supprimer.', 'suppliers/' . $id);
        }
        $this->db->update('suppliers', ['deleted_at' => date('Y-m-d H:i:s'), 'is_active' => 0], 'id = :id', ['id' => $id]);
        AuditLog::log('delete', 'suppliers', 'supplier', $id, 'Fournisseur supprimé : ' . $s['code'] . ' ' . $s['name']);
        Session::flash('success', 'Fournisseur supprimé. Son historique reste conservé.');
        $this->redirect('suppliers');
    }

    public function show(int $id): void
    {
        $s = $this->findOrFail('suppliers', $id, 'suppliers', 'Fournisseur');
        $db = $this->db;
        $stats = $db->fetch(
            "SELECT COALESCE(SUM(CASE WHEN status <> 'cancelled' THEN total END), 0) AS purchased,
                    COALESCE(SUM(CASE WHEN status IN ('unpaid','partial') THEN total - paid_amount END), 0) AS debt,
                    SUM(status IN ('unpaid','partial') AND due_date < CURDATE()) AS overdue,
                    COUNT(*) AS invoices
               FROM purchase_invoices WHERE supplier_id = ?",
            [$id]
        );
        $this->view('suppliers/show', [
            'title'       => $s['company'] ?: $s['name'],
            'breadcrumbs' => [['Fournisseurs', 'suppliers'], [$s['company'] ?: $s['name'], null]],
            's'           => $s,
            'stats'       => $stats + ['paid' => (float) $db->fetchColumn("SELECT COALESCE(SUM(amount), 0) FROM supplier_payments WHERE supplier_id = ? AND status = 'valid'", [$id])],
            'orders'      => $db->fetchAll('SELECT id, number, status, order_date, expected_date, total FROM purchase_orders WHERE supplier_id = ? ORDER BY order_date DESC, id DESC LIMIT 20', [$id]),
            'invoices'    => $db->fetchAll('SELECT id, number, supplier_invoice_number, status, invoice_date, due_date, total, paid_amount FROM purchase_invoices WHERE supplier_id = ? ORDER BY invoice_date DESC LIMIT 20', [$id]),
            'payments'    => $db->fetchAll('SELECT sp.*, pm.name AS method FROM supplier_payments sp JOIN payment_methods pm ON pm.id = sp.payment_method_id WHERE sp.supplier_id = ? ORDER BY sp.payment_date DESC LIMIT 20', [$id]),
            'returns'     => $db->fetchAll('SELECT id, number, return_date, total, status, reason FROM supplier_returns WHERE supplier_id = ? ORDER BY return_date DESC LIMIT 20', [$id]),
            'prices'      => $db->fetchAll(
                'SELECT smp.*, m.code, m.name, u.symbol, m.unit_cost
                   FROM supplier_material_prices smp JOIN materials m ON m.id = smp.material_id JOIN units u ON u.id = m.unit_id
                  WHERE smp.supplier_id = ? AND m.deleted_at IS NULL ORDER BY m.name, smp.valid_from DESC', [$id]),
            'materials'   => $db->fetchAll('SELECT m.id, m.code, m.name, m.stock_quantity, m.unit_cost, u.symbol FROM materials m JOIN units u ON u.id = m.unit_id WHERE m.supplier_id = ? AND m.deleted_at IS NULL ORDER BY m.name', [$id]),
        ]);
    }

    /** Prix d'une matière chez ce fournisseur (historique, sert à la comparaison). */
    public function savePrice(int $id): void
    {
        $this->findOrFail('suppliers', $id, 'suppliers', 'Fournisseur');
        $materialId = Request::int('material_id');
        $price = Request::decimal('price', -1);
        $from = Request::string('valid_from') ?: date('Y-m-d');
        if (!$this->db->fetchColumn('SELECT id FROM materials WHERE id = ? AND deleted_at IS NULL', [$materialId]) || $price < 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $this->fail('Matière, prix ou date invalide.', 'suppliers/' . $id, ['tab' => 'prices']);
        }
        $pid = $this->db->insert('supplier_material_prices', [
            'supplier_id' => $id, 'material_id' => $materialId, 'price' => round($price, 4),
            'min_quantity' => max(0.001, Request::decimal('min_quantity', 1)), 'lead_time_days' => Request::int('lead_time_days') ?: null,
            'valid_from' => $from, 'notes' => mb_substr(Request::string('notes'), 0, 255) ?: null,
        ]);
        AuditLog::log('create', 'suppliers', 'supplier_price', $pid, 'Prix fournisseur enregistré (fournisseur #' . $id . ', matière #' . $materialId . ')');
        Session::flash('success', 'Prix enregistré.');
        $this->redirect('suppliers/' . $id, ['tab' => 'prices']);
    }

    public function deletePrice(int $id, int $priceId): void
    {
        $this->db->query('DELETE FROM supplier_material_prices WHERE id = ? AND supplier_id = ?', [$priceId, $id]);
        AuditLog::log('delete', 'suppliers', 'supplier_price', $priceId, 'Prix fournisseur supprimé');
        Session::flash('success', 'Prix supprimé.');
        $this->redirect('suppliers/' . $id, ['tab' => 'prices']);
    }
}
