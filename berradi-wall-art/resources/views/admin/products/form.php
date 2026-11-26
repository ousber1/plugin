<?php
$isEdit = $product !== null;
$p = $product ?? [];
$this->layout('layouts/admin', ['title' => $isEdit ? __('admin.edit_product') : __('admin.qa_add_product')]);
$v = fn (string $k, mixed $default = '') => old($k, $p[$k] ?? $default);
$dtl = fn (?string $d) => $d ? date('Y-m-d\TH:i', (int) strtotime($d)) : '';
$checked = fn (string $k, int $default = 0) => in_array(old($k, (string) ($p[$k] ?? $default)), [1, '1', 'on', true], true) ? 'checked' : '';
$catOptions = ['' => __('admin.no_category')];
foreach ($categories as $c) {
    $catOptions[$c['id']] = ($c['parent_id'] ? '— ' : '') . $c['name'];
}
$colOptions = ['' => '—'];
foreach ($collections as $c) {
    $colOptions[$c['id']] = $c['name'];
}
$types = [];
foreach (\App\Services\CatalogService::TYPES as $t) {
    $types[$t] = __('shop.type_' . $t);
}
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <a class="small text-muted-bwa" href="<?= e(route('admin.products.index')) ?>"><i class="bi bi-arrow-<?= is_rtl() ? 'right' : 'left' ?> me-1"></i><?= e(__('admin.nav_products')) ?></a>
    <?php if ($isEdit): ?>
        <div class="page-actions">
            <a class="btn btn-sm btn-outline-bwa" href="<?= e(route('product.show', ['slug' => $p['slug']])) ?>" target="_blank" rel="noopener"><i class="bi bi-eye me-1"></i><?= e(__('admin.view_store')) ?></a>
            <form method="post" action="<?= e(route('admin.products.duplicate', ['id' => $p['id']])) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-outline-bwa" type="submit"><i class="bi bi-copy me-1"></i><?= e(__('admin.duplicate')) ?></button></form>
            <form method="post" action="<?= e(route('admin.products.destroy', ['id' => $p['id']])) ?>" data-confirm="<?= e($orderCount > 0 ? __('admin.delete_product_orders', ['n' => $orderCount]) : __('common.confirm_delete')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button></form>
        </div>
    <?php endif; ?>
</div>

<ul class="nav settings-tabs border-bottom mb-4 flex-nowrap overflow-auto" role="tablist" data-hash-tabs>
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#general" type="button"><?= e(__('admin.tab_general')) ?></button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#pricing" type="button"><?= e(__('admin.tab_pricing')) ?></button></li>
    <?php if ($isEdit): ?>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#images" type="button"><?= e(__('admin.tab_images')) ?> (<?= count($images) ?>)</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#variants" type="button"><?= e(__('admin.tab_variants')) ?> (<?= count($variants) ?>)</button></li>
    <?php endif; ?>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#options" type="button"><?= e(__('admin.tab_options')) ?></button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#seo" type="button"><?= e(__('admin.tab_seo')) ?></button></li>
</ul>

<form method="post" action="<?= e($isEdit ? route('admin.products.update', ['id' => $p['id']]) : route('admin.products.store')) ?>" enctype="multipart/form-data" novalidate id="product-form">
    <?= csrf_field() ?>
    <input type="hidden" name="tab" value="general" data-current-tab>
</form>

