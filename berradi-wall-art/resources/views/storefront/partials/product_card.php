<?php
/** @var array<string,mixed> $p decorated product row from CatalogService */
$url = route('product.show', ['slug' => $p['slug']]);
$effect = match ($p['product_type']) {
    'framed' => 'is-framed',
    'canvas', 'multi_panel' => 'is-canvas',
    'poster', 'photo_print' => 'is-poster',
    default => 'is-canvas',
};
?>
<article class="product-card">
    <a href="<?= e($url) ?>" class="product-media" aria-label="<?= e($p['title']) ?>">
        <span class="art-wall">
            <?php if (!empty($p['main_image'])): ?>
                <img class="art-piece <?= $effect ?>" src="<?= e(upload_url($p['main_image'])) ?>" alt="<?= e($p['title']) ?>" loading="lazy" decoding="async">
            <?php else: ?>
                <span class="art-piece art-placeholder <?= $effect ?>"><?= e(mb_substr((string) $p['title'], 0, 1)) ?></span>
            <?php endif; ?>
        </span>
        <span class="product-badges">
            <?php if ($p['on_sale']): ?><span class="pbadge is-sale">-<?= (int) $p['discount_percent'] ?>%</span><?php endif; ?>
            <?php if ((int) $p['is_new'] === 1): ?><span class="pbadge"><?= e(__('shop.badge_new')) ?></span><?php endif; ?>
            <?php if ((int) $p['is_best_seller'] === 1): ?><span class="pbadge is-dark"><?= e(__('shop.badge_best')) ?></span><?php endif; ?>
            <?php if ((int) $p['is_customizable'] === 1 || (int) ($p['perso_photo'] ?? 0) > 0): ?><span class="pbadge is-gold" title="<?= e(__('gifts.badge_photo')) ?>"><i class="bi bi-camera"></i> <?= e(__('gifts.badge_photo')) ?></span>
            <?php elseif ((int) ($p['perso_count'] ?? 0) > 0): ?><span class="pbadge is-gold"><i class="bi bi-pencil"></i> <?= e(__('gifts.badge_text')) ?></span><?php endif; ?>
        </span>
        <?php if ($p['out_of_stock']): ?><span class="product-soldout"><?= e(__('shop.out_of_stock')) ?></span><?php endif; ?>
    </a>
    <div class="product-info">
        <?php if (!empty($p['category_name'])): ?><span class="product-cat"><?= e($p['category_name']) ?></span><?php endif; ?>
        <h3 class="product-title"><a href="<?= e($url) ?>"><?= e($p['title']) ?></a></h3>
        <div class="product-price">
            <?php if ($p['from_price']): ?><span class="from"><?= e(__('shop.from')) ?></span><?php endif; ?>
            <span class="now"><?= e(money($p['display_price'])) ?></span>
            <?php if ($p['on_sale']): ?><del class="was"><?= e(money($p['compare_price'])) ?></del><?php endif; ?>
        </div>
        <?php if ((int) $p['review_count'] > 0): ?>
            <div class="product-rating" aria-label="<?= e(__('shop.rating', ['rating' => (string) $p['avg_rating']])) ?>">
                <?= $this->insert('storefront/partials/stars', ['rating' => (float) $p['avg_rating']]) ?>
                <span>(<?= (int) $p['review_count'] ?>)</span>
            </div>
        <?php endif; ?>
        <?php
        $router = app()->router();
        $personal = (int) ($p['perso_count'] ?? 0) > 0 || (int) $p['is_customizable'] === 1;
        ?>
        <div class="product-cta">
            <?php if ($p['out_of_stock']): ?>
                <a class="btn btn-outline-bwa btn-sm w-100" href="<?= e($url) ?>"><?= e(__('gifts.see_product')) ?></a>
            <?php elseif ($personal): ?>
                <a class="btn btn-bwa btn-sm w-100" href="<?= e((int) $p['is_customizable'] === 1 && $router->has('builder.index') ? route('builder.index', ['product' => $p['slug']]) : $url) ?>"><i class="bi bi-pencil-square me-1"></i><?= e(__('gifts.personalize')) ?></a>
            <?php elseif ((int) ($p['variant_count'] ?? 1) > 0 || !$router->has('cart.add')): ?>
                <a class="btn btn-outline-bwa btn-sm w-100" href="<?= e($url) ?>"><?= e(__('gifts.choose')) ?></a>
            <?php else: ?>
                <form method="post" action="<?= e(route('cart.add')) ?>"><?= csrf_field() ?><input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>"><input type="hidden" name="quantity" value="1">
                    <button class="btn btn-bwa btn-sm w-100" type="submit"><i class="bi bi-basket me-1"></i><?= e(__('gifts.add_to_basket')) ?></button></form>
            <?php endif; ?>
        </div>
    </div>
</article>
