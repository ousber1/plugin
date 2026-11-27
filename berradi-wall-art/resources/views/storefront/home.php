<?php
$this->layout('layouts/storefront', ['seo' => $seo]);
$router = app()->router();
$builderUrl = $router->has('builder.index') ? route('builder.index') : null;
$wa = whatsapp_link(null, __('common.whatsapp_greeting'));
$waCustom = whatsapp_link(null, __('home.whatsapp_custom_message'));
$customCta = $builderUrl ?? ($waCustom !== '' ? $waCustom : route('page.contact'));
$arrow = is_rtl() ? 'left' : 'right';
?>

<!-- 1-2. Hero -->
<?php if ($heroBanners !== []): ?>
    <section class="hero hero-banners">
        <div id="heroCarousel" class="carousel slide carousel-fade w-100" data-bs-ride="carousel" data-bs-interval="7000">
            <div class="carousel-inner">
                <?php foreach ($heroBanners as $i => $b): ?>
                    <div class="carousel-item<?= $i === 0 ? ' active' : '' ?>">
                        <?php if (!empty($b['image'])): ?>
                            <picture>
                                <?php if (!empty($b['mobile_image'])): ?><source media="(max-width: 767px)" srcset="<?= e(upload_url($b['mobile_image'])) ?>"><?php endif; ?>
                                <img class="hero-bg" src="<?= e(upload_url($b['image'])) ?>" alt="" <?= $i === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?>>
                            </picture>
                        <?php endif; ?>
                        <div class="container hero-content">
                            <span class="eyebrow text-gold"><?= e(__('home.hero_eyebrow')) ?></span>
                            <?php if ($i === 0): ?><h1 class="mt-3 mb-4"><?= e($b['title']) ?></h1><?php else: ?><p class="h1 mt-3 mb-4"><?= e($b['title']) ?></p><?php endif; ?>
                            <?php if (!empty($b['subtitle'])): ?><p class="lead mb-5"><?= e($b['subtitle']) ?></p><?php endif; ?>
                            <?php if (!empty($b['link_url'])): ?><a href="<?= e($b['link_url']) ?>" class="btn btn-gold btn-lg"><?= e($b['button_text'] ?: __('home.discover')) ?></a><?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if (count($heroBanners) > 1): ?>
                <div class="carousel-indicators">
                    <?php foreach ($heroBanners as $i => $b): ?><button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="<?= $i ?>" class="<?= $i === 0 ? 'active' : '' ?>" aria-label="<?= $i + 1 ?>"></button><?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
<?php else: ?>
    <section class="hero">
        <div class="container position-relative">
            <div class="row align-items-center g-4 g-lg-5">
                <div class="col-lg-6">
                    <span class="eyebrow text-gold"><?= e(__('home.hero_eyebrow')) ?></span>
                    <h1 class="mt-3 mb-4"><?= __('home.hero_title_html') ?></h1>
                    <p class="lead mb-5"><?= e(__('home.hero_lead')) ?></p>
                    <div class="d-flex flex-wrap gap-3">
                        <a href="<?= e($customCta) ?>" class="btn btn-gold btn-lg" <?= $builderUrl ? '' : 'target="_blank" rel="noopener"' ?>><?= e(__('home.cta_create')) ?></a>
                        <a href="<?= e(route('shop.index')) ?>" class="btn btn-outline-light-bwa btn-lg"><?= e(__('home.cta_shop')) ?></a>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="hero-frame" aria-hidden="true">
                        <span class="frame-caption"><?= e(__('home.frame_caption')) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </section>
<?php endif; ?>

<!-- trust bar -->
<section class="trust-bar">
    <div class="container">
        <div class="row g-3 text-center">
            <?php foreach ([['bi-gem', 'trust_quality'], ['bi-brush', 'trust_handmade'], ['bi-truck', 'trust_delivery'], ['bi-cash-coin', 'trust_cod']] as [$icon, $key]): ?>
                <div class="col-6 col-lg-3"><i class="bi <?= $icon ?>"></i><span><?= e(__('home.' . $key)) ?></span></div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- 3. Custom photo wall art -->
<section class="section custom-section">
    <div class="container">
        <div class="row align-items-center g-4 g-lg-5">
            <div class="col-lg-6 order-lg-2">
                <span class="eyebrow"><?= e(__('home.custom_eyebrow')) ?></span>
                <h2 class="section-title mt-2"><?= e(__('home.custom_title')) ?></h2>
                <hr class="gold-rule">
                <p class="text-muted-bwa mb-4"><?= e(__('home.custom_text')) ?></p>
                <ul class="check-list mb-5">
                    <?php foreach ([1, 2, 3, 4] as $i): ?><li><i class="bi bi-check2"></i><?= e(__('home.custom_point_' . $i)) ?></li><?php endforeach; ?>
                </ul>
                <a href="<?= e($customCta) ?>" class="btn btn-bwa btn-lg" <?= $builderUrl ? '' : 'target="_blank" rel="noopener"' ?>>
                    <?= $builderUrl ? e(__('home.custom_cta')) : '<i class="bi bi-whatsapp me-2"></i>' . e(__('home.custom_cta_whatsapp')) ?>
                </a>
            </div>
            <div class="col-lg-6 order-lg-1">
                <div class="photo-to-art" aria-hidden="true">
                    <div class="pta-photo"><i class="bi bi-camera"></i><span><?= e(__('home.your_photo')) ?></span></div>
                    <div class="pta-arrow"><i class="bi bi-arrow-<?= $arrow ?>"></i></div>
                    <div class="pta-canvas"><span><?= e(__('home.your_art')) ?></span></div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- 4. Collections -->
