<?php
$this->layout('layouts/admin', ['title' => __('orders.new_manual')]);
$oldLines = old('lines', []);
$rows = max(3, is_array($oldLines) ? count($oldLines) : 0);
$ol = fn (int $i, string $k) => is_array($oldLines) && isset($oldLines[$i][$k]) ? (string) $oldLines[$i][$k] : '';
?>
<a class="small text-muted-bwa d-inline-block mb-3" href="<?= e(route('admin.orders.index')) ?>"><i class="bi bi-arrow-<?= is_rtl() ? 'right' : 'left' ?> me-1"></i><?= e(__('admin.nav_orders')) ?></a>
<?php if (error('lines')): ?><div class="alert alert-danger"><?= e(error('lines')) ?></div><?php endif; ?>
<form method="post" action="<?= e(route('admin.orders.store')) ?>" novalidate data-once>
    <?= csrf_field() ?>
    <div class="row g-4">
        <div class="col-xl-8">
            <div class="admin-card mb-4">
                <div class="card-header"><h2><?= e(__('orders.items')) ?></h2><button class="btn btn-sm btn-outline-bwa" type="button" data-repeat-add="#order-lines"><i class="bi bi-plus-lg me-1"></i><?= e(__('orders.add_line')) ?></button></div>
                <div class="card-body" id="order-lines" data-repeater>
                    <p class="small text-muted-bwa"><?= e(__('orders.lines_help')) ?></p>
                    <?php for ($i = 0; $i < $rows; $i++): ?>
                        <div class="border rounded p-3 mb-3" data-repeat-row>
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label class="form-label small"><?= e(__('orders.catalog_product')) ?></label>
                                    <select class="form-select" name="lines[<?= $i ?>][product]" data-name="lines[__i__][product]">
                                        <option value=""><?= e(__('orders.custom_line_option')) ?></option>
                                        <?php foreach ($products as $p): ?><option value="<?= e($p['value']) ?>" <?= $ol($i, 'product') === $p['value'] ? 'selected' : '' ?>><?= e($p['label']) ?></option><?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-6 col-md-2"><label class="form-label small"><?= e(__('orders.qty')) ?></label><input class="form-control" type="number" min="1" max="999" name="lines[<?= $i ?>][quantity]" data-name="lines[__i__][quantity]" value="<?= e($ol($i, 'quantity') ?: '1') ?>"></div>
                                <div class="col-6 col-md-4"><label class="form-label small"><?= e(__('orders.unit_price_opt')) ?></label><input class="form-control" name="lines[<?= $i ?>][unit_price]" data-name="lines[__i__][unit_price]" inputmode="decimal" value="<?= e($ol($i, 'unit_price')) ?>"></div>
                                <div class="col-md-6"><label class="form-label small"><?= e(__('orders.custom_title')) ?></label><input class="form-control" name="lines[<?= $i ?>][title]" data-name="lines[__i__][title]" value="<?= e($ol($i, 'title')) ?>" placeholder="<?= e(__('orders.custom_title_ph')) ?>"></div>
                                <div class="col-3 col-md-2"><label class="form-label small"><?= e(__('builder.width')) ?></label><input class="form-control" name="lines[<?= $i ?>][width_cm]" data-name="lines[__i__][width_cm]" inputmode="decimal" value="<?= e($ol($i, 'width_cm')) ?>"></div>
                                <div class="col-3 col-md-2"><label class="form-label small"><?= e(__('builder.height')) ?></label><input class="form-control" name="lines[<?= $i ?>][height_cm]" data-name="lines[__i__][height_cm]" inputmode="decimal" value="<?= e($ol($i, 'height_cm')) ?>"></div>
                                <div class="col-6 col-md-2"><label class="form-label small"><?= e(__('orders.unit_cost')) ?></label><input class="form-control" name="lines[<?= $i ?>][unit_cost]" data-name="lines[__i__][unit_cost]" inputmode="decimal" value="<?= e($ol($i, 'unit_cost')) ?>"></div>
                                <div class="col-md-6"><label class="form-label small"><?= e(__('builder.support')) ?></label><input class="form-control" name="lines[<?= $i ?>][material_name]" data-name="lines[__i__][material_name]" value="<?= e($ol($i, 'material_name')) ?>"></div>
                                <div class="col-md-6"><label class="form-label small"><?= e(__('builder.frame')) ?></label><input class="form-control" name="lines[<?= $i ?>][frame_name]" data-name="lines[__i__][frame_name]" value="<?= e($ol($i, 'frame_name')) ?>"></div>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
            <div class="admin-card">
                <div class="card-header"><h2><?= e(__('orders.customer_delivery')) ?></h2></div>
                <div class="card-body">
                    <?= $this->insert('partials/field', ['name' => 'customer_id', 'label' => __('orders.existing_customer'), 'type' => 'select', 'options' => ['' => __('orders.new_or_match')] + array_combine(array_column($customers, 'id'), array_map(fn ($c) => trim($c['first_name'] . ' ' . $c['last_name']) . ' — ' . ($c['phone'] ?: $c['email']), $customers)), 'help' => __('orders.customer_match_help')]) ?>
                    <div class="row g-2">
                        <div class="col-md-6"><?= $this->insert('partials/field', ['name' => 'full_name', 'label' => __('checkout.full_name'), 'required' => true]) ?></div>
                        <div class="col-md-3"><?= $this->insert('partials/field', ['name' => 'phone', 'label' => __('auth.phone'), 'type' => 'tel', 'required' => true, 'attrs' => 'dir="ltr"']) ?></div>
                        <div class="col-md-3"><?= $this->insert('partials/field', ['name' => 'email', 'label' => __('auth.email'), 'type' => 'email', 'attrs' => 'dir="ltr"']) ?></div>
                        <div class="col-md-6"><?= $this->insert('partials/field', ['name' => 'address', 'label' => __('checkout.address'), 'required' => true]) ?></div>
                        <div class="col-md-4"><?= $this->insert('partials/field', ['name' => 'city', 'label' => __('checkout.city'), 'required' => true, 'attrs' => 'list="city-list"']) ?><datalist id="city-list"><?php foreach ($cities as $c): ?><option value="<?= e(is_array($c) ? (string) ($c['city'] ?? $c['name'] ?? '') : (string) $c) ?>"><?php endforeach; ?></datalist></div>
                        <div class="col-md-2"><?= $this->insert('partials/field', ['name' => 'postal_code', 'label' => __('checkout.postal_code')]) ?></div>
                    </div>
                    <?= $this->insert('partials/field', ['name' => 'delivery_instructions', 'label' => __('checkout.instructions'), 'type' => 'textarea', 'rows' => 2]) ?>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="admin-card"><div class="card-body">
                <?= $this->insert('partials/field', ['name' => 'source', 'label' => __('orders.source'), 'type' => 'select', 'options' => ['phone' => __('orders.source_phone'), 'whatsapp' => __('orders.source_whatsapp'), 'admin' => __('orders.source_admin')], 'value' => 'phone']) ?>
                <?= $this->insert('partials/field', ['name' => 'payment_method_id', 'label' => __('orders.payment_method'), 'type' => 'select', 'options' => array_column($methods, 'name', 'id')]) ?>
                <?= $this->insert('partials/field', ['name' => 'shipping', 'label' => __('orders.shipping_amount'), 'help' => __('orders.shipping_auto'), 'attrs' => 'inputmode="decimal"']) ?>
                <?= $this->insert('partials/field', ['name' => 'discount', 'label' => __('cart.discount'), 'attrs' => 'inputmode="decimal"']) ?>
                <?= $this->insert('partials/field', ['name' => 'locale', 'label' => __('admin.language'), 'type' => 'select', 'options' => ['fr' => 'Français', 'ar' => 'العربية'], 'value' => 'fr']) ?>
                <?= $this->insert('partials/field', ['name' => 'customer_notes', 'label' => __('orders.customer_note'), 'type' => 'textarea', 'rows' => 2]) ?>
                <?= $this->insert('partials/field', ['name' => 'internal_notes', 'label' => __('admin.internal_notes'), 'type' => 'textarea', 'rows' => 2]) ?>
                <p class="small text-muted-bwa"><?= e(__('orders.manual_help')) ?></p>
                <button class="btn btn-bwa btn-lg w-100" type="submit" data-once><i class="bi bi-check2 me-1"></i><?= e(__('orders.create_order')) ?></button>
            </div></div>
        </div>
    </div>
</form>
