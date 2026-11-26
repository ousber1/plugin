<?php $this->layout('layouts/storefront', ['seo' => $seo]); ?>
<section class="page-hero">
    <div class="container">
        <span class="eyebrow d-block"><?= e(__('pages.legal')) ?></span>
        <h1 class="page-title mt-2"><?= e(__('pages.terms')) ?></h1>
    </div>
</section>
<section class="section-sm">
    <div class="container about-body" style="max-width:860px">
        <?php foreach (preg_split('/\n\s*\n/', trim($body)) ?: [] as $para): ?>
            <?php if (preg_match('/^##\s*(.+)$/u', trim($para), $m)): ?>
                <h2 class="h4 mt-4"><?= e($m[1]) ?></h2>
            <?php else: ?>
                <p><?= nl2br(e(trim($para))) ?></p>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</section>
