<?php $this->layout('layouts/storefront', ['title' => __('auth.create_account'), 'noindex' => true]) ?>
<section class="auth-wrap">
    <div class="container">
        <div class="card-bwa auth-card" style="max-width:560px">
            <div class="card-body">
                <div class="text-center mb-4">
                    <span class="eyebrow"><?= e(__('auth.join')) ?></span>
                    <h1 class="mt-2"><?= e(__('auth.create_account')) ?></h1>
                    <hr class="gold-rule mx-auto">
                </div>
                <form method="post" action="<?= e(route('customer.register.post')) ?>" novalidate data-once>
                    <?= csrf_field() ?>
                    <div class="row">
                        <div class="col-sm-6"><?= $this->insert('partials/field', ['name' => 'first_name', 'label' => __('auth.first_name'), 'required' => true, 'attrs' => 'autocomplete="given-name"']) ?></div>
                        <div class="col-sm-6"><?= $this->insert('partials/field', ['name' => 'last_name', 'label' => __('auth.last_name'), 'required' => true, 'attrs' => 'autocomplete="family-name"']) ?></div>
                    </div>
                    <?= $this->insert('partials/field', ['name' => 'email', 'label' => __('auth.email'), 'type' => 'email', 'required' => true, 'attrs' => 'autocomplete="email"']) ?>
                    <?= $this->insert('partials/field', ['name' => 'phone', 'label' => __('auth.phone'), 'type' => 'tel', 'required' => true, 'placeholder' => '06 00 00 00 00', 'attrs' => 'autocomplete="tel"']) ?>
                    <div class="row">
                        <div class="col-sm-6"><?= $this->insert('partials/field', ['name' => 'password', 'label' => __('auth.password'), 'type' => 'password', 'required' => true, 'autocomplete' => 'new-password']) ?></div>
                        <div class="col-sm-6"><?= $this->insert('partials/field', ['name' => 'password_confirmation', 'label' => __('auth.password_confirm'), 'type' => 'password', 'required' => true, 'autocomplete' => 'new-password']) ?></div>
                    </div>
                    <p class="form-text mt-n2 mb-3"><?= e(__('auth.password_rules')) ?></p>
                    <div class="form-check mb-2">
                        <input class="form-check-input<?= invalid('accept_terms') ?>" type="checkbox" name="accept_terms" value="1" id="accept_terms" <?= old('accept_terms') ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="accept_terms"><?= e(__('auth.accept_terms')) ?></label>
                        <?php if (error('accept_terms')): ?><div class="invalid-feedback d-block"><?= e(__('auth.terms_required')) ?></div><?php endif; ?>
                    </div>
                    <div class="form-check mb-4">
                        <input class="form-check-input" type="checkbox" name="marketing_opt_in" value="1" id="marketing_opt_in" <?= old('marketing_opt_in') ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="marketing_opt_in"><?= e(__('auth.marketing_opt_in')) ?></label>
                    </div>
                    <button type="submit" class="btn btn-bwa w-100"><?= e(__('auth.create_account')) ?></button>
                </form>
                <p class="text-center small mt-4 mb-0"><?= e(__('auth.have_account')) ?> <a href="<?= e(route('customer.login')) ?>" class="fw-semibold"><?= e(__('auth.sign_in')) ?></a></p>
            </div>
        </div>
    </div>
</section>
