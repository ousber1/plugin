<?php
$company = (string) setting('company_name', 'BERRADI WALL ART');
$email = (string) setting('company_email', '');
$phone = (string) setting('company_phone', '');
$address = trim((string) setting('company_address', '') . ' ' . (string) setting('company_city', ''));
$wa = whatsapp_link(null, __('common.whatsapp_greeting'));
$socials = array_filter([
    'instagram' => (string) setting('social_instagram', ''),
    'facebook' => (string) setting('social_facebook', ''),
    'tiktok' => (string) setting('social_tiktok', ''),
]);
?>
<footer class="site-footer pt-5 mt-auto">
    <div class="container py-4">
        <div class="row g-4 g-lg-5">
            <div class="col-lg-4">
                <a href="<?= e(route('home')) ?>" class="d-inline-block mb-3"><?= $this->insert('partials/brand', ['textOnly' => true]) ?></a>
                <p class="mb-4" style="max-width:340px"><?= e(__('common.footer_about')) ?></p>
                <div class="d-flex gap-3 fs-5">
                    <?php foreach ($socials as $network => $link): ?>
                        <a href="<?= e($link) ?>" target="_blank" rel="noopener" aria-label="<?= e(ucfirst($network)) ?>"><i class="bi bi-<?= e($network) ?>"></i></a>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="col-6 col-lg-2">
                <h6><?= e(__('nav.shop')) ?></h6>
                <ul class="list-unstyled d-grid gap-2">
                    <li><a href="<?= e(route('home')) ?>"><?= e(__('nav.home')) ?></a></li>
                    <?php foreach (['shop.index' => 'nav.shop', 'builder.index' => 'nav.custom', 'collections.index' => 'nav.collections'] as $r => $label): ?>
                        <?php if (app()->router()->has($r)): ?><li><a href="<?= e(route($r)) ?>"><?= e(__($label)) ?></a></li><?php endif; ?>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="col-6 col-lg-2">
                <h6><?= e(__('nav.account')) ?></h6>
                <ul class="list-unstyled d-grid gap-2">
                    <li><a href="<?= e(route('customer.login')) ?>"><?= e(__('nav.login')) ?></a></li>
                    <li><a href="<?= e(route('customer.register')) ?>"><?= e(__('nav.register')) ?></a></li>
                    <?php if (app()->router()->has('order.track')): ?><li><a href="<?= e(route('order.track')) ?>"><?= e(__('nav.track')) ?></a></li><?php endif; ?>
                </ul>
            </div>
            <div class="col-lg-4">
                <h6><?= e(__('nav.contact')) ?></h6>
                <ul class="list-unstyled d-grid gap-2">
                    <?php if ($address !== ''): ?><li><i class="bi bi-geo-alt me-2 text-gold"></i><?= e($address) ?></li><?php endif; ?>
                    <?php if ($phone !== ''): ?><li><i class="bi bi-telephone me-2 text-gold"></i><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $phone)) ?>" dir="ltr"><?= e($phone) ?></a></li><?php endif; ?>
                    <?php if ($email !== ''): ?><li><i class="bi bi-envelope me-2 text-gold"></i><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></li><?php endif; ?>
                    <?php if ($wa !== ''): ?><li><i class="bi bi-whatsapp me-2 text-gold"></i><a href="<?= e($wa) ?>" target="_blank" rel="noopener">WhatsApp</a></li><?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
    <div class="footer-bottom py-3">
        <div class="container d-flex flex-column flex-md-row justify-content-between gap-2">
            <span>&copy; <?= date('Y') ?> <?= e($company) ?>. <?= e(__('common.rights')) ?></span>
            <span><?= e(__('common.made_in_morocco')) ?></span>
        </div>
    </div>
</footer>
<?php if ($wa !== ''): ?>
    <a class="whatsapp-fab" href="<?= e($wa) ?>" target="_blank" rel="noopener" aria-label="WhatsApp"><i class="bi bi-whatsapp"></i></a>
<?php endif; ?>
