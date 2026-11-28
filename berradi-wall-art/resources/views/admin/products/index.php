<?php
$this->layout('layouts/admin', ['title' => __('admin.nav_products')]);
$manage = can('products.manage');
$sortLink = function (string $col, string $label) use ($filters): string {
    $dir = $filters['sort'] === $col && strtolower($filters['dir']) === 'desc' ? 'asc' : 'desc';
    $q = array_filter(array_merge($filters, ['sort' => $col, 'dir' => $dir, 'page' => null]), fn ($v) => $v !== '' && $v !== null && $v !== 0);
    $arrow = $filters['sort'] === $col ? (strtolower($filters['dir']) === 'desc' ? ' ↓' : ' ↑') : '';
    return '<a class="text-reset" href="?' . e(http_build_query($q)) . '">' . e($label) . $arrow . '</a>';
};
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
    <span class="text-muted-bwa small"><?= e(__('admin.n_products', ['n' => $products->total])) ?></span>
    <div class="page-actions">
        <?php if ($manage): ?>
            <a class="btn btn-sm btn-outline-bwa" href="<?= e(route('admin.options.index')) ?>"><i class="bi bi-sliders me-1"></i><?= e(__('admin.nav_options')) ?></a>
            <a class="btn btn-sm btn-outline-bwa" href="<?= e(route('admin.products.templates')) ?>"><i class="bi bi-gift me-1"></i><?= e(__('templates.button')) ?></a>
            <a class="btn btn-sm btn-bwa" href="<?= e(route('admin.products.create')) ?>"><i class="bi bi-plus-lg me-1"></i><?= e(__('admin.qa_add_product')) ?></a>
        <?php endif; ?>
    </div>
