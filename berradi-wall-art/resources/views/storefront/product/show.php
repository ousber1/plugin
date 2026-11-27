<?php
$this->layout('layouts/storefront', ['seo' => $seo]);
$p = $product;
$router = app()->router();
$effect = match ($p['product_type']) {
    'framed' => 'is-framed',
    'poster', 'photo_print' => 'is-poster',
    default => 'is-canvas',
};
$images = $p['images'];
if ($images === [] && !empty($p['main_image'])) {
    $images = [['id' => 0, 'path' => $p['main_image'], 'thumb_path' => null, 'alt' => $p['title']]];
}
$variants = $p['variants'];
$default = null;
foreach ($variants as $v) {
    if ((int) $v['is_default'] === 1 && !$v['out_of_stock']) {
        $default = $v;
        break;
    }
}
$default ??= $variants[0] ?? null;
$sizes = $materials = $frames = [];
foreach ($variants as $v) {
    $sizes[$v['size_label']] = $v['size_label'];
    $materials[(string) ($v['product_material_id'] ?? '')] = $v['material_name'] ?? __('product.standard');
    $frames[(string) ($v['frame_id'] ?? '')] = $v['frame_name'] ?? __('product.no_frame');
}
$variantJson = array_map(fn ($v) => [
    'id' => (int) $v['id'],
    'sku' => $v['sku'],
    'name' => $v['name'],
    'size' => $v['size_label'],
    'material' => (string) ($v['product_material_id'] ?? ''),
    'frame' => (string) ($v['frame_id'] ?? ''),
    'frameColor' => $v['frame_color'],
    'width' => (float) $v['width_cm'],
    'height' => (float) $v['height_cm'],
    'panels' => (int) $v['panels'],
    'price' => money($v['effective_price']),
    'regular' => money($v['regular_price']),
    'onSale' => $v['effective_price'] !== $v['regular_price'],
    'out' => (bool) $v['out_of_stock'],
], $variants);
$whatsapp = whatsapp_link(null, __('product.whatsapp_message', ['title' => $p['title'], 'url' => absolute_url('product/' . $p['slug'])]));
$shipDays = (int) setting('production_days', 3) + (int) setting('shipping_max_days', 5);
?>
<section class="pt-4 pb-5">
    <div class="container">
        <?= $this->insert('storefront/partials/breadcrumbs', ['crumbs' => $crumbs]) ?>
        <div class="row g-4 g-lg-5 mt-1">
            <div class="col-lg-7">
                <div class="product-gallery" data-gallery>
                    <div class="gallery-stage">
                        <div class="gallery-views">
                            <button type="button" class="view-toggle active" data-view="art"><?= e(__('product.view_art')) ?></button>
                            <?php if ($default !== null): ?><button type="button" class="view-toggle" data-open-wall aria-haspopup="dialog"><i class="bi bi-house me-1"></i><?= e(__('product.view_room')) ?></button><?php endif; ?>
                        </div>
                        <div class="stage-art" data-stage="art">
                            <span class="art-wall is-large">
                                <?php if ($images !== []): ?>
                                    <img class="art-piece <?= $effect ?>" src="<?= e(upload_url($images[0]['path'])) ?>" alt="<?= e($images[0]['alt'] ?: $p['title']) ?>" data-main-image>
                                <?php else: ?>
                                    <span class="art-piece art-placeholder <?= $effect ?>"><?= e(mb_substr((string) $p['title'], 0, 1)) ?></span>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                    <?php if (count($images) > 1): ?>
                        <div class="gallery-thumbs">
                            <?php foreach ($images as $i => $img): ?>
                                <button type="button" class="thumb<?= $i === 0 ? ' active' : '' ?>" data-thumb="<?= e(upload_url($img['path'])) ?>" aria-label="<?= e(__('product.image_n', ['n' => $i + 1])) ?>">
                                    <img src="<?= e(upload_url($img['thumb_path'] ?: $img['path'])) ?>" alt="" loading="lazy">
                                </button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="product-summary">
                    <?php if ($p['collection']): ?>
                        <a class="eyebrow" href="<?= e(route('collections.show', ['slug' => $p['collection']['slug']])) ?>"><?= e($p['collection']['name']) ?></a>
                    <?php elseif ($p['category']): ?>
                        <a class="eyebrow" href="<?= e(route('shop.category', ['slug' => $p['category']['slug']])) ?>"><?= e($p['category']['name']) ?></a>
                    <?php endif; ?>
                    <h1 class="product-name mt-2"><?= e($p['title']) ?></h1>
                    <?php if ((int) $p['review_count'] > 0): ?>
                        <a href="#reviews" class="product-rating mb-2 d-inline-flex">
                            <?= $this->insert('storefront/partials/stars', ['rating' => (float) $p['avg_rating']]) ?>
                            <span><?= e(__('product.reviews_count', ['count' => (int) $p['review_count'], 'rating' => (string) $p['avg_rating']])) ?></span>
                        </a>
                    <?php endif; ?>

                    <div class="product-price-lg my-3" data-price-box>
                        <?php if ($default !== null): ?>
                            <span class="now" data-price><?= e(money($default['effective_price'])) ?></span>
                            <del class="was<?= $default['effective_price'] === $default['regular_price'] ? ' d-none' : '' ?>" data-regular><?= e(money($default['regular_price'])) ?></del>
                        <?php else: ?>
                            <span class="now"><?= e(money($p['display_price'])) ?></span>
                            <?php if ($p['on_sale']): ?><del class="was"><?= e(money($p['compare_price'])) ?></del><?php endif; ?>
                        <?php endif; ?>
                        <?php if ($p['on_sale'] && !empty($p['promo_ends_at'])): ?>
                            <div class="promo-ends"><i class="bi bi-clock me-1"></i><?= e(__('product.promo_until', ['date' => format_date($p['promo_ends_at'])])) ?></div>
                        <?php endif; ?>
                        <div class="tax-note"><?= e(setting('tax_enabled', true) && setting('tax_prices_include_tax', true) ? __('product.price_ttc', ['tax' => (string) setting('tax_label', 'TVA')]) : __('product.price_ht')) ?></div>
                    </div>

                    <?php if (!empty($p['short_description'])): ?><p class="product-lead"><?= e($p['short_description']) ?></p><?php endif; ?>

                    <form class="product-form" data-product-form data-variants="<?= e(json_encode($variantJson, JSON_UNESCAPED_UNICODE)) ?>" data-whatsapp="<?= e($whatsapp) ?>"
                          method="post" action="<?= e($router->has('cart.add') ? route('cart.add') : '#') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>">
                        <input type="hidden" name="variant_id" value="<?= (int) ($default['id'] ?? 0) ?>" data-variant-input>

                        <?php if (count($variants) > 0): ?>
                            <?php if (count($sizes) > 0): ?>
                                <fieldset class="option-group">
                                    <legend><?= e(__('product.size')) ?> <span class="option-value" data-label="size"><?= e($default['size_label'] ?? '') ?></span></legend>
                                    <div class="option-chips">
                                        <?php foreach ($sizes as $s): ?>
                                            <label class="chip"><input type="radio" name="opt_size" value="<?= e($s) ?>" <?= ($default['size_label'] ?? '') === $s ? 'checked' : '' ?>><span><?= e($s) ?></span></label>
                                        <?php endforeach; ?>
                                    </div>
                                </fieldset>
                            <?php endif; ?>
                            <?php if (count($materials) > 1): ?>
                                <fieldset class="option-group">
                                    <legend><?= e(__('product.material')) ?> <span class="option-value" data-label="material"><?= e($materials[(string) ($default['product_material_id'] ?? '')] ?? '') ?></span></legend>
                                    <div class="option-chips">
                                        <?php foreach ($materials as $id => $name): ?>
                                            <label class="chip"><input type="radio" name="opt_material" value="<?= e((string) $id) ?>" data-name="<?= e($name) ?>" <?= (string) ($default['product_material_id'] ?? '') === (string) $id ? 'checked' : '' ?>><span><?= e($name) ?></span></label>
                                        <?php endforeach; ?>
                                    </div>
                                </fieldset>
                            <?php else: ?>
                                <input type="hidden" name="opt_material" value="<?= e((string) array_key_first($materials)) ?>">
                            <?php endif; ?>
                            <?php if (count($frames) > 1): ?>
                                <fieldset class="option-group">
                                    <legend><?= e(__('product.frame')) ?> <span class="option-value" data-label="frame"><?= e($frames[(string) ($default['frame_id'] ?? '')] ?? '') ?></span></legend>
                                    <div class="option-chips">
                                        <?php foreach ($frames as $id => $name): ?>
                                            <label class="chip"><input type="radio" name="opt_frame" value="<?= e((string) $id) ?>" data-name="<?= e($name) ?>" <?= (string) ($default['frame_id'] ?? '') === (string) $id ? 'checked' : '' ?>><span><?= e($name) ?></span></label>
                                        <?php endforeach; ?>
                                    </div>
                                </fieldset>
                            <?php else: ?>
                                <input type="hidden" name="opt_frame" value="<?= e((string) array_key_first($frames)) ?>">
                            <?php endif; ?>
                            <p class="small text-danger d-none" data-unavailable><?= e(__('product.combination_unavailable')) ?></p>
                        <?php endif; ?>

                        <div class="d-flex flex-wrap gap-2 align-items-stretch mt-4">
                            <div class="qty-input" data-qty>
                                <button type="button" data-step="-1" aria-label="<?= e(__('product.decrease')) ?>">−</button>
                                <input type="number" name="quantity" value="1" min="1" max="50" aria-label="<?= e(__('product.quantity')) ?>">
                                <button type="button" data-step="1" aria-label="<?= e(__('product.increase')) ?>">+</button>
                            </div>
                            <?php if ((int) $p['is_customizable'] === 1 && $router->has('builder.index')): ?>
                                <a class="btn btn-bwa btn-lg flex-grow-1" href="<?= e(route('builder.index', ['product' => $p['slug']])) ?>"><i class="bi bi-camera me-2"></i><?= e(__('product.customize')) ?></a>
                            <?php elseif ($router->has('cart.add')): ?>
                                <button type="submit" class="btn btn-bwa btn-lg flex-grow-1" data-buy <?= $p['out_of_stock'] ? 'disabled' : '' ?>><i class="bi bi-bag me-2"></i><?= e(__('product.add_to_cart')) ?></button>
                            <?php elseif ($whatsapp !== ''): ?>
                                <a class="btn btn-bwa btn-lg flex-grow-1" href="<?= e($whatsapp) ?>" target="_blank" rel="noopener" data-whatsapp-order><i class="bi bi-whatsapp me-2"></i><?= e(__('product.order_whatsapp')) ?></a>
                            <?php endif; ?>
                        </div>

                        <?php if ($p['out_of_stock']): ?><p class="small text-danger mt-2"><?= e(__('shop.out_of_stock')) ?></p><?php endif; ?>
                    </form>

                    <ul class="trust-list">
                        <li><i class="bi bi-truck"></i><?= e(__('product.trust_delivery', ['days' => $shipDays])) ?></li>
                        <li><i class="bi bi-cash-coin"></i><?= e(__('product.trust_cod')) ?></li>
                        <li><i class="bi bi-brush"></i><?= e(__('product.trust_handmade')) ?></li>
                    </ul>
                    <p class="small text-muted-bwa mb-0"><?= e(__('product.sku')) ?>: <span data-sku><?= e($default['sku'] ?? $p['sku']) ?></span></p>
                </div>
            </div>
        </div>

        <div class="product-tabs mt-5">
            <ul class="nav settings-tabs border-bottom" role="tablist">
                <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-desc" type="button" role="tab"><?= e(__('product.description')) ?></button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-specs" type="button" role="tab"><?= e(__('product.specifications')) ?></button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-delivery" type="button" role="tab"><?= e(__('product.delivery_returns')) ?></button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-reviews" type="button" role="tab" id="reviews"><?= e(__('product.reviews')) ?> (<?= (int) $p['review_count'] ?>)</button></li>
            </ul>
            <div class="tab-content py-4">
                <div class="tab-pane fade show active product-description" id="tab-desc" role="tabpanel">
                    <?php if (!empty($p['description'])): ?>
                        <?= nl2br(e($p['description'])) ?>
                    <?php else: ?>
                        <p class="text-muted-bwa"><?= e($p['short_description'] ?: __('product.no_description')) ?></p>
                    <?php endif; ?>
                </div>
                <div class="tab-pane fade" id="tab-specs" role="tabpanel">
                    <table class="table spec-table">
                        <tbody>
                        <tr><th><?= e(__('shop.product_type')) ?></th><td><?= e(__('shop.type_' . $p['product_type'])) ?></td></tr>
                        <?php if ($sizes): ?><tr><th><?= e(__('product.sizes_available')) ?></th><td><?= e(implode(' · ', $sizes)) ?></td></tr><?php endif; ?>
                        <?php if ($variants): ?><tr><th><?= e(__('product.material')) ?></th><td><?= e(implode(' · ', array_unique($materials))) ?></td></tr><?php endif; ?>
                        <?php if ($variants): ?><tr><th><?= e(__('product.frame')) ?></th><td><?= e(implode(' · ', array_unique($frames))) ?></td></tr><?php endif; ?>
                        <?php if ((int) $p['max_panels'] > 1): ?><tr><th><?= e(__('product.panels')) ?></th><td><?= (int) $p['min_panels'] ?>–<?= (int) $p['max_panels'] ?></td></tr><?php endif; ?>
                        <tr><th><?= e(__('product.customizable')) ?></th><td><?= e((int) $p['is_customizable'] === 1 ? __('common.yes') : __('common.no')) ?></td></tr>
                        <tr><th><?= e(__('product.sku')) ?></th><td><?= e($p['sku']) ?></td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="tab-pane fade" id="tab-delivery" role="tabpanel">
                    <p><?= e(__('product.delivery_text', ['production' => (int) setting('production_days', 3), 'min' => (int) setting('shipping_min_days', 2), 'max' => (int) setting('shipping_max_days', 5)])) ?></p>
                    <?php if ((string) setting('shipping_free_threshold', '') !== ''): ?>
                        <p><?= e(__('product.free_shipping_from', ['amount' => money((string) setting('shipping_free_threshold'))])) ?></p>
                    <?php endif; ?>
                    <p class="mb-0"><?= e(__('product.returns_text')) ?></p>
                </div>
                <div class="tab-pane fade" id="tab-reviews" role="tabpanel">
                    <?php if ($p['reviews'] === []): ?>
                        <p class="text-muted-bwa mb-0"><?= e(__('product.no_reviews')) ?></p>
                    <?php else: ?>
                        <div class="row g-3">
                            <?php foreach ($p['reviews'] as $r): ?>
                                <div class="col-md-6">
                                    <div class="review-card">
                                        <?= $this->insert('storefront/partials/stars', ['rating' => (float) $r['rating']]) ?>
                                        <?php if (!empty($r['title'])): ?><h3 class="h6 mt-2 mb-1" dir="auto"><?= e($r['title']) ?></h3><?php endif; ?>
                                        <p class="mb-2" dir="auto"><?= e($r['body']) ?></p>
                                        <div class="small text-muted-bwa">
                                            <?= e($r['author_name']) ?><?= $r['author_city'] ? ' · ' . e($r['author_city']) : '' ?> · <?= e(format_date($r['created_at'])) ?>
                                            <?php if ((int) $r['is_verified_purchase'] === 1): ?><span class="ms-2 text-success"><i class="bi bi-patch-check"></i> <?= e(__('product.verified')) ?></span><?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($related !== []): ?>
            <div class="mt-5 pt-3">
                <?= $this->insert('storefront/partials/section_head', ['eyebrow' => __('product.related_eyebrow'), 'title' => __('product.related')]) ?>
                <?= $this->insert('storefront/partials/product_grid', ['items' => $related]) ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php if ($default !== null): ?>
    <?= $this->insert('storefront/product/wall_studio', ['p' => $p, 'effect' => $effect, 'artUrl' => $images !== [] ? upload_url($images[0]['path']) : '']) ?>
    <?php $this->start('scripts'); ?>
    <script src="<?= e(asset('js/wall-studio.js')) ?>"></script>
    <?php $this->stop(); ?>
<?php endif; ?>
