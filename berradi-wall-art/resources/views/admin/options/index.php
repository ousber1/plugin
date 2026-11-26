<?php
/**
 * Print supports, frames and builder options. Each row is its own small form (no shared field names
 * across rows are re-filled from old input, so values always reflect the database).
 */
$this->layout('layouts/admin', ['title' => __('admin.nav_options')]);
$stockOptions = ['' => '—'] + array_column($stock, 'name', 'id');
$sel = function (string $name, array $options, mixed $current): string {
    $h = '<select class="form-select form-select-sm" name="' . e($name) . '">';
    foreach ($options as $val => $label) {
        $h .= '<option value="' . e((string) $val) . '"' . ((string) $val === (string) $current ? ' selected' : '') . '>' . e((string) $label) . '</option>';
    }
    return $h . '</select>';
};
$in = fn (string $name, mixed $value, string $extra = '') => '<input class="form-control form-control-sm" name="' . e($name) . '" value="' . e((string) $value) . '" ' . $extra . '>';
$chk = fn (string $name, mixed $value, string $label) => '<label class="form-check form-switch mb-1"><input class="form-check-input" type="checkbox" role="switch" name="' . e($name) . '" value="1" ' . ((int) $value ? 'checked' : '') . '><span class="form-check-label small">' . e($label) . '</span></label>';
$lbl = fn (string $text) => '<label class="form-label small mb-1">' . e($text) . '</label>';
$materialTypes = [];
foreach (['canvas', 'poster', 'photo_paper', 'bache', 'vinyl', 'other'] as $t) {
    $materialTypes[$t] = __('admin.mt_' . $t);
}
$frameTypes = [];
foreach (['wood', 'aluminium', 'floating', 'box', 'canvas_stretcher'] as $t) {
    $frameTypes[$t] = __('admin.ft_' . $t);
}
$groups = ['size' => __('admin.og_size'), 'border' => __('admin.og_border'), 'panel_layout' => __('admin.og_panel_layout'), 'finish' => __('admin.og_finish')];
$priceTypes = ['fixed' => __('admin.pt_fixed'), 'percent' => __('admin.pt_percent'), 'per_m2' => __('admin.pt_per_m2')];
$byGroup = [];
foreach ($options as $o) {
    if (isset($groups[$o['option_group']])) {
        $byGroup[$o['option_group']][] = $o;
    }
}
?>
<?php if (errors() !== []): ?>
    <div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i><?= e(__('admin.option_not_saved')) ?><ul class="mb-0 mt-1"><?php foreach (errors() as $f => $msgs): ?><li><?= e(is_array($msgs) ? (string) ($msgs[0] ?? '') : (string) $msgs) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
<p class="small text-muted-bwa"><?= e(__('admin.print_options_help')) ?></p>
<ul class="nav settings-tabs border-bottom mb-4" role="tablist" data-hash-tabs>
    <?php foreach (['materials' => __('admin.supports'), 'frames' => __('admin.frames'), 'options' => __('admin.builder_options')] as $k => $label): ?>
        <li class="nav-item"><button class="nav-link<?= $tab === $k ? ' active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tab-<?= $k ?>" type="button"><?= e($label) ?></button></li>
    <?php endforeach; ?>
