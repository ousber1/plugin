<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\CustomerService;

/**
 * Recherche globale (barre supérieure) : clients, fournisseurs, catalogue,
 * matières, packs et documents par numéro — filtrée selon les permissions.
 */
final class SearchController extends Controller
{
    public function index(): void
    {
        $q = Request::string('q');
        $groups = mb_strlen($q) >= 2 ? $this->search($q, 25) : [];
        if (Request::isAjax()) {
            Response::json(['success' => true, 'query' => $q, 'groups' => $groups]);
        }
        $this->view('search/index', ['title' => 'Recherche', 'q' => $q, 'groups' => $groups]);
    }

    /** @return array<int, array{label: string, icon: string, items: array}> */
    private function search(string $q, int $limit): array
    {
        $like = '%' . $q . '%';
        $out = [];
        $add = static function (string $label, string $icon, array $items) use (&$out): void {
            if ($items) {
                $out[] = ['label' => $label, 'icon' => $icon, 'items' => $items];
            }
        };

        if (can('customers.view')) {
            $add('Clients', 'bi-people', array_map(static fn($r) => [
                'title' => CustomerService::displayName($r), 'subtitle' => trim($r['code'] . ' · ' . ($r['phone'] ?? '') . ' ' . ($r['city'] ?? ''), ' ·'),
                'url' => url('customers/' . $r['id']),
            ], $this->db->fetchAll(
                'SELECT id, code, full_name, company_name, phone, city FROM customers
                  WHERE deleted_at IS NULL AND (full_name LIKE :a OR company_name LIKE :b OR code LIKE :c OR phone LIKE :d OR whatsapp LIKE :e OR email LIKE :f OR ice LIKE :g)
                  ORDER BY is_walk_in, full_name LIMIT ' . $limit,
                ['a' => $like, 'b' => $like, 'c' => $like, 'd' => $like, 'e' => $like, 'f' => $like, 'g' => $like]
            )));
        }
        if (can('products.view')) {
            $add('Produits & services', 'bi-box', array_map(static fn($r) => [
                'title' => $r['name'], 'subtitle' => $r['code'] . ' · ' . money($r['selling_price']) . ' / ' . $r['symbol'],
                'url' => url('products/' . $r['id']),
            ], $this->db->fetchAll(
                'SELECT p.id, p.code, p.name, p.selling_price, u.symbol FROM products p JOIN units u ON u.id = p.unit_id
                  WHERE p.deleted_at IS NULL AND (p.name LIKE :a OR p.code LIKE :b OR p.barcode = :c OR p.name_fr LIKE :d)
                  ORDER BY p.is_active DESC, p.name LIMIT ' . $limit,
                ['a' => $like, 'b' => $like, 'c' => $q, 'd' => $like]
            )));
        }
        if (can('packs.view')) {
            $add('Packs', 'bi-boxes', array_map(static fn($r) => [
                'title' => $r['name'], 'subtitle' => $r['code'] . ' · ' . money($r['price']) . ' HT', 'url' => url('packs/' . $r['id']),
            ], $this->db->fetchAll('SELECT id, code, name, price FROM packs WHERE deleted_at IS NULL AND (name LIKE :a OR code LIKE :b) ORDER BY name LIMIT ' . $limit, ['a' => $like, 'b' => $like])));
        }
        if (can('stock.view')) {
            $add('Matières', 'bi-layers', array_map(static fn($r) => [
                'title' => $r['name'], 'subtitle' => $r['code'] . ' · stock ' . number_fr($r['stock_quantity'], 2) . ' ' . $r['symbol'], 'url' => url('materials/' . $r['id']),
            ], $this->db->fetchAll(
                'SELECT m.id, m.code, m.name, m.stock_quantity, u.symbol FROM materials m JOIN units u ON u.id = m.unit_id
                  WHERE m.deleted_at IS NULL AND (m.name LIKE :a OR m.code LIKE :b OR m.barcode = :c) ORDER BY m.name LIMIT ' . $limit,
                ['a' => $like, 'b' => $like, 'c' => $q]
            )));
        }
        if (can('suppliers.view')) {
            $add('Fournisseurs', 'bi-truck', array_map(static fn($r) => [
                'title' => $r['company'] ?: $r['name'], 'subtitle' => trim($r['code'] . ' · ' . ($r['phone'] ?? ''), ' ·'), 'url' => url('suppliers/' . $r['id']),
            ], $this->db->fetchAll(
                'SELECT id, code, name, company, phone FROM suppliers WHERE deleted_at IS NULL AND (name LIKE :a OR company LIKE :b OR code LIKE :c OR phone LIKE :d) ORDER BY name LIMIT ' . $limit,
                ['a' => $like, 'b' => $like, 'c' => $like, 'd' => $like]
            )));
        }
        // Documents par numéro : liés à la fiche client tant que les modules de documents (Phases 4-5) ne sont pas livrés
        if (can('customers.view') && preg_match('/[0-9]/', $q)) {
            $docs = [];
            if (can('quotations.view')) {
                foreach ($this->db->fetchAll("SELECT number, customer_id, total FROM quotations WHERE deleted_at IS NULL AND number LIKE :a LIMIT 10", ['a' => $like]) as $r) {
                    $docs[] = ['title' => 'Devis ' . $r['number'], 'subtitle' => money($r['total']), 'url' => url('customers/' . $r['customer_id'], ['tab' => 'quotations'])];
                }
            }
            if (can('invoices.view')) {
                foreach ($this->db->fetchAll("SELECT number, customer_id, total FROM invoices WHERE number LIKE :a LIMIT 10", ['a' => $like]) as $r) {
                    $docs[] = ['title' => 'Facture ' . $r['number'], 'subtitle' => money($r['total']), 'url' => url('customers/' . $r['customer_id'], ['tab' => 'invoices'])];
                }
            }
            $add('Documents', 'bi-file-earmark-text', $docs);
        }
        return $out;
    }
}
