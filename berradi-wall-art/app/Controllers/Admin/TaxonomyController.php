<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Response;
use App\Core\ValidationException;
use App\Services\ActivityLogger;
use App\Services\Admin\CatalogAdmin;
use App\Services\TranslationService;
use App\Services\UploadException;

/**
 * Shared CRUD for categories and collections (FR content + Arabic translation, image, ordering, SEO).
 */
abstract class TaxonomyController extends Controller
{
    /** 'categories' | 'collections' */
    abstract protected function table(): string;

    /** 'category' | 'collection' (translations entity type, routes, lang) */
    abstract protected function entity(): string;

    /** @return array<string,string> extra validation rules */
    protected function extraRules(): array
    {
        return [];
    }

    /** @param array<string,mixed> $d @return array<string,mixed> extra columns */
    protected function extraColumns(array $d, ?int $id): array
    {
        return [];
    }

    /** @return array<string,mixed> */
    protected function extraViewData(?array $row): array
    {
        return [];
    }

    private function routeName(string $suffix): string
    {
        return 'admin.' . $this->table() . '.' . $suffix;
    }

    public function index(): Response
    {
        $rows = Database::instance()->fetchAll(
            'SELECT t.*, (SELECT COUNT(*) FROM products p WHERE p.' . $this->entity() . '_id = t.id AND p.deleted_at IS NULL) AS product_count
             FROM ' . $this->table() . ' t ORDER BY t.sort_order, t.name'
        );
        $ar = [];
        foreach (Database::instance()->fetchAll("SELECT entity_id, value FROM translations WHERE entity_type = ? AND locale = 'ar' AND field = 'name'", [$this->entity()]) as $t) {
            $ar[(int) $t['entity_id']] = $t['value'];
        }
        return $this->view('admin/taxonomy/index', ['rows' => $rows, 'ar' => $ar, 'entity' => $this->entity(), 'table' => $this->table()]);
    }

    public function create(): Response
    {
        return $this->view('admin/taxonomy/form', ['row' => null, 'arv' => [], 'entity' => $this->entity(), 'table' => $this->table()] + $this->extraViewData(null));
    }

    /** @return array<string,mixed> */
    protected function find(string $id): array
    {
        $row = Database::instance()->fetch('SELECT * FROM ' . $this->table() . ' WHERE id = ?', [(int) $id]);
        if ($row === null) {
            throw new HttpException(404);
        }
        return $row;
    }

    public function edit(string $id): Response
    {
        $row = $this->find($id);
        return $this->view('admin/taxonomy/form', [
            'row' => $row, 'arv' => TranslationService::get($this->entity(), (int) $row['id'], 'ar'), 'entity' => $this->entity(), 'table' => $this->table(),
        ] + $this->extraViewData($row));
    }

    private function save(?array $row): int
    {
        $d = $this->validate($this->extraRules() + [
            'name' => 'required|max:150',
            'slug' => 'nullable|slug|max:180',
            'description' => 'nullable|max:5000',
            'sort_order' => 'nullable|integer|between:-10000,10000',
            'seo_title' => 'nullable|max:191',
            'seo_description' => 'nullable|max:320',
            'ar_name' => 'nullable|max:150',
            'ar_description' => 'nullable|max:5000',
            'ar_seo_title' => 'nullable|max:191',
            'ar_seo_description' => 'nullable|max:320',
        ], ['name' => __('admin.name')]);
        $id = $row !== null ? (int) $row['id'] : null;
        $values = [
            'name' => $d['name'],
            'slug' => CatalogAdmin::uniqueSlug($this->table(), (string) (($d['slug'] ?? '') ?: $d['name']), $id),
            'description' => $d['description'],
            'sort_order' => (int) ($d['sort_order'] ?? ($row['sort_order'] ?? 0)),
            'is_active' => $this->request->bool('is_active') ? 1 : 0,
            'is_featured' => $this->request->bool('is_featured') ? 1 : 0,
            'seo_title' => $d['seo_title'],
            'seo_description' => $d['seo_description'],
        ] + $this->extraColumns($d, $id);

        $file = $this->request->file('image');
        if ($file !== null && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try {
                $values['image'] = CatalogAdmin::storeImage($file, $this->table(), 1800)['path'];
            } catch (UploadException $e) {
                throw new ValidationException(['image' => $e->getMessage()], $this->request->all());
            }
        } elseif ($this->request->bool('remove_image')) {
            $values['image'] = null;
        }
        $db = Database::instance();
        if ($id === null) {
            $id = $db->insert($this->table(), $values);
            ActivityLogger::log($this->entity() . '.created', $this->entity(), $id, (string) $d['name']);
        } else {
            $db->update($this->table(), $values, ['id' => $id]);
            if (array_key_exists('image', $values) && $row['image'] !== $values['image']) {
                CatalogAdmin::releaseImage($row['image']);
            }
            ActivityLogger::log($this->entity() . '.updated', $this->entity(), $id, (string) $d['name']);
        }
        $ar = [];
        foreach (CatalogAdmin::TAXONOMY_AR_FIELDS as $f) {
            $ar[$f] = $d['ar_' . $f] ?? null;
        }
        TranslationService::save($this->entity(), $id, 'ar', $ar);
        \App\Services\Seo\SitemapService::forget();
        return $id;
    }

    public function store(): Response
    {
        $id = $this->save(null);
        $this->flash('success', __('common.created'));
        return $this->redirectRoute($this->routeName('edit'), ['id' => $id]);
    }

    public function update(string $id): Response
    {
        $this->save($this->find($id));
        $this->flash('success', __('common.saved'));
        return $this->redirectRoute($this->routeName('index'));
    }

    /** Save a new order (ids in display order). */
    public function reorder(): Response
    {
        $ids = array_values(array_filter(array_map('intval', (array) $this->request->input('order', []))));
        $db = Database::instance();
        $db->transaction(function (Database $db) use ($ids): void {
            foreach ($ids as $i => $id) {
                $db->update($this->table(), ['sort_order' => ($i + 1) * 10], ['id' => $id]);
            }
        });
        ActivityLogger::log($this->entity() . '.reordered', $this->entity(), null, implode(',', $ids));
        if ($this->request->isAjax()) {
            return $this->json(['ok' => true]);
        }
        $this->flash('success', __('admin.order_saved'));
        return $this->redirectRoute($this->routeName('index'));
    }

    public function destroy(string $id): Response
    {
        $row = $this->find($id);
        $db = Database::instance();
        $products = (int) $db->value('SELECT COUNT(*) FROM products WHERE ' . $this->entity() . '_id = ? AND deleted_at IS NULL', [(int) $row['id']]);
        if ($products > 0 && !$this->request->bool('confirm_detach')) {
            $this->flash('danger', __('admin.taxonomy_has_products', ['n' => $products]));
            return $this->redirectRoute($this->routeName('index'));
        }
        $db->transaction(function (Database $db) use ($row): void {
            $db->query("DELETE FROM translations WHERE entity_type = ? AND entity_id = ?", [$this->entity(), (int) $row['id']]);
            $db->delete($this->table(), ['id' => (int) $row['id']]); // products keep existing (FK SET NULL)
        });
        CatalogAdmin::releaseImage($row['image']);
        ActivityLogger::log($this->entity() . '.deleted', $this->entity(), (int) $row['id'], (string) $row['name']);
        $this->flash('success', __('common.deleted'));
        return $this->redirectRoute($this->routeName('index'));
    }
}