<?php if ($collections !== []): ?>
    <section class="section bg-white border-top border-bottom">
        <div class="container">
            <?= $this->insert('storefront/partials/section_head', ['eyebrow' => __('collections.eyebrow'), 'title' => __('home.collections_title'), 'link' => route('collections.index')]) ?>
            <div class="row g-4">
                <?php $featuredFirst = count($collections) === 3; // 6 + 3 + 3 fills one row; otherwise equal tiles ?>
                <?php foreach ($collections as $i => $c): ?>
                    <?php $big = $featuredFirst && $i === 0; ?>
                    <div class="col-6 <?= $big ? 'col-lg-6' : 'col-lg-' . (count($collections) >= 4 ? 3 : (count($collections) === 3 ? 3 : 12 / max(1, count($collections)))) ?>"><?= $this->insert('storefront/partials/collection_tile', ['c' => $c, 'large' => $big]) ?></div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<!-- 5. Best sellers -->
<?php if ($bestSellers !== []): ?>
    <section class="section">
        <div class="container">
            <?= $this->insert('storefront/partials/section_head', ['eyebrow' => __('home.best_eyebrow'), 'title' => __('nav.best_sellers'), 'link' => route('shop.best_sellers')]) ?>
            <?= $this->insert('storefront/partials/product_grid', ['items' => $bestSellers]) ?>
        </div>
    </section>
<?php endif; ?>

<!-- 11. Special offers -->
<?php if ($promoBanners !== [] || $offers !== []): ?>
    <section class="section offers-section">
        <div class="container">
            <?php foreach ($promoBanners as $b): ?>
                <div class="promo-banner mb-4">
                    <?php if (!empty($b['image'])): ?><img src="<?= e(upload_url($b['image'])) ?>" alt="" loading="lazy"><?php endif; ?>
                    <div class="promo-content">
                        <span class="eyebrow text-gold"><?= e(__('home.offers_eyebrow')) ?></span>
                        <h2 class="mt-2"><?= e($b['title']) ?></h2>
                        <?php if (!empty($b['subtitle'])): ?><p><?= e($b['subtitle']) ?></p><?php endif; ?>
                        <?php if (!empty($b['link_url'])): ?><a class="btn btn-gold" href="<?= e($b['link_url']) ?>"><?= e($b['button_text'] ?: __('home.discover')) ?></a><?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if ($offers !== []): ?>
                <?= $this->insert('storefront/partials/section_head', ['eyebrow' => __('home.offers_eyebrow'), 'title' => __('shop.offers'), 'link' => route('shop.offers')]) ?>
                <?= $this->insert('storefront/partials/product_grid', ['items' => $offers]) ?>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>

<!-- 6. New arrivals -->
<?php if ($newArrivals !== []): ?>
    <section class="section <?= $promoBanners === [] && $offers === [] ? 'bg-white border-top border-bottom' : '' ?>">
        <div class="container">
            <?= $this->insert('storefront/partials/section_head', ['eyebrow' => __('home.new_eyebrow'), 'title' => __('shop.new_arrivals'), 'link' => route('shop.new')]) ?>
            <?= $this->insert('storefront/partials/product_grid', ['items' => $newArrivals]) ?>
        </div>
    </section>
<?php endif; ?>

<!-- 7. Featured categories -->
<?php if ($categories !== []): ?>
    <section class="section">
        <div class="container">
            <?= $this->insert('storefront/partials/section_head', ['eyebrow' => __('home.categories_eyebrow'), 'title' => __('home.categories_title'), 'link' => route('shop.index'), 'linkLabel' => __('home.all_categories')]) ?>
            <div class="category-scroller">
                <?php foreach ($categories as $cat): ?>
                    <a href="<?= e(route('shop.category', ['slug' => $cat['slug']])) ?>" class="category-tile">
                        <?php if (!empty($cat['image'])): ?>
                            <img src="<?= e(upload_url($cat['image'])) ?>" alt="<?= e($cat['name']) ?>" loading="lazy">
                        <?php else: ?>
                            <span class="tile-monogram"><?= e(mb_substr((string) $cat['name'], 0, 1)) ?></span>
                        <?php endif; ?>
                        <span class="tile-label"><?= e($cat['name']) ?><?php if ((int) $cat['product_count'] > 0): ?><small><?= e(__('collections.pieces', ['count' => (int) $cat['product_count']])) ?></small><?php endif; ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<!-- 8. How it works -->
