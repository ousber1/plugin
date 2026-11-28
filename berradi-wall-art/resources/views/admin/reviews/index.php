<?php $this->layout('layouts/admin', ['title' => __('admin.nav_reviews')]); ?>
<ul class="nav settings-tabs border-bottom mb-3">
    <?php foreach (['pending' => __('reviews.pending', ['n' => $pending]), 'approved' => __('reviews.approved'), 'all' => __('orders.all')] as $k => $l): ?><li class="nav-item"><a class="nav-link<?= $status === $k ? ' active' : '' ?>" href="?status=<?= $k ?>"><?= e($l) ?></a></li><?php endforeach; ?>
</ul>
<?php foreach ($rows as $r): ?>
    <div class="admin-card mb-3"><div class="card-body">
        <div class="d-flex flex-wrap justify-content-between gap-2">
            <div><span class="text-gold" aria-label="<?= (int) $r['rating'] ?>/5"><?= str_repeat('★', (int) $r['rating']) ?><span class="text-muted-bwa"><?= str_repeat('★', 5 - (int) $r['rating']) ?></span></span>
                <strong class="ms-2" dir="auto"><?= e((string) $r['title']) ?></strong></div>
            <div class="small text-muted-bwa"><?= e($r['author_name']) ?><?= $r['author_city'] ? ' · ' . e($r['author_city']) : '' ?> · <?= e(format_date($r['created_at'])) ?><?= (int) $r['is_verified_purchase'] ? ' · <span class="text-success"><i class="bi bi-patch-check"></i> ' . e(__('reviews.verified')) . '</span>' : '' ?></div>
        </div>
        <p class="mt-2 mb-2" dir="auto" style="white-space:pre-line"><?= e((string) $r['body']) ?></p>
        <div class="small text-muted-bwa mb-2"><?php if ($r['product_title']): ?><a href="<?= e(route('product.show', ['slug' => $r['slug']])) ?>" target="_blank" rel="noopener"><?= e($r['product_title']) ?></a><?php endif; ?><?= $r['order_number'] ? ' · ' . e($r['order_number']) : '' ?></div>
        <div class="d-flex flex-wrap gap-2">
            <?php foreach ((int) $r['is_approved'] ? ((int) $r['is_featured'] ? ['unfeature', 'unapprove'] : ['feature', 'unapprove']) : ['approve', 'feature'] as $a): ?>
                <form method="post" action="<?= e(route('admin.reviews.action', ['id' => $r['id']])) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="<?= $a ?>"><button class="btn btn-sm <?= $a === 'approve' ? 'btn-bwa' : 'btn-outline-bwa' ?>" type="submit"><?= e(__('reviews.a_' . $a)) ?></button></form>
            <?php endforeach; ?>
            <form method="post" action="<?= e(route('admin.reviews.action', ['id' => $r['id']])) ?>" data-confirm="<?= e(__('common.confirm_delete')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><button class="btn btn-sm btn-link text-danger" type="submit"><?= e(__('account.delete_now')) ?></button></form>
        </div>
    </div></div>
<?php endforeach; ?>
<?php if ($rows === []): ?><div class="admin-card"><div class="card-body"><div class="empty-state"><i class="bi bi-star"></i><?= e(__('reviews.none')) ?></div></div></div><?php endif; ?>
<p class="small text-muted-bwa mt-3"><?= e(__('reviews.help')) ?></p>
