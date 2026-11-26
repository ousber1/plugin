<?php
$isEdit = $p !== null;
$this->layout('layouts/admin', ['title' => $isEdit ? $p['purchase_number'] : __('inventory.new_purchase')]);
$old = old('lines', null);
$src = is_array($old) ? array_values($old) : ($lines !== [] ? $lines : []);
$rows = max(3, count($src) + 1);
$val = function (int $i, string $k) use ($src, $old): string {
    if (!isset($src[$i][$k])) {
        return $k === 'tax_rate' ? '' : '';
    }
    $v = (string) $src[$i][$k];
    if ($k === 'tax_rate' && !is_array($old)) {
        return rtrim(rtrim(number_format((float) $v * 100, 2, '.', ''), '0'), '.');
    }
    return $v;
};
?>
<a class="small text-muted-bwa d-inline-block mb-3" href="<?= e(route('admin.purchases.index')) ?>"><i class="bi bi-arrow-<?= is_rtl() ? 'right' : 'left' ?> me-1"></i><?= e(__('admin.nav_purchases')) ?></a>
<?php if (error('lines')): ?><div class="alert alert-danger"><?= e(error('lines')) ?></div><?php endif; ?>
<?php if ($suppliers === []): ?><div class="alert alert-warning"><?= e(__('inventory.need_supplier')) ?> <a href="<?= e(route('admin.suppliers.index')) ?>"><?= e(__('admin.nav_suppliers')) ?></a></div><?php endif; ?>
<form method="post" action="<?= e($isEdit ? route('admin.purchases.update', ['id' => $p['id']]) : route('admin.purchases.store')) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="admin-card mb-4"><div class="card-body"><div class="row g-2">
        <div class="col-md-4"><?= $this->insert('partials/field', ['name' => 'supplier_id', 'label' => __('inventory.supplier'), 'type' => 'select', 'options' => array_column($suppliers, 'name', 'id'), 'value' => (string) ($p['supplier_id'] ?? ''), 'required' => true]) ?></div>
        <div class="col-md-3"><?= $this->insert('partials/field', ['name' => 'purchase_date', 'label' => __('admin.date'), 'type' => 'date', 'value' => $p['purchase_date'] ?? date('Y-m-d'), 'required' => true]) ?></div>
        <div class="col-md-3"><?= $this->insert('partials/field', ['name' => 'supplier_invoice_ref', 'label' => __('inventory.supplier_ref'), 'value' => (string) ($p['supplier_invoice_ref'] ?? '')]) ?></div>
        <div class="col-md-2"><?= $this->insert('partials/field', ['name' => 'status', 'label' => __('admin.status'), 'type' => 'select', 'options' => ['draft' => __('inventory.ps_draft'), 'ordered' => __('inventory.ps_ordered')], 'value' => $p['status'] ?? 'draft']) ?></div>
    </div></div></div>
    <div class="admin-card mb-4">
        <div class="card-header"><h2><?= e(__('inventory.lines')) ?></h2><button class="btn btn-sm btn-outline-bwa" type="button" data-repeat-add="#purchase-lines"><i class="bi bi-plus-lg me-1"></i><?= e(__('orders.add_line')) ?></button></div>
        <div class="card-body" id="purchase-lines" data-repeater>
            <p class="small text-muted-bwa"><?= e(__('inventory.lines_help')) ?></p>
            <?php for ($i = 0; $i < $rows; $i++): ?>
                <div class="line-row" data-repeat-row>
                    <div class="row g-2">
                        <div class="col-md-4"><label class="form-label small"><?= e(__('inventory.material')) ?></label>
                            <select class="form-select form-select-sm" name="lines[<?= $i ?>][material_id]" data-name="lines[__i__][material_id]"><option value=""><?= e(__('inventory.not_stocked')) ?></option><?php foreach ($materials as $m): ?><option value="<?= (int) $m['id'] ?>" <?= $val($i, 'material_id') === (string) $m['id'] ? 'selected' : '' ?>><?= e($m['name']) ?> (<?= e(__('inventory.unit_' . $m['unit'])) ?>)</option><?php endforeach; ?></select></div>
                        <div class="col-md-3"><label class="form-label small"><?= e(__('product.description')) ?></label><input class="form-control form-control-sm" name="lines[<?= $i ?>][description]" data-name="lines[__i__][description]" value="<?= e($val($i, 'description')) ?>" maxlength="255"></div>
                        <div class="col-4 col-md-2"><label class="form-label small"><?= e(__('inventory.quantity')) ?></label><input class="form-control form-control-sm" name="lines[<?= $i ?>][quantity]" data-name="lines[__i__][quantity]" value="<?= e($val($i, 'quantity')) ?>" inputmode="decimal"></div>
                        <div class="col-4 col-md-2"><label class="form-label small"><?= e(__('inventory.unit_cost_ht')) ?></label><input class="form-control form-control-sm" name="lines[<?= $i ?>][unit_cost]" data-name="lines[__i__][unit_cost]" value="<?= e($val($i, 'unit_cost')) ?>" inputmode="decimal"></div>
                        <div class="col-4 col-md-1"><label class="form-label small"><?= e(__('inventory.vat')) ?></label><input class="form-control form-control-sm" name="lines[<?= $i ?>][tax_rate]" data-name="lines[__i__][tax_rate]" value="<?= e($val($i, 'tax_rate') !== '' ? $val($i, 'tax_rate') : ($i < count($src) ? '0' : $taxRate)) ?>" inputmode="decimal"></div>
                    </div>
                </div>
            <?php endfor; ?>
        </div>
    </div>
    <div class="admin-card"><div class="card-body"><?= $this->insert('partials/field', ['name' => 'notes', 'label' => __('admin.internal_notes'), 'type' => 'textarea', 'rows' => 2, 'value' => (string) ($p['notes'] ?? '')]) ?></div></div>
    <div class="admin-sticky-save"><button class="btn btn-bwa btn-lg" type="submit"><i class="bi bi-check2 me-1"></i><?= e(__('common.save')) ?></button></div>
</form>
