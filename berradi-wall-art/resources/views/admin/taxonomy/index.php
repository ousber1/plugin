<?php
$this->layout('layouts/admin', ['title' => __('admin.nav_' . $table)]);
$public = fn (array $r) => $entity === 'category' ? route('shop.category', ['slug' => $r['slug']]) : route('collections.show', ['slug' => $r['slug']]);
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <p class="text-muted-bwa mb-0 small"><?= e(__('admin.reorder_help')) ?></p>
    <a class="btn btn-bwa" href="<?= e(route('admin.' . $table . '.create')) ?>"><i class="bi bi-plus-lg me-1"></i><?= e(__('admin.new_' . $entity)) ?></a>
</div>
<div class="admin-card">
    <?php if ($rows === []): ?>
        <div class="card-body"><div class="empty-state"><i class="bi bi-tags"></i><?= e(__('admin.no_' . $table)) ?></div></div>
    <?php else: ?>
        <form method="post" action="<?= e(route('admin.' . $table . '.reorder')) ?>" data-sortable-form>
            <?= csrf_field() ?>
            <div class="table-responsive">
                <table class="table table-bwa">
                    <thead><tr><th style="width:36px"></th><th></th><th><?= e(__('admin.name')) ?></th><th><?= e(__('admin.arabic_version')) ?></th><?php if ($entity === 'category'): ?><th><?= e(__('admin.parent')) ?></th><?php else: ?><th><?= e(__('admin.dates')) ?></th><?php endif; ?><th><?= e(__('admin.nav_products')) ?></th><th><?= e(__('admin.status')) ?></th><th></th></tr></thead>
                    <tbody data-sortable>
                    <?php $names = array_column($rows, 'name', 'id'); ?>
                    <?php foreach ($rows as $r): ?>
                        <tr class="sortable-row" draggable="true">
                            <td><i class="bi bi-grip-vertical drag-handle" aria-hidden="true"></i><input type="hidden" name="order[]" value="<?= (int) $r['id'] ?>"></td>
                            <td><?php if ($r['image']): ?><img class="thumb-sm" src="<?= e(upload_url($r['image'])) ?>" alt="" loading="lazy"><?php else: ?><span class="thumb-sm"><i class="bi bi-image"></i></span><?php endif; ?></td>
                            <td><a class="fw-semibold" href="<?= e(route('admin.' . $table . '.edit', ['id' => $r['id']])) ?>"><?= e($r['name']) ?></a><div class="small text-muted-bwa">/<?= e($r['slug']) ?></div></td>
                            <td dir="rtl" lang="ar"><?= isset($ar[(int) $r['id']]) && $ar[(int) $r['id']] !== '' ? e($ar[(int) $r['id']]) : '<span class="text-muted-bwa small" dir="auto">' . e(__('admin.missing')) . '</span>' ?></td>
                            <?php if ($entity === 'category'): ?>
                                <td class="small"><?= $r['parent_id'] ? e($names[(int) $r['parent_id']] ?? '—') : '—' ?></td>
                            <?php else: ?>
                                <td class="small text-nowrap"><?= $r['starts_at'] ? e(format_date($r['starts_at'])) : '—' ?> → <?= $r['ends_at'] ? e(format_date($r['ends_at'])) : '—' ?><?= (int) $r['is_limited'] ? ' <span class="badge text-bg-dark">' . e(__('admin.limited')) . '</span>' : '' ?></td>
                            <?php endif; ?>
                            <td><?= (int) $r['product_count'] ?></td>
                            <td>
                                <span class="badge <?= (int) $r['is_active'] ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= e(__((int) $r['is_active'] ? 'admin.active' : 'admin.inactive')) ?></span>
                                <?= (int) $r['is_featured'] ? '<i class="bi bi-star-fill text-gold ms-1" title="' . e(__('admin.featured')) . '"></i>' : '' ?>
                            </td>
                            <td class="text-end text-nowrap">
                                <a class="btn btn-sm btn-link" href="<?= e($public($r)) ?>" target="_blank" rel="noopener" aria-label="<?= e(__('admin.view_store')) ?>"><i class="bi bi-box-arrow-up-right"></i></a>
                                <a class="btn btn-sm btn-outline-bwa" href="<?= e(route('admin.' . $table . '.edit', ['id' => $r['id']])) ?>"><?= e(__('common.edit')) ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="p-3 border-top d-flex justify-content-end"><button class="btn btn-outline-bwa" type="submit" data-sortable-save><i class="bi bi-arrow-down-up me-1"></i><?= e(__('admin.save_order')) ?></button></div>
        </form>
    <?php endif; ?>
</div>
