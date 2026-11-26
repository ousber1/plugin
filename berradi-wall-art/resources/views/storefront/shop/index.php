<?php
$this->layout('layouts/storefront', ['seo' => $seo]);
$sortOptions = [];
foreach (\App\Services\CatalogService::SORTS as $s) {
    $sortOptions[$s] = __('shop.sort_' . $s);
}
$activeFilters = array_diff_key($filters, $fixed, ['sort' => 1]);
$query = fn (array $override) => $action . '?' . http_build_query(array_filter(array_merge(array_diff_key($filters, $fixed), $override), fn ($v) => $v !== null && $v !== ''));
?>
<section class="page-hero<?= !empty($heroImage) ? ' has-image' : '' ?>">
    <?php if (!empty($heroImage)): ?><img src="<?= e(upload_url($heroImage)) ?>" alt="" class="page-hero-bg"><?php endif; ?>
    <div class="container position-relative">
        <?= $this->insert('storefront/partials/breadcrumbs', ['crumbs' => $breadcrumbs]) ?>
        <span class="eyebrow d-block mt-4"><?= e($eyebrow) ?></span>
        <h1 class="page-title mt-2"><?= e($heading) ?></h1>
        <?php if (!empty($intro)): ?><p class="page-lead"><?= e($intro) ?></p><?php endif; ?>
    </div>
</section>

