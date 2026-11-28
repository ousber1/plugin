<?php $this->layout('layouts/admin', ['title' => __('admin.my_profile')]) ?>
<div class="row g-4">
    <div class="col-lg-7">
        <div class="admin-card">
            <div class="card-header"><h2><?= e(__('admin.profile_info')) ?></h2></div>
            <div class="card-body">
                <form method="post" action="<?= e(route('admin.profile.update')) ?>" novalidate>
                    <?= csrf_field() ?>
                    <div class="row">
                        <div class="col-md-6"><?= $this->insert('partials/field', ['name' => 'name', 'label' => __('admin.full_name'), 'value' => $user['name'], 'required' => true]) ?></div>
                        <div class="col-md-6"><?= $this->insert('partials/field', ['name' => 'email', 'label' => __('auth.email'), 'type' => 'email', 'value' => $user['email'], 'required' => true]) ?></div>
                        <div class="col-md-6"><?= $this->insert('partials/field', ['name' => 'phone', 'label' => __('auth.phone'), 'type' => 'tel', 'value' => $user['phone']]) ?></div>
                        <div class="col-md-6"><?= $this->insert('partials/field', ['name' => 'locale', 'label' => __('admin.language'), 'type' => 'select', 'value' => $user['locale'], 'options' => config('app.locales')]) ?></div>
                    </div>
                    <button class="btn btn-bwa" type="submit"><?= e(__('common.save')) ?></button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="admin-card">
            <div class="card-header"><h2><?= e(__('auth.change_password')) ?></h2></div>
            <div class="card-body">
                <form method="post" action="<?= e(route('admin.profile.password')) ?>" novalidate>
                    <?= csrf_field() ?>
                    <?= $this->insert('partials/field', ['name' => 'current_password', 'label' => __('auth.current_password'), 'type' => 'password', 'required' => true]) ?>
                    <?= $this->insert('partials/field', ['name' => 'password', 'label' => __('auth.new_password'), 'type' => 'password', 'required' => true, 'autocomplete' => 'new-password', 'help' => __('auth.password_rules')]) ?>
                    <?= $this->insert('partials/field', ['name' => 'password_confirmation', 'label' => __('auth.password_confirm'), 'type' => 'password', 'required' => true, 'autocomplete' => 'new-password']) ?>
                    <button class="btn btn-outline-bwa" type="submit"><?= e(__('auth.change_password')) ?></button>
                </form>
            </div>
        </div>
    </div>
</div>
