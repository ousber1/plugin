<?php
$this->layout('layouts/admin', ['title' => __('admin.nav_orders')]);
use App\Services\Sales\OrderStatus;
$tabs = ['' => __('orders.all'), 'open' => __('orders.open')] + array_combine(OrderStatus::ALL, array_map([OrderStatus::class, 'label'], OrderStatus::ALL));
$qs = fn (array $over) => '?' . http_build_query(array_filter(array_merge($f, $over), fn ($v) => $v !== '' && $v !== null));
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <form method="get" class="filter-bar" role="search">
        <input type="hidden" name="status" value="<?= e($f['status']) ?>">
        <input class="form-control" type="search" name="q" value="<?= e($f['q']) ?>" placeholder="<?= e(__('orders.search')) ?>" aria-label="<?= e(__('orders.search')) ?>">
        <select class="form-select" name="payment" aria-label="<?= e(__('admin.payment')) ?>">
            <option value=""><?= e(__('orders.all_payments')) ?></option>
            <?php foreach (['unpaid', 'partial', 'paid', 'refunded'] as $p): ?><option value="<?= $p ?>" <?= $f['payment'] === $p ? 'selected' : '' ?>><?= e(__('account.payment_' . $p)) ?></option><?php endforeach; ?>
        </select>
        <select class="form-select" name="source" aria-label="<?= e(__('orders.source')) ?>">
            <option value=""><?= e(__('orders.all_sources')) ?></option>
            <?php foreach (['storefront', 'phone', 'whatsapp', 'admin', 'quotation'] as $s): ?><option value="<?= $s ?>" <?= $f['source'] === $s ? 'selected' : '' ?>><?= e(__('orders.source_' . $s)) ?></option><?php endforeach; ?>
        </select>
        <input class="form-control" type="date" name="from" value="<?= e($f['from']) ?>" aria-label="<?= e(__('orders.from')) ?>">
        <input class="form-control" type="date" name="to" value="<?= e($f['to']) ?>" aria-label="<?= e(__('orders.to')) ?>">
        <button class="btn btn-outline-bwa" type="submit"><?= e(__('admin.apply')) ?></button>
    </form>
    <?php if (can('orders.create')): ?><a class="btn btn-bwa" href="<?= e(route('admin.orders.create')) ?>"><i class="bi bi-telephone-plus me-1"></i><?= e(__('orders.new_manual')) ?></a><?php endif; ?>
</div>
<ul class="nav settings-tabs border-bottom mb-3 flex-nowrap overflow-auto">
    <?php foreach ($tabs as $k => $label):
        $n = $k === '' ? array_sum($counts) : ($k === 'open' ? array_sum(array_diff_key($counts, array_flip(['delivered', 'cancelled', 'returned']))) : ($counts[$k] ?? 0));
        if ($k !== '' && $k !== 'open' && $n === 0) { continue; } ?>
        <li class="nav-item"><a class="nav-link text-nowrap<?= $f['status'] === $k ? ' active' : '' ?>" href="<?= e($qs(['status' => $k, 'page' => null])) ?>"><?= e($label) ?> <span class="text-muted-bwa">(<?= $n ?>)</span></a></li>
    <?php endforeach; ?>
</ul>
<div class="admin-card">
    <div class="table-responsive">
        <table class="table table-bwa">
            <thead><tr><th><?= e(__('orders.number')) ?></th><th><?= e(__('admin.customer')) ?></th><th><?= e(__('admin.status')) ?></th><th><?= e(__('admin.payment')) ?></th><th class="text-end"><?= e(__('admin.total')) ?></th><th><?= e(__('orders.source')) ?></th></tr></thead>
            <tbody>
            <?php foreach ($orders->items as $o): ?>
                <tr>
                    <td class="text-nowrap"><a class="fw-semibold" href="<?= e(route('admin.orders.show', ['id' => $o['id']])) ?>"><?= e($o['order_number']) ?></a><div class="small text-muted-bwa"><?= e(format_date($o['placed_at'], true)) ?></div></td>
                    <td><?= e($o['customer_name']) ?><div class="small text-muted-bwa"><span dir="ltr"><?= e($o['customer_phone']) ?></span> · <?= e($o['shipping_city']) ?></div></td>
                    <td><span class="badge-status <?= status_class($o['status']) ?>"><?= e(OrderStatus::label($o['status'])) ?></span><?php if ((int) $o['artwork_pending'] > 0 && !in_array($o['status'], ['cancelled', 'returned'], true)): ?><div class="small text-gold mt-1"><i class="bi bi-palette me-1"></i><?= e(__('orders.artwork_to_check', ['n' => (int) $o['artwork_pending']])) ?></div><?php endif; ?></td>
                    <td><span class="badge-status <?= status_class($o['payment_status']) ?>"><?= e(__('account.payment_' . $o['payment_status'])) ?></span><?php if ((float) $o['balance_due'] > 0 && $o['payment_status'] !== 'unpaid'): ?><div class="small text-muted-bwa"><?= e(__('orders.due', ['amount' => money($o['balance_due'])])) ?></div><?php endif; ?></td>
                    <td class="text-end text-nowrap"><?= e(money($o['grand_total'])) ?><div class="small text-muted-bwa"><?= e(__('orders.n_items', ['n' => (int) $o['items']])) ?></div></td>
                    <td class="small"><?= e(__('orders.source_' . $o['source'])) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($orders->items === []): ?><tr><td colspan="6"><div class="empty-state"><i class="bi bi-receipt"></i><?= e(__('common.no_results')) ?></div></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($orders->hasPages()): ?><div class="p-3 d-flex justify-content-between align-items-center"><span class="small text-muted-bwa"><?= $orders->from() ?>–<?= $orders->to() ?> / <?= $orders->total ?></span><?= $orders->links() ?></div><?php endif; ?>
</div>
