<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\AuditLog;
use App\Core\Controller;
use App\Core\Csv;
use App\Core\DocumentNumber;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Settings;
use App\Core\Uploader;

/**
 * Packs commerciaux (START BUSINESS, RESTAURANT PRO, SHOP OPENING…) :
 * composants, valeur et coût calculés depuis le catalogue, prix, remise, marge, disponibilité.
 */
final class PackController extends Controller
{
    /** Valeur (prix catalogue) et coût des composants, calculés en direct. */
    private const TOTALS_SQL = "SELECT pi.pack_id,
            COUNT(*) AS items_count,
            SUM(pi.quantity * p.selling_price) AS components_value,
            SUM(pi.quantity * IF(p.estimated_cost > 0, p.estimated_cost, p.purchase_price)) AS components_cost,
            SUM(p.is_active = 0 OR p.deleted_at IS NOT NULL) AS inactive_items
        FROM pack_items pi JOIN products p ON p.id = pi.product_id GROUP BY pi.pack_id";

    public function index(): void
    {
        $q = Request::string('q');
        $status = Request::string('status', 'active');
        $where = ['pk.deleted_at IS NULL'];
        $params = [];
        if ($q !== '') {
            $where[] = '(pk.name LIKE :a OR pk.code LIKE :b OR pk.description LIKE :c)';
            $params = ['a' => "%{$q}%", 'b' => "%{$q}%", 'c' => "%{$q}%"];
        }
        if ($status === 'active') {
            $where[] = 'pk.is_active = 1';
        } elseif ($status === 'inactive') {
            $where[] = 'pk.is_active = 0';
        }
        $packs = $this->db->fetchAll(
            'SELECT pk.*, t.items_count, t.components_value, t.components_cost, t.inactive_items
               FROM packs pk LEFT JOIN (' . self::TOTALS_SQL . ') t ON t.pack_id = pk.id
              WHERE ' . implode(' AND ', $where) . ' ORDER BY pk.is_active DESC, pk.name',
            $params
        );
        $this->view('packs/index', ['title' => 'Packs commerciaux', 'packs' => $packs, 'q' => $q, 'status' => $status]);
    }

    public function show(int $id): void
    {
        $pack = $this->db->fetch(
            'SELECT pk.*, t.items_count, t.components_value, t.components_cost, tx.name AS tax_name, tx.rate AS tax_rate
               FROM packs pk LEFT JOIN (' . self::TOTALS_SQL . ') t ON t.pack_id = pk.id LEFT JOIN taxes tx ON tx.id = pk.tax_id
              WHERE pk.id = ? AND pk.deleted_at IS NULL',
            [$id]
        );
        if (!$pack) {
            $this->fail('Pack introuvable.', 'packs');
        }
        $this->view('packs/show', [
            'title' => $pack['name'], 'breadcrumbs' => [['Packs', 'packs'], [$pack['name'], null]], 'pack' => $pack, 'items' => $this->items($id),
        ]);
    }

    public function create(): void
    {
        $copy = Request::int('duplicate') ? $this->db->fetch('SELECT * FROM packs WHERE id = ? AND deleted_at IS NULL', [Request::int('duplicate')]) : null;
        $this->form(null, $copy ? array_merge($copy, ['code' => '', 'name' => $copy['name'] . ' (copie)', 'image' => null]) : [], $copy ? $this->items((int) $copy['id']) : []);
    }

    public function edit(int $id): void
    {
        $pack = $this->findOrFail('packs', $id, 'packs', 'Pack');
        $this->form($pack, $pack, $this->items($id));
    }

    private function form(?array $pack, array $values, array $items): void
    {
        $this->view('packs/form', [
            'title' => $pack ? 'Modifier : ' . $pack['name'] : 'Nouveau pack',
            'breadcrumbs' => [['Packs', 'packs'], [$pack ? $pack['name'] : 'Nouveau', null]],
            'pack' => $pack, 'values' => $values, 'items' => $items,
            'taxes' => $this->db->fetchAll('SELECT id, name, rate FROM taxes WHERE is_active = 1 ORDER BY rate DESC'),
        ]);
    }

