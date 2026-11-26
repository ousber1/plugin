<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\ValidationException;

final class CategoryController extends TaxonomyController
{
    protected function table(): string
    {
        return 'categories';
    }

    protected function entity(): string
    {
        return 'category';
    }

    protected function extraRules(): array
    {
        return ['parent_id' => 'nullable|integer|exists:categories,id'];
    }

    protected function extraColumns(array $d, ?int $id): array
    {
        $parent = !empty($d['parent_id']) ? (int) $d['parent_id'] : null;
        if ($parent !== null && $id !== null) {
            // refuse cycles: the parent may not be the category itself or one of its descendants
            $cursor = $parent;
            $guard = 0;
            while ($cursor !== null && $guard++ < 50) {
                if ($cursor === $id) {
                    throw new ValidationException(['parent_id' => __('admin.category_cycle')], $this->request->all());
                }
                $next = Database::instance()->value('SELECT parent_id FROM categories WHERE id = ?', [$cursor]);
                $cursor = $next !== null ? (int) $next : null;
            }
        }
        return ['parent_id' => $parent];
    }

    protected function extraViewData(?array $row): array
    {
        return ['parents' => Database::instance()->fetchAll('SELECT id, name FROM categories' . ($row ? ' WHERE id <> ' . (int) $row['id'] : '') . ' ORDER BY sort_order, name')];
    }
}
