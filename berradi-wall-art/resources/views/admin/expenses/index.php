<?php
use App\Controllers\Admin\ExpenseController;

$this->layout('layouts/admin', ['title' => __('admin.nav_expenses')]);
$e = $edit ?? null;
$cats = array_combine(ExpenseController::CATEGORIES, array_map(fn ($c) => __('finance.ec_' . $c), ExpenseController::CATEGORIES));
$prev = date('Y-m', strtotime($month . '-01 -1 month'));
$next = date('Y-m', strtotime($month . '-01 +1 month'));
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div class="d-flex align-items-center gap-2">
        <a class="btn btn-sm btn-outline-bwa" href="?month=<?= e($prev) ?>" aria-label="<?= e(__('finance.prev_month')) ?>"><i class="bi bi-chevron-<?= is_rtl() ? 'right' : 'left' ?>"></i></a>
        <strong><?= e(\App\Controllers\Admin\DashboardController::monthLabel($month)) ?></strong>
        <a class="btn btn-sm btn-outline-bwa" href="?month=<?= e($next) ?>" aria-label="<?= e(__('finance.next_month')) ?>"><i class="bi bi-chevron-<?= is_rtl() ? 'left' : 'right' ?>"></i></a>
    </div>
    <form method="get" class="filter-bar"><input type="hidden" name="month" value="<?= e($month) ?>">
        <select class="form-select" name="category" aria-label="<?= e(__('admin.category')) ?>"><option value=""><?= e(__('inventory.all_categories')) ?></option><?php foreach ($cats as $k => $l): ?><option value="<?= $k ?>" <?= $cat === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
        <button class="btn btn-outline-bwa" type="submit"><?= e(__('admin.apply')) ?></button>
    </form>
