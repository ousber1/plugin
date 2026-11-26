<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\ValidationException;

final class CollectionController extends TaxonomyController
{
    protected function table(): string
    {
        return 'collections';
    }

    protected function entity(): string
    {
        return 'collection';
    }

    protected function extraRules(): array
    {
        return ['starts_at' => 'nullable|date', 'ends_at' => 'nullable|date'];
    }

    protected function extraColumns(array $d, ?int $id): array
    {
        $dt = fn ($v) => $v !== null && $v !== '' ? date('Y-m-d H:i:s', (int) strtotime((string) $v)) : null;
        if (!empty($d['starts_at']) && !empty($d['ends_at']) && strtotime((string) $d['ends_at']) <= strtotime((string) $d['starts_at'])) {
            throw new ValidationException(['ends_at' => __('admin.dates_order')], $this->request->all());
        }
        return ['is_limited' => $this->request->bool('is_limited') ? 1 : 0, 'starts_at' => $dt($d['starts_at'] ?? null), 'ends_at' => $dt($d['ends_at'] ?? null)];
    }
}
