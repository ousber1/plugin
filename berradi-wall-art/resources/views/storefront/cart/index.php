<?php $this->layout('layouts/storefront', ['seo' => $seo]); ?>
<section class="section-sm">
    <div class="container">
        <h1 class="page-title mb-4"><?= e(__('cart.title')) ?></h1>
        <?php if ($lines === []): ?>
            <div class="empty-state card-bwa py-5">
                <i class="bi bi-bag"></i>
                <p class="mb-4"><?= e(__('cart.empty')) ?></p>
                <div class="d-flex justify-content-center gap-2 flex-wrap">
                    <a class="btn btn-bwa" href="<?= e(route('shop.index')) ?>"><?= e(__('nav.shop')) ?></a>
                    <a class="btn btn-outline-bwa" href="<?= e(route('builder.index')) ?>"><?= e(__('nav.custom')) ?></a>
                </div>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="card-bwa">
                        <?php foreach ($lines as $l): ?>
                            <div class="cart-line<?= $l['available'] ? '' : ' is-unavailable' ?>">
                                <a class="cl-thumb" href="<?= e((string) $l['url']) ?>">
                                    <?php if ($l['preview_url']): ?><img src="<?= e($l['preview_url']) ?>" alt="" loading="lazy"><?php else: ?><i class="bi bi-image"></i><?php endif; ?>
                                </a>
                                <div class="cl-info">
                                    <a class="cl-title" href="<?= e((string) $l['url']) ?>"><?= e($l['title']) ?></a>
                                    <?php if ($l['details'] !== ''): ?><div class="small text-muted-bwa"><?= e($l['details']) ?></div><?php endif; ?>
                                    <?= $this->insert('partials/personalization', ['options' => ['personalization' => $l['personalization'] ?? []]]) ?>
                                    <?php if ($l['kind'] === 'design'): ?><span class="badge-status mt-1 d-inline-block"><i class="bi bi-camera me-1"></i><?= e(__('cart.custom_design')) ?></span><?php endif; ?>
                                    <?php if ($l['error']): ?><div class="small text-danger mt-1"><i class="bi bi-exclamation-circle me-1"></i><?= e($l['error']) ?></div><?php endif; ?>
                                    <div class="cl-unit small mt-1"><?= e(money($l['unit_price'])) ?><?php if (!empty($l['regular_price']) && \App\Helpers\Money::compare($l['regular_price'], $l['unit_price']) > 0): ?> <del class="text-muted-bwa"><?= e(money($l['regular_price'])) ?></del><?php endif; ?></div>
                                </div>
                                <form method="post" action="<?= e(route('cart.update', ['id' => $l['id']])) ?>" class="cl-qty" data-autosubmit-qty>
                                    <?= csrf_field() ?>
                                    <div class="qty-input" data-qty>
                                        <button type="button" data-step="-1" aria-label="<?= e(__('product.decrease')) ?>">−</button>
                                        <input type="number" name="quantity" value="<?= (int) $l['quantity'] ?>" min="1" max="50" aria-label="<?= e(__('product.quantity')) ?>">
                                        <button type="button" data-step="1" aria-label="<?= e(__('product.increase')) ?>">+</button>
                                    </div>
                                    <noscript><button class="btn btn-sm btn-outline-bwa mt-1" type="submit"><?= e(__('cart.update')) ?></button></noscript>
                                </form>
                                <div class="cl-total"><?= e(money($l['line_total'])) ?></div>
                                <form method="post" action="<?= e(route('cart.remove', ['id' => $l['id']])) ?>" class="cl-remove">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-link text-muted-bwa p-0" type="submit" aria-label="<?= e(__('cart.remove')) ?>"><i class="bi bi-x-lg"></i></button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <a class="btn-link-bwa d-inline-block mt-4" href="<?= e(route('shop.index')) ?>"><i class="bi bi-arrow-<?= is_rtl() ? 'right' : 'left' ?> me-1"></i><?= e(__('cart.continue')) ?></a>
                </div>
                <div class="col-lg-4">
                    <div class="card-bwa summary-card">
                        <div class="card-body">
                            <h2 class="h5 mb-3"><?= e(__('cart.summary')) ?></h2>
                            <form method="post" action="<?= e(route('cart.coupon')) ?>" class="mb-3">
                                <?= csrf_field() ?>
                                <?php if ($totals['coupon']): ?>
                                    <div class="d-flex justify-content-between align-items-center coupon-applied">
                                        <span><i class="bi bi-ticket-perforated me-1"></i><?= e($totals['coupon']['code']) ?></span>
                                        <button class="btn btn-link btn-sm p-0" type="submit" name="remove" value="1"><?= e(__('cart.remove')) ?></button>
                                    </div>
                                <?php else: ?>
                                    <label class="form-label" for="coupon"><?= e(__('cart.coupon')) ?></label>
                                    <div class="input-group">
                                        <input class="form-control" id="coupon" name="code" maxlength="50" autocomplete="off" style="text-transform:uppercase">
                                        <button class="btn btn-outline-bwa" type="submit"><?= e(__('shop.apply')) ?></button>
                                    </div>
                                <?php endif; ?>
                            </form>
                            <form method="get" action="<?= e(route('cart.index')) ?>" class="mb-3" data-shipping-estimator data-url="<?= e(route('cart.shipping')) ?>">
                                <label class="form-label" for="ship-city"><?= e(__('cart.shipping_city')) ?></label>
                                <div class="input-group">
                                    <input class="form-control" id="ship-city" name="city" list="ma-cities" value="<?= e($city) ?>" autocomplete="address-level2">
                                    <button class="btn btn-outline-bwa" type="submit"><i class="bi bi-arrow-repeat"></i></button>
                                </div>
                            </form>
                            <datalist id="ma-cities"><?php foreach ($cities as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist>
                            <div data-totals><?= $this->insert('storefront/partials/totals', ['totals' => $totals]) ?></div>
                            <?php if ($totals['has_unavailable']): ?><div class="alert alert-warning small mt-3 mb-0"><?= e(__('cart.remove_unavailable')) ?></div><?php endif; ?>
                            <a class="btn btn-bwa btn-lg w-100 mt-4<?= $totals['has_unavailable'] || $totals['item_count'] === 0 ? ' disabled' : '' ?>" href="<?= e(route('checkout.index')) ?>"><?= e(__('cart.checkout')) ?> <i class="bi bi-arrow-<?= is_rtl() ? 'left' : 'right' ?> ms-1"></i></a>
                            <ul class="list-unstyled small text-muted-bwa mt-3 mb-0 d-grid gap-1">
                                <li><i class="bi bi-cash-coin me-2 text-gold"></i><?= e(__('product.trust_cod')) ?></li>
                                <li><i class="bi bi-shield-check me-2 text-gold"></i><?= e(__('cart.secure')) ?></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>
