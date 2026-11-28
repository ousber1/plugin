<div class="card-bwa">
    <div class="card-body p-2">
        <div class="list-group account-nav">
            <a class="list-group-item list-group-item-action <?= ($current ?? '') === 'dashboard' ? 'active' : '' ?>" href="<?= e(route('customer.account')) ?>"><i class="bi bi-grid me-2"></i><?= e(__('account.overview')) ?></a>
            <a class="list-group-item list-group-item-action <?= ($current ?? '') === 'profile' ? 'active' : '' ?>" href="<?= e(route('customer.profile')) ?>"><i class="bi bi-person me-2"></i><?= e(__('account.profile')) ?></a>
            <?php foreach (['customer.orders' => ['bi-bag', 'account.orders'], 'customer.designs' => ['bi-images', 'account.designs'], 'customer.addresses' => ['bi-geo-alt', 'account.addresses']] as $r => [$icon, $label]): ?>
                <?php if (app()->router()->has($r)): ?>
                    <a class="list-group-item list-group-item-action <?= ($current ?? '') === $r ? 'active' : '' ?>" href="<?= e(route($r)) ?>"><i class="bi <?= $icon ?> me-2"></i><?= e(__($label)) ?></a>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <form method="post" action="<?= e(route('customer.logout')) ?>" class="px-3 py-2 border-top mt-2">
            <?= csrf_field() ?>
            <button class="btn btn-link p-0 small text-danger text-decoration-none" type="submit"><i class="bi bi-box-arrow-right me-1"></i><?= e(__('auth.logout')) ?></button>
        </form>
    </div>
</div>
