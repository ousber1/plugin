<?php
$this->layout('layouts/storefront', ['seo' => $seo]);
$guest = $customer === null;
$selectedMethod = (string) old('payment_method', $methods[0]['code'] ?? '');
?>
<section class="section-sm">
    <div class="container">
        <h1 class="page-title mb-4"><?= e(__('checkout.title')) ?></h1>
        <?php if (error('cart') || error('coupon')): ?>
            <div class="alert alert-danger"><?= e(error('cart') ?? error('coupon')) ?> <a href="<?= e(route('cart.index')) ?>"><?= e(__('checkout.review_cart')) ?></a></div>
        <?php endif; ?>
        <form method="post" action="<?= e(route('checkout.place')) ?>" novalidate data-once data-checkout data-shipping-url="<?= e(route('cart.shipping')) ?>">
            <?= csrf_field() ?>
            <div class="row g-4">
                <div class="col-lg-7">
                    <?php if ($guest): ?>
                        <div class="checkout-login card-bwa p-3 mb-4 d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <span class="small"><i class="bi bi-person-circle me-2 text-gold"></i><?= e(__('checkout.have_account')) ?></span>
                            <a class="btn btn-sm btn-outline-bwa" href="<?= e(route('customer.login')) ?>"><?= e(__('auth.sign_in')) ?></a>
                        </div>
                    <?php endif; ?>

                    <div class="card-bwa mb-4">
                        <div class="card-header"><span class="step-pill">1</span><?= e(__('checkout.contact_delivery')) ?></div>
                        <div class="card-body">
                            <?php if ($addresses !== []): ?>
                                <div class="saved-addresses mb-3">
                                    <span class="filter-title"><?= e(__('checkout.saved_addresses')) ?></span>
                                    <div class="d-flex flex-wrap gap-2">
                                        <?php foreach ($addresses as $a): ?>
                                            <button type="button" class="chip-btn" data-fill-address="<?= e(json_encode(['full_name' => $a['full_name'], 'phone' => $a['phone'], 'city' => $a['city'], 'address' => trim($a['address_line1'] . ' ' . $a['address_line2']), 'postal_code' => $a['postal_code']], JSON_UNESCAPED_UNICODE)) ?>">
                                                <i class="bi bi-geo-alt"></i><?= e(($a['label'] ?: $a['city']) . ' — ' . mb_substr($a['address_line1'], 0, 28)) ?>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <div class="row">
                                <div class="col-md-6"><?= $this->insert('partials/field', ['name' => 'full_name', 'label' => __('checkout.full_name'), 'value' => $prefill['full_name'], 'required' => true, 'attrs' => 'autocomplete="name"']) ?></div>
                                <div class="col-md-6"><?= $this->insert('partials/field', ['name' => 'phone', 'label' => __('auth.phone'), 'type' => 'tel', 'value' => $prefill['phone'], 'required' => true, 'placeholder' => '06 00 00 00 00', 'attrs' => 'autocomplete="tel"', 'help' => __('checkout.phone_help')]) ?></div>
                                <div class="col-12"><?= $this->insert('partials/field', ['name' => 'email', 'label' => __('auth.email') . ($guest ? ' (' . __('checkout.recommended') . ')' : ''), 'type' => 'email', 'value' => $prefill['email'], 'attrs' => 'autocomplete="email"' . ($guest ? '' : ' readonly'), 'help' => __('checkout.email_help')]) ?></div>
                                <div class="col-md-4">
                                    <label class="form-label" for="f_country"><?= e(__('checkout.country')) ?></label>
                                    <select class="form-select mb-3" id="f_country" name="country"><option value="MA" selected><?= e(__('checkout.morocco')) ?></option></select>
                                </div>
                                <div class="col-md-8"><?= $this->insert('partials/field', ['name' => 'city', 'label' => __('checkout.city'), 'value' => $prefill['city'], 'required' => true, 'attrs' => 'list="ma-cities" autocomplete="address-level2" data-city']) ?></div>
                                <div class="col-md-8"><?= $this->insert('partials/field', ['name' => 'address', 'label' => __('checkout.address'), 'value' => $prefill['address'], 'required' => true, 'attrs' => 'autocomplete="street-address"', 'placeholder' => __('checkout.address_placeholder')]) ?></div>
                                <div class="col-md-4"><?= $this->insert('partials/field', ['name' => 'postal_code', 'label' => __('checkout.postal_code'), 'value' => $prefill['postal_code'], 'attrs' => 'autocomplete="postal-code" inputmode="numeric"']) ?></div>
                                <div class="col-12"><?= $this->insert('partials/field', ['name' => 'delivery_instructions', 'label' => __('checkout.instructions'), 'type' => 'textarea', 'rows' => 2, 'placeholder' => __('checkout.instructions_placeholder')]) ?></div>
                            </div>
                            <datalist id="ma-cities"><?php foreach ($cities as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist>
                            <?php if (!$guest): ?>
                                <div class="form-check"><input class="form-check-input" type="checkbox" name="save_address" value="1" id="save_address" <?= $addresses === [] ? 'checked' : '' ?>><label class="form-check-label small" for="save_address"><?= e(__('checkout.save_address')) ?></label></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card-bwa mb-4">
                        <div class="card-header"><span class="step-pill">2</span><?= e(__('checkout.payment')) ?></div>
                        <div class="card-body">
                            <?php if ($methods === []): ?>
                                <div class="alert alert-warning mb-0"><?= e(__('checkout.no_payment_method')) ?></div>
                            <?php endif; ?>
                            <div class="payment-methods">
                                <?php foreach ($methods as $m): ?>
                                    <label class="payment-option">
                                        <input type="radio" name="payment_method" value="<?= e($m['code']) ?>" <?= $selectedMethod === $m['code'] ? 'checked' : '' ?>>
                                        <span class="po-body">
                                            <strong><i class="bi <?= ['cod' => 'bi-cash-coin', 'bank_transfer' => 'bi-bank', 'manual' => 'bi-receipt'][$m['code']] ?? 'bi-credit-card' ?> me-2"></i><?= e($m['name']) ?></strong>
                                            <?php if (!empty($m['description'])): ?><small><?= e($m['description']) ?></small><?php endif; ?>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <?php if (error('payment_method')): ?><div class="invalid-feedback d-block"><?= e(error('payment_method')) ?></div><?php endif; ?>
                            <p class="small text-muted-bwa mt-3 mb-0"><i class="bi bi-info-circle me-1"></i><?= e(__('checkout.no_online_charge')) ?></p>
                        </div>
                    </div>

                    <?php if ($guest): ?>
                        <div class="card-bwa mb-4">
                            <div class="card-header"><span class="step-pill">3</span><?= e(__('checkout.account_optional')) ?></div>
                            <div class="card-body">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="create_account" value="1" id="create_account" <?= old('create_account') ? 'checked' : '' ?> data-toggle-target="#account-fields">
                                    <label class="form-check-label" for="create_account"><?= e(__('checkout.create_account')) ?></label>
                                </div>
                                <div id="account-fields" class="row <?= old('create_account') ? '' : 'd-none' ?>">
                                    <div class="col-md-6"><?= $this->insert('partials/field', ['name' => 'password', 'label' => __('auth.password'), 'type' => 'password', 'autocomplete' => 'new-password', 'help' => __('auth.password_rules')]) ?></div>
                                    <div class="col-md-6"><?= $this->insert('partials/field', ['name' => 'password_confirmation', 'label' => __('auth.password_confirm'), 'type' => 'password', 'autocomplete' => 'new-password']) ?></div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?= $this->insert('partials/field', ['name' => 'customer_notes', 'label' => __('checkout.notes'), 'type' => 'textarea', 'rows' => 2]) ?>
                </div>

                <div class="col-lg-5">
                    <div class="card-bwa summary-card">
                        <div class="card-body">
                            <h2 class="h5 mb-3"><?= e(__('checkout.your_order')) ?> <small class="text-muted-bwa">(<?= (int) $totals['item_count'] ?>)</small></h2>
                            <ul class="order-lines compact list-unstyled">
                                <?php foreach ($lines as $l): ?>
                                    <li>
                                        <span class="ol-thumb"><?php if ($l['preview_url']): ?><img src="<?= e($l['preview_url']) ?>" alt=""><?php else: ?><i class="bi bi-image"></i><?php endif; ?><span class="ol-qty"><?= (int) $l['quantity'] ?></span></span>
                                        <span class="ol-main"><strong><?= e($l['title']) ?></strong><small><?= e($l['details']) ?></small></span>
                                        <span class="ol-total"><?= e(money($l['line_total'])) ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <div data-totals><?= $this->insert('storefront/partials/totals', ['totals' => $totals]) ?></div>
                            <div class="form-check mt-4">
                                <input class="form-check-input<?= invalid('accept_terms') ?>" type="checkbox" name="accept_terms" value="1" id="accept_terms">
                                <label class="form-check-label small" for="accept_terms"><?= e(__('checkout.terms_before')) ?> <a href="<?= e(route('page.terms')) ?>" target="_blank"><?= e(__('pages.terms')) ?></a>.</label>
                                <?php if (error('accept_terms')): ?><div class="invalid-feedback d-block"><?= e(__('auth.terms_required')) ?></div><?php endif; ?>
                            </div>
                            <button type="submit" class="btn btn-bwa btn-lg w-100 mt-3" data-loading="<?= e(__('checkout.placing')) ?>" <?= $methods === [] || $totals['has_unavailable'] ? 'disabled' : '' ?>><i class="bi bi-lock me-2"></i><?= e(__('checkout.place_order')) ?></button>
                            <p class="small text-muted-bwa text-center mt-2 mb-0"><?= e(__('checkout.confirm_call')) ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</section>
