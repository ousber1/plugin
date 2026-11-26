<?php $this->layout('layouts/storefront', ['title' => __('auth.forgot_title'), 'noindex' => true]) ?>
<section class="auth-wrap">
    <div class="container">
        <div class="card-bwa auth-card">
            <div class="card-body">
                <div class="text-center mb-4">
                    <h1><?= e(__('auth.forgot_title')) ?></h1>
                    <p class="text-muted-bwa small mb-0"><?= e(__('auth.forgot_intro')) ?></p>
                </div>
                <form method="post" action="<?= e(route('customer.password.email')) ?>" novalidate data-once>
                    <?= csrf_field() ?>
                    <?= $this->insert('partials/field', ['name' => 'email', 'label' => __('auth.email'), 'type' => 'email', 'required' => true]) ?>
                    <button type="submit" class="btn btn-bwa w-100"><?= e(__('auth.send_link')) ?></button>
                </form>
                <div class="text-center mt-3"><a class="small" href="<?= e(route('customer.login')) ?>"><?= e(__('auth.back_to_login')) ?></a></div>
            </div>
        </div>
    </div>
</section>
