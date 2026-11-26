<?php $this->layout('layouts/storefront', ['seo' => $seo]); ?>
<section class="page-hero">
    <div class="container">
        <?= $this->insert('storefront/partials/breadcrumbs', ['crumbs' => [[__('nav.home'), '/'], [__('nav.collections'), null]]]) ?>
        <span class="eyebrow d-block mt-4"><?= e(__('collections.eyebrow')) ?></span>
        <h1 class="page-title mt-2"><?= e(__('nav.collections')) ?></h1>
        <p class="page-lead"><?= e(__('collections.intro')) ?></p>
    </div>
</section>
<section class="section-sm">
    <div class="container">
        <?php if ($collections === []): ?>
            <div class="empty-state card-bwa"><i class="bi bi-collection"></i><?= e(__('collections.empty')) ?></div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($collections as $i => $c): ?>
                    <div class="col-md-6 <?= $i % 3 === 0 ? 'col-lg-8' : 'col-lg-4' ?>">
                        <?= $this->insert('storefront/partials/collection_tile', ['c' => $c, 'large' => $i % 3 === 0]) ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
