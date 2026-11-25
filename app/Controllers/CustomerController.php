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
use App\Core\Uploader;
use App\Core\Validator;
use App\Services\CustomerService;

/**
 * CRM : clients, fiche client, relevé de compte, fichiers, fidélité,
 * prix négociés, groupes, import / export CSV.
 */
final class CustomerController extends Controller
{
    public const TYPES = [
        'particulier' => 'Particulier', 'entreprise' => 'Entreprise', 'association' => 'Association',
        'administration' => 'Administration', 'restaurant' => 'Restaurant', 'commerce' => 'Commerce',
        'agence' => 'Agence', 'ecole' => 'École',
    ];

    private const SORTS = [
        'name' => 'COALESCE(NULLIF(c.company_name, \'\'), c.full_name)', 'code' => 'c.code',
        'city' => 'c.city', 'created' => 'c.created_at', 'type' => 'c.type',
    ];

    /* ================================================================
       Liste, recherche, export
       ================================================================ */

    private function filters(): array
    {
        return [
            'q'      => Request::string('q'),
            'type'   => Request::string('type'),
            'group'  => Request::int('group'),
            'city'   => Request::string('city'),
            'status' => Request::string('status', 'active'),
            'sort'   => array_key_exists(Request::string('sort'), self::SORTS) ? Request::string('sort') : 'name',
            'dir'    => Request::string('dir') === 'desc' ? 'DESC' : 'ASC',
        ];
    }

    /** @return array{0:string,1:array} */
    private function where(array $f): array
    {
        $where = ['c.deleted_at IS NULL', 'c.is_walk_in = 0'];
        $params = [];
        if ($f['q'] !== '') {
            $cols = ['c.full_name', 'c.company_name', 'c.code', 'c.phone', 'c.whatsapp', 'c.email', 'c.ice'];
            $or = [];
            foreach ($cols as $i => $col) {
                $or[] = "{$col} LIKE :q{$i}";
                $params['q' . $i] = '%' . $f['q'] . '%';
            }
            $where[] = '(' . implode(' OR ', $or) . ')';
        }
        if (isset(self::TYPES[$f['type']])) {
            $where[] = 'c.type = :type';
            $params['type'] = $f['type'];
        }
        if ($f['group'] > 0) {
            $where[] = 'c.group_id = :grp';
            $params['grp'] = $f['group'];
        }
        if ($f['city'] !== '') {
            $where[] = 'c.city = :city';
            $params['city'] = $f['city'];
        }
        if ($f['status'] === 'active') {
            $where[] = 'c.is_active = 1';
        } elseif ($f['status'] === 'inactive') {
            $where[] = 'c.is_active = 0';
        }
        return [implode(' AND ', $where), $params];
    }

    public function index(): void
    {
        $f = $this->filters();
        [$where, $params] = $this->where($f);
        $p = Paginator::fromRequest((int) $this->db->fetchColumn("SELECT COUNT(*) FROM customers c WHERE {$where}", $params));
        $customers = $this->db->fetchAll(
            "SELECT c.*, g.name AS group_name
               FROM customers c LEFT JOIN customer_groups g ON g.id = c.group_id
              WHERE {$where} ORDER BY " . self::SORTS[$f['sort']] . " {$f['dir']}, c.id DESC
              LIMIT {$p->limit()} OFFSET {$p->offset()}",
            $params
        );
        foreach ($customers as &$c) {
            $c['balance'] = CustomerService::balance((int) $c['id']);
        }
        unset($c);
        $this->view('customers/index', [
            'title'     => 'Clients',
            'customers' => $customers,
            'p'         => $p,
            'f'         => $f,
            'groups'    => $this->groups(),
            'cities'    => array_column($this->db->fetchAll("SELECT DISTINCT city FROM customers WHERE city IS NOT NULL AND city <> '' AND deleted_at IS NULL ORDER BY city"), 'city'),
            'total'     => (int) $this->db->fetchColumn('SELECT COUNT(*) FROM customers WHERE deleted_at IS NULL AND is_walk_in = 0'),
        ]);
    }