</div>
<div class="admin-card">
    <div class="card-header">
        <form class="filter-bar" method="get">
            <input type="search" class="form-control form-control-sm" name="q" value="<?= e($filters['q']) ?>" placeholder="<?= e(__('admin.search_products')) ?>">
            <select class="form-select form-select-sm" name="category"><option value=""><?= e(__('shop.categories')) ?></option><?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>" <?= $filters['category'] === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select>
            <select class="form-select form-select-sm" name="collection"><option value=""><?= e(__('nav.collections')) ?></option><?php foreach ($collections as $c): ?><option value="<?= (int) $c['id'] ?>" <?= $filters['collection'] === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select>
            <select class="form-select form-select-sm" name="type"><option value=""><?= e(__('shop.all_types')) ?></option><?php foreach (\App\Services\CatalogService::TYPES as $t): ?><option value="<?= e($t) ?>" <?= $filters['type'] === $t ? 'selected' : '' ?>><?= e(__('shop.type_' . $t)) ?></option><?php endforeach; ?></select>
            <select class="form-select form-select-sm" name="status"><option value=""><?= e(__('admin.all_statuses')) ?></option><option value="active" <?= $filters['status'] === 'active' ? 'selected' : '' ?>><?= e(__('admin.active')) ?></option><option value="inactive" <?= $filters['status'] === 'inactive' ? 'selected' : '' ?>><?= e(__('admin.inactive')) ?></option></select>
            <select class="form-select form-select-sm" name="flag"><option value=""><?= e(__('shop.highlights')) ?></option><?php foreach (['featured', 'best_seller', 'new', 'promo', 'customizable', 'low_stock'] as $fl): ?><option value="<?= e($fl) ?>" <?= $filters['flag'] === $fl ? 'selected' : '' ?>><?= e(__('admin.flag_' . $fl)) ?></option><?php endforeach; ?></select>
            <button class="btn btn-sm btn-outline-bwa" type="submit"><?= e(__('common.filter')) ?></button>
            <?php if (array_filter([$filters['q'], $filters['category'], $filters['collection'], $filters['type'], $filters['status'], $filters['flag']])): ?><a class="small" href="<?= e(route('admin.products.index')) ?>"><?= e(__('shop.reset')) ?></a><?php endif; ?>
        </form>
    </div>
    <form method="post" action="<?= e($manage ? route('admin.products.bulk') : '#') ?>" data-bulk-form data-delete-confirm="<?= e(__('admin.bulk_delete_confirm')) ?>">
        <?= csrf_field() ?>
        <?php if ($manage): ?>
            <div class="bulk-bar" data-bulk-bar>
                <span class="small"><span data-bulk-count>0</span> <?= e(__('admin.selected')) ?></span>
                <select class="form-select form-select-sm w-auto" name="bulk_action" required>
                    <option value=""><?= e(__('admin.bulk_choose')) ?></option>
                    <?php foreach (['activate', 'deactivate', 'feature', 'unfeature', 'category', 'delete'] as $a): ?><option value="<?= $a ?>"><?= e(__('admin.bulk_' . $a)) ?></option><?php endforeach; ?>
                </select>
                <select class="form-select form-select-sm w-auto" name="bulk_category"><option value="0"><?= e(__('admin.no_category')) ?></option><?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select>
                <button class="btn btn-sm btn-bwa" type="submit"><?= e(__('admin.apply')) ?></button>
            </div>
        <?php endif; ?>
        <div class="table-responsive">
            <table class="table table-bwa align-middle">
                <thead><tr>
                    <?php if ($manage): ?><th style="width:36px"><input class="form-check-input" type="checkbox" data-check-all aria-label="<?= e(__('admin.select_all')) ?>"></th><?php endif; ?>
                    <th style="width:60px"></th>
                    <th><?= $sortLink('title', __('admin.p_title')) ?></th>
                    <th><?= e(__('admin.category')) ?></th>
                    <th class="text-end"><?= $sortLink('selling_price', __('admin.p_price')) ?></th>
                    <th class="text-end"><?= $sortLink('stock_quantity', __('admin.stock')) ?></th>
                    <th class="text-end"><?= $sortLink('views', __('admin.views')) ?></th>
                    <th><?= e(__('admin.status')) ?></th>
                    <th class="text-end"><?= e(__('common.actions')) ?></th>
                </tr></thead>
                <tbody>
                <?php foreach ($products->items as $p): ?>
                    <tr>
                        <?php if ($manage): ?><td><input class="form-check-input" type="checkbox" name="ids[]" value="<?= (int) $p['id'] ?>" data-bulk-item aria-label="<?= e($p['title']) ?>"></td><?php endif; ?>
                        <td><?php if ($p['main_image']): ?><img class="thumb-sm" src="<?= e(upload_url($p['main_image'])) ?>" alt="" loading="lazy"><?php else: ?><span class="thumb-sm"><i class="bi bi-image"></i></span><?php endif; ?></td>
                        <td>
                            <?php if ($manage): ?><a class="fw-semibold" href="<?= e(route('admin.products.edit', ['id' => $p['id']])) ?>"><?= e($p['title']) ?></a><?php else: ?><span class="fw-semibold"><?= e($p['title']) ?></span><?php endif; ?>
                            <div class="small text-muted-bwa"><?= e($p['sku']) ?> · <?= e(__('shop.type_' . $p['product_type'])) ?><?= (int) $p['variant_count'] > 0 ? ' · ' . e(__('admin.n_variants', ['n' => (int) $p['variant_count']])) : '' ?></div>
                            <div class="d-flex gap-1 mt-1">
                                <?php if ((int) $p['is_featured']): ?><span class="badge-status"><?= e(__('admin.flag_featured')) ?></span><?php endif; ?>
                                <?php if ((int) $p['is_best_seller']): ?><span class="badge-status is-dark"><?= e(__('admin.flag_best_seller')) ?></span><?php endif; ?>
                                <?php if ((int) $p['is_new']): ?><span class="badge-status"><?= e(__('admin.flag_new')) ?></span><?php endif; ?>
                                <?php if ((int) $p['is_customizable']): ?><span class="badge-status"><i class="bi bi-camera"></i></span><?php endif; ?>
                            </div>
                        </td>
                        <td class="small"><?= e($p['category_name'] ?? '—') ?></td>
                        <td class="text-end text-nowrap">
                            <?= $p['min_variant'] !== null ? '<small class="text-muted-bwa">' . e(__('shop.from')) . '</small> ' . e(money((string) $p['min_variant'])) : e(money((string) $p['selling_price'])) ?>
                            <?php if ($p['promo_price'] !== null): ?><div class="small text-danger"><?= e(__('admin.flag_promo')) ?> <?= e(money((string) $p['promo_price'])) ?></div><?php endif; ?>
                        </td>
                        <td class="text-end"><?= (int) $p['track_stock'] ? '<span class="' . ((int) $p['stock_quantity'] <= 5 ? 'text-danger fw-semibold' : '') . '">' . (int) $p['stock_quantity'] . '</span>' : '<span class="text-muted-bwa small">' . e(__('admin.made_to_order')) . '</span>' ?></td>
                        <td class="text-end small"><?= (int) $p['views'] ?></td>
                        <td><span class="badge-status <?= (int) $p['is_active'] ? 'is-success' : 'is-muted' ?>"><?= e((int) $p['is_active'] ? __('admin.active') : __('admin.inactive')) ?></span></td>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-outline-bwa" href="<?= e(route('product.show', ['slug' => $p['slug']])) ?>" target="_blank" rel="noopener" title="<?= e(__('admin.view_store')) ?>"><i class="bi bi-eye"></i></a>
                            <?php if ($manage): ?><a class="btn btn-sm btn-outline-bwa" href="<?= e(route('admin.products.edit', ['id' => $p['id']])) ?>" title="<?= e(__('account.edit')) ?>"><i class="bi bi-pencil"></i></a><?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($products->items === []): ?>
                    <tr><td colspan="9"><div class="empty-state"><i class="bi bi-image"></i><?= e(__('common.no_results')) ?></div></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </form>
    <?php if ($products->hasPages()): ?><div class="p-3 d-flex justify-content-between align-items-center"><span class="small text-muted-bwa"><?= $products->from() ?>–<?= $products->to() ?> / <?= $products->total ?></span><?= $products->links() ?></div><?php endif; ?>
</div>
