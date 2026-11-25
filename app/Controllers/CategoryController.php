<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\AuditLog;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;

/**
 * Catégories du catalogue (avec sous-catégories, couleur, icône, ordre).
 */
final class CategoryController extends Controller
{
    public const TYPES = ['both' => 'Produits et services', 'product' => 'Produits', 'service' => 'Services'];

    public function index(): void
    {
        $rows = $this->db->fetchAll(
            'SELECT c.*, p.name AS parent_name,
                    (SELECT COUNT(*) FROM products pr WHERE pr.category_id = c.id AND pr.deleted_at IS NULL) AS products_count
               FROM categories c LEFT JOIN categories p ON p.id = c.parent_id
              WHERE c.deleted_at IS NULL ORDER BY COALESCE(p.sort_order, c.sort_order), COALESCE(c.parent_id, c.id), c.parent_id IS NOT NULL, c.sort_order, c.name'
        );
        $this->view('categories/index', ['title' => 'Catégories', 'categories' => $rows,
            'parents' => array_values(array_filter($rows, static fn($r) => $r['parent_id'] === null))]);
    }

    public function save(): void
    {
        $id = Request::int('id');
        $data = [
            'name'       => Request::string('name'),
            'parent_id'  => Request::int('parent_id') ?: null,
            'type'       => Request::string('type'),
            'color'      => Request::string('color'),
            'icon'       => Request::string('icon'),
            'sort_order' => Request::int('sort_order'),
            'is_active'  => Request::post('is_active') ? 1 : 0,
        ];
        if ($data['name'] === '' || mb_strlen($data['name']) > 100) {
            $this->fail('Le nom de la catégorie est obligatoire (100 caractères max.).', 'categories');
        }
        if (!isset(self::TYPES[$data['type']])) {
            $data['type'] = 'both';
        }
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $data['color'])) {
            $data['color'] = '#6c757d';
        }
        $data['icon'] = preg_match('/^bi-[a-z0-9\-]{1,40}$/', $data['icon']) ? $data['icon'] : null;
        if ($data['parent_id'] !== null) {
            $parent = $this->db->fetch('SELECT id, parent_id FROM categories WHERE id = ? AND deleted_at IS NULL', [$data['parent_id']]);
            // Un seul niveau de sous-catégories ; pas de boucle
            if (!$parent || $parent['parent_id'] !== null || (int) $data['parent_id'] === $id) {
                $this->fail('Catégorie parente invalide (un seul niveau de sous-catégories).', 'categories');
            }
            if ($id && (int) $this->db->fetchColumn('SELECT COUNT(*) FROM categories WHERE parent_id = ? AND deleted_at IS NULL', [$id]) > 0) {
                $this->fail('Cette catégorie possède des sous-catégories : elle ne peut pas devenir elle-même une sous-catégorie.', 'categories');
            }
        }
        $data['slug'] = $this->slug($data['name'], $id);
        if ($id > 0) {
            $before = $this->findOrFail('categories', $id, 'categories', 'Catégorie');
            $this->db->update('categories', $data, 'id = :id', ['id' => $id]);
            [$old, $new] = AuditLog::diff($before, $data);
            AuditLog::log('update', 'products', 'category', $id, 'Catégorie modifiée : ' . $data['name'], $old, $new);
        } else {
            $id = $this->db->insert('categories', $data);
            AuditLog::log('create', 'products', 'category', $id, 'Catégorie créée : ' . $data['name'], null, $data);
        }
        Session::flash('success', 'Catégorie « ' . $data['name'] . ' » enregistrée.');
        $this->redirect('categories');
    }

    public function delete(int $id): void
    {
        $c = $this->findOrFail('categories', $id, 'categories', 'Catégorie');
        $products = (int) $this->db->fetchColumn('SELECT COUNT(*) FROM products WHERE category_id = ? AND deleted_at IS NULL', [$id]);
        $children = (int) $this->db->fetchColumn('SELECT COUNT(*) FROM categories WHERE parent_id = ? AND deleted_at IS NULL', [$id]);
        if ($products || $children) {
            $this->fail("Impossible : {$products} produit(s) et {$children} sous-catégorie(s) y sont rattachés. Déplacez-les d'abord ou désactivez la catégorie.", 'categories');
        }
        // Suppression logique ; le slug est libéré pour pouvoir être réutilisé
        $this->db->update('categories', ['deleted_at' => date('Y-m-d H:i:s'), 'slug' => mb_substr($c['slug'], 0, 100) . '-del' . $id], 'id = :id', ['id' => $id]);
        AuditLog::log('delete', 'products', 'category', $id, 'Catégorie supprimée : ' . $c['name']);
        Session::flash('success', 'Catégorie supprimée.');
        $this->redirect('categories');
    }

    private function slug(string $name, int $ignoreId): string
    {
        $base = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: 'categorie')), '-') ?: 'categorie';
        $base = substr($base, 0, 100);
        $slug = $base;
        $i = 2;
        while ((int) $this->db->fetchColumn('SELECT COUNT(*) FROM categories WHERE slug = ? AND id <> ?', [$slug, $ignoreId]) > 0) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }
}
