<?php $this->layout('layouts/admin', ['title' => __('admin.nav_payments')]); ?>
<div class="kpi-grid mb-3">
    <div class="kpi-tile"><div class="kpi-label"><i class="bi bi-arrow-down-circle"></i><?= e(__('finance.cash_in')) ?></div><div class="kpi-value"><?= e(money($sum['inflow'])) ?></div></div>
    <div class="kpi-tile"><div class="kpi-label"><i class="bi bi-arrow-up-circle"></i><?= e(__('finance.refunds')) ?></div><div class="kpi-value"><?= e(money($sum['outflow'])) ?></div></div>
    <div class="kpi-tile"><div class="kpi-label"><i class="bi bi-cash-coin"></i><?= e(__('finance.net')) ?></div><div class="kpi-value"><?= e(money(\App\Helpers\Money::sub((string) $sum['inflow'], (string) $sum['outflow']))) ?></div><div class="kpi-sub"><?= e(__('finance.filtered')) ?></div></div>
</div>
<form method="get" class="filter-bar mb-3" role="search">
    <input class="form-control" type="search" name="q" value="<?= e($f['q']) ?>" placeholder="<?= e(__('finance.search_payments')) ?>" aria-label="<?= e(__('finance.search_payments')) ?>">
    <select class="form-select" name="type" aria-label="<?= e(__('admin.type')) ?>"><option value=""><?= e(__('finance.all_types')) ?></option><option value="payment" <?= $f['type'] === 'payment' ? 'selected' : '' ?>><?= e(__('finance.pt_payment')) ?></option><option value="refund" <?= $f['type'] === 'refund' ? 'selected' : '' ?>><?= e(__('finance.pt_refund')) ?></option></select>
    <select class="form-select" name="method" aria-label="<?= e(__('orders.payment_method')) ?>"><option value=""><?= e(__('finance.all_methods')) ?></option><?php foreach ($methods as $m): ?><option value="<?= (int) $m['id'] ?>" <?= $f['method'] === (int) $m['id'] ? 'selected' : '' ?>><?= e($m['name']) ?></option><?php endforeach; ?></select>
    <input class="form-control" type="date" name="from" value="<?= e($f['from']) ?>" aria-label="<?= e(__('orders.from')) ?>">
    <input class="form-control" type="date" name="to" value="<?= e($f['to']) ?>" aria-label="<?= e(__('orders.to')) ?>">
    <button class="btn btn-outline-bwa" type="submit"><?= e(__('admin.apply')) ?></button>
</form>
<div class="admin-card">
    <div class="table-responsive"><table class="table table-bwa">
        <thead><tr><th>N°</th><th><?= e(__('admin.date')) ?></th><th><?= e(__('finance.document')) ?></th><th><?= e(__('orders.payment_method')) ?></th><th><?= e(__('orders.reference')) ?></th><th class="text-end"><?= e(__('orders.amount')) ?></th></tr></thead>
        <tbody>
        <?php foreach ($rows->items as $p): $ref = $p['payment_type'] === 'refund'; ?>
            <tr>
                <td class="text-nowrap"><code class="small"><?= e($p['payment_number']) ?></code><?= $ref ? ' <span class="badge-status is-danger">' . e(__('finance.pt_refund')) . '</span>' : '' ?></td>
                <td class="small text-nowrap"><?= e(format_date((string) $p['paid_at'], true)) ?><div class="text-muted-bwa"><?= e((string) $p['user_name']) ?></div></td>
                <td class="small"><?php if ($p['oid'] && (can('orders.view') || can('orders.manage'))): ?><a href="<?= e(route('admin.orders.show', ['id' => $p['oid']])) ?>"><?= e($p['order_number']) ?></a><?php elseif ($p['iid']): ?><a href="<?= e(route('admin.invoices.show', ['id' => $p['iid']])) ?>"><?= e((string) $p['invoice_number']) ?></a><?php else: ?><?= e((string) ($p['order_number'] ?? $p['invoice_number'] ?? '—')) ?><?php endif; ?><div class="text-muted-bwa"><?= e((string) $p['customer']) ?></div></td>
                <td class="small"><?= e((string) $p['method_name']) ?></td>
                <td class="small"><?= e((string) ($p['reference'] ?: $p['notes'])) ?></td>
                <td class="text-end text-nowrap <?= $ref ? 'text-danger' : '' ?>"><?= $ref ? '−' : '' ?><?= e(money($p['amount'])) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($rows->items === []): ?><tr><td colspan="6"><div class="empty-state"><i class="bi bi-cash-coin"></i><?= e(__('orders.no_payment')) ?></div></td></tr><?php endif; ?>
        </tbody>
    </table></div>
    <?php if ($rows->hasPages()): ?><div class="p-3"><?= $rows->links() ?></div><?php endif; ?>
</div>
<p class="small text-muted-bwa mt-3"><?= e(__('finance.ledger_help')) ?></p>
