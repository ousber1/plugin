<?php
use App\Services\Finance\QuotationService;

$this->layout('layouts/admin', ['title' => __('admin.nav_quotations')]);
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <form method="get" class="filter-bar" role="search">
        <input type="hidden" name="status" value="<?= e($status) ?>">
        <input class="form-control" type="search" name="q" value="<?= e($q) ?>" placeholder="<?= e(__('finance.search_docs')) ?>" aria-label="<?= e(__('finance.search_docs')) ?>">
        <button class="btn btn-outline-bwa" type="submit"><?= e(__('admin.apply')) ?></button>
    </form>
    <?php if (can('quotations.manage')): ?><a class="btn btn-bwa" href="<?= e(route('admin.quotations.create')) ?>"><i class="bi bi-plus-lg me-1"></i><?= e(__('finance.new_quote')) ?></a><?php endif; ?>
</div>
<ul class="nav settings-tabs border-bottom mb-3 flex-nowrap overflow-auto">
    <li class="nav-item"><a class="nav-link<?= $status === '' ? ' active' : '' ?>" href="?"><?= e(__('orders.all')) ?> <span class="text-muted-bwa">(<?= array_sum(array_map('intval', $counts)) ?>)</span></a></li>
    <?php foreach (QuotationService::STATUSES as $s): if (empty($counts[$s])) { continue; } ?>
        <li class="nav-item"><a class="nav-link text-nowrap<?= $status === $s ? ' active' : '' ?>" href="?status=<?= $s ?>"><?= e(__('finance.qs_' . $s)) ?> <span class="text-muted-bwa">(<?= (int) $counts[$s] ?>)</span></a></li>
    <?php endforeach; ?>
</ul>
<div class="admin-card"><div class="table-responsive"><table class="table table-bwa">
    <thead><tr><th>N°</th><th><?= e(__('finance.customer')) ?></th><th><?= e(__('admin.date')) ?></th><th><?= e(__('finance.valid_until')) ?></th><th><?= e(__('admin.status')) ?></th><th class="text-end"><?= e(__('pdf.total_ttc')) ?></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $late = $r['status'] === 'sent' && $r['valid_until'] < date('Y-m-d'); ?>
        <tr>
            <td class="text-nowrap"><a class="fw-semibold" href="<?= e(route('admin.quotations.show', ['id' => $r['id']])) ?>"><?= e($r['quotation_number']) ?></a></td>
            <td><?= e($r['customer_company'] ?: $r['customer_name']) ?><?= $r['customer_company'] ? '<div class="small text-muted-bwa">' . e($r['customer_name']) . '</div>' : '' ?></td>
            <td class="small"><?= e(format_date($r['issue_date'])) ?></td>
            <td class="small <?= $late ? 'text-danger' : '' ?>"><?= e(format_date($r['valid_until'])) ?></td>
            <td><span class="badge-status <?= status_class($r['status'] === 'accepted' || $r['status'] === 'converted' ? 'approved' : ($r['status'] === 'rejected' || $r['status'] === 'expired' ? 'cancelled' : $r['status'])) ?>"><?= e(__('finance.qs_' . $r['status'])) ?></span></td>
            <td class="text-end text-nowrap"><?= e(money($r['total'])) ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if ($rows === []): ?><tr><td colspan="6"><div class="empty-state"><i class="bi bi-file-earmark-text"></i><?= e(__('finance.no_quotes')) ?></div></td></tr><?php endif; ?>
    </tbody>
</table></div></div>
