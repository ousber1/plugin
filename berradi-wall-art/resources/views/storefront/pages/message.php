<?php $this->layout('layouts/storefront', ['seo' => $seo]); ?>
<section class="auth-wrap">
    <div class="container text-center" style="max-width:620px">
        <i class="bi bi-envelope-check text-gold" style="font-size:3rem"></i>
        <h1 class="mt-3"><?= e($heading) ?></h1>
        <p class="text-muted-bwa"><?= e($text) ?></p>
        <a class="btn btn-bwa mt-3" href="<?= e(route('home')) ?>"><?= e(__('errors.home')) ?></a>
    </div>
</section>