    public function store(): void
    {
        [$data, $items] = $this->validated(null);
        $data['image'] = $this->uploadImage('packs/create');
        $id = $this->db->transaction(function () use (&$data, $items): int {
            $data['code'] = $data['code'] ?: DocumentNumber::next('pack');
            $data['created_by'] = auth_user()['id'];
            $id = $this->db->insert('packs', $data);
            $this->saveItems($id, $items);
            return $id;
        });
        AuditLog::log('create', 'packs', 'pack', $id, 'Pack créé : ' . $data['code'] . ' ' . $data['name'], null, $data + ['items' => $items]);
        Session::flash('success', 'Pack « ' . $data['name'] . ' » créé.');
        $this->redirect('packs/' . $id);
    }

    public function update(int $id): void
    {
        $before = $this->findOrFail('packs', $id, 'packs', 'Pack');
        [$data, $items] = $this->validated($id);
        if ($data['code'] === '') {
            unset($data['code']);
        }
        $image = $this->uploadImage('packs/' . $id . '/edit');
        if ($image) {
            $data['image'] = $image;
        } elseif (Request::post('remove_image')) {
            $data['image'] = null;
        }
        $this->db->transaction(function () use ($id, $data, $items): void {
            $this->db->update('packs', $data, 'id = :id', ['id' => $id]);
            $this->saveItems($id, $items);
        });
        if (array_key_exists('image', $data)) {
            Uploader::deletePublic($before['image']);
        }
        [$old, $new] = AuditLog::diff($before, $data);
        AuditLog::log('update', 'packs', 'pack', $id, 'Pack modifié : ' . $before['code'], $old, $new + ['items' => $items]);
        Session::flash('success', 'Pack mis à jour.');
        $this->redirect('packs/' . $id);
    }

