<?php
use App\Services\Operations\PurchaseService;

$this->layout('layouts/admin', ['title' => __('admin.nav_purchases')]);
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <ul class="nav settings-tabs flex-nowrap overflow-auto">
        <li class="nav-item"><a class="nav-link<?= $status === '' ? ' active' : '' ?>" href="?"><?= e(__('orders.all')) ?></a></li>
        <?php foreach (PurchaseService::STATUSES as $s): ?><li class="nav-item"><a class="nav-link<?= $status === $s ? ' active' : '' ?>" href="?status=<?= $s ?>"><?= e(__('inventory.ps_' . $s)) ?></a></li><?php endforeach; ?>
    </ul>
    <a class="btn btn-bwa" href="<?= e(route('admin.purchases.create')) ?>"><i class="bi bi-plus-lg me-1"></i><?= e(__('inventory.new_purchase')) ?></a>
</div>
<div class="admin-card"><div class="table-responsive"><table class="table table-bwa">
    <thead><tr><th>N°</th><th><?= e(__('inventory.supplier')) ?></th><th><?= e(__('admin.date')) ?></th><th><?= e(__('admin.status')) ?></th><th class="text-end"><?= e(__('admin.total')) ?></th><th class="text-end"><?= e(__('inventory.remaining')) ?></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $p): $rest = (float) $p['total'] - (float) $p['amount_paid']; ?>
        <tr>
            <td class="text-nowrap"><a class="fw-semibold" href="<?= e(route('admin.purchases.show', ['id' => $p['id']])) ?>"><?= e($p['purchase_number']) ?></a><?= $p['supplier_invoice_ref'] ? '<div class="small text-muted-bwa">' . e($p['supplier_invoice_ref']) . '</div>' : '' ?></td>
            <td><?= e($p['supplier_name']) ?><div class="small text-muted-bwa"><?= e(__('inventory.n_lines', ['n' => (int) $p['line_count']])) ?></div></td>
            <td class="small"><?= e(format_date($p['purchase_date'])) ?></td>
            <td><span class="badge-status <?= status_class($p['status']) ?>"><?= e(__('inventory.ps_' . $p['status'])) ?></span></td>
            <td class="text-end text-nowrap"><?= e(money($p['total'])) ?></td>
            <td class="text-end text-nowrap <?= $rest > 0 && $p['status'] !== 'cancelled' ? 'text-danger' : '' ?>"><?= $p['status'] === 'cancelled' ? '—' : e(money(round($rest, 2))) ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if ($rows === []): ?><tr><td colspan="6"><div class="empty-state"><i class="bi bi-cart-check"></i><?= e(__('inventory.no_purchases')) ?></div></td></tr><?php endif; ?>
    </tbody>
</table></div></div>