    public function export(): void
    {
        $f = $this->filters();
        [$where, $params] = $this->where($f);
        $rows = $this->db->fetchAll(
            "SELECT c.*, g.name AS group_name FROM customers c LEFT JOIN customer_groups g ON g.id = c.group_id
              WHERE {$where} ORDER BY c.code",
            $params
        );
        foreach ($rows as &$r) {
            $r['type_label'] = self::TYPES[$r['type']] ?? $r['type'];
            $r['credit_limit'] = (float) $r['credit_limit'];
            $r['balance'] = CustomerService::balance((int) $r['id']);
            $r['is_active'] = $r['is_active'] ? 'oui' : 'non';
        }
        unset($r);
        AuditLog::log('export', 'customers', null, null, 'Export CSV de ' . count($rows) . ' client(s)');
        Csv::download('clients-' . date('Y-m-d') . '.csv', self::csvColumns() + ['balance' => 'Solde', 'created_at' => 'Créé le'], $rows);
    }

    private static function csvColumns(): array
    {
        return [
            'code' => 'Code', 'type_label' => 'Type', 'full_name' => 'Nom', 'company_name' => 'Entreprise',
            'phone' => 'Téléphone', 'whatsapp' => 'WhatsApp', 'email' => 'Email', 'address' => 'Adresse',
            'city' => 'Ville', 'ice' => 'ICE', 'if_number' => 'IF', 'rc' => 'RC', 'cin' => 'CIN',
            'group_name' => 'Groupe', 'credit_limit' => 'Limite de crédit', 'notes' => 'Notes', 'is_active' => 'Actif',
        ];
    }

    /** Modèle CSV vide pour l'import. */
    public function template(): void
    {
        Csv::download('modele-import-clients.csv', self::csvColumns(), [[
            'code' => '', 'type_label' => 'Entreprise', 'full_name' => 'Nom du contact', 'company_name' => 'Société SARL',
            'phone' => '0522000000', 'whatsapp' => '0661000000', 'email' => 'contact@exemple.ma', 'address' => 'Adresse',
            'city' => 'Casablanca', 'ice' => '001234567000089', 'if_number' => '', 'rc' => '', 'cin' => '',
            'group_name' => 'Professionnels', 'credit_limit' => 0.0, 'notes' => '', 'is_active' => 'oui',
        ]]);
    }

    public function importForm(): void
    {
        $this->view('customers/import', ['title' => 'Importer des clients', 'report' => Session::get('_import_report')]);
        Session::forget('_import_report');
    }

