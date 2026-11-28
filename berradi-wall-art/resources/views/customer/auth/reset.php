<?php $this->layout('layouts/storefront', ['title' => __('auth.reset_title'), 'noindex' => true]) ?>
<section class="auth-wrap">
    <div class="container">
        <div class="card-bwa auth-card">
            <div class="card-body">
                <h1 class="text-center mb-4"><?= e(__('auth.reset_title')) ?></h1>
                <form method="post" action="<?= e(route('customer.password.update')) ?>" novalidate data-once>
                    <?= csrf_field() ?>
                    <input type="hidden" name="token" value="<?= e($token) ?>">
                    <?= $this->insert('partials/field', ['name' => 'password', 'label' => __('auth.new_password'), 'type' => 'password', 'required' => true, 'autocomplete' => 'new-password', 'help' => __('auth.password_rules')]) ?>
                    <?= $this->insert('partials/field', ['name' => 'password_confirmation', 'label' => __('auth.password_confirm'), 'type' => 'password', 'required' => true, 'autocomplete' => 'new-password']) ?>
                    <button type="submit" class="btn btn-bwa w-100"><?= e(__('auth.reset_button')) ?></button>
                </form>
            </div>
        </div>
    </div>
</section>
