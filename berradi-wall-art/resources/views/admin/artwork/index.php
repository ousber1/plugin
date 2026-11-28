<?php
$this->layout('layouts/admin', ['title' => __('admin.nav_artwork')]);
$tabs = ['open' => __('artwork.tab_open'), 'sent' => __('artwork.tab_sent'), 'changes_requested' => __('artwork.tab_changes'), 'approved' => __('artwork.tab_approved'), 'all' => __('orders.all')];
$minDpi = (int) setting('builder_min_dpi', 100);
$warnDpi = (int) setting('builder_warn_dpi', 150);
?>
<p class="small text-muted-bwa"><?= e(__('artwork.intro')) ?></p>
<ul class="nav settings-tabs border-bottom mb-3 flex-nowrap overflow-auto">
    <?php foreach ($tabs as $k => $label): ?><li class="nav-item"><a class="nav-link text-nowrap<?= $status === $k ? ' active' : '' ?>" href="?status=<?= e($k) ?>"><?= e($label) ?></a></li><?php endforeach; ?>
</ul>
<div class="admin-card">
    <div class="table-responsive">
        <table class="table table-bwa">
            <thead><tr><th><?= e(__('orders.number')) ?></th><th><?= e(__('orders.item')) ?></th><th><?= e(__('artwork.images')) ?></th><th><?= e(__('artwork.quality')) ?></th><th><?= e(__('admin.status')) ?></th><th></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r):
                $state = (int) $r['approved'] === (int) $r['images'] ? 'approved' : ((int) $r['changes'] > 0 ? 'changes_requested' : ((int) $r['sent'] > 0 ? 'sent' : 'pending'));
                $dpi = $r['min_dpi'] !== null ? (int) $r['min_dpi'] : null; ?>
                <tr>
                    <td class="text-nowrap"><a href="<?= e(route('admin.orders.show', ['id' => $r['order_id']])) ?>"><?= e($r['order_number']) ?></a><div class="small text-muted-bwa"><?= e($r['customer_name']) ?> · <?= e(format_date($r['placed_at'])) ?></div></td>
                    <td><?= e($r['title']) ?><div class="small text-muted-bwa"><?= $r['width_cm'] ? e(cm($r['width_cm']) . ' × ' . cm($r['height_cm']) . ' cm · ') : '' ?><?= e((string) $r['material_name']) ?><?= $r['frame_name'] ? ' · ' . e($r['frame_name']) : '' ?> · ×<?= (int) $r['quantity'] ?></div></td>
                    <td><?= (int) $r['images'] ?></td>
                    <td><?php if ($dpi === null): ?>—<?php else: ?><span class="badge-status <?= $dpi < $minDpi ? 'is-danger' : ($dpi < $warnDpi ? '' : 'is-success') ?>"><?= $dpi ?> DPI</span><?php endif; ?></td>
                    <td><span class="badge-status <?= status_class($state) ?>"><?= e(__('artwork.status_' . $state)) ?></span></td>
                    <td class="text-end"><a class="btn btn-sm btn-outline-bwa" href="<?= e(route('admin.artwork.show', ['itemId' => $r['item_id']])) ?>"><?= e(__('artwork.review')) ?></a></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($rows === []): ?><tr><td colspan="6"><div class="empty-state"><i class="bi bi-palette"></i><?= e(__('artwork.empty')) ?></div></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