    /**
     * Import CSV : mise à jour si le code existe, création sinon.
     * Chaque ligne est validée ; les lignes invalides sont listées sans bloquer les autres.
     */
    public function import(): void
    {
        try {
            [, $rows] = Csv::parseUpload('file');
        } catch (\RuntimeException $e) {
            $this->fail($e->getMessage(), 'customers/import');
        }
        $groups = [];
        foreach ($this->groups() as $g) {
            $groups[Csv::normalize($g['name'])] = (int) $g['id'];
        }
        $types = [];
        foreach (self::TYPES as $k => $l) {
            $types[Csv::normalize($l)] = $k;
            $types[$k] = $k;
        }
        $created = $updated = 0;
        $errors = [];
        foreach ($rows as $i => $r) {
            $line = $i + 2;
            $data = [
                'type'         => $types[Csv::normalize($r['type'] ?? '')] ?? 'particulier',
                'full_name'    => mb_substr($r['nom'] ?? '', 0, 150),
                'company_name' => mb_substr($r['entreprise'] ?? '', 0, 190) ?: null,
                'phone'        => ($r['telephone'] ?? '') ?: null,
                'whatsapp'     => ($r['whatsapp'] ?? '') ?: null,
                'email'        => mb_strtolower($r['email'] ?? '') ?: null,
                'address'      => mb_substr($r['adresse'] ?? '', 0, 255) ?: null,
                'city'         => mb_substr($r['ville'] ?? '', 0, 100) ?: null,
                'ice'          => preg_replace('/\D/', '', $r['ice'] ?? '') ?: null,
                'if_number'    => mb_substr($r['if'] ?? '', 0, 20) ?: null,
                'rc'           => mb_substr($r['rc'] ?? '', 0, 30) ?: null,
                'cin'          => mb_substr($r['cin'] ?? '', 0, 20) ?: null,
                'group_id'     => $groups[Csv::normalize($r['groupe'] ?? '')] ?? null,
                'credit_limit' => max(0, Csv::number($r['limite_de_credit'] ?? '') ?? 0),
                'notes'        => ($r['notes'] ?? '') ?: null,
                'is_active'    => Csv::bool($r['actif'] ?? '') ? 1 : 0,
            ];
            if ($data['full_name'] === '' && $data['company_name']) {
                $data['full_name'] = mb_substr($data['company_name'], 0, 150);
            }
            $v = Validator::make($data, [
                'full_name' => 'required', 'email' => 'nullable|email', 'ice' => 'nullable|ice',
                'phone' => 'nullable|phone', 'whatsapp' => 'nullable|phone',
            ], ['full_name' => 'Nom', 'email' => 'Email', 'ice' => 'ICE', 'phone' => 'Téléphone', 'whatsapp' => 'WhatsApp']);
            if ($v->fails()) {
                $errors[] = "Ligne {$line} : " . $v->first();
                continue;
            }
            $code = mb_strtoupper(trim($r['code'] ?? ''));
            try {
                $this->db->transaction(function () use ($code, $data, &$created, &$updated): void {
                    $existing = $code !== '' ? $this->db->fetch('SELECT id FROM customers WHERE code = ? AND deleted_at IS NULL', [$code]) : null;
                    if ($existing) {
                        $this->db->update('customers', $data, 'id = :id', ['id' => $existing['id']]);
                        $updated++;
                    } else {
                        $data['code'] = $code !== '' ? $code : DocumentNumber::next('customer');
                        $data['created_by'] = auth_user()['id'];
                        $data['price_list_id'] = $data['group_id'] ? $this->db->fetchColumn('SELECT price_list_id FROM customer_groups WHERE id = ?', [$data['group_id']]) : 1;
                        $this->db->insert('customers', $data);
                        $created++;
                    }
                });
            } catch (\PDOException $e) {
                $errors[] = "Ligne {$line} : code « {$code} » déjà utilisé par un client supprimé ou invalide.";
            }
        }
        AuditLog::log('import', 'customers', null, null, "Import CSV clients : {$created} créé(s), {$updated} mis à jour, " . count($errors) . ' erreur(s)');
        Session::set('_import_report', ['created' => $created, 'updated' => $updated, 'errors' => array_slice($errors, 0, 100), 'error_count' => count($errors)]);
        $this->redirect('customers/import');
    }

    /** Recherche rapide JSON (sélecteurs, POS). */
    public function lookup(): void
    {
        $q = Request::string('q');
        $rows = $q === '' ? [] : $this->db->fetchAll(
            "SELECT id, code, full_name, company_name, phone, city FROM customers
              WHERE deleted_at IS NULL AND is_active = 1
                AND (full_name LIKE :a OR company_name LIKE :b OR code LIKE :c OR phone LIKE :d)
              ORDER BY is_walk_in DESC, full_name LIMIT 15",
            ['a' => "%{$q}%", 'b' => "%{$q}%", 'c' => "%{$q}%", 'd' => "%{$q}%"]
        );
        Response::json(['success' => true, 'items' => array_map(static fn($r) => [
            'id' => (int) $r['id'], 'code' => $r['code'], 'name' => CustomerService::displayName($r),
            'detail' => trim(($r['company_name'] ? $r['full_name'] . ' · ' : '') . ($r['phone'] ?? '') . ' ' . ($r['city'] ?? '')),
        ], $rows)]);
    }

