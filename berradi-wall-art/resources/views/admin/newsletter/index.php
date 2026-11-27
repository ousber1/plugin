<?php $this->layout('layouts/admin', ['title' => __('admin.nav_newsletter')]); ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <ul class="nav settings-tabs">
        <?php foreach (['subscribed', 'unsubscribed'] as $s): ?><li class="nav-item"><a class="nav-link<?= $status === $s ? ' active' : '' ?>" href="?status=<?= $s ?>"><?= e(__('newsletter_admin.' . $s)) ?> <span class="text-muted-bwa">(<?= (int) ($counts[$s] ?? 0) ?>)</span></a></li><?php endforeach; ?>
    </ul>
    <div class="d-flex flex-wrap gap-2">
        <form method="get" class="filter-bar flex-grow-1"><input type="hidden" name="status" value="<?= e($status) ?>"><input class="form-control" type="search" name="q" value="<?= e($q) ?>" placeholder="E-mail" aria-label="E-mail"><button class="btn btn-outline-bwa" type="submit"><?= e(__('admin.apply')) ?></button></form>
        <a class="btn btn-bwa" href="?status=<?= e($status) ?>&q=<?= e(rawurlencode($q)) ?>&export=csv"><i class="bi bi-filetype-csv me-1"></i><?= e(__('reports.export_csv')) ?></a>
    </div>
</div>
<div class="admin-card"><div class="table-responsive"><table class="table table-bwa">
    <thead><tr><th>E-mail</th><th><?= e(__('admin.language')) ?></th><th><?= e(__('orders.source')) ?></th><th><?= e(__('newsletter_admin.since')) ?></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows->items as $s): ?>
        <tr><td dir="ltr"><?= e($s['email']) ?></td><td class="small"><?= e(strtoupper((string) $s['locale'])) ?></td><td class="small"><?= e((string) $s['source']) ?></td>
            <td class="small"><?= e(format_date((string) ($status === 'subscribed' ? $s['subscribed_at'] : $s['unsubscribed_at']), true)) ?></td>
            <td class="text-end"><?php if ($s['status'] === 'subscribed'): ?><form method="post" action="<?= e(route('admin.newsletter.unsubscribe', ['id' => $s['id']])) ?>" data-confirm="<?= e(__('newsletter_admin.unsubscribe_confirm')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-link text-danger" type="submit"><?= e(__('newsletter_admin.unsubscribe')) ?></button></form><?php endif; ?></td></tr>
    <?php endforeach; ?>
    <?php if ($rows->items === []): ?><tr><td colspan="5"><div class="empty-state"><i class="bi bi-envelope-heart"></i><?= e(__('common.no_results')) ?></div></td></tr><?php endif; ?>
    </tbody>
</table></div><?php if ($rows->hasPages()): ?><div class="p-3"><?= $rows->links() ?></div><?php endif; ?></div>
<p class="small text-muted-bwa mt-3"><?= e(__('newsletter_admin.help')) ?></p>
