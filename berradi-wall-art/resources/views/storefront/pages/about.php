<?php $this->layout('layouts/storefront', ['seo' => $seo]); ?>
<section class="page-hero">
    <div class="container">
        <?= $this->insert('storefront/partials/breadcrumbs', ['crumbs' => [[__('nav.home'), '/'], [__('nav.about'), null]]]) ?>
        <span class="eyebrow d-block mt-4"><?= e(__('pages.about_eyebrow')) ?></span>
        <h1 class="page-title mt-2"><?= e($title) ?></h1>
    </div>
</section>
<section class="section-sm">
    <div class="container">
        <div class="row g-4 g-lg-5 align-items-start">
            <div class="col-lg-7 about-body">
                <?php foreach (preg_split('/\n\s*\n/', trim($body)) ?: [] as $para): ?>
                    <p><?= nl2br(e(trim($para))) ?></p>
                <?php endforeach; ?>
            </div>
            <div class="col-lg-5">
                <div class="card-bwa p-4">
                    <h2 class="h4 mb-4"><?= e(__('pages.values_title')) ?></h2>
                    <?php foreach (['quality', 'craft', 'service'] as $v): ?>
                        <div class="d-flex gap-3 mb-4">
                            <i class="bi bi-<?= ['quality' => 'gem', 'craft' => 'brush', 'service' => 'chat-heart'][$v] ?> fs-4 text-gold"></i>
                            <div><h3 class="h6 mb-1"><?= e(__('pages.value_' . $v)) ?></h3><p class="small text-muted-bwa mb-0"><?= e(__('pages.value_' . $v . '_text')) ?></p></div>
                        </div>
                    <?php endforeach; ?>
                    <a class="btn btn-bwa w-100" href="<?= e(route('page.contact')) ?>"><?= e(__('pages.contact_us')) ?></a>
                </div>
            </div>
        </div>
        <?php if ($collections !== []): ?>
            <div class="mt-5 pt-4">
                <?= $this->insert('storefront/partials/section_head', ['eyebrow' => __('collections.eyebrow'), 'title' => __('nav.collections'), 'link' => route('collections.index')]) ?>
                <div class="row g-4">
                    <?php foreach ($collections as $c): ?><div class="col-md-4"><?= $this->insert('storefront/partials/collection_tile', ['c' => $c]) ?></div><?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>
