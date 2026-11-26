<a class="collection-tile<?= !empty($large) ? ' is-large' : '' ?>" href="<?= e(route('collections.show', ['slug' => $c['slug']])) ?>">
    <?php if (!empty($c['image'])): ?>
        <img src="<?= e(upload_url($c['image'])) ?>" alt="<?= e($c['name']) ?>" loading="lazy">
    <?php else: ?>
        <span class="tile-monogram"><?= e(mb_substr((string) $c['name'], 0, 1)) ?></span>
    <?php endif; ?>
    <span class="collection-caption">
        <?php if ((int) ($c['is_limited'] ?? 0) === 1): ?><span class="eyebrow text-gold"><?= e(__('collections.limited')) ?></span><?php endif; ?>
        <span class="collection-name"><?= e($c['name']) ?></span>
        <span class="collection-meta"><?= e(__('collections.pieces', ['count' => (int) ($c['product_count'] ?? 0)])) ?> <i class="bi bi-arrow-<?= is_rtl() ? 'left' : 'right' ?> ms-1"></i></span>
    </span>
</a>
