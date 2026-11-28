<div class="text-center py-3">
    <i class="bi bi-shield-lock text-gold" style="font-size:3rem"></i>
    <p class="lead mt-3"><?= e(__('installer.locked_intro')) ?></p>
    <p class="small text-muted-bwa"><?= e(__('installer.locked_note')) ?></p>
    <?php if (!empty($demo)): ?><div class="alert alert-info small"><?= e(__('installer.demo_done', ['products' => (int) $demo['products'], 'variants' => (int) $demo['variants']])) ?></div><?php endif; ?>
    <div class="d-flex justify-content-center gap-3 mt-4 flex-wrap">
        <a class="btn btn-bwa" href="<?= e($adminUrl) ?>"><?= e(__('installer.go_admin')) ?></a>
        <a class="btn btn-outline-bwa" href="<?= e(url('/')) ?>"><?= e(__('installer.go_store')) ?></a>
    </div>
    <?php if (!empty($adminEmail)): ?><p class="small text-muted-bwa mt-4"><?= e(__('installer.login_with', ['email' => $adminEmail])) ?></p><?php endif; ?>
</div>
