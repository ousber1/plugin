<?php $this->layout('layouts/admin', ['title' => __('notifications.email_log')]); ?>
<?php if ($driver === 'log'): ?><div class="alert alert-warning"><?= e(__('notifications.log_driver')) ?></div><?php endif; ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <ul class="nav settings-tabs">
        <li class="nav-item"><a class="nav-link<?= $status === '' ? ' active' : '' ?>" href="?"><?= e(__('orders.all')) ?></a></li>
        <?php foreach (['queued', 'sent', 'failed'] as $s): ?><li class="nav-item"><a class="nav-link<?= $status === $s ? ' active' : '' ?>" href="?status=<?= $s ?>"><?= e(__('notifications.st_' . $s)) ?> <span class="text-muted-bwa">(<?= (int) ($counts[$s] ?? 0) ?>)</span></a></li><?php endforeach; ?>
    </ul>
    <form method="get" class="filter-bar"><input type="hidden" name="status" value="<?= e($status) ?>"><input class="form-control" type="search" name="q" value="<?= e($q) ?>" placeholder="<?= e(__('notifications.search')) ?>" aria-label="<?= e(__('notifications.search')) ?>"><button class="btn btn-outline-bwa" type="submit"><?= e(__('admin.apply')) ?></button></form>
</div>
<div class="admin-card"><div class="table-responsive"><table class="table table-bwa">
    <thead><tr><th><?= e(__('admin.date')) ?></th><th><?= e(__('notifications.recipient')) ?></th><th><?= e(__('notifications.subject')) ?></th><th><?= e(__('admin.status')) ?></th><th class="text-end"><?= e(__('notifications.attempts')) ?></th></tr></thead>
    <tbody>
    <?php foreach ($rows->items as $n): ?>
        <tr>
            <td class="small text-nowrap"><?= e(format_date($n['created_at'], true)) ?></td>
            <td class="small text-break" dir="ltr"><?= e((string) $n['recipient']) ?></td>
            <td><a href="<?= e(route('admin.notifications.email', ['id' => $n['id']])) ?>"><?= e((string) $n['subject']) ?></a><div class="small text-muted-bwa"><code><?= e((string) $n['event']) ?></code></div></td>
            <td><span class="badge-status <?= status_class($n['status'] === 'sent' ? 'delivered' : ($n['status'] === 'failed' ? 'failed' : 'pending')) ?>"><?= e(__('notifications.st_' . $n['status'])) ?></span></td>
            <td class="text-end"><?= (int) $n['attempts'] ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if ($rows->items === []): ?><tr><td colspan="5"><div class="empty-state"><i class="bi bi-envelope"></i><?= e(__('common.no_results')) ?></div></td></tr><?php endif; ?>
    </tbody>
</table></div>
<?php if ($rows->hasPages()): ?><div class="p-3"><?= $rows->links() ?></div><?php endif; ?></div>
<p class="small text-muted-bwa mt-3"><?= e(__('notifications.log_help')) ?></p>