</ul>
<div class="tab-content">
    <!-- ======================== supports ======================== -->
    <div class="tab-pane fade<?= $tab === 'materials' ? ' show active' : '' ?>" id="tab-materials" role="tabpanel">
        <?php foreach (array_merge($materials, [null]) as $m): $isNew = $m === null; $m ??= ['id' => 0, 'code' => '', 'name' => '', 'description' => '', 'material_type' => 'canvas', 'price_per_m2' => '', 'minimum_price' => '', 'cost_per_m2' => '0.00', 'min_width_cm' => '20.0', 'max_width_cm' => '150.0', 'min_height_cm' => '20.0', 'max_height_cm' => '150.0', 'consumption_factor' => '1.100', 'inventory_material_id' => null, 'supports_frame' => 1, 'is_active' => 1, 'sort_order' => 0]; ?>
            <details class="admin-card option-row mb-2" id="row-product_material-<?= (int) $m['id'] ?>">
                <summary class="card-body d-flex flex-wrap align-items-center gap-3">
                    <?php if ($isNew): ?>
                        <span class="fw-semibold text-gold"><i class="bi bi-plus-lg me-1"></i><?= e(__('admin.add_support')) ?></span>
                    <?php else: ?>
                        <span class="fw-semibold"><?= e($m['name']) ?></span><code class="small"><?= e($m['code']) ?></code>
                        <span class="small text-muted-bwa"><?= e(money($m['price_per_m2'])) ?> / m² · min <?= e(money($m['minimum_price'])) ?></span>
                        <?php if (!(int) $m['is_active']): ?><span class="badge text-bg-secondary"><?= e(__('admin.inactive')) ?></span><?php endif; ?>
                        <span class="ms-auto small" dir="rtl" lang="ar"><?= e($arMaterials[(int) $m['id']] ?? '') ?></span>
                    <?php endif; ?>
                </summary>
                <form method="post" action="<?= e($isNew ? route('admin.options.materials.store') : route('admin.options.materials.update', ['id' => $m['id']])) ?>" class="card-body border-top" novalidate>
                    <?= csrf_field() ?>
                    <div class="row g-2">
                        <div class="col-6 col-md-2"><?= $lbl(__('admin.code')) . $in('code', $m['code'], 'required maxlength="40"') ?></div>
                        <div class="col-6 col-md-4"><?= $lbl(__('admin.name')) . $in('name', $m['name'], 'required maxlength="120"') ?></div>
                        <div class="col-6 col-md-3"><?= $lbl(__('admin.type')) . $sel('material_type', $materialTypes, $m['material_type']) ?></div>
                        <div class="col-6 col-md-3"><?= $lbl(__('admin.ar_name')) . $in('ar_name', $arMaterials[(int) $m['id']] ?? '', 'dir="rtl" lang="ar"') ?></div>
                        <div class="col-12"><?= $lbl(__('product.description')) . $in('description', $m['description'], 'maxlength="500"') ?></div>
                        <div class="col-4 col-md-2"><?= $lbl(__('admin.price_per_m2')) . $in('price_per_m2', $m['price_per_m2'], 'required inputmode="decimal"') ?></div>
                        <div class="col-4 col-md-2"><?= $lbl(__('admin.minimum_price')) . $in('minimum_price', $m['minimum_price'], 'required inputmode="decimal"') ?></div>
                        <div class="col-4 col-md-2"><?= $lbl(__('admin.cost_per_m2')) . $in('cost_per_m2', $m['cost_per_m2'], 'required inputmode="decimal"') ?></div>
                        <div class="col-6 col-md-3"><?= $lbl(__('admin.consumption_factor')) . $in('consumption_factor', $m['consumption_factor'], 'required inputmode="decimal"') ?></div>
                        <div class="col-6 col-md-3"><?= $lbl(__('admin.stock_material')) . $sel('inventory_material_id', $stockOptions, $m['inventory_material_id']) ?></div>
                        <div class="col-3 col-md-2"><?= $lbl(__('admin.min_width')) . $in('min_width_cm', $m['min_width_cm'], 'inputmode="decimal"') ?></div>
                        <div class="col-3 col-md-2"><?= $lbl(__('admin.max_width')) . $in('max_width_cm', $m['max_width_cm'], 'inputmode="decimal"') ?></div>
                        <div class="col-3 col-md-2"><?= $lbl(__('admin.min_height')) . $in('min_height_cm', $m['min_height_cm'], 'inputmode="decimal"') ?></div>
                        <div class="col-3 col-md-2"><?= $lbl(__('admin.max_height')) . $in('max_height_cm', $m['max_height_cm'], 'inputmode="decimal"') ?></div>
                        <div class="col-6 col-md-2"><?= $lbl(__('admin.sort_order')) . $in('sort_order', $m['sort_order'], 'type="number"') ?></div>
                        <div class="col-6 col-md-2 pt-md-4"><?= $chk('supports_frame', $m['supports_frame'], __('admin.supports_frame')) ?><?= $chk('is_active', $m['is_active'], __('admin.active')) ?></div>
                    </div>
                    <div class="d-flex justify-content-end mt-2"><button class="btn btn-sm btn-bwa" type="submit"><?= e($isNew ? __('common.create') : __('common.save')) ?></button></div>
                </form>
            </details>
        <?php endforeach; ?>
    </div>

    <!-- ======================== frames ======================== -->
    <div class="tab-pane fade<?= $tab === 'frames' ? ' show active' : '' ?>" id="tab-frames" role="tabpanel">
        <?php foreach (array_merge($frames, [null]) as $f): $isNew = $f === null; $f ??= ['id' => 0, 'code' => '', 'name' => '', 'color_name' => '', 'color_hex' => '#171717', 'profile_width_mm' => 20, 'frame_type' => 'wood', 'price_per_meter' => '', 'fixed_price' => '0.00', 'cost_per_meter' => '0.00', 'inventory_material_id' => null, 'is_active' => 1, 'sort_order' => 0]; ?>
            <details class="admin-card option-row mb-2" id="row-frame-<?= (int) $f['id'] ?>">
                <summary class="card-body d-flex flex-wrap align-items-center gap-3">
                    <?php if ($isNew): ?>
                        <span class="fw-semibold text-gold"><i class="bi bi-plus-lg me-1"></i><?= e(__('admin.add_frame')) ?></span>
                    <?php else: ?>
                        <span class="d-inline-block rounded-circle border" style="width:16px;height:16px;background:<?= e($f['color_hex']) ?>"></span>
                        <span class="fw-semibold"><?= e($f['name']) ?></span><code class="small"><?= e($f['code']) ?></code>
                        <span class="small text-muted-bwa"><?= e(money($f['price_per_meter'])) ?> / m · + <?= e(money($f['fixed_price'])) ?></span>
                        <?php if (!(int) $f['is_active']): ?><span class="badge text-bg-secondary"><?= e(__('admin.inactive')) ?></span><?php endif; ?>
                        <span class="ms-auto small" dir="rtl" lang="ar"><?= e($arFrames[(int) $f['id']] ?? '') ?></span>
                    <?php endif; ?>
                </summary>
                <form method="post" action="<?= e($isNew ? route('admin.options.frames.store') : route('admin.options.frames.update', ['id' => $f['id']])) ?>" class="card-body border-top" novalidate>
                    <?= csrf_field() ?>
                    <div class="row g-2">
                        <div class="col-6 col-md-2"><?= $lbl(__('admin.code')) . $in('code', $f['code'], 'required maxlength="40"') ?></div>
                        <div class="col-6 col-md-4"><?= $lbl(__('admin.name')) . $in('name', $f['name'], 'required maxlength="120"') ?></div>
                        <div class="col-6 col-md-3"><?= $lbl(__('admin.type')) . $sel('frame_type', $frameTypes, $f['frame_type']) ?></div>
                        <div class="col-6 col-md-3"><?= $lbl(__('admin.ar_name')) . $in('ar_name', $arFrames[(int) $f['id']] ?? '', 'dir="rtl" lang="ar"') ?></div>
                        <div class="col-6 col-md-3"><?= $lbl(__('admin.color_name')) . $in('color_name', $f['color_name'], 'required maxlength="60"') ?></div>
                        <div class="col-6 col-md-2"><?= $lbl(__('admin.color')) ?><input class="form-control form-control-sm form-control-color w-100" type="color" name="color_hex" value="<?= e($f['color_hex']) ?>"></div>
                        <div class="col-6 col-md-2"><?= $lbl(__('admin.profile_mm')) . $in('profile_width_mm', $f['profile_width_mm'], 'type="number" min="5" max="150"') ?></div>
                        <div class="col-6 col-md-2"><?= $lbl(__('admin.price_per_meter')) . $in('price_per_meter', $f['price_per_meter'], 'required inputmode="decimal"') ?></div>
                        <div class="col-6 col-md-3"><?= $lbl(__('admin.fixed_price')) . $in('fixed_price', $f['fixed_price'], 'required inputmode="decimal"') ?></div>
                        <div class="col-6 col-md-2"><?= $lbl(__('admin.cost_per_meter')) . $in('cost_per_meter', $f['cost_per_meter'], 'required inputmode="decimal"') ?></div>
                        <div class="col-6 col-md-3"><?= $lbl(__('admin.stock_material')) . $sel('inventory_material_id', $stockOptions, $f['inventory_material_id']) ?></div>
                        <div class="col-6 col-md-2"><?= $lbl(__('admin.sort_order')) . $in('sort_order', $f['sort_order'], 'type="number"') ?></div>
                        <div class="col-6 col-md-2 pt-md-4"><?= $chk('is_active', $f['is_active'], __('admin.active')) ?></div>
                    </div>
                    <div class="d-flex justify-content-end mt-2"><button class="btn btn-sm btn-bwa" type="submit"><?= e($isNew ? __('common.create') : __('common.save')) ?></button></div>
                </form>
            </details>
        <?php endforeach; ?>
    </div>

    <!-- ======================== builder options ======================== -->
    <div class="tab-pane fade<?= $tab === 'options' ? ' show active' : '' ?>" id="tab-options" role="tabpanel">
        <?php foreach ($groups as $g => $gLabel): ?>
            <h3 class="h6 text-uppercase small fw-bold mt-4 mb-2"><?= e($gLabel) ?></h3>
            <?php foreach (array_merge($byGroup[$g] ?? [], [null]) as $o): $isNew = $o === null; $o ??= ['id' => 0, 'option_group' => $g, 'code' => '', 'name' => '', 'width_cm' => '', 'height_cm' => '', 'panels' => '', 'price_type' => 'fixed', 'price_value' => '0.00', 'meta' => null, 'is_active' => 1, 'sort_order' => 0];
                $meta = $o['meta'] ? (json_decode((string) $o['meta'], true) ?: []) : []; ?>
                <details class="admin-card option-row mb-2" id="row-customization_option-<?= (int) $o['id'] ?>">
                    <summary class="card-body d-flex flex-wrap align-items-center gap-3">
                        <?php if ($isNew): ?>
                            <span class="fw-semibold text-gold small"><i class="bi bi-plus-lg me-1"></i><?= e(__('admin.add_option')) ?></span>
                        <?php else: ?>
                            <span class="fw-semibold"><?= e($o['name']) ?></span><code class="small"><?= e($o['code']) ?></code>
                            <span class="small text-muted-bwa"><?= e($priceTypes[$o['price_type']] ?? $o['price_type']) ?> · <?= e($o['price_type'] === 'percent' ? $o['price_value'] . ' %' : money($o['price_value'])) ?></span>
                            <?php if (!(int) $o['is_active']): ?><span class="badge text-bg-secondary"><?= e(__('admin.inactive')) ?></span><?php endif; ?>
                            <span class="ms-auto small" dir="rtl" lang="ar"><?= e($arOptions[(int) $o['id']] ?? '') ?></span>
                        <?php endif; ?>
                    </summary>
                    <form method="post" action="<?= e($isNew ? route('admin.options.options.store') : route('admin.options.options.update', ['id' => $o['id']])) ?>" class="card-body border-top" novalidate>
                        <?= csrf_field() ?><input type="hidden" name="option_group" value="<?= e($g) ?>">
                        <div class="row g-2">
                            <div class="col-6 col-md-2"><?= $lbl(__('admin.code')) . $in('code', $o['code'], 'required maxlength="60"') ?></div>
                            <div class="col-6 col-md-4"><?= $lbl(__('admin.name')) . $in('name', $o['name'], 'required maxlength="120"') ?></div>
                            <div class="col-6 col-md-3"><?= $lbl(__('admin.ar_name')) . $in('ar_name', $arOptions[(int) $o['id']] ?? '', 'dir="rtl" lang="ar"') ?></div>
                            <div class="col-6 col-md-3"><?= $lbl(__('admin.sort_order')) . $in('sort_order', $o['sort_order'], 'type="number"') ?></div>
                            <?php if ($g === 'size'): ?>
                                <div class="col-6 col-md-2"><?= $lbl(__('builder.width')) . $in('width_cm', $o['width_cm'], 'inputmode="decimal" required') ?></div>
                                <div class="col-6 col-md-2"><?= $lbl(__('builder.height')) . $in('height_cm', $o['height_cm'], 'inputmode="decimal" required') ?></div>
                            <?php elseif ($g === 'panel_layout'): ?>
                                <div class="col-6 col-md-2"><?= $lbl(__('product.panels')) . $in('panels', $o['panels'], 'type="number" min="1" max="5" required') ?></div>
                                <div class="col-6 col-md-2"><?= $lbl(__('admin.gap_cm')) . $in('gap_cm', $meta['gap_cm'] ?? 3, 'inputmode="decimal"') ?></div>
                            <?php endif; ?>
                            <div class="col-6 col-md-3"><?= $lbl(__('admin.price_type')) . $sel('price_type', $priceTypes, $o['price_type']) ?></div>
                            <div class="col-6 col-md-2"><?= $lbl(__('admin.price_value')) . $in('price_value', $o['price_value'], 'required inputmode="decimal"') ?></div>
                            <div class="col-6 col-md-1 pt-md-4"><?= $chk('is_active', $o['is_active'], __('admin.active')) ?></div>
                        </div>
                        <div class="d-flex justify-content-end mt-2"><button class="btn btn-sm btn-bwa" type="submit"><?= e($isNew ? __('common.create') : __('common.save')) ?></button></div>
                    </form>
                </details>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </div>
</div>