</div>
<div class="row g-4">
    <div class="col-xl-8">
        <div class="kpi-grid mb-3">
            <div class="kpi-tile"><div class="kpi-label"><i class="bi bi-wallet2"></i><?= e(__('finance.total_ttc_month')) ?></div><div class="kpi-value"><?= e(money($totals['ttc'])) ?></div></div>
            <div class="kpi-tile"><div class="kpi-label"><i class="bi bi-percent"></i><?= e(__('finance.vat_deductible')) ?></div><div class="kpi-value"><?= e(money($totals['tax'])) ?></div></div>
            <div class="kpi-tile"><div class="kpi-label"><i class="bi bi-calculator"></i><?= e(__('pdf.total_ht')) ?></div><div class="kpi-value"><?= e(money(\App\Helpers\Money::sub((string) $totals['ttc'], (string) $totals['tax']))) ?></div></div>
        </div>
        <div class="admin-card"><div class="table-responsive"><table class="table table-bwa">
            <thead><tr><th><?= e(__('admin.date')) ?></th><th><?= e(__('product.description')) ?></th><th><?= e(__('admin.category')) ?></th><th class="text-end"><?= e(__('finance.amount_ttc')) ?></th><th class="text-end"><?= e(__('inventory.vat')) ?></th><th></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr id="expense-<?= (int) $r['id'] ?>">
                    <td class="small text-nowrap"><?= e(format_date($r['expense_date'])) ?></td>
                    <td><?= e($r['description']) ?><div class="small text-muted-bwa"><?= e(implode(' · ', array_filter([(string) $r['supplier_name'], $r['reference'], $r['payment_method'] ? __('inventory.pm_' . $r['payment_method']) : null]))) ?></div></td>
                    <td class="small"><?= e($cats[$r['category']] ?? $r['category']) ?></td>
                    <td class="text-end text-nowrap"><?= e(money($r['amount'])) ?></td>
                    <td class="text-end small text-nowrap"><?= e(money($r['tax_amount'])) ?></td>
                    <td class="text-end text-nowrap">
                        <?php if ($r['attachment_path']): ?><a class="btn btn-sm btn-link" href="<?= e(route('admin.expenses.receipt', ['id' => $r['id']])) ?>" aria-label="<?= e(__('finance.receipt')) ?>"><i class="bi bi-paperclip"></i></a><?php endif; ?>
                        <a class="btn btn-sm btn-link" href="?month=<?= e($month) ?>&edit=<?= (int) $r['id'] ?>#expense-form" aria-label="<?= e(__('common.edit')) ?>"><i class="bi bi-pencil"></i></a>
                        <form class="d-inline" method="post" action="<?= e(route('admin.expenses.destroy', ['id' => $r['id']])) ?>" data-confirm="<?= e(__('common.confirm_delete')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-link text-danger" type="submit" aria-label="<?= e(__('account.delete_now')) ?>"><i class="bi bi-trash"></i></button></form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($rows === []): ?><tr><td colspan="6"><div class="empty-state"><i class="bi bi-wallet2"></i><?= e(__('finance.no_expenses')) ?></div></td></tr><?php endif; ?>
            </tbody>
        </table></div></div>
    </div>
    <div class="col-xl-4">
        <form method="post" action="<?= e($e ? route('admin.expenses.update', ['id' => $e['id']]) : route('admin.expenses.store')) ?>" enctype="multipart/form-data" class="admin-card mb-4" id="expense-form" novalidate>
            <?= csrf_field() ?>
            <div class="card-header"><h2><?= e($e ? __('finance.edit_expense') : __('finance.new_expense')) ?></h2><?php if ($e): ?><a class="small" href="?month=<?= e($month) ?>"><?= e(__('common.cancel')) ?></a><?php endif; ?></div>
            <div class="card-body">
                <?= $this->insert('partials/field', ['name' => 'expense_date', 'label' => __('admin.date'), 'type' => 'date', 'value' => $e['expense_date'] ?? date('Y-m-d'), 'required' => true]) ?>
                <?= $this->insert('partials/field', ['name' => 'category', 'label' => __('admin.category'), 'type' => 'select', 'options' => $cats, 'value' => $e['category'] ?? 'other']) ?>
                <?= $this->insert('partials/field', ['name' => 'description', 'label' => __('product.description'), 'value' => $e['description'] ?? '', 'required' => true]) ?>
                <div class="row g-2">
                    <div class="col-6"><?= $this->insert('partials/field', ['name' => 'amount', 'label' => __('finance.amount_ttc'), 'value' => $e['amount'] ?? '', 'required' => true, 'attrs' => 'inputmode="decimal"']) ?></div>
                    <div class="col-6"><?= $this->insert('partials/field', ['name' => 'tax_amount', 'label' => __('finance.vat_included'), 'value' => $e['tax_amount'] ?? '', 'attrs' => 'inputmode="decimal"', 'help' => __('finance.vat_included_help')]) ?></div>
                </div>
                <?= $this->insert('partials/field', ['name' => 'payment_method', 'label' => __('orders.payment_method'), 'type' => 'select', 'options' => ['' => '—'] + array_combine(['bank_transfer', 'cash', 'cheque', 'card', 'other'], array_map(fn ($m) => __('inventory.pm_' . $m), ['bank_transfer', 'cash', 'cheque', 'card', 'other'])), 'value' => $e['payment_method'] ?? '']) ?>
                <?= $this->insert('partials/field', ['name' => 'supplier_id', 'label' => __('inventory.supplier'), 'type' => 'select', 'options' => ['' => '—'] + array_column($suppliers, 'name', 'id'), 'value' => (string) ($e['supplier_id'] ?? '')]) ?>
                <?= $this->insert('partials/field', ['name' => 'reference', 'label' => __('orders.reference'), 'value' => $e['reference'] ?? '']) ?>
                <label class="form-label small" for="receipt"><?= e(__('finance.receipt')) ?></label>
                <input class="form-control mb-1<?= error('receipt') ? ' is-invalid' : '' ?>" type="file" id="receipt" name="receipt" accept=".pdf,.jpg,.jpeg,.png,.webp">
                <?php if (error('receipt')): ?><div class="invalid-feedback d-block"><?= e(error('receipt')) ?></div><?php endif; ?>
                <div class="form-text mb-3"><?= e(__('finance.receipt_help')) ?></div>
                <button class="btn btn-bwa w-100" type="submit"><?= e(__('common.save')) ?></button>
            </div>
        </form>
        <?php if ($byCat !== []): ?>
            <div class="admin-card"><div class="card-header"><h2><?= e(__('finance.by_category')) ?></h2></div><div class="card-body">
                <?php foreach ($byCat as $c): ?><div class="stat-line small"><span><?= e($cats[$c['category']] ?? $c['category']) ?></span><span><?= e(money($c['ttc'])) ?></span></div><?php endforeach; ?>
            </div></div>
        <?php endif; ?>
    </div>
</div>
