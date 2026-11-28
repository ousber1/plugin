<?php
use App\Controllers\Admin\InventoryController;

$this->layout('layouts/admin', ['title' => __('admin.nav_inventory')]);
$manage = can('inventory.manage');
$qty = fn ($v) => rtrim(rtrim((string) $v, '0'), '.') ?: '0';
?>
<div class="kpi-grid mb-3">
    <div class="kpi-tile"><div class="kpi-label"><i class="bi bi-box-seam"></i><?= e(__('inventory.materials')) ?></div><div class="kpi-value"><?= count($rows) ?></div></div>
    <div class="kpi-tile"><div class="kpi-label"><i class="bi bi-cash-stack"></i><?= e(__('inventory.stock_value')) ?></div><div class="kpi-value"><?= e(money($value)) ?></div><div class="kpi-sub"><?= e(__('inventory.value_help')) ?></div></div>
    <div class="kpi-tile"><div class="kpi-label"><i class="bi bi-exclamation-triangle"></i><?= e(__('admin.low_stock')) ?></div><div class="kpi-value<?= $lowCount > 0 ? ' text-danger' : '' ?>"><?= (int) $lowCount ?></div></div>
</div>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <form method="get" class="filter-bar" role="search">
        <input class="form-control" type="search" name="q" value="<?= e($q) ?>" placeholder="<?= e(__('inventory.search')) ?>" aria-label="<?= e(__('inventory.search')) ?>">
        <select class="form-select" name="category" aria-label="<?= e(__('admin.category')) ?>"><option value=""><?= e(__('inventory.all_categories')) ?></option><?php foreach (InventoryController::CATEGORIES as $c): ?><option value="<?= $c ?>" <?= $cat === $c ? 'selected' : '' ?>><?= e(__('inventory.cat_' . $c)) ?></option><?php endforeach; ?></select>
        <label class="form-check mb-0"><input class="form-check-input" type="checkbox" name="low" value="1" <?= $low ? 'checked' : '' ?>> <?= e(__('admin.low_stock')) ?></label>
        <button class="btn btn-outline-bwa" type="submit"><?= e(__('admin.apply')) ?></button>
    </form>
    <?php if ($manage): ?><a class="btn btn-bwa" href="<?= e(route('admin.inventory.create')) ?>"><i class="bi bi-plus-lg me-1"></i><?= e(__('inventory.new_material')) ?></a><?php endif; ?>
</div>
<div class="admin-card">
    <div class="table-responsive">
        <table class="table table-bwa">
            <thead><tr><th><?= e(__('admin.name')) ?></th><th><?= e(__('admin.category')) ?></th><th class="text-end"><?= e(__('admin.stock')) ?></th><th class="text-end"><?= e(__('inventory.min')) ?></th><th class="text-end"><?= e(__('inventory.wac')) ?></th><th class="text-end"><?= e(__('inventory.value')) ?></th><th><?= e(__('inventory.supplier')) ?></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $m): $isLow = (float) $m['min_stock'] > 0 && (float) $m['stock_quantity'] <= (float) $m['min_stock']; ?>
                <tr class="<?= (int) $m['is_active'] ? '' : 'opacity-50' ?>">
                    <td><a class="fw-semibold" href="<?= e(route('admin.inventory.show', ['id' => $m['id']])) ?>"><?= e($m['name']) ?></a><div class="small text-muted-bwa"><code><?= e($m['sku']) ?></code><?= $m['location'] ? ' · ' . e($m['location']) : '' ?></div></td>
                    <td class="small"><?= e(__('inventory.cat_' . $m['category'])) ?></td>
                    <td class="text-end text-nowrap <?= $isLow ? 'text-danger fw-semibold' : '' ?>"><?= e($qty($m['stock_quantity'])) ?> <?= e(__('inventory.unit_' . $m['unit'])) ?><?= $isLow ? ' <i class="bi bi-exclamation-triangle-fill"></i>' : '' ?></td>
                    <td class="text-end small"><?= e($qty($m['min_stock'])) ?></td>
                    <td class="text-end small text-nowrap"><?= e(money(round((float) $m['unit_cost'], 2))) ?></td>
                    <td class="text-end text-nowrap"><?= e(money(round(max(0, (float) $m['stock_value']), 2))) ?></td>
                    <td class="small"><?= e((string) $m['supplier_name']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($rows === []): ?><tr><td colspan="7"><div class="empty-state"><i class="bi bi-box-seam"></i><?= e(__('inventory.empty')) ?></div></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
