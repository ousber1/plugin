<?php
$this->layout('layouts/storefront', ['title' => '']);
$router = app()->router();
$primaryCta = $router->has('builder.index') ? route('builder.index') : ($router->has('shop.index') ? route('shop.index') : null);
$wa = whatsapp_link(null, __('common.whatsapp_greeting'));
?>
<section class="hero">
    <div class="container position-relative">
        <div class="row align-items-center g-4 g-lg-5">
            <div class="col-lg-6">
                <span class="eyebrow text-gold"><?= e(__('home.hero_eyebrow')) ?></span>
                <h1 class="mt-3 mb-4"><?= __('home.hero_title_html') ?></h1>
                <p class="lead mb-5"><?= e(__('home.hero_lead')) ?></p>
                <div class="d-flex flex-wrap gap-3">
                    <?php if ($primaryCta): ?>
                        <a href="<?= e($primaryCta) ?>" class="btn btn-gold btn-lg"><?= e(__('home.cta_create')) ?></a>
                    <?php endif; ?>
                    <?php if ($wa !== ''): ?>
                        <a href="<?= e($wa) ?>" class="btn btn-outline-light-bwa btn-lg" target="_blank" rel="noopener"><i class="bi bi-whatsapp me-2"></i><?= e(__('home.cta_whatsapp')) ?></a>
                    <?php elseif (!$primaryCta): ?>
                        <a href="<?= e(route('customer.register')) ?>" class="btn btn-gold btn-lg"><?= e(__('auth.create_account')) ?></a>
                    <?php endif; ?>
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

<section class="section">
    <div class="container">
        <div class="text-center mb-5">
            <span class="eyebrow"><?= e(__('home.categories_eyebrow')) ?></span>
            <h2 class="section-title mt-2"><?= e(__('home.categories_title')) ?></h2>
            <hr class="gold-rule mx-auto">
        </div>
        <?php if ($categories === []): ?>
            <div class="empty-state"><i class="bi bi-grid"></i><?= e(__('common.no_results')) ?></div>
        <?php else: ?>
            <div class="row g-3 g-md-4">
                <?php foreach ($categories as $cat): ?>
                    <?php $href = $router->has('shop.category') ? route('shop.category', ['slug' => $cat['slug']]) : null; ?>
                    <div class="col-6 col-md-4 col-xl-3">
                        <<?= $href ? 'a href="' . e($href) . '"' : 'div' ?> class="category-tile">
                            <?php if (!empty($cat['image'])): ?>
                                <img src="<?= e(upload_url($cat['image'])) ?>" alt="<?= e($cat['label']) ?>" loading="lazy">
                            <?php else: ?>
                                <span class="tile-monogram"><?= e(mb_substr((string) $cat['name'], 0, 1)) ?></span>
                            <?php endif; ?>
                            <span class="tile-label"><?= e($cat['label']) ?></span>
                        </<?= $href ? 'a' : 'div' ?>>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="section bg-white border-top border-bottom">
    <div class="container">
        <div class="text-center mb-5">
            <span class="eyebrow"><?= e(__('home.how_eyebrow')) ?></span>
            <h2 class="section-title mt-2"><?= e(__('home.how_title')) ?></h2>
            <hr class="gold-rule mx-auto">
        </div>
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
