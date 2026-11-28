<?php $this->layout('layouts/admin', ['title' => __('admin.nav_invoices')]); ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <form method="get" class="filter-bar" role="search">
        <input class="form-control" type="search" name="q" value="<?= e($f['q']) ?>" placeholder="<?= e(__('finance.search_docs')) ?>" aria-label="<?= e(__('finance.search_docs')) ?>">
        <select class="form-select" name="type" aria-label="<?= e(__('admin.type')) ?>"><option value=""><?= e(__('finance.all_types')) ?></option><option value="invoice" <?= $f['type'] === 'invoice' ? 'selected' : '' ?>><?= e(__('finance.invoices')) ?></option><option value="credit_note" <?= $f['type'] === 'credit_note' ? 'selected' : '' ?>><?= e(__('finance.credit_notes')) ?></option></select>
        <select class="form-select" name="status" aria-label="<?= e(__('admin.status')) ?>"><option value=""><?= e(__('admin.all_statuses')) ?></option><?php foreach (['draft', 'unpaid', 'issued', 'partially_paid', 'paid', 'cancelled'] as $s): ?><option value="<?= $s ?>" <?= $f['status'] === $s ? 'selected' : '' ?>><?= e(__('finance.is_' . $s)) ?></option><?php endforeach; ?></select>
        <input class="form-control" type="date" name="from" value="<?= e($f['from']) ?>" aria-label="<?= e(__('orders.from')) ?>">
        <input class="form-control" type="date" name="to" value="<?= e($f['to']) ?>" aria-label="<?= e(__('orders.to')) ?>">
        <button class="btn btn-outline-bwa" type="submit"><?= e(__('admin.apply')) ?></button>
    </form>
    <?php if (can('invoices.manage')): ?><a class="btn btn-bwa" href="<?= e(route('admin.invoices.create')) ?>"><i class="bi bi-plus-lg me-1"></i><?= e(__('finance.new_invoice')) ?></a><?php endif; ?>
</div>
<p class="small text-muted-bwa"><?= e(__('finance.net_invoiced', ['amount' => money($net)])) ?><?= $drafts > 0 ? ' · ' . e(__('finance.n_drafts', ['n' => $drafts])) : '' ?></p>
<div class="admin-card">
    <div class="table-responsive"><table class="table table-bwa">
        <thead><tr><th>N°</th><th><?= e(__('admin.type')) ?></th><th><?= e(__('finance.customer')) ?></th><th><?= e(__('admin.date')) ?></th><th><?= e(__('admin.status')) ?></th><th class="text-end"><?= e(__('pdf.total_ttc')) ?></th></tr></thead>
        <tbody>
        <?php foreach ($rows->items as $i): $cn = $i['invoice_type'] === 'credit_note'; ?>
            <tr>
                <td class="text-nowrap"><a class="fw-semibold" href="<?= e(route('admin.invoices.show', ['id' => $i['id']])) ?>"><?= e($i['invoice_number'] ?: __('finance.draft_n', ['n' => $i['id']])) ?></a><?= $i['order_number'] ? '<div class="small text-muted-bwa">' . e($i['order_number']) . '</div>' : '' ?></td>
                <td class="small"><?= e(__($cn ? 'finance.credit_note' : 'finance.invoice')) ?></td>
                <td><?= e($i['customer_company'] ?: $i['customer_name']) ?><?= $i['customer_ice'] ? '<div class="small text-muted-bwa">ICE ' . e($i['customer_ice']) . '</div>' : '' ?></td>
                <td class="small"><?= $i['issue_date'] ? e(format_date($i['issue_date'])) : '—' ?><?php if ($i['due_date'] && in_array($i['status'], ['issued', 'partially_paid'], true) && $i['due_date'] < date('Y-m-d')): ?><div class="text-danger"><?= e(__('finance.overdue')) ?></div><?php endif; ?></td>
                <td><span class="badge-status <?= status_class($i['status'] === 'issued' ? 'unpaid' : ($i['status'] === 'partially_paid' ? 'partial' : $i['status'])) ?>"><?= e(__('finance.is_' . $i['status'])) ?></span></td>
                <td class="text-end text-nowrap <?= $cn ? 'text-danger' : '' ?>"><?= $cn ? '−' : '' ?><?= e(money($i['total_ttc'])) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($rows->items === []): ?><tr><td colspan="6"><div class="empty-state"><i class="bi bi-file-earmark-check"></i><?= e(__('finance.no_invoices')) ?></div></td></tr><?php endif; ?>
        </tbody>
    </table></div>
    <?php if ($rows->hasPages()): ?><div class="p-3"><?= $rows->links() ?></div><?php endif; ?>
</div>
