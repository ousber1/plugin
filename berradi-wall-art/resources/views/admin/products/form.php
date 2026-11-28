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
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#gift" type="button"><i class="bi bi-gift me-1"></i><?= e(__('personalize.tab')) ?><?= $persoFields !== [] ? ' (' . count($persoFields) . ')' : '' ?></button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#options" type="button"><?= e(__($family === 'wall' ? 'admin.tab_options' : 'product.related')) ?></button></li>
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
                        <thead><tr><th><?= e(__('admin.default')) ?></th><th><?= e(__('admin.name')) ?></th><th><?= e(__('product.sku')) ?></th><th><?= e(__('admin.p_price')) ?></th><th><?= e(__('admin.p_promo_price')) ?></th><th><?= e(__('admin.p_cost_price')) ?></th><?php if ($family !== 'wall'): ?><th><?= e($p['option1_name'] ?: __('personalize.option1_name')) ?></th><th><?= e($p['option2_name'] ?: __('personalize.option2_name')) ?></th><th><?= e(__('personalize.color')) ?></th><th><?= e(__('personalize.dimensions')) ?></th><?php endif; ?><th><?= e(__('admin.stock')) ?></th><th><?= e(__('admin.sort_order')) ?></th><th><?= e(__('admin.active')) ?></th><th><?= e(__('account.delete_now')) ?></th></tr></thead>
                        <tbody>
                        <?php foreach ($variants as $vr): $k = 'variants[' . (int) $vr['id'] . ']'; ?>
                            <tr>
                                <td><input class="form-check-input" type="radio" name="default_variant" value="<?= (int) $vr['id'] ?>" <?= (int) $vr['is_default'] ? 'checked' : '' ?>></td>
                                <td><input class="form-control" name="<?= $k ?>[name]" value="<?= e($vr['name']) ?>"><input type="hidden" name="<?= $k ?>[sku]" value="<?= e($vr['sku']) ?>"></td>
                                <td class="small text-muted-bwa text-nowrap"><?= e($vr['sku']) ?></td>
                                <td><input class="form-control" name="<?= $k ?>[price]" value="<?= e($vr['price']) ?>" inputmode="decimal" style="width:100px"></td>
                                <td><input class="form-control" name="<?= $k ?>[promo_price]" value="<?= e((string) $vr['promo_price']) ?>" inputmode="decimal" style="width:100px"></td>
                                <td><input class="form-control" name="<?= $k ?>[cost_price]" value="<?= e($vr['cost_price']) ?>" inputmode="decimal" style="width:100px"></td>
                                <?php if ($family !== 'wall'): $va = $variantAr[(int) $vr['id']] ?? []; ?>
                                    <td><input class="form-control form-control-sm mb-1" name="<?= $k ?>[option1]" value="<?= e((string) $vr['option1']) ?>" style="width:120px" aria-label="option 1"><input class="form-control form-control-sm" name="<?= $k ?>[ar_option1]" value="<?= e((string) ($va['option1'] ?? '')) ?>" dir="rtl" placeholder="AR" style="width:120px" aria-label="option 1 AR"></td>
                                    <td><input class="form-control form-control-sm mb-1" name="<?= $k ?>[option2]" value="<?= e((string) $vr['option2']) ?>" style="width:100px" aria-label="option 2"><input class="form-control form-control-sm" name="<?= $k ?>[ar_option2]" value="<?= e((string) ($va['option2'] ?? '')) ?>" dir="rtl" placeholder="AR" style="width:100px" aria-label="option 2 AR"></td>
                                    <td class="text-nowrap"><input class="form-control form-control-color form-control-sm" type="color" name="<?= $k ?>[color_hex]" value="<?= e((string) ($vr['color_hex'] ?: '#FFFFFF')) ?>" aria-label="<?= e(__('personalize.color')) ?>"><label class="form-check small mt-1"><input class="form-check-input" type="checkbox" name="<?= $k ?>[no_color]" value="1" <?= $vr['color_hex'] ? '' : 'checked' ?>><span class="form-check-label"><?= e(__('personalize.no_color')) ?></span></label></td>
                                    <td class="text-nowrap"><div class="input-group input-group-sm" style="width:150px"><input class="form-control" name="<?= $k ?>[width_cm]" value="<?= e(rtrim(rtrim((string) $vr['width_cm'], '0'), '.')) ?>" inputmode="decimal" aria-label="W"><span class="input-group-text">×</span><input class="form-control" name="<?= $k ?>[height_cm]" value="<?= e(rtrim(rtrim((string) $vr['height_cm'], '0'), '.')) ?>" inputmode="decimal" aria-label="H"></div></td>
                                <?php endif; ?>
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
        <?php if ($family !== 'wall'): ?>
            <form method="post" action="<?= e(route('admin.products.option_variants', ['id' => $p['id']])) ?>" class="admin-card" novalidate>
                <?= csrf_field() ?>
                <div class="card-header"><h2><i class="bi bi-magic me-2 text-gold"></i><?= e(__('personalize.generate_title')) ?></h2></div>
                <div class="card-body">
                    <p class="small text-muted-bwa"><?= e(__('personalize.generate_help')) ?><?php if ($laserBed !== null && $family === 'laser'): ?> <?= e(__('personalize.laser_bed', ['w' => cm($laserBed[0] / 10), 'h' => cm($laserBed[1] / 10)])) ?><?php endif; ?></p>
                    <div class="row g-2">
                        <div class="col-md-6"><?= $this->insert('partials/field', ['name' => 'ov_values1', 'label' => ($p['option1_name'] ?: __('personalize.option1_name')) . ' — ' . __('personalize.values'), 'placeholder' => __('personalize.values1_ph')]) ?></div>
                        <div class="col-md-6"><?= $this->insert('partials/field', ['name' => 'ov_values2', 'label' => ($p['option2_name'] ?: __('personalize.option2_name')) . ' — ' . __('personalize.values'), 'placeholder' => __('personalize.values2_ph')]) ?></div>
                        <div class="col-6 col-md-3"><?= $this->insert('partials/field', ['name' => 'ov_price', 'label' => __('admin.p_price'), 'value' => $p['selling_price'], 'attrs' => 'inputmode="decimal"']) ?></div>
                        <div class="col-6 col-md-3"><?= $this->insert('partials/field', ['name' => 'ov_cost', 'label' => __('admin.p_cost_price'), 'attrs' => 'inputmode="decimal"']) ?></div>
                        <div class="col-6 col-md-3"><?= $this->insert('partials/field', ['name' => 'ov_width', 'label' => __('builder.width') . ' (cm)', 'attrs' => 'inputmode="decimal"']) ?></div>
                        <div class="col-6 col-md-3"><?= $this->insert('partials/field', ['name' => 'ov_height', 'label' => __('builder.height') . ' (cm)', 'attrs' => 'inputmode="decimal"']) ?></div>
                    </div>
                    <button class="btn btn-bwa" type="submit"><?= e(__('admin.generate')) ?></button>
                </div>
            </form>
        <?php else: ?>
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
        <?php endif; ?>
    </div>
    <?php endif; ?>


    <!-- ======================== gifts & laser: options, personalisation, price breaks, bill of materials ======================== -->
    <div class="tab-pane fade" id="gift" role="tabpanel">
        <div class="admin-card mb-4"><div class="card-body">
            <p class="small text-muted-bwa"><?= e(__('personalize.tab_help')) ?></p>
            <div class="row">
                <div class="col-md-3"><?= $this->insert('partials/field', ['name' => 'option1_name', 'label' => __('personalize.option1_name'), 'value' => $v('option1_name'), 'form' => 'product-form', 'placeholder' => __('personalize.option1_ph')]) ?></div>
                <div class="col-md-3"><?= $this->insert('partials/field', ['name' => 'ar_option1_name', 'label' => __('personalize.option1_name') . ' (AR)', 'value' => old('ar_option1_name', $ar['option1_name'] ?? ''), 'form' => 'product-form', 'attrs' => 'dir="rtl"']) ?></div>
                <div class="col-md-3"><?= $this->insert('partials/field', ['name' => 'option2_name', 'label' => __('personalize.option2_name'), 'value' => $v('option2_name'), 'form' => 'product-form', 'placeholder' => __('personalize.option2_ph')]) ?></div>
                <div class="col-md-3"><?= $this->insert('partials/field', ['name' => 'ar_option2_name', 'label' => __('personalize.option2_name') . ' (AR)', 'value' => old('ar_option2_name', $ar['option2_name'] ?? ''), 'form' => 'product-form', 'attrs' => 'dir="rtl"']) ?></div>
                <div class="col-6 col-md-3"><?= $this->insert('partials/field', ['name' => 'min_qty', 'label' => __('personalize.min_qty'), 'type' => 'number', 'value' => $v('min_qty', 1), 'form' => 'product-form', 'attrs' => 'min="1"']) ?></div>
                <div class="col-6 col-md-3"><?= $this->insert('partials/field', ['name' => 'max_qty', 'label' => __('personalize.max_qty'), 'type' => 'number', 'value' => $v('max_qty', 50), 'form' => 'product-form', 'attrs' => 'min="1"']) ?></div>
                <div class="col-6 col-md-3"><?= $this->insert('partials/field', ['name' => 'production_days', 'label' => __('personalize.production_days'), 'type' => 'number', 'value' => $v('production_days'), 'form' => 'product-form', 'attrs' => 'min="0" max="60"', 'help' => __('personalize.production_days_help')]) ?></div>
                <div class="col-6 col-md-3 pt-md-4"><?= $this->insert('partials/field', ['name' => 'requires_proof', 'label' => __('personalize.requires_proof'), 'type' => 'checkbox', 'value' => $v('requires_proof', 0), 'form' => 'product-form']) ?></div>
            </div>
            <p class="small text-muted-bwa mb-0"><?= e(__('personalize.save_with_product')) ?></p>
        </div></div>

        <?php if ($isEdit): ?>
        <form method="post" action="<?= e(route('admin.products.personalization', ['id' => $p['id']])) ?>" class="admin-card mb-4" novalidate>
            <?= csrf_field() ?>
            <div class="card-header"><h2><i class="bi bi-pencil-square me-2 text-gold"></i><?= e(__('personalize.fields_title')) ?></h2></div>
            <div class="card-body">
                <p class="small text-muted-bwa"><?= e(__('personalize.fields_help')) ?></p>
                <?php foreach (array_merge($persoFields, [null]) as $pf): $fid = $pf === null ? 'new' : (int) $pf['id']; $k = 'fields[' . $fid . ']'; ?>
                    <div class="perso-edit border rounded p-3 mb-3<?= $pf === null ? ' perso-new' : '' ?>">
                        <?php if ($pf === null): ?><div class="small fw-semibold mb-2 text-gold"><i class="bi bi-plus-lg me-1"></i><?= e(__('personalize.add_field')) ?></div><?php endif; ?>
                        <div class="row g-2">
                            <div class="col-md-3"><label class="form-label small"><?= e(__('personalize.f_type')) ?></label>
                                <select class="form-select form-select-sm" name="<?= $k ?>[field_type]"><?php foreach (\App\Services\Catalog\Personalization::TYPES as $ft): ?><option value="<?= $ft ?>" <?= ($pf['field_type'] ?? 'text') === $ft ? 'selected' : '' ?>><?= e(__('personalize.type_' . $ft)) ?></option><?php endforeach; ?></select></div>
                            <div class="col-md-3"><label class="form-label small"><?= e(__('personalize.f_label')) ?></label><input class="form-control form-control-sm" name="<?= $k ?>[label]" value="<?= e((string) ($pf['label'] ?? '')) ?>" maxlength="120"></div>
                            <div class="col-md-3"><label class="form-label small"><?= e(__('personalize.f_label')) ?> (AR)</label><input class="form-control form-control-sm" name="<?= $k ?>[ar_label]" value="<?= e((string) ($pf['ar']['label'] ?? '')) ?>" maxlength="120" dir="rtl"></div>
                            <div class="col-md-3"><label class="form-label small"><?= e(__('personalize.f_placeholder')) ?></label><input class="form-control form-control-sm" name="<?= $k ?>[placeholder]" value="<?= e((string) ($pf['placeholder'] ?? '')) ?>" maxlength="120"></div>
                            <div class="col-md-6"><label class="form-label small"><?= e(__('personalize.f_help')) ?></label><input class="form-control form-control-sm" name="<?= $k ?>[help]" value="<?= e((string) ($pf['help'] ?? '')) ?>" maxlength="255"></div>
                            <div class="col-md-6"><label class="form-label small"><?= e(__('personalize.f_help')) ?> (AR)</label><input class="form-control form-control-sm" name="<?= $k ?>[ar_help]" value="<?= e((string) ($pf['ar']['help'] ?? '')) ?>" maxlength="255" dir="rtl"></div>
                            <div class="col-md-4"><label class="form-label small"><?= e(__('personalize.f_choices')) ?></label><textarea class="form-control form-control-sm" name="<?= $k ?>[choices]" rows="2" placeholder="<?= e(__('personalize.f_choices_ph')) ?>"><?= e((string) ($pf['choices_text'] ?? '')) ?></textarea></div>
                            <div class="col-4 col-md-2"><label class="form-label small"><?= e(__('personalize.f_max')) ?></label><input class="form-control form-control-sm" type="number" min="1" max="2000" name="<?= $k ?>[max_length]" value="<?= e((string) ($pf['max_length'] ?? '')) ?>"></div>
                            <div class="col-4 col-md-2"><label class="form-label small"><?= e(__('personalize.f_price')) ?></label><input class="form-control form-control-sm" name="<?= $k ?>[price_add]" value="<?= e((string) ($pf['price_add'] ?? '0.00')) ?>" inputmode="decimal"></div>
                            <div class="col-4 col-md-2"><label class="form-label small"><?= e(__('personalize.f_print')) ?></label><div class="input-group input-group-sm"><input class="form-control" name="<?= $k ?>[print_width_cm]" value="<?= e((string) ($pf['print_width_cm'] ?? '')) ?>" inputmode="decimal" aria-label="W"><span class="input-group-text">×</span><input class="form-control" name="<?= $k ?>[print_height_cm]" value="<?= e((string) ($pf['print_height_cm'] ?? '')) ?>" inputmode="decimal" aria-label="H"></div></div>
                            <div class="col-6 col-md-1"><label class="form-label small"><?= e(__('admin.sort_order')) ?></label><input class="form-control form-control-sm" type="number" name="<?= $k ?>[sort_order]" value="<?= (int) ($pf['sort_order'] ?? count($persoFields)) ?>"></div>
                            <div class="col-6 col-md-1 d-flex flex-column justify-content-end small">
                                <label class="form-check mb-0"><input class="form-check-input" type="checkbox" name="<?= $k ?>[is_required]" value="1" <?= !empty($pf['is_required']) ? 'checked' : '' ?>><span class="form-check-label"><?= e(__('personalize.f_required')) ?></span></label>
                                <?php if ($pf !== null): ?><label class="form-check mb-0 text-danger"><input class="form-check-input" type="checkbox" name="<?= $k ?>[delete]" value="1"><span class="form-check-label"><?= e(__('common.delete')) ?></span></label><?php endif; ?>
                            </div>
                            <input type="hidden" name="<?= $k ?>[field_key]" value="<?= e((string) ($pf['field_key'] ?? '')) ?>">
                        </div>
                    </div>
                <?php endforeach; ?>
                <button class="btn btn-bwa" type="submit"><?= e(__('common.save')) ?></button>
            </div>
        </form>

        <div class="row g-4">
            <div class="col-lg-5">
                <form method="post" action="<?= e(route('admin.products.tiers', ['id' => $p['id']])) ?>" class="admin-card h-100" novalidate>
                    <?= csrf_field() ?>
                    <div class="card-header"><h2><i class="bi bi-percent me-2 text-gold"></i><?= e(__('personalize.tiers_title')) ?></h2></div>
                    <div class="card-body">
                        <p class="small text-muted-bwa"><?= e(__('personalize.tiers_help')) ?></p>
                        <?php if (error('tiers')): ?><div class="alert alert-danger small py-2"><?= e(error('tiers')) ?></div><?php endif; ?>
                        <?php foreach (array_merge($tiers, [null, null]) as $i => $t): ?>
                            <div class="row g-2 mb-2">
                                <div class="col-6"><div class="input-group input-group-sm"><span class="input-group-text"><?= e(__('personalize.from_qty')) ?></span><input class="form-control" type="number" min="2" name="tiers[<?= $i ?>][min_qty]" value="<?= e((string) ($t['min_qty'] ?? '')) ?>"></div></div>
                                <div class="col-6"><div class="input-group input-group-sm"><span class="input-group-text">−</span><input class="form-control" name="tiers[<?= $i ?>][discount_percent]" value="<?= e((string) ($t['discount_percent'] ?? '')) ?>" inputmode="decimal"><span class="input-group-text">%</span></div></div>
                            </div>
                        <?php endforeach; ?>
                        <button class="btn btn-sm btn-bwa mt-2" type="submit"><?= e(__('common.save')) ?></button>
                    </div>
                </form>
            </div>
            <div class="col-lg-7">
                <form method="post" action="<?= e(route('admin.products.components', ['id' => $p['id']])) ?>" class="admin-card h-100" novalidate>
                    <?= csrf_field() ?>
                    <div class="card-header"><h2><i class="bi bi-boxes me-2 text-gold"></i><?= e(__('personalize.components_title')) ?></h2></div>
                    <div class="card-body">
                        <p class="small text-muted-bwa"><?= e(__('personalize.components_help')) ?></p>
                        <?php if (error('components')): ?><div class="alert alert-danger small py-2"><?= e(error('components')) ?></div><?php endif; ?>
                        <?php foreach (array_merge($components, [null, null]) as $i => $c): ?>
                            <div class="row g-2 mb-2 align-items-center">
                                <div class="col-md-5"><select class="form-select form-select-sm" name="components[<?= $i ?>][material_id]" aria-label="<?= e(__('personalize.material')) ?>"><option value=""><?= e(__('personalize.material')) ?>…</option><?php foreach ($stockItems as $si): ?><option value="<?= (int) $si['id'] ?>" <?= (int) ($c['material_id'] ?? 0) === (int) $si['id'] ? 'selected' : '' ?>><?= e($si['name']) ?> (<?= e($si['unit']) ?>)</option><?php endforeach; ?></select></div>
                                <div class="col-md-3"><select class="form-select form-select-sm" name="components[<?= $i ?>][variant_id]" aria-label="<?= e(__('admin.tab_variants')) ?>"><option value=""><?= e(__('personalize.all_variants')) ?></option><?php foreach ($variants as $vr): ?><option value="<?= (int) $vr['id'] ?>" <?= (int) ($c['product_variant_id'] ?? 0) === (int) $vr['id'] ? 'selected' : '' ?>><?= e($vr['name']) ?></option><?php endforeach; ?></select></div>
                                <div class="col-8 col-md-3"><div class="input-group input-group-sm"><input class="form-control" name="components[<?= $i ?>][quantity]" value="<?= e($c !== null ? rtrim(rtrim((string) $c['quantity'], '0'), '.') : '') ?>" inputmode="decimal" aria-label="<?= e(__('personalize.qty_per_unit')) ?>"><span class="input-group-text"><?= e(__('personalize.per_unit')) ?></span></div></div>
                                <div class="col-4 col-md-1"><?php if ($c !== null): ?><label class="form-check mb-0 small text-danger"><input class="form-check-input" type="checkbox" name="components[<?= $i ?>][delete]" value="1" aria-label="<?= e(__('common.delete')) ?>"><i class="bi bi-trash"></i></label><?php endif; ?></div>
                            </div>
                        <?php endforeach; ?>
                        <button class="btn btn-sm btn-bwa mt-2" type="submit"><?= e(__('common.save')) ?></button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <!-- ======================== options (builder) ======================== -->
    <div class="tab-pane fade" id="options" role="tabpanel">
        <div class="admin-card"><div class="card-body">
            <?php if ($family === 'wall'): ?>
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
            <?php else: ?>
                <input type="hidden" name="min_panels" value="1" form="product-form"><input type="hidden" name="max_panels" value="1" form="product-form">
            <?php endif; ?>
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
                <div class="admin-card h-100"><div class="card-header"><h2>SEO (FR)</h2></div><div class="card-body" data-serp-form>
                    <div class="small mb-1"><?= e(__('seo_admin.title')) ?> <span class="serp-count" data-max="60"></span> · <?= e(__('seo_admin.description')) ?> <span class="serp-count" data-max="160"></span></div>
                    <?= $this->insert('partials/field', ['name' => 'seo_title', 'label' => __('settings.seo_meta_title'), 'value' => $v('seo_title'), 'help' => __('admin.seo_title_help'), 'form' => 'product-form', 'attrs' => 'maxlength="191" data-serp-title']) ?>
                    <?= $this->insert('partials/field', ['name' => 'seo_description', 'label' => __('settings.seo_meta_description'), 'type' => 'textarea', 'rows' => 3, 'value' => $v('seo_description'), 'form' => 'product-form', 'attrs' => 'maxlength="320" data-serp-desc']) ?>
                    <div class="serp-preview" dir="ltr"><div class="serp-url"><?= e(absolute_url('product/' . ($v('slug') ?: '…'))) ?></div><div class="serp-title" data-serp-title-out><?= e($v('seo_title') ?: $v('title')) ?></div><div class="serp-desc" data-serp-desc-out><?= e($v('seo_description')) ?></div></div>
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