<section class="section bg-white border-top border-bottom">
    <div class="container">
        <?= $this->insert('storefront/partials/section_head', ['eyebrow' => __('home.how_eyebrow'), 'title' => __('home.how_title'), 'center' => true]) ?>
        <div class="row g-4">
            <?php foreach ([1, 2, 3, 4] as $i): ?>
                <div class="col-sm-6 col-lg-3">
                    <div class="step-card">
                        <div class="step-number"><?= $i ?></div>
                        <h3 class="h5"><?= e(__('home.how_' . $i . '_title')) ?></h3>
                        <p class="text-muted-bwa small mb-0"><?= e(__('home.how_' . $i . '_text')) ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- 9. Customer reviews (approved real reviews only) -->
<?php if ($reviews !== []): ?>
    <section class="section">
        <div class="container">
            <?= $this->insert('storefront/partials/section_head', ['eyebrow' => __('home.reviews_eyebrow'), 'title' => __('home.reviews_title'), 'center' => true,
                'lead' => $reviewStats['count'] > 0 ? __('home.reviews_stats', ['avg' => (string) $reviewStats['avg'], 'count' => $reviewStats['count']]) : null]) ?>
            <div class="row g-4">
                <?php foreach ($reviews as $r): ?>
                    <div class="col-md-6 col-lg-4">
                        <figure class="review-card h-100 mb-0">
                            <?= $this->insert('storefront/partials/stars', ['rating' => (float) $r['rating']]) ?>
                            <blockquote class="mt-3 mb-3" dir="auto"><?= e($r['body']) ?></blockquote>
                            <figcaption class="small text-muted-bwa">
                                <strong class="text-dark"><?= e($r['author_name']) ?></strong><?= $r['author_city'] ? ' · ' . e($r['author_city']) : '' ?>
                                <?php if (!empty($r['product_slug'])): ?><br><a href="<?= e(route('product.show', ['slug' => $r['product_slug']])) ?>"><?= e($r['product_title']) ?></a><?php endif; ?>
                            </figcaption>
                        </figure>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<!-- 10. Gallery of customer installations (published with consent) -->
<?php if ($gallery !== []): ?>
    <section class="section bg-white border-top border-bottom">
        <div class="container">
            <?= $this->insert('storefront/partials/section_head', ['eyebrow' => __('home.gallery_eyebrow'), 'title' => __('home.gallery_title'), 'center' => true]) ?>
            <div class="gallery-masonry">
                <?php foreach ($gallery as $g): ?>
                    <figure class="gallery-item">
                        <img src="<?= e(upload_url($g['image'])) ?>" alt="<?= e($g['title'] ?? '') ?>" loading="lazy">
                        <?php if (!empty($g['title']) || !empty($g['city'])): ?><figcaption><?= e(trim(($g['title'] ?? '') . ($g['city'] ? ' · ' . $g['city'] : ''), ' ·')) ?></figcaption><?php endif; ?>
                    </figure>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<!-- 12. FAQ -->
<?php if ($faqs !== []): ?>
    <section class="section">
        <div class="container" style="max-width:860px">
            <?= $this->insert('storefront/partials/section_head', ['eyebrow' => __('pages.faq_eyebrow'), 'title' => __('pages.faq'), 'center' => true]) ?>
            <?= $this->insert('storefront/partials/faq_list', ['faqs' => $faqs, 'id' => 'homeFaq']) ?>
            <div class="text-center mt-4"><a class="btn-link-bwa" href="<?= e(route('page.faq')) ?>"><?= e(__('home.view_all')) ?></a></div>
        </div>
    </section>
<?php endif; ?>

<!-- 13-14. Newsletter + WhatsApp -->
<section class="newsletter-band">
    <div class="container">
        <div class="row g-4 align-items-center">
            <div class="col-lg-6">
                <span class="eyebrow text-gold"><?= e(__('newsletter.eyebrow')) ?></span>
                <h2 class="mt-2 text-white"><?= e(__('newsletter.title')) ?></h2>
                <p class="mb-0"><?= e(__('newsletter.text')) ?></p>
            </div>
            <div class="col-lg-6">
                <form method="post" action="<?= e(route('newsletter.subscribe')) ?>" class="newsletter-form" data-newsletter novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="source" value="home">
                    <div class="input-group">
                        <input type="email" name="email" class="form-control<?= invalid('newsletter_email') ?>" placeholder="<?= e(__('newsletter.placeholder')) ?>" required autocomplete="email" value="<?= e(old('newsletter_email')) ?>" aria-label="<?= e(__('auth.email')) ?>">
                        <button class="btn btn-gold" type="submit"><?= e(__('newsletter.subscribe')) ?></button>
                    </div>
                    <label class="form-check mt-2 small">
                        <input class="form-check-input" type="checkbox" name="consent" value="1" required>
                        <span class="form-check-label"><?= e(__('newsletter.consent')) ?></span>
                    </label>
                    <div class="newsletter-feedback small mt-2" data-feedback role="status"><?= error('newsletter_email') ? e(error('newsletter_email')) : '' ?></div>
                </form>
                <?php if ($wa !== ''): ?>
                    <a class="whatsapp-line mt-3" href="<?= e($wa) ?>" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i> <?= e(__('home.whatsapp_band')) ?></a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