<div class="tab-content">
    <!-- ======================== general ======================== -->
    <div class="tab-pane fade show active" id="general" role="tabpanel">
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="admin-card"><div class="card-body">
                    <?= $this->insert('partials/field', ['name' => 'title', 'label' => __('admin.p_title'), 'value' => $v('title'), 'required' => true, 'form' => 'product-form']) ?>
                    <div class="row">
                        <div class="col-md-6"><?= $this->insert('partials/field', ['name' => 'slug', 'label' => __('admin.slug'), 'value' => $v('slug'), 'help' => __('admin.slug_auto'), 'form' => 'product-form']) ?></div>
                        <div class="col-md-6"><?= $this->insert('partials/field', ['name' => 'sku', 'label' => __('product.sku'), 'value' => $v('sku'), 'help' => __('admin.sku_auto'), 'form' => 'product-form']) ?></div>
                    </div>
                    <?= $this->insert('partials/field', ['name' => 'short_description', 'label' => __('admin.p_short'), 'type' => 'textarea', 'rows' => 2, 'value' => $v('short_description'), 'form' => 'product-form', 'attrs' => 'maxlength="500"']) ?>
                    <?= $this->insert('partials/field', ['name' => 'description', 'label' => __('product.description'), 'type' => 'textarea', 'rows' => 8, 'value' => $v('description'), 'form' => 'product-form']) ?>
                    <?php if (!$isEdit): ?>
                        <label class="form-label"><?= e(__('admin.tab_images')) ?></label>
                        <input class="form-control" type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple form="product-form">
                        <div class="form-text"><?= e(__('admin.images_help')) ?></div>
                    <?php endif; ?>
                </div></div>
            </div>
            <div class="col-lg-4">
                <div class="admin-card"><div class="card-body">
                    <?= $this->insert('partials/field', ['name' => 'is_active', 'label' => __('admin.p_active'), 'type' => 'checkbox', 'value' => $v('is_active', 1), 'form' => 'product-form']) ?>
                    <?= $this->insert('partials/field', ['name' => 'product_type', 'label' => __('shop.product_type'), 'type' => 'select', 'options' => $types, 'value' => $v('product_type', 'canvas'), 'form' => 'product-form']) ?>
                    <?= $this->insert('partials/field', ['name' => 'category_id', 'label' => __('admin.category'), 'type' => 'select', 'options' => $catOptions, 'value' => $v('category_id'), 'form' => 'product-form']) ?>
                    <?= $this->insert('partials/field', ['name' => 'collection_id', 'label' => __('admin.collection'), 'type' => 'select', 'options' => $colOptions, 'value' => $v('collection_id'), 'form' => 'product-form']) ?>
                    <div class="form-section mt-2 pt-3">
                        <?php foreach (['is_featured', 'is_best_seller', 'is_new', 'is_customizable'] as $flag): ?>
                            <?= $this->insert('partials/field', ['name' => $flag, 'label' => __('admin.p_' . $flag), 'type' => 'checkbox', 'value' => $v($flag, 0), 'form' => 'product-form']) ?>
                        <?php endforeach; ?>
                    </div>
                    <?= $this->insert('partials/field', ['name' => 'sort_order', 'label' => __('admin.sort_order'), 'type' => 'number', 'value' => $v('sort_order', 0), 'form' => 'product-form']) ?>
                </div></div>
            </div>
        </div>
    </div>

    <!-- ======================== pricing & stock ======================== -->
    <div class="tab-pane fade" id="pricing" role="tabpanel">
        <div class="admin-card"><div class="card-body">
            <div class="row">
                <div class="col-md-3"><?= $this->insert('partials/field', ['name' => 'selling_price', 'label' => __('admin.p_selling_price'), 'value' => $v('selling_price', '0.00'), 'required' => true, 'help' => count($variants) > 0 ? __('admin.price_from_variants') : null, 'form' => 'product-form', 'attrs' => 'inputmode="decimal"']) ?></div>
                <div class="col-md-3"><?= $this->insert('partials/field', ['name' => 'base_price', 'label' => __('admin.p_base_price'), 'value' => $v('base_price'), 'form' => 'product-form', 'attrs' => 'inputmode="decimal"']) ?></div>
                <div class="col-md-3"><?= $this->insert('partials/field', ['name' => 'cost_price', 'label' => __('admin.p_cost_price'), 'value' => $v('cost_price', '0.00'), 'help' => __('admin.cost_help'), 'form' => 'product-form', 'attrs' => 'inputmode="decimal"']) ?></div>
                <div class="col-md-3"><?php if ($isEdit && (float) $p['selling_price'] > 0): ?><label class="form-label"><?= e(__('admin.margin')) ?></label><div class="form-control-plaintext fw-semibold"><?= e((string) round((1 - (float) $p['cost_price'] / ((float) $p['selling_price'] / (1 + (float) setting('tax_default_rate', 20) / 100))) * 100)) ?> %</div><?php endif; ?></div>
            </div>
            <div class="form-section">
                <h3><?= e(__('admin.promotion')) ?></h3>
                <div class="row">
                    <div class="col-md-4"><?= $this->insert('partials/field', ['name' => 'promo_price', 'label' => __('admin.p_promo_price'), 'value' => $v('promo_price'), 'help' => __('admin.promo_help'), 'form' => 'product-form', 'attrs' => 'inputmode="decimal"']) ?></div>
                    <div class="col-md-4"><?= $this->insert('partials/field', ['name' => 'promo_starts_at', 'label' => __('admin.starts_at'), 'type' => 'datetime-local', 'value' => old('promo_starts_at', $dtl($p['promo_starts_at'] ?? null)), 'form' => 'product-form']) ?></div>
                    <div class="col-md-4"><?= $this->insert('partials/field', ['name' => 'promo_ends_at', 'label' => __('admin.ends_at'), 'type' => 'datetime-local', 'value' => old('promo_ends_at', $dtl($p['promo_ends_at'] ?? null)), 'form' => 'product-form']) ?></div>
                </div>
            </div>
            <div class="form-section">
                <h3><?= e(__('admin.stock')) ?></h3>
                <div class="row">
                    <div class="col-md-4 pt-md-4"><?= $this->insert('partials/field', ['name' => 'track_stock', 'label' => __('admin.p_track_stock'), 'type' => 'checkbox', 'value' => $v('track_stock', 0), 'form' => 'product-form']) ?></div>
                    <div class="col-md-4"><?= $this->insert('partials/field', ['name' => 'stock_quantity', 'label' => __('admin.stock'), 'type' => 'number', 'value' => $v('stock_quantity', 0), 'help' => __('admin.stock_help'), 'form' => 'product-form', 'attrs' => 'min="0"']) ?></div>
                </div>
            </div>
        </div></div>
    </div>

    <?php if ($isEdit): ?>
    <!-- ======================== images ======================== -->
    <div class="tab-pane fade" id="images" role="tabpanel">
        <div class="admin-card"><div class="card-body">
            <form method="post" action="<?= e(route('admin.products.images', ['id' => $p['id']])) ?>" enctype="multipart/form-data" class="d-flex flex-wrap gap-2 align-items-end mb-4">
                <?= csrf_field() ?>
                <div><label class="form-label"><?= e(__('admin.add_images')) ?></label><input class="form-control" type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple required></div>
                <button class="btn btn-bwa" type="submit"><i class="bi bi-upload me-1"></i><?= e(__('admin.upload')) ?></button>
                <div class="form-text w-100"><?= e(__('admin.images_help')) ?></div>
            </form>
            <?php if ($images === []): ?>
                <div class="empty-state"><i class="bi bi-images"></i><?= e(__('admin.no_images')) ?></div>
            <?php else: ?>
                <div class="gallery-admin">
                    <?php foreach ($images as $i => $img): ?>
                        <div class="gimg<?= $p['main_image'] === $img['path'] ? ' is-main' : '' ?>">
                            <img src="<?= e(upload_url($img['thumb_path'] ?: $img['path'])) ?>" alt="<?= e((string) $img['alt']) ?>" loading="lazy">
                            <div class="gactions">
                                <?php foreach ([['main', 'bi-star' . ($p['main_image'] === $img['path'] ? '-fill text-gold' : ''), __('admin.set_main')], ['up', 'bi-arrow-' . (is_rtl() ? 'right' : 'left'), __('admin.move_up')], ['down', 'bi-arrow-' . (is_rtl() ? 'left' : 'right'), __('admin.move_down')], ['delete', 'bi-trash', __('account.delete_now')]] as [$act, $icon, $label]): ?>
                                    <form method="post" action="<?= e(route('admin.products.image', ['id' => $p['id'], 'imageId' => $img['id']])) ?>" <?= $act === 'delete' ? 'data-confirm="' . e(__('common.confirm_delete')) . '"' : '' ?>>
                                        <?= csrf_field() ?><input type="hidden" name="action" value="<?= $act ?>">
                                        <button type="submit" title="<?= e($label) ?>" aria-label="<?= e($label) ?>"><i class="bi <?= e($icon) ?>"></i></button>
                                    </form>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div></div>
    </div>

    <!-- ======================== variants ======================== -->
    <div class="tab-pane fade" id="variants" role="tabpanel">
        <?php if ($variants !== []): ?>
            <form method="post" action="<?= e(route('admin.products.variants', ['id' => $p['id']])) ?>" class="admin-card mb-4">
                <?= csrf_field() ?>
                <div class="table-responsive">
                    <table class="table table-bwa variant-table">
                        <thead><tr><th><?= e(__('admin.default')) ?></th><th><?= e(__('admin.name')) ?></th><th><?= e(__('product.sku')) ?></th><th><?= e(__('admin.p_price')) ?></th><th><?= e(__('admin.p_promo_price')) ?></th><th><?= e(__('admin.p_cost_price')) ?></th><th><?= e(__('admin.stock')) ?></th><th><?= e(__('admin.sort_order')) ?></th><th><?= e(__('admin.active')) ?></th><th><?= e(__('account.delete_now')) ?></th></tr></thead>
                        <tbody>
                        <?php foreach ($variants as $vr): $k = 'variants[' . (int) $vr['id'] . ']'; ?>
                            <tr>
                                <td><input class="form-check-input" type="radio" name="default_variant" value="<?= (int) $vr['id'] ?>" <?= (int) $vr['is_default'] ? 'checked' : '' ?>></td>
                                <td><input class="form-control" name="<?= $k ?>[name]" value="<?= e($vr['name']) ?>"><input type="hidden" name="<?= $k ?>[sku]" value="<?= e($vr['sku']) ?>"></td>
                                <td class="small text-muted-bwa text-nowrap"><?= e($vr['sku']) ?></td>
                                <td><input class="form-control" name="<?= $k ?>[price]" value="<?= e($vr['price']) ?>" inputmode="decimal" style="width:100px"></td>
                                <td><input class="form-control" name="<?= $k ?>[promo_price]" value="<?= e((string) $vr['promo_price']) ?>" inputmode="decimal" style="width:100px"></td>
                                <td><input class="form-control" name="<?= $k ?>[cost_price]" value="<?= e($vr['cost_price']) ?>" inputmode="decimal" style="width:100px"></td>
                                <td><input class="form-control" type="number" min="0" name="<?= $k ?>[stock_quantity]" value="<?= (int) $vr['stock_quantity'] ?>" style="width:80px"></td>
                                <td><input class="form-control" type="number" name="<?= $k ?>[sort_order]" value="<?= (int) $vr['sort_order'] ?>" style="width:70px"></td>
                                <td><input class="form-check-input" type="checkbox" name="<?= $k ?>[is_active]" value="1" <?= (int) $vr['is_active'] ? 'checked' : '' ?>></td>
                                <td><input class="form-check-input" type="checkbox" name="<?= $k ?>[delete]" value="1" aria-label="<?= e(__('account.delete_now')) ?>"></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="p-3 border-top d-flex justify-content-between align-items-center"><span class="small text-muted-bwa"><?= e(__('admin.variants_help')) ?></span><button class="btn btn-bwa" type="submit"><?= e(__('common.save')) ?></button></div>
            </form>
        <?php endif; ?>
        <div class="row g-4">
            <div class="col-xl-7">
                <form method="post" action="<?= e(route('admin.products.variants.generate', ['id' => $p['id']])) ?>" class="admin-card h-100">
                    <?= csrf_field() ?>
                    <div class="card-header"><h2><i class="bi bi-magic me-2 text-gold"></i><?= e(__('admin.generate_variants')) ?></h2></div>
                    <div class="card-body">
                        <p class="small text-muted-bwa"><?= e(__('admin.generate_help')) ?></p>
                        <span class="filter-title"><?= e(__('builder.size')) ?></span>
                        <div class="d-flex flex-wrap gap-2 mb-2"><?php foreach ($sizes as $s): ?><label class="chip"><input type="checkbox" name="g_sizes[]" value="<?= e($s['code']) ?>"><span><?= e($s['name']) ?></span></label><?php endforeach; ?></div>
                        <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="g_landscape" value="1" id="g_landscape"><label class="form-check-label small" for="g_landscape"><?= e(__('admin.landscape_sizes')) ?></label></div>
                        <span class="filter-title"><?= e(__('builder.support')) ?></span>
                        <div class="d-flex flex-wrap gap-2 mb-3"><?php foreach ($materials as $m): ?><label class="chip"><input type="checkbox" name="g_materials[]" value="<?= (int) $m['id'] ?>"><span><?= e($m['name']) ?></span></label><?php endforeach; ?></div>
                        <span class="filter-title"><?= e(__('builder.frame')) ?></span>
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            <label class="chip"><input type="checkbox" name="g_frames[]" value="none" checked><span><?= e(__('builder.no_frame')) ?></span></label>
                            <?php foreach ($frames as $f): ?><label class="chip"><input type="checkbox" name="g_frames[]" value="<?= (int) $f['id'] ?>"><span><?= e($f['name']) ?></span></label><?php endforeach; ?>
                        </div>
                        <div class="d-flex align-items-end gap-3">
                            <div><label class="form-label" for="g_panels"><?= e(__('product.panels')) ?></label><select class="form-select" id="g_panels" name="g_panels"><?php for ($i = 1; $i <= 5; $i++): ?><option value="<?= $i ?>"><?= $i ?></option><?php endfor; ?></select></div>
                            <button class="btn btn-bwa" type="submit"><?= e(__('admin.generate')) ?></button>
                        </div>
                    </div>
                </form>
            </div>
            <div class="col-xl-5">
                <form method="post" action="<?= e(route('admin.products.variants.add', ['id' => $p['id']])) ?>" class="admin-card h-100" novalidate>
                    <?= csrf_field() ?>
                    <div class="card-header"><h2><?= e(__('admin.add_variant')) ?></h2></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-6"><?= $this->insert('partials/field', ['name' => 'v_width', 'label' => __('builder.width'), 'attrs' => 'inputmode="decimal"']) ?></div>
                            <div class="col-6"><?= $this->insert('partials/field', ['name' => 'v_height', 'label' => __('builder.height'), 'attrs' => 'inputmode="decimal"']) ?></div>
                            <div class="col-6"><?= $this->insert('partials/field', ['name' => 'v_material', 'label' => __('builder.support'), 'type' => 'select', 'options' => array_column($materials, 'name', 'id')]) ?></div>
                            <div class="col-6"><?= $this->insert('partials/field', ['name' => 'v_frame', 'label' => __('builder.frame'), 'type' => 'select', 'options' => ['' => __('builder.no_frame')] + array_column($frames, 'name', 'id')]) ?></div>
                            <div class="col-6"><?= $this->insert('partials/field', ['name' => 'v_price', 'label' => __('admin.p_price'), 'attrs' => 'inputmode="decimal"']) ?></div>
                            <div class="col-6"><?= $this->insert('partials/field', ['name' => 'v_panels', 'label' => __('product.panels'), 'type' => 'number', 'value' => 1, 'attrs' => 'min="1" max="5"']) ?></div>
                        </div>
                        <button class="btn btn-outline-bwa" type="submit"><i class="bi bi-plus-lg me-1"></i><?= e(__('admin.add_variant')) ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ======================== options (builder) ======================== -->
    <div class="tab-pane fade" id="options" role="tabpanel">
        <div class="admin-card"><div class="card-body">
            <p class="small text-muted-bwa"><?= e(__('admin.options_help')) ?></p>
            <div class="row">
                <div class="col-md-6">
                    <span class="filter-title"><?= e(__('admin.allowed_supports')) ?></span>
                    <?php foreach ($materials as $m): ?>
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="materials[]" value="<?= (int) $m['id'] ?>" id="m<?= (int) $m['id'] ?>" form="product-form" <?= in_array((int) $m['id'], $selMaterials, true) ? 'checked' : '' ?>><label class="form-check-label" for="m<?= (int) $m['id'] ?>"><?= e($m['name']) ?></label></div>
                    <?php endforeach; ?>
                </div>
                <div class="col-md-6">
                    <span class="filter-title"><?= e(__('admin.allowed_frames')) ?></span>
                    <?php foreach ($frames as $f): ?>
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="frames[]" value="<?= (int) $f['id'] ?>" id="fr<?= (int) $f['id'] ?>" form="product-form" <?= in_array((int) $f['id'], $selFrames, true) ? 'checked' : '' ?>><label class="form-check-label" for="fr<?= (int) $f['id'] ?>"><span class="d-inline-block rounded-circle me-1 border" style="width:12px;height:12px;background:<?= e($f['color_hex']) ?>"></span><?= e($f['name']) ?></label></div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="form-section">
                <div class="row">
                    <div class="col-md-3"><?= $this->insert('partials/field', ['name' => 'min_panels', 'label' => __('admin.min_panels'), 'type' => 'number', 'value' => $v('min_panels', 1), 'form' => 'product-form', 'attrs' => 'min="1" max="5"']) ?></div>
                    <div class="col-md-3"><?= $this->insert('partials/field', ['name' => 'max_panels', 'label' => __('admin.max_panels'), 'type' => 'number', 'value' => $v('max_panels', 1), 'form' => 'product-form', 'attrs' => 'min="1" max="5"']) ?></div>
                    <div class="col-md-6 pt-md-4"><?= $this->insert('partials/field', ['name' => 'allow_custom_size', 'label' => __('admin.p_allow_custom_size'), 'type' => 'checkbox', 'value' => $v('allow_custom_size', 0), 'form' => 'product-form']) ?></div>
                </div>
            </div>
            <div class="form-section">
                <h3><?= e(__('product.related')) ?></h3>
                <select class="form-select" name="related[]" multiple size="8" form="product-form" aria-label="<?= e(__('product.related')) ?>">
                    <?php foreach ($allProducts as $ap): ?><option value="<?= (int) $ap['id'] ?>" <?= in_array((int) $ap['id'], $related, true) ? 'selected' : '' ?>><?= e($ap['title']) ?> (<?= e($ap['sku']) ?>)</option><?php endforeach; ?>
                </select>
                <div class="form-text"><?= e(__('admin.related_help')) ?></div>
            </div>
        </div></div>
    </div>

    <!-- ======================== Arabic & SEO ======================== -->
    <div class="tab-pane fade" id="seo" role="tabpanel">
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="admin-card h-100"><div class="card-header"><h2>SEO (FR)</h2></div><div class="card-body">
                    <?= $this->insert('partials/field', ['name' => 'seo_title', 'label' => __('settings.seo_meta_title'), 'value' => $v('seo_title'), 'help' => __('admin.seo_title_help'), 'form' => 'product-form', 'attrs' => 'maxlength="191"']) ?>
                    <?= $this->insert('partials/field', ['name' => 'seo_description', 'label' => __('settings.seo_meta_description'), 'type' => 'textarea', 'rows' => 3, 'value' => $v('seo_description'), 'form' => 'product-form', 'attrs' => 'maxlength="320"']) ?>
                </div></div>
            </div>
            <div class="col-lg-6">
                <div class="admin-card h-100"><div class="card-header"><h2><?= e(__('admin.arabic_version')) ?></h2></div><div class="card-body">
                    <?php foreach (['title' => 'text', 'short_description' => 'textarea', 'description' => 'textarea', 'seo_title' => 'text', 'seo_description' => 'textarea'] as $f => $type): ?>
                        <?= $this->insert('partials/field', ['name' => 'ar_' . $f, 'label' => __('admin.ar_' . $f), 'type' => $type, 'rows' => $f === 'description' ? 6 : 2, 'value' => old('ar_' . $f, $ar[$f] ?? ''), 'form' => 'product-form', 'attrs' => 'dir="rtl" lang="ar"']) ?>
                    <?php endforeach; ?>
                    <p class="small text-muted-bwa mb-0"><?= e(__('admin.arabic_fallback')) ?></p>
                </div></div>
            </div>
        </div>
    </div>
</div>

<div class="admin-sticky-save" data-hide-on="images variants">
    <button class="btn btn-bwa btn-lg" type="submit" form="product-form"><i class="bi bi-check2 me-1"></i><?= e($isEdit ? __('common.save') : __('admin.create_product')) ?></button>
</div>