    /** @return array{0: array, 1: array} */
    private function validated(?int $id): array
    {
        $back = $id ? 'packs/' . $id . '/edit' : 'packs/create';
        $code = mb_strtoupper(Request::string('code'));
        $name = Request::string('name');
        if ($name === '' || mb_strlen($name) > 190) {
            $this->fail('Le nom du pack est obligatoire (190 caractères max.).', $back);
        }
        if ($code !== '' && (!preg_match('/^[A-Z0-9_\-.]{1,40}$/', $code)
            || (int) $this->db->fetchColumn('SELECT COUNT(*) FROM packs WHERE code = ? AND id <> ?', [$code, (int) $id]))) {
            $this->fail('Code invalide ou déjà utilisé.', $back);
        }
        $price = Request::decimal('price');
        $discount = Request::decimal('discount_percent');
        if ($price < 0 || $discount < 0 || $discount > 100) {
            $this->fail('Prix positif et remise entre 0 et 100 % obligatoires.', $back);
        }
        $from = Request::string('available_from');
        $until = Request::string('available_until');
        $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) ? $from : null;
        $until = preg_match('/^\d{4}-\d{2}-\d{2}$/', $until) ? $until : null;
        if ($from && $until && $until < $from) {
            $this->fail('La date de fin de disponibilité précède la date de début.', $back);
        }
        $items = [];
        $raw = (array) ($_POST['items'] ?? []);
        foreach ((array) ($raw['product_id'] ?? []) as $i => $pid) {
            $pid = (int) $pid;
            $qty = Csv::number((string) ($raw['quantity'][$i] ?? ''));
            if ($pid <= 0 || $qty === null || $qty <= 0) {
                continue;
            }
            if (!$this->db->fetchColumn('SELECT id FROM products WHERE id = ? AND deleted_at IS NULL', [$pid])) {
                continue;
            }
            $items[] = ['product_id' => $pid, 'quantity' => round($qty, 3), 'description' => mb_substr(trim((string) ($raw['description'][$i] ?? '')), 0, 255) ?: null];
        }
        if (!$items) {
            $this->fail('Ajoutez au moins un produit ou service au pack.', $back);
        }
        $tax = Request::int('tax_id');
        return [[
            'code' => $code, 'name' => $name, 'description' => Request::string('description') ?: null,
            'price' => round($price, 2), 'discount_percent' => round($discount, 2),
            'tax_id' => $tax && $this->db->fetchColumn('SELECT id FROM taxes WHERE id = ?', [$tax]) ? $tax : (int) Settings::get('default_tax_id', 1),
            'available_from' => $from, 'available_until' => $until,
            'is_custom' => Request::post('is_custom') ? 1 : 0, 'is_active' => Request::post('is_active') ? 1 : 0,
        ], $items];
    }

    private function saveItems(int $packId, array $items): void
    {
        $this->db->query('DELETE FROM pack_items WHERE pack_id = ?', [$packId]);
        foreach ($items as $i => $it) {
            $this->db->insert('pack_items', $it + ['pack_id' => $packId, 'sort_order' => $i]);
        }
    }

    private function uploadImage(string $back): ?string
    {
        if (!Uploader::has('image')) {
            return null;
        }
        try {
            return Uploader::publicImage('image', 'packs', 3072, 900);
        } catch (\Throwable $e) {
            $this->fail('Image : ' . $e->getMessage(), $back);
        }
    }

    public function delete(int $id): void
    {
        $pack = $this->findOrFail('packs', $id, 'packs', 'Pack');
        $suffix = '-DEL' . $id;
        $this->db->update('packs', ['deleted_at' => date('Y-m-d H:i:s'), 'is_active' => 0,
            'code' => mb_substr($pack['code'], 0, 40 - strlen($suffix)) . $suffix], 'id = :id', ['id' => $id]);
        AuditLog::log('delete', 'packs', 'pack', $id, 'Pack supprimé : ' . $pack['code'] . ' ' . $pack['name']);
        Session::flash('success', 'Pack supprimé.');
        $this->redirect('packs');
    }

    /** Recherche JSON (POS, devis). Seuls les packs disponibles aujourd'hui sont proposés. */
    public function lookup(): void
    {
        $q = Request::string('q');
        $rows = $q === '' ? [] : $this->db->fetchAll(
            'SELECT pk.id, pk.code, pk.name, pk.price, t.rate FROM packs pk LEFT JOIN taxes t ON t.id = pk.tax_id
              WHERE pk.deleted_at IS NULL AND pk.is_active = 1
                AND (pk.available_from IS NULL OR pk.available_from <= CURDATE()) AND (pk.available_until IS NULL OR pk.available_until >= CURDATE())
                AND (pk.name LIKE :a OR pk.code LIKE :b) ORDER BY pk.name LIMIT 15',
            ['a' => "%{$q}%", 'b' => "%{$q}%"]
        );
        Response::json(['success' => true, 'items' => array_map(static fn($r) => [
            'id' => (int) $r['id'], 'code' => $r['code'], 'name' => $r['name'], 'price' => (float) $r['price'],
            'tax_rate' => (float) ($r['rate'] ?? 0), 'detail' => money($r['price']) . ' HT',
        ], $rows)]);
    }

    private function items(int $packId): array
    {
        return $this->db->fetchAll(
            'SELECT pi.*, p.code, p.name, p.selling_price, p.is_active, p.deleted_at,
                    IF(p.estimated_cost > 0, p.estimated_cost, p.purchase_price) AS unit_cost, u.symbol
               FROM pack_items pi JOIN products p ON p.id = pi.product_id JOIN units u ON u.id = p.unit_id
              WHERE pi.pack_id = ? ORDER BY pi.sort_order, pi.id',
            [$packId]
        );
    }
}
