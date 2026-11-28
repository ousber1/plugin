<div class="section-head <?= !empty($center) ? 'text-center' : 'd-flex flex-wrap align-items-end justify-content-between gap-3' ?> mb-4 mb-md-5">
    <div>
        <?php if (!empty($eyebrow)): ?><span class="eyebrow"><?= e($eyebrow) ?></span><?php endif; ?>
        <h2 class="section-title mt-2 mb-0"><?= e($title) ?></h2>
        <?php if (!empty($center)): ?><hr class="gold-rule mx-auto"><?php endif; ?>
        <?php if (!empty($lead)): ?><p class="text-muted-bwa mt-3 mb-0 <?= !empty($center) ? 'mx-auto' : '' ?>" style="max-width:620px"><?= e($lead) ?></p><?php endif; ?>
    </div>
    <?php if (!empty($link) && empty($center)): ?>
        <a class="btn-link-bwa" href="<?= e($link) ?>"><?= e($linkLabel ?? __('home.view_all')) ?> <i class="bi bi-arrow-<?= is_rtl() ? 'left' : 'right' ?>"></i></a>
    <?php endif; ?>
</div>
