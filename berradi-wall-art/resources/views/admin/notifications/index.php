<?php $this->layout('layouts/admin', ['title' => __('notifications.title')]); ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <p class="small text-muted-bwa mb-0"><?= e(__('notifications.intro')) ?></p>
    <form method="post" action="<?= e(route('admin.notifications.read_all')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-outline-bwa" type="submit"><?= e(__('notifications.mark_all')) ?></button></form>
</div>
<div class="admin-card">
    <?php foreach ($rows->items as $n): ?>
        <a class="d-flex gap-3 px-3 py-3 border-bottom text-decoration-none text-dark<?= $n['status'] === 'queued' ? ' notif-item is-unread' : '' ?>" href="<?= e(route('admin.notifications.open', ['id' => $n['id']])) ?>">
            <i class="bi <?= e(match (true) { str_starts_with((string) $n['event'], 'order') => 'bi-receipt', str_starts_with((string) $n['event'], 'artwork') => 'bi-palette', str_starts_with((string) $n['event'], 'quote') => 'bi-file-earmark-text', $n['event'] === 'low_stock' => 'bi-box-seam', $n['event'] === 'invoice_overdue' => 'bi-hourglass-split', $n['event'] === 'production_late' => 'bi-alarm', default => 'bi-envelope' }) ?> text-gold fs-5"></i>
            <span class="flex-grow-1"><span class="d-block"><?= e((string) $n['subject']) ?></span><span class="small text-muted-bwa"><?= e(format_date($n['created_at'], true)) ?></span></span>
        </a>
    <?php endforeach; ?>
    <?php if ($rows->items === []): ?><div class="card-body"><div class="empty-state"><i class="bi bi-bell"></i><?= e(__('notifications.none')) ?></div></div><?php endif; ?>
    <?php if ($rows->hasPages()): ?><div class="p-3"><?= $rows->links() ?></div><?php endif; ?>
</div>
