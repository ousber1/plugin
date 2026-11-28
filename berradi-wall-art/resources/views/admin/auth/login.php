<?php $this->layout('layouts/admin_auth', ['title' => __('auth.admin_login')]) ?>
<h1 class="h3 text-center mb-4"><?= e(__('auth.admin_login')) ?></h1>
<form method="post" action="<?= e(route('admin.login.post')) ?>" novalidate data-once>
    <?= csrf_field() ?>
    <?= $this->insert('partials/field', ['name' => 'email', 'label' => __('auth.email'), 'type' => 'email', 'required' => true, 'attrs' => 'autocomplete="username" autofocus']) ?>
    <?= $this->insert('partials/field', ['name' => 'password', 'label' => __('auth.password'), 'type' => 'password', 'required' => true]) ?>
    <button type="submit" class="btn btn-bwa w-100 mt-2"><?= e(__('auth.sign_in')) ?></button>
</form>
<div class="text-center mt-3">
    <a class="small" href="<?= e(route('admin.password.request')) ?>"><?= e(__('auth.forgot')) ?></a>
</div>
