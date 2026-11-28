<?php $this->layout('layouts/storefront', ['title' => __('auth.login_title'), 'noindex' => true]) ?>
<section class="auth-wrap">
    <div class="container">
        <div class="card-bwa auth-card">
            <div class="card-body">
                <div class="text-center mb-4">
                    <span class="eyebrow"><?= e(__('auth.my_account')) ?></span>
                    <h1 class="mt-2"><?= e(__('auth.login_title')) ?></h1>
                    <hr class="gold-rule mx-auto">
                </div>
                <form method="post" action="<?= e(route('customer.login.post')) ?>" novalidate data-once>
                    <?= csrf_field() ?>
                    <?= $this->insert('partials/field', ['name' => 'email', 'label' => __('auth.email'), 'type' => 'email', 'required' => true, 'attrs' => 'autocomplete="username"']) ?>
                    <?= $this->insert('partials/field', ['name' => 'password', 'label' => __('auth.password'), 'type' => 'password', 'required' => true]) ?>
                    <div class="d-flex justify-content-end mb-3"><a class="small" href="<?= e(route('customer.password.request')) ?>"><?= e(__('auth.forgot')) ?></a></div>
                    <button type="submit" class="btn btn-bwa w-100"><?= e(__('auth.sign_in')) ?></button>
                </form>
                <div class="divider-text my-4"><?= e(__('auth.new_customer')) ?></div>
                <a class="btn btn-outline-bwa w-100" href="<?= e(route('customer.register')) ?>"><?= e(__('auth.create_account')) ?></a>
            </div>
        </div>
    </div>
</section>