<section class="section-sm pt-4">
    <div class="container">
        <div class="row g-4">
            <aside class="col-lg-3">
                <button class="btn btn-outline-bwa w-100 d-lg-none mb-3" type="button" data-bs-toggle="collapse" data-bs-target="#shopFilters" aria-expanded="false" aria-controls="shopFilters">
                    <i class="bi bi-sliders me-1"></i><?= e(__('shop.filters')) ?><?= $activeFilters ? ' (' . count($activeFilters) . ')' : '' ?>
                </button>
                <div class="collapse d-lg-block" id="shopFilters">
                    <form method="get" action="<?= e($action) ?>" class="shop-filters" data-autosubmit>
                        <?php if (isset($filters['sort'])): ?><input type="hidden" name="sort" value="<?= e($filters['sort']) ?>"><?php endif; ?>
                        <div class="filter-block">
                            <label class="filter-title" for="fq"><?= e(__('shop.search')) ?></label>
                            <div class="input-group">
                                <input type="search" id="fq" name="q" class="form-control" value="<?= e($filters['q'] ?? '') ?>" placeholder="<?= e(__('shop.search_placeholder')) ?>">
                                <button class="btn btn-bwa px-3" type="submit" aria-label="<?= e(__('shop.search')) ?>"><i class="bi bi-search"></i></button>
                            </div>
                        </div>
                        <?php if (empty($currentCategory)): ?>
                            <div class="filter-block">
                                <span class="filter-title"><?= e(__('shop.categories')) ?></span>
                                <ul class="list-unstyled filter-list">
                                    <?php foreach ($categories as $c): ?>
                                        <?php if ((int) $c['product_count'] === 0 && ($filters['category'] ?? '') !== $c['slug']) continue; ?>
                                        <li>
                                            <label class="form-check">
                                                <input class="form-check-input" type="radio" name="category" value="<?= e($c['slug']) ?>" <?= ($filters['category'] ?? '') === $c['slug'] ? 'checked' : '' ?>>
                                                <span class="form-check-label"><?= e($c['name']) ?> <small class="text-muted-bwa">(<?= (int) $c['product_count'] ?>)</small></span>
                                            </label>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php else: ?>
                            <div class="filter-block">
                                <span class="filter-title"><?= e(__('shop.categories')) ?></span>
                                <ul class="list-unstyled filter-list">
                                    <?php foreach ($categories as $c): ?>
                                        <?php if ((int) $c['product_count'] === 0 && $c['slug'] !== $currentCategory) continue; ?>
                                        <li><a class="<?= $c['slug'] === $currentCategory ? 'fw-semibold text-gold' : '' ?>" href="<?= e(route('shop.category', ['slug' => $c['slug']])) ?>"><?= e($c['name']) ?></a> <small class="text-muted-bwa">(<?= (int) $c['product_count'] ?>)</small></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        <div class="filter-block">
                            <span class="filter-title"><?= e(__('shop.product_type')) ?></span>
                            <select name="type" class="form-select">
                                <option value=""><?= e(__('shop.all_types')) ?></option>
                                <?php foreach (\App\Services\CatalogService::TYPES as $type): ?>
                                    <option value="<?= e($type) ?>" <?= ($filters['type'] ?? '') === $type ? 'selected' : '' ?>><?= e(__('shop.type_' . $type)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-block">
                            <span class="filter-title"><?= e(__('shop.price')) ?> (<?= e(setting('currency_symbol', 'DH')) ?>)</span>
                            <div class="d-flex gap-2 align-items-center">
                                <input type="number" min="0" step="1" name="min" class="form-control" value="<?= e($filters['min'] ?? '') ?>" placeholder="<?= e((string) (int) $bounds['min']) ?>" aria-label="<?= e(__('shop.min')) ?>">
                                <span>—</span>
                                <input type="number" min="0" step="1" name="max" class="form-control" value="<?= e($filters['max'] ?? '') ?>" placeholder="<?= e((string) (int) ceil((float) $bounds['max'])) ?>" aria-label="<?= e(__('shop.max')) ?>">
                            </div>
                        </div>
                        <?php if (!isset($fixed['flag'])): ?>
                            <div class="filter-block">
                                <span class="filter-title"><?= e(__('shop.highlights')) ?></span>
                                <?php foreach (\App\Services\CatalogService::FLAGS as $flag): ?>
                                    <label class="form-check">
                                        <input class="form-check-input" type="radio" name="flag" value="<?= e($flag) ?>" <?= ($filters['flag'] ?? '') === $flag ? 'checked' : '' ?>>
                                        <span class="form-check-label"><?= e(__('shop.flag_' . $flag)) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-bwa flex-grow-1"><?= e(__('shop.apply')) ?></button>
                            <?php if ($activeFilters): ?><a href="<?= e($action) ?>" class="btn btn-outline-bwa"><?= e(__('shop.reset')) ?></a><?php endif; ?>
                        </div>
                    </form>
                </div>
            </aside>
            <div class="col-lg-9">
                <div class="shop-toolbar">
                    <span class="text-muted-bwa small"><?= e(__('shop.count', ['count' => $products->total])) ?></span>
                    <form method="get" action="<?= e($action) ?>" class="d-flex align-items-center gap-2" data-autosubmit>
                        <?php foreach (array_diff_key($filters, $fixed, ['sort' => 1]) as $k => $v): ?><input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>"><?php endforeach; ?>
                        <label for="sort" class="small text-muted-bwa text-nowrap"><?= e(__('shop.sort_by')) ?></label>
                        <select id="sort" name="sort" class="form-select form-select-sm">
                            <?php foreach ($sortOptions as $k => $label): ?><option value="<?= e($k) ?>" <?= ($filters['sort'] ?? 'featured') === $k ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                        </select>
                    </form>
                </div>
                <?php if ($activeFilters): ?>
                    <div class="active-filters">
                        <?php foreach ($activeFilters as $k => $v): ?>
                            <?php $label = match ($k) {
                                'type' => __('shop.type_' . $v),
                                'flag' => __('shop.flag_' . $v),
                                'min' => __('shop.min') . ' ' . $v,
                                'max' => __('shop.max') . ' ' . $v,
                                'category' => (array_values(array_filter($categories, fn ($c) => $c['slug'] === $v))[0]['name'] ?? $v),
                                default => '« ' . $v . ' »',
                            }; ?>
                            <a class="filter-chip" href="<?= e($query([$k => null, 'page' => null])) ?>"><?= e($label) ?> <i class="bi bi-x"></i></a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if ($products->items === []): ?>
                    <div class="empty-state card-bwa">
                        <i class="bi bi-image"></i>
                        <p class="mb-3"><?= e($activeFilters ? __('shop.no_match') : __('shop.empty')) ?></p>
                        <?php if ($activeFilters): ?><a class="btn btn-outline-bwa" href="<?= e($action) ?>"><?= e(__('shop.reset')) ?></a><?php endif; ?>
                    </div>
                <?php else: ?>
                    <?= $this->insert('storefront/partials/product_grid', ['items' => $products->items, 'rowClass' => 'row-cols-2 row-cols-md-3']) ?>
                    <?php if ($products->hasPages()): ?><div class="d-flex justify-content-center mt-5"><?= $products->links() ?></div><?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
