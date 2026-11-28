<?php $this->layout('layouts/storefront', ['seo' => $seo]); ?>
<section class="page-hero">
    <div class="container">
        <?= $this->insert('storefront/partials/breadcrumbs', ['crumbs' => [[__('nav.home'), '/'], [__('pages.faq'), null]]]) ?>
        <span class="eyebrow d-block mt-4"><?= e(__('pages.faq_eyebrow')) ?></span>
        <h1 class="page-title mt-2"><?= e(__('pages.faq')) ?></h1>
        <p class="page-lead"><?= e(__('pages.faq_intro')) ?></p>
    </div>
</section>
<section class="section-sm">
    <div class="container" style="max-width:860px">
        <?php if ($faqs === []): ?>
            <div class="empty-state card-bwa"><i class="bi bi-question-circle"></i><?= e(__('pages.faq_empty')) ?></div>
        <?php else: ?>
            <?= $this->insert('storefront/partials/faq_list', ['faqs' => $faqs, 'id' => 'faqPage']) ?>
        <?php endif; ?>
        <div class="text-center mt-5">
            <p class="text-muted-bwa"><?= e(__('pages.faq_more')) ?></p>
            <a class="btn btn-outline-bwa" href="<?= e(route('page.contact')) ?>"><?= e(__('pages.contact_us')) ?></a>
        </div>
    </div>
</section>
