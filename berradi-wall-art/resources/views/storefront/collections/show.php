<?php $this->layout('layouts/storefront', ['seo' => $seo]); ?>
<section class="page-hero<?= !empty($collection['image']) ? ' has-image' : '' ?>">
    <?php if (!empty($collection['image'])): ?><img src="<?= e(upload_url($collection['image'])) ?>" alt="" class="page-hero-bg"><?php endif; ?>
    <div class="container position-relative">
        <?= $this->insert('storefront/partials/breadcrumbs', ['crumbs' => [[__('nav.home'), '/'], [__('nav.collections'), '/collections'], [$collection['name'], null]]]) ?>
        <span class="eyebrow d-block mt-4"><?= e((int) $collection['is_limited'] === 1 ? __('collections.limited') : __('collections.eyebrow')) ?></span>
        <h1 class="page-title mt-2"><?= e($collection['name']) ?></h1>
        <?php if (!empty($collection['description'])): ?><p class="page-lead"><?= e($collection['description']) ?></p><?php endif; ?>
        <?php if (!empty($collection['ends_at'])): ?><p class="small mt-2"><i class="bi bi-hourglass-split me-1"></i><?= e(__('collections.until', ['date' => format_date($collection['ends_at'])])) ?></p><?php endif; ?>
    </div>
</section>
<section class="section-sm">
    <div class="container">
        <div class="shop-toolbar">
            <span class="text-muted-bwa small"><?= e(__('shop.count', ['count' => $products->total])) ?></span>
            <form method="get" class="d-flex align-items-center gap-2" data-autosubmit>
                <label for="sort" class="small text-muted-bwa text-nowrap"><?= e(__('shop.sort_by')) ?></label>
                <select id="sort" name="sort" class="form-select form-select-sm">
                    <?php foreach (\App\Services\CatalogService::SORTS as $s): ?><option value="<?= e($s) ?>" <?= $sort === $s ? 'selected' : '' ?>><?= e(__('shop.sort_' . $s)) ?></option><?php endforeach; ?>
                </select>
            </form>
        </div>
        <?php if ($products->items === []): ?>
            <div class="empty-state card-bwa"><i class="bi bi-image"></i><?= e(__('shop.empty')) ?></div>
        <?php else: ?>
            <?= $this->insert('storefront/partials/product_grid', ['items' => $products->items]) ?>
            <?php if ($products->hasPages()): ?><div class="d-flex justify-content-center mt-5"><?= $products->links() ?></div><?php endif; ?>
        <?php endif; ?>
    </div>
</section>