    /* ================================================================
       Création / modification
       ================================================================ */

    public function create(): void
    {
        $this->view('customers/form', ['title' => 'Nouveau client', 'customer' => null, 'groups' => $this->groups(), 'priceLists' => $this->priceLists()]);
    }

    public function edit(int $id): void
    {
        $customer = $this->find($id);
        $this->view('customers/form', [
            'title' => 'Modifier le client', 'customer' => $customer, 'groups' => $this->groups(), 'priceLists' => $this->priceLists(),
            'breadcrumbs' => [['Clients', 'customers'], [CustomerService::displayName($customer), 'customers/' . $id], ['Modifier', null]],
        ]);
    }

    public function store(): void
    {
        $data = $this->validated(null);
        $id = $this->db->transaction(function () use (&$data): int {
            $data['code'] = $data['code'] ?: DocumentNumber::next('customer');
            $data['created_by'] = auth_user()['id'];
            return $this->db->insert('customers', $data);
        });
        AuditLog::log('create', 'customers', 'customer', $id, 'Client créé : ' . $data['code'] . ' ' . $data['full_name'], null, $data);
        Session::flash('success', 'Client « ' . CustomerService::displayName($data) . ' » créé.');
        $this->redirect(Request::post('redirect') === 'new' ? 'customers/create' : 'customers/' . $id);
    }

    public function update(int $id): void
    {
        $before = $this->find($id);
        $data = $this->validated($id);
        if ($data['code'] === '') {
            unset($data['code']);
        }
        $this->db->update('customers', $data, 'id = :id', ['id' => $id]);
        [$old, $new] = AuditLog::diff($before, $data);
        AuditLog::log('update', 'customers', 'customer', $id, 'Client modifié : ' . $before['code'], $old, $new);
        Session::flash('success', 'Client mis à jour.');
        $this->redirect('customers/' . $id);
    }

    private function validated(?int $id): array
    {
        $in = [
            'code'          => mb_strtoupper(Request::string('code')),
            'type'          => Request::string('type'),
            'full_name'     => Request::string('full_name'),
            'company_name'  => Request::string('company_name'),
            'phone'         => Request::string('phone'),
            'whatsapp'      => Request::string('whatsapp'),
            'email'         => mb_strtolower(Request::string('email')),
            'address'       => Request::string('address'),
            'city'          => Request::string('city'),
            'ice'           => preg_replace('/\s+/', '', Request::string('ice')) ?? '',
            'if_number'     => Request::string('if_number'),
            'rc'            => Request::string('rc'),
            'cin'           => mb_strtoupper(Request::string('cin')),
            'group_id'      => Request::int('group_id'),
            'price_list_id' => Request::int('price_list_id'),
            'notes'         => Request::string('notes'),
        ];
        $v = Validator::make($in, [
            'code'          => 'nullable|alpha_dash|maxlen:30|unique:customers,code' . ($id ? ',' . $id : ''),
            'type'          => 'required|in:' . implode(',', array_keys(self::TYPES)),
            'full_name'     => 'required|maxlen:150',
            'company_name'  => 'nullable|maxlen:190',
            'phone'         => 'nullable|phone',
            'whatsapp'      => 'nullable|phone',
            'email'         => 'nullable|email|maxlen:190',
            'address'       => 'nullable|maxlen:255',
            'city'          => 'nullable|maxlen:100',
            'ice'           => 'nullable|ice',
            'if_number'     => 'nullable|maxlen:20',
            'rc'            => 'nullable|maxlen:30',
            'cin'           => 'nullable|maxlen:20',
            'notes'         => 'nullable|maxlen:5000',
        ], [
            'code' => 'Code', 'type' => 'Type', 'full_name' => 'Nom complet', 'company_name' => 'Raison sociale',
            'phone' => 'Téléphone', 'whatsapp' => 'WhatsApp', 'email' => 'E-mail', 'address' => 'Adresse', 'city' => 'Ville',
            'if_number' => 'IF', 'rc' => 'RC', 'cin' => 'CIN', 'notes' => 'Notes',
        ]);
        if ($v->fails()) {
            $this->fail((string) $v->first(), $id ? 'customers/' . $id . '/edit' : 'customers/create');
        }
        foreach (['company_name', 'phone', 'whatsapp', 'email', 'address', 'city', 'ice', 'if_number', 'rc', 'cin', 'notes'] as $k) {
            $in[$k] = $in[$k] !== '' ? $in[$k] : null;
        }
        $in['group_id'] = $in['group_id'] > 0 && $this->db->fetchColumn('SELECT id FROM customer_groups WHERE id = ?', [$in['group_id']]) ? $in['group_id'] : null;
        $in['price_list_id'] = $in['price_list_id'] > 0 && $this->db->fetchColumn('SELECT id FROM price_lists WHERE id = ?', [$in['price_list_id']]) ? $in['price_list_id'] : null;
        if ($in['price_list_id'] === null && $in['group_id']) {
            $in['price_list_id'] = (int) $this->db->fetchColumn('SELECT price_list_id FROM customer_groups WHERE id = ?', [$in['group_id']]) ?: null;
        }
        $in['is_active'] = Request::post('is_active') ? 1 : 0;
        // La limite de crédit est réservée aux utilisateurs autorisés
        if (can('customers.credit_limit')) {
            $limit = Request::decimal('credit_limit');
            if ($limit < 0) {
                $this->fail('La limite de crédit ne peut pas être négative.', $id ? 'customers/' . $id . '/edit' : 'customers/create');
            }
            $in['credit_limit'] = round($limit, 2);
        }
        return $in;
    }

