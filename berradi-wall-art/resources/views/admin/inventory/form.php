<?php
use App\Controllers\Admin\InventoryController;

$isEdit = $m !== null;
$r = $m ?? [];
$this->layout('layouts/admin', ['title' => $isEdit ? $r['name'] : __('inventory.new_material')]);
$v = fn (string $k, mixed $d = '') => $r[$k] ?? $d;
?>
<a class="small text-muted-bwa d-inline-block mb-3" href="<?= e($isEdit ? route('admin.inventory.show', ['id' => $r['id']]) : route('admin.inventory.index')) ?>"><i class="bi bi-arrow-<?= is_rtl() ? 'right' : 'left' ?> me-1"></i><?= e(__('admin.nav_inventory')) ?></a>
<form method="post" action="<?= e($isEdit ? route('admin.inventory.update', ['id' => $r['id']]) : route('admin.inventory.store')) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="row g-4">
        <div class="col-lg-8"><div class="admin-card"><div class="card-body">
            <div class="row g-2">
                <div class="col-md-4"><?= $this->insert('partials/field', ['name' => 'sku', 'label' => 'SKU', 'value' => $v('sku'), 'required' => true]) ?></div>
                <div class="col-md-8"><?= $this->insert('partials/field', ['name' => 'name', 'label' => __('admin.name'), 'value' => $v('name'), 'required' => true]) ?></div>
                <div class="col-md-4"><?= $this->insert('partials/field', ['name' => 'category', 'label' => __('admin.category'), 'type' => 'select', 'options' => array_combine(InventoryController::CATEGORIES, array_map(fn ($c) => __('inventory.cat_' . $c), InventoryController::CATEGORIES)), 'value' => $v('category', 'canvas')]) ?></div>
                <div class="col-md-4"><?= $this->insert('partials/field', ['name' => 'unit', 'label' => __('inventory.unit'), 'type' => 'select', 'options' => array_combine(InventoryController::UNITS, array_map(fn ($u) => __('inventory.unit_' . $u), InventoryController::UNITS)), 'value' => $v('unit', 'm2'), 'help' => __('inventory.unit_help')]) ?></div>
                <div class="col-md-4"><?= $this->insert('partials/field', ['name' => 'min_stock', 'label' => __('inventory.min'), 'value' => $v('min_stock', '0'), 'attrs' => 'inputmode="decimal"', 'help' => __('inventory.min_help')]) ?></div>
                <div class="col-md-6"><?= $this->insert('partials/field', ['name' => 'supplier_id', 'label' => __('inventory.supplier'), 'type' => 'select', 'options' => ['' => '—'] + array_column($suppliers, 'name', 'id'), 'value' => (string) $v('supplier_id')]) ?></div>
                <div class="col-md-6"><?= $this->insert('partials/field', ['name' => 'location', 'label' => __('inventory.location'), 'value' => $v('location')]) ?></div>
            </div>
            <?= $this->insert('partials/field', ['name' => 'notes', 'label' => __('admin.internal_notes'), 'type' => 'textarea', 'rows' => 3, 'value' => $v('notes')]) ?>
        </div></div></div>
        <div class="col-lg-4"><div class="admin-card"><div class="card-body">
            <?= $this->insert('partials/field', ['name' => 'is_active', 'label' => __('admin.active'), 'type' => 'checkbox', 'value' => $v('is_active', 1)]) ?>
            <?php if (!$isEdit): ?>
                <div class="form-section">
                    <h3><?= e(__('inventory.opening_stock')) ?></h3>
                    <?= $this->insert('partials/field', ['name' => 'initial_stock', 'label' => __('inventory.quantity'), 'attrs' => 'inputmode="decimal"']) ?>
                    <?= $this->insert('partials/field', ['name' => 'unit_cost', 'label' => __('inventory.unit_cost_ht'), 'attrs' => 'inputmode="decimal"']) ?>
                </div>
            <?php else: ?>
                <p class="small text-muted-bwa"><?= e(__('inventory.stock_via_movements')) ?></p>
            <?php endif; ?>
        </div></div></div>
    </div>
    <div class="admin-sticky-save"><button class="btn btn-bwa btn-lg" type="submit"><i class="bi bi-check2 me-1"></i><?= e(__('common.save')) ?></button></div>
</form>
