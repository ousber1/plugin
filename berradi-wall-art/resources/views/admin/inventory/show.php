<?php
$this->layout('layouts/admin', ['title' => $m['name']]);
$manage = can('inventory.manage');
$qty = fn ($v) => ($s = rtrim(rtrim(ltrim((string) $v, '+'), '0'), '.')) === '' || $s === '-' ? '0' : $s;
$unit = __('inventory.unit_' . $m['unit']);
$isLow = (float) $m['min_stock'] > 0 && (float) $m['stock_quantity'] <= (float) $m['min_stock'];
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <a class="small text-muted-bwa" href="<?= e(route('admin.inventory.index')) ?>"><i class="bi bi-arrow-<?= is_rtl() ? 'right' : 'left' ?> me-1"></i><?= e(__('admin.nav_inventory')) ?></a>
    <?php if ($manage): ?><a class="btn btn-sm btn-outline-bwa" href="<?= e(route('admin.inventory.edit', ['id' => $m['id']])) ?>"><i class="bi bi-pencil me-1"></i><?= e(__('common.edit')) ?></a><?php endif; ?>
</div>
<div class="kpi-grid mb-4">
    <div class="kpi-tile"><div class="kpi-label"><i class="bi bi-box-seam"></i><?= e(__('admin.stock')) ?></div><div class="kpi-value<?= $isLow ? ' text-danger' : '' ?>"><?= e($qty($m['stock_quantity'])) ?> <small class="fs-6"><?= e($unit) ?></small></div><div class="kpi-sub"><?= e(__('inventory.min')) ?> : <?= e($qty($m['min_stock'])) ?></div></div>
    <div class="kpi-tile"><div class="kpi-label"><i class="bi bi-calculator"></i><?= e(__('inventory.wac')) ?></div><div class="kpi-value"><?= e(number_format((float) $m['unit_cost'], 4, ',', ' ')) ?> <small class="fs-6">DH / <?= e($unit) ?></small></div><div class="kpi-sub"><?= e(__('inventory.wac_help')) ?></div></div>
    <div class="kpi-tile"><div class="kpi-label"><i class="bi bi-cash-stack"></i><?= e(__('inventory.value')) ?></div><div class="kpi-value"><?= e(money(round(max(0, (float) $m['stock_quantity']) * (float) $m['unit_cost'], 2))) ?></div></div>
