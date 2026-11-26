<?php
$this->layout('layouts/admin', ['title' => $p['purchase_number']]);
$open = in_array($p['status'], ['draft', 'ordered'], true);
$rest = round((float) $p['total'] - (float) $p['amount_paid'], 2);
$qty = fn ($v) => rtrim(rtrim((string) $v, '0'), '.');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <a class="small text-muted-bwa" href="<?= e(route('admin.purchases.index')) ?>"><i class="bi bi-arrow-<?= is_rtl() ? 'right' : 'left' ?> me-1"></i><?= e(__('admin.nav_purchases')) ?></a>
    <div class="page-actions">
        <span class="badge-status <?= status_class($p['status']) ?>"><?= e(__('inventory.ps_' . $p['status'])) ?></span>
        <?php if ($open): ?>
            <a class="btn btn-sm btn-outline-bwa" href="<?= e(route('admin.purchases.edit', ['id' => $p['id']])) ?>"><i class="bi bi-pencil me-1"></i><?= e(__('common.edit')) ?></a>
            <form method="post" action="<?= e(route('admin.purchases.receive', ['id' => $p['id']])) ?>" data-confirm="<?= e(__('inventory.receive_confirm')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-bwa" type="submit"><i class="bi bi-box-arrow-in-down me-1"></i><?= e(__('inventory.receive')) ?></button></form>
            <?php if ((float) $p['amount_paid'] == 0.0): ?><form method="post" action="<?= e(route('admin.purchases.cancel', ['id' => $p['id']])) ?>" data-confirm="<?= e(__('inventory.cancel_confirm')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger" type="submit"><?= e(__('inventory.cancel_purchase')) ?></button></form><?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<div class="row g-4">
    <div class="col-lg-8">
        <div class="admin-card">
            <div class="card-header"><h2><?= e($p['supplier_name']) ?></h2><span class="small text-muted-bwa"><?= e(format_date($p['purchase_date'])) ?><?= $p['supplier_invoice_ref'] ? ' · ' . e($p['supplier_invoice_ref']) : '' ?></span></div>
            <div class="table-responsive"><table class="table table-bwa">
                <thead><tr><th><?= e(__('product.description')) ?></th><th class="text-end"><?= e(__('inventory.quantity')) ?></th><th class="text-end"><?= e(__('inventory.unit_cost_ht')) ?></th><th class="text-end"><?= e(__('inventory.vat')) ?></th><th class="text-end"><?= e(__('inventory.total_ht')) ?></th></tr></thead>
                <tbody><?php foreach ($lines as $l): ?>
                    <tr><td><?= e($l['description']) ?><?= $l['material_id'] ? '<div class="small text-muted-bwa"><a href="' . e(route('admin.inventory.show', ['id' => $l['material_id']])) . '">' . e((string) $l['sku']) . '</a></div>' : '<div class="small text-muted-bwa">' . e(__('inventory.not_stocked')) . '</div>' ?></td>
                        <td class="text-end text-nowrap"><?= e($qty($l['quantity'])) ?> <?= $l['unit'] ? e(__('inventory.unit_' . $l['unit'])) : '' ?></td>
                        <td class="text-end text-nowrap"><?= e(number_format((float) $l['unit_cost'], 4, ',', ' ')) ?></td>
                        <td class="text-end"><?= e(rtrim(rtrim(number_format((float) $l['tax_rate'] * 100, 2, '.', ''), '0'), '.')) ?> %</td>
                        <td class="text-end text-nowrap"><?= e(money($l['line_total'])) ?></td></tr>
                <?php endforeach; ?></tbody>
            </table></div>
            <div class="card-body border-top"><div class="ms-auto" style="max-width:320px">
                <div class="stat-line"><span><?= e(__('inventory.total_ht')) ?></span><span><?= e(money($p['subtotal'])) ?></span></div>
                <div class="stat-line"><span><?= e(__('inventory.vat')) ?></span><span><?= e(money($p['tax_total'])) ?></span></div>
                <div class="stat-line fw-bold"><span><?= e(__('inventory.total_ttc')) ?></span><span><?= e(money($p['total'])) ?></span></div>
                <div class="stat-line"><span><?= e(__('orders.paid')) ?></span><span><?= e(money($p['amount_paid'])) ?></span></div>
                <div class="stat-line"><span><?= e(__('inventory.remaining')) ?></span><span class="<?= $rest > 0 ? 'text-danger fw-semibold' : '' ?>"><?= e(money($rest)) ?></span></div>
            </div></div>
        </div>
        <?php if ($p['notes']): ?><div class="admin-card mt-4"><div class="card-body small" style="white-space:pre-line"><?= e($p['notes']) ?></div></div><?php endif; ?>
    </div>
    <div class="col-lg-4">
        <div class="admin-card">
            <div class="card-header"><h2><?= e(__('inventory.supplier_payments')) ?></h2></div>
            <div class="card-body">
                <?php foreach ($payments as $sp): ?><div class="stat-line small"><span><?= e(format_date($sp['paid_at'])) ?> · <?= e(__('inventory.pm_' . $sp['payment_method'])) ?><?= $sp['reference'] ? ' · ' . e($sp['reference']) : '' ?></span><span><?= e(money($sp['amount'])) ?></span></div><?php endforeach; ?>
                <?php if ($payments === []): ?><p class="small text-muted-bwa"><?= e(__('orders.no_payment')) ?></p><?php endif; ?>
                <?php if ($p['status'] !== 'cancelled' && $rest > 0): ?>
                    <form method="post" action="<?= e(route('admin.purchases.pay', ['id' => $p['id']])) ?>" class="mt-3" novalidate>
                        <?= csrf_field() ?>
                        <div class="row g-2">
                            <div class="col-6"><label class="form-label small" for="amount"><?= e(__('orders.amount')) ?></label><input class="form-control form-control-sm" id="amount" name="amount" value="<?= e(number_format($rest, 2, '.', '')) ?>" inputmode="decimal"></div>
                            <div class="col-6"><label class="form-label small" for="paid_at"><?= e(__('admin.date')) ?></label><input class="form-control form-control-sm" id="paid_at" name="paid_at" type="date" value="<?= date('Y-m-d') ?>"></div>
                            <div class="col-6"><label class="form-label small" for="payment_method"><?= e(__('orders.payment_method')) ?></label><select class="form-select form-select-sm" id="payment_method" name="payment_method"><?php foreach (['bank_transfer', 'cash', 'cheque', 'card', 'other'] as $pm): ?><option value="<?= $pm ?>"><?= e(__('inventory.pm_' . $pm)) ?></option><?php endforeach; ?></select></div>
                            <div class="col-6"><label class="form-label small" for="reference"><?= e(__('orders.reference')) ?></label><input class="form-control form-control-sm" id="reference" name="reference" maxlength="120"></div>
                        </div>
                        <button class="btn btn-sm btn-outline-bwa w-100 mt-2" type="submit"><?= e(__('inventory.record_payment')) ?></button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($p['received_at']): ?><p class="small text-muted-bwa mt-3"><i class="bi bi-box-arrow-in-down me-1"></i><?= e(__('inventory.received_on', ['date' => format_date($p['received_at'], true)])) ?></p><?php endif; ?>
    </div>
</div>
