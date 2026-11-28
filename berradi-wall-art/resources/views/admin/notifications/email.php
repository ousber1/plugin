<?php $this->layout('layouts/admin', ['title' => (string) $n['subject']]); ?>
<a class="small text-muted-bwa d-inline-block mb-3" href="<?= e(route('admin.notifications.emails')) ?>"><i class="bi bi-arrow-<?= is_rtl() ? 'right' : 'left' ?> me-1"></i><?= e(__('notifications.email_log')) ?></a>
<div class="row g-4">
    <div class="col-lg-8"><div class="admin-card"><div class="card-body small" style="white-space:pre-line"><?= e($text) ?></div></div></div>
    <div class="col-lg-4"><div class="admin-card"><div class="card-body small">
        <div class="stat-line"><span><?= e(__('notifications.recipient')) ?></span><span dir="ltr" class="text-break"><?= e((string) $n['recipient']) ?></span></div>
        <div class="stat-line"><span><?= e(__('admin.status')) ?></span><span><?= e(__('notifications.st_' . $n['status'])) ?></span></div>
        <div class="stat-line"><span><?= e(__('notifications.attempts')) ?></span><span><?= (int) $n['attempts'] ?></span></div>
        <div class="stat-line"><span><?= e(__('admin.created')) ?></span><span><?= e(format_date($n['created_at'], true)) ?></span></div>
        <?php if ($n['sent_at']): ?><div class="stat-line"><span><?= e(__('notifications.sent_at')) ?></span><span><?= e(format_date($n['sent_at'], true)) ?></span></div><?php endif; ?>
        <?php if ($n['last_error']): ?><p class="text-danger mt-2 mb-0"><?= e((string) $n['last_error']) ?></p><?php endif; ?>
        <?php if (in_array($n['status'], ['queued', 'failed'], true)): ?>
            <form method="post" action="<?= e(route('admin.notifications.retry', ['id' => $n['id']])) ?>" class="mt-3"><?= csrf_field() ?><button class="btn btn-sm btn-bwa w-100" type="submit"><i class="bi bi-arrow-repeat me-1"></i><?= e(__('notifications.retry')) ?></button></form>
        <?php endif; ?>
    </div></div></div>
</div>