</div>
<div class="row g-4">
    <div class="col-lg-8">
        <div class="admin-card">
            <div class="card-header"><h2><?= e(__('inventory.movements')) ?></h2></div>
            <div class="table-responsive"><table class="table table-bwa">
                <thead><tr><th><?= e(__('admin.date')) ?></th><th><?= e(__('admin.type')) ?></th><th class="text-end"><?= e(__('inventory.quantity')) ?></th><th class="text-end"><?= e(__('inventory.balance')) ?></th><th class="text-end"><?= e(__('inventory.cost')) ?></th><th><?= e(__('inventory.reference')) ?></th></tr></thead>
                <tbody>
                <?php foreach ($moves->items as $s): $neg = str_starts_with((string) $s['quantity'], '-'); ?>
                    <tr>
                        <td class="small text-nowrap"><?= e(format_date($s['created_at'], true)) ?><div class="text-muted-bwa"><?= e((string) $s['user_name']) ?></div></td>
                        <td><span class="badge-status <?= $neg ? 'is-muted' : 'is-success' ?>"><?= e(__('inventory.mv_' . $s['movement_type'])) ?></span></td>
                        <td class="text-end text-nowrap <?= $neg ? 'text-danger' : 'text-success' ?>"><?= $neg ? '' : '+' ?><?= e($qty($s['quantity'])) ?></td>
                        <td class="text-end text-nowrap <?= str_starts_with((string) $s['balance_after'], '-') ? 'text-danger fw-semibold' : '' ?>"><?= e($qty($s['balance_after'])) ?></td>
                        <td class="text-end small text-nowrap"><?= e(money($s['total_cost'])) ?></td>
                        <td class="small">
                            <?php if ($s['purchase_number']): ?><a href="<?= e(route('admin.purchases.show', ['id' => $s['purchase_id']])) ?>"><?= e($s['purchase_number']) ?></a><?php endif; ?>
                            <?php if ($s['job_number']): ?><a href="<?= e(route('admin.production.show', ['id' => $s['production_job_id']])) ?>"><?= e($s['job_number']) ?></a><?php endif; ?>
                            <?= $s['order_number'] && !$s['job_number'] ? e($s['order_number']) : '' ?>
                            <?php if ($s['notes'] && !$s['job_number'] && !$s['purchase_number']): ?><span class="text-muted-bwa"><?= e($s['notes']) ?></span><?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($moves->items === []): ?><tr><td colspan="6"><div class="empty-state"><i class="bi bi-arrow-left-right"></i><?= e(__('inventory.no_movements')) ?></div></td></tr><?php endif; ?>
                </tbody>
            </table></div>
            <?php if ($moves->hasPages()): ?><div class="p-3"><?= $moves->links() ?></div><?php endif; ?>
        </div>
    </div>
    <div class="col-lg-4">
        <?php if ($manage): ?>
            <form method="post" action="<?= e(route('admin.inventory.movement', ['id' => $m['id']])) ?>" class="admin-card mb-4" novalidate>
                <?= csrf_field() ?>
                <div class="card-header"><h2><?= e(__('inventory.new_movement')) ?></h2></div>
                <div class="card-body">
                    <?= $this->insert('partials/field', ['name' => 'type', 'label' => __('admin.type'), 'type' => 'select', 'options' => ['in' => __('inventory.mv_in'), 'out' => __('inventory.mv_out'), 'waste' => __('inventory.mv_waste'), 'return' => __('inventory.mv_return'), 'count' => __('inventory.mv_count')]]) ?>
                    <?= $this->insert('partials/field', ['name' => 'quantity', 'label' => __('inventory.quantity') . ' (' . $unit . ')', 'required' => true, 'help' => __('inventory.quantity_help'), 'attrs' => 'inputmode="decimal"']) ?>
                    <?= $this->insert('partials/field', ['name' => 'unit_cost', 'label' => __('inventory.unit_cost_ht'), 'help' => __('inventory.unit_cost_help'), 'attrs' => 'inputmode="decimal"']) ?>
                    <?= $this->insert('partials/field', ['name' => 'notes', 'label' => __('inventory.reason'), 'attrs' => 'maxlength="500"']) ?>
                    <button class="btn btn-bwa w-100" type="submit"><?= e(__('common.save')) ?></button>
                </div>
            </form>
        <?php endif; ?>
        <div class="admin-card"><div class="card-body small">
            <div class="stat-line"><span>SKU</span><span><code><?= e($m['sku']) ?></code></span></div>
            <div class="stat-line"><span><?= e(__('admin.category')) ?></span><span><?= e(__('inventory.cat_' . $m['category'])) ?></span></div>
            <div class="stat-line"><span><?= e(__('inventory.supplier')) ?></span><span><?= e((string) ($m['supplier_name'] ?? '—')) ?></span></div>
            <div class="stat-line"><span><?= e(__('inventory.location')) ?></span><span><?= e((string) ($m['location'] ?: '—')) ?></span></div>
            <div class="stat-line"><span><?= e(__('inventory.used_by')) ?></span><span><?= $usedBy ? e(implode(', ', array_column($usedBy, 'name'))) : '—' ?></span></div>
            <?php if (!$usedBy): ?><p class="text-muted-bwa mt-2 mb-0"><?= e(__('inventory.link_help')) ?></p><?php endif; ?>
            <?php if ($m['notes']): ?><p class="mt-2 mb-0" style="white-space:pre-line"><?= e($m['notes']) ?></p><?php endif; ?>
        </div></div>
    </div>
</div>