    public function delete(int $id): void
    {
        $c = $this->find($id);
        if ((int) $c['is_walk_in'] === 1) {
            $this->fail('Le client comptoir ne peut pas être supprimé.', 'customers');
        }
        $balance = CustomerService::balance($id);
        if (abs($balance) >= 0.01) {
            $this->fail('Ce client a un solde de ' . money($balance) . ' : soldez son compte avant de le supprimer.', 'customers/' . $id);
        }
        // Suppression logique : factures et historique restent rattachés et consultables
        $this->db->update('customers', ['deleted_at' => date('Y-m-d H:i:s'), 'is_active' => 0], 'id = :id', ['id' => $id]);
        AuditLog::log('delete', 'customers', 'customer', $id, 'Client supprimé : ' . $c['code'] . ' ' . $c['full_name']);
        Session::flash('success', 'Client supprimé. Son historique reste conservé.');
        $this->redirect('customers');
    }

    /* ================================================================
       Fiche client
       ================================================================ */

    public function show(int $id): void
    {
        $c = $this->db->fetch(
            'SELECT c.*, g.name AS group_name, pl.name AS price_list_name, u.full_name AS creator
               FROM customers c
               LEFT JOIN customer_groups g ON g.id = c.group_id
               LEFT JOIN price_lists pl ON pl.id = c.price_list_id
               LEFT JOIN users u ON u.id = c.created_by
              WHERE c.id = ? AND c.deleted_at IS NULL',
            [$id]
        );
        if (!$c) {
            $this->fail('Client introuvable.', 'customers');
        }
        $db = $this->db;
        $this->view('customers/show', [
            'title'       => CustomerService::displayName($c),
            'breadcrumbs' => [['Clients', 'customers'], [CustomerService::displayName($c), null]],
            'c'           => $c,
            'stats'       => CustomerService::stats($id),
            'quotations'  => $db->fetchAll('SELECT id, number, status, issue_date, valid_until, total FROM quotations WHERE customer_id = ? AND deleted_at IS NULL ORDER BY issue_date DESC, id DESC LIMIT 20', [$id]),
            'invoices'    => $db->fetchAll("SELECT id, number, invoice_type, status, issue_date, due_date, total, paid_amount, balance_due FROM invoices WHERE customer_id = ? AND status <> 'draft' ORDER BY issue_date DESC, id DESC LIMIT 20", [$id]),
            'sales'       => $db->fetchAll("SELECT id, number, status, sale_date, total, paid_amount, invoice_id FROM sales WHERE customer_id = ? AND status NOT IN ('draft','suspended') ORDER BY sale_date DESC LIMIT 20", [$id]),
            'payments'    => $db->fetchAll("SELECT p.id, p.number, p.direction, p.amount, p.payment_date, p.reference, p.status, pm.name AS method FROM payments p JOIN payment_methods pm ON pm.id = p.payment_method_id WHERE p.customer_id = ? ORDER BY p.payment_date DESC LIMIT 20", [$id]),
            'orders'      => $db->fetchAll('SELECT id, number, title, status, deadline, created_at FROM production_orders WHERE customer_id = ? AND deleted_at IS NULL ORDER BY id DESC LIMIT 20', [$id]),
            'creditNotes' => $db->fetchAll("SELECT id, number, status, issue_date, total, reason FROM credit_notes WHERE customer_id = ? AND status <> 'draft' ORDER BY issue_date DESC LIMIT 20", [$id]),
            'files'       => $db->fetchAll('SELECT f.*, u.full_name AS uploader FROM customer_files f LEFT JOIN users u ON u.id = f.uploaded_by WHERE f.customer_id = ? ORDER BY f.id DESC', [$id]),
            'loyalty'     => $db->fetchAll('SELECT l.*, u.full_name AS user_name FROM loyalty_transactions l LEFT JOIN users u ON u.id = l.user_id WHERE l.customer_id = ? ORDER BY l.id DESC LIMIT 20', [$id]),
            'prices'      => $db->fetchAll(
                'SELECT cp.*, p.code, p.name, p.selling_price, u.symbol FROM customer_prices cp
                   JOIN products p ON p.id = cp.product_id JOIN units u ON u.id = p.unit_id
                  WHERE cp.customer_id = ? ORDER BY p.name', [$id]),
        ]);
    }

    /** Relevé de compte imprimable (PDF en Phase 5). */
    public function statement(int $id): void
    {
        $c = $this->find($id);
        $from = Request::string('from');
        $to = Request::string('to');
        $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) ? $from : date('Y-01-01');
        $to = preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) ? $to : date('Y-m-d');
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }
        $this->view('customers/statement', [
            'title' => 'Relevé de compte — ' . CustomerService::displayName($c),
            'c' => $c, 'from' => $from, 'to' => $to,
            'st' => CustomerService::statement($id, $from, $to),
        ], 'print');
    }

    /* ================================================================
       Fichiers du client (stockage privé)
       ================================================================ */

    public function uploadFile(int $id): void
    {
        $this->find($id);
        try {
            [$stored, $mime, $size, $original] = Uploader::privateFile('file', 'customers/' . $id);
        } catch (\Throwable $e) {
            $this->fail($e->getMessage(), 'customers/' . $id, ['tab' => 'files']);
        }
        $fid = $this->db->insert('customer_files', [
            'customer_id' => $id, 'original_name' => $original, 'stored_name' => $stored, 'mime_type' => $mime,
            'file_size' => $size, 'description' => mb_substr(Request::string('description'), 0, 255) ?: null, 'uploaded_by' => auth_user()['id'],
        ]);
        AuditLog::log('upload', 'customers', 'customer_file', $fid, 'Fichier ajouté au client #' . $id . ' : ' . $original);
        Session::flash('success', 'Fichier ajouté.');
        $this->redirect('customers/' . $id, ['tab' => 'files']);
    }

    public function downloadFile(int $id, int $fileId): void
    {
        $f = $this->db->fetch('SELECT * FROM customer_files WHERE id = ? AND customer_id = ?', [$fileId, $id]);
        $path = $f ? BASE_PATH . '/storage/uploads/' . $f['stored_name'] : '';
        if (!$f || !preg_match('#^customers/\d+/[a-f0-9]{32}\.[a-z]{2,4}$#', $f['stored_name']) || !is_file($path)) {
            $this->fail('Fichier introuvable.', 'customers/' . $id, ['tab' => 'files']);
        }
        $inline = in_array($f['mime_type'], ['application/pdf', 'image/png', 'image/jpeg', 'image/webp'], true) && Request::get('inline');
        header('Content-Type: ' . ($inline ? $f['mime_type'] : 'application/octet-stream'));
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . rawurlencode($f['original_name']) . '"; filename*=UTF-8\'\'' . rawurlencode($f['original_name']));
        header('X-Content-Type-Options: nosniff');
        header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'");
        header('Cache-Control: private, no-store');
        readfile($path);
        exit;
    }

    public function deleteFile(int $id, int $fileId): void
    {
        $f = $this->db->fetch('SELECT * FROM customer_files WHERE id = ? AND customer_id = ?', [$fileId, $id]);
        if ($f) {
            $path = BASE_PATH . '/storage/uploads/' . $f['stored_name'];
            if (preg_match('#^customers/\d+/[a-f0-9]{32}\.[a-z]{2,4}$#', $f['stored_name']) && is_file($path)) {
                @unlink($path);
            }
            $this->db->query('DELETE FROM customer_files WHERE id = ?', [$fileId]);
            AuditLog::log('delete', 'customers', 'customer_file', $fileId, 'Fichier supprimé du client #' . $id . ' : ' . $f['original_name']);
            Session::flash('success', 'Fichier supprimé.');
        }
        $this->redirect('customers/' . $id, ['tab' => 'files']);
    }

    /* ================================================================
       Fidélité et prix négociés
       ================================================================ */

    public function loyalty(int $id): void
    {
        $c = $this->find($id);
        $points = Request::int('points');
        $reason = Request::string('reason');
        if ($points === 0 || $reason === '') {
            $this->fail('Indiquez un nombre de points (positif ou négatif) et un motif.', 'customers/' . $id, ['tab' => 'loyalty']);
        }
        if ((int) $c['loyalty_points'] + $points < 0) {
            $this->fail('Le client ne dispose que de ' . (int) $c['loyalty_points'] . ' point(s).', 'customers/' . $id, ['tab' => 'loyalty']);
        }
        $this->db->transaction(function () use ($id, $points, $reason): void {
            $this->db->insert('loyalty_transactions', [
                'customer_id' => $id, 'points' => $points, 'reason' => mb_substr($reason, 0, 190),
                'reference_type' => 'manual', 'user_id' => auth_user()['id'],
            ]);
            $this->db->query('UPDATE customers SET loyalty_points = loyalty_points + ? WHERE id = ?', [$points, $id]);
        });
        AuditLog::log('loyalty', 'customers', 'customer', $id, "Fidélité : {$points} point(s) — {$reason}");
        Session::flash('success', 'Points de fidélité mis à jour.');
        $this->redirect('customers/' . $id, ['tab' => 'loyalty']);
    }

    public function savePrice(int $id): void
    {
        $this->find($id);
        $productId = Request::int('product_id');
        $price = Request::decimal('price', -1);
        $until = Request::string('valid_until');
        $product = $this->db->fetch('SELECT id, name, min_selling_price FROM products WHERE id = ? AND deleted_at IS NULL', [$productId]);
        if (!$product || $price < 0) {
            $this->fail('Produit ou prix invalide.', 'customers/' . $id, ['tab' => 'prices']);
        }
        if ($price < (float) $product['min_selling_price'] && !can('pos.below_min_price')) {
            $this->fail('Ce prix est inférieur au prix minimum de vente (' . money($product['min_selling_price']) . ').', 'customers/' . $id, ['tab' => 'prices']);
        }
        $this->db->query(
            'INSERT INTO customer_prices (customer_id, product_id, price, valid_until) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE price = VALUES(price), valid_until = VALUES(valid_until)',
            [$id, $productId, round($price, 2), preg_match('/^\d{4}-\d{2}-\d{2}$/', $until) ? $until : null]
        );
        AuditLog::log('update', 'customers', 'customer_price', $id, 'Prix négocié : ' . $product['name'] . ' = ' . number_fr($price) . ' (client #' . $id . ')');
        Session::flash('success', 'Prix négocié enregistré.');
        $this->redirect('customers/' . $id, ['tab' => 'prices']);
    }

    public function deletePrice(int $id, int $priceId): void
    {
        $this->db->query('DELETE FROM customer_prices WHERE id = ? AND customer_id = ?', [$priceId, $id]);
        AuditLog::log('delete', 'customers', 'customer_price', $priceId, 'Prix négocié supprimé (client #' . $id . ')');
        Session::flash('success', 'Prix négocié supprimé.');
        $this->redirect('customers/' . $id, ['tab' => 'prices']);
    }

    /* ================================================================
       Groupes de clients
       ================================================================ */

    public function groupsIndex(): void
    {
        $this->view('customers/groups', [
            'title' => 'Groupes de clients',
            'breadcrumbs' => [['Clients', 'customers'], ['Groupes', null]],
            'groups' => $this->db->fetchAll(
                'SELECT g.*, pl.name AS price_list_name,
                        (SELECT COUNT(*) FROM customers c WHERE c.group_id = g.id AND c.deleted_at IS NULL) AS customers_count
                   FROM customer_groups g LEFT JOIN price_lists pl ON pl.id = g.price_list_id ORDER BY g.name'
            ),
            'priceLists' => $this->priceLists(),
        ]);
    }

    public function saveGroup(): void
    {
        $id = Request::int('id');
        $data = [
            'name'             => Request::string('name'),
            'price_list_id'    => Request::int('price_list_id') ?: null,
            'discount_percent' => round(Request::decimal('discount_percent'), 2),
            'description'      => Request::string('description') ?: null,
        ];
        if ($data['name'] === '' || mb_strlen($data['name']) > 100 || $data['discount_percent'] < 0 || $data['discount_percent'] > 100) {
            $this->fail('Nom obligatoire (100 caractères max.) et remise entre 0 et 100 %.', 'customers/groups');
        }
        if ((int) $this->db->fetchColumn('SELECT COUNT(*) FROM customer_groups WHERE name = ? AND id <> ?', [$data['name'], $id])) {
            $this->fail('Un groupe porte déjà ce nom.', 'customers/groups');
        }
        if ($id > 0) {
            $this->db->update('customer_groups', $data, 'id = :id', ['id' => $id]);
        } else {
            $id = $this->db->insert('customer_groups', $data);
        }
        AuditLog::log('save', 'customers', 'customer_group', $id, 'Groupe de clients enregistré : ' . $data['name'], null, $data);
        Session::flash('success', 'Groupe enregistré.');
        $this->redirect('customers/groups');
    }

    public function deleteGroup(int $id): void
    {
        $n = (int) $this->db->fetchColumn('SELECT COUNT(*) FROM customers WHERE group_id = ? AND deleted_at IS NULL', [$id]);
        if ($n > 0) {
            $this->fail("Ce groupe contient {$n} client(s) : changez-les de groupe avant de le supprimer.", 'customers/groups');
        }
        $this->db->query('UPDATE customers SET group_id = NULL WHERE group_id = ?', [$id]);
        $this->db->query('DELETE FROM customer_groups WHERE id = ?', [$id]);
        AuditLog::log('delete', 'customers', 'customer_group', $id, 'Groupe de clients supprimé');
        Session::flash('success', 'Groupe supprimé.');
        $this->redirect('customers/groups');
    }

    /* ---------------------------------------------------------------- */

    private function find(int $id): array
    {
        return $this->findOrFail('customers', $id, 'customers', 'Client');
    }

    private function groups(): array
    {
        return $this->db->fetchAll('SELECT id, name, price_list_id, discount_percent FROM customer_groups ORDER BY name');
    }

    private function priceLists(): array
    {
        return $this->db->fetchAll('SELECT id, name FROM price_lists WHERE is_active = 1 ORDER BY id');
    }
}
