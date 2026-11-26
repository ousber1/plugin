<?php $this->layout('layouts/admin_auth', ['title' => __('auth.forgot_title')]) ?>
<h1 class="h4 text-center mb-2"><?= e(__('auth.forgot_title')) ?></h1>
<p class="text-center text-muted-bwa small mb-4"><?= e(__('auth.forgot_intro')) ?></p>
<form method="post" action="<?= e(route('admin.password.email')) ?>" novalidate data-once>
    <?= csrf_field() ?>
    <?= $this->insert('partials/field', ['name' => 'email', 'label' => __('auth.email'), 'type' => 'email', 'required' => true, 'attrs' => 'autocomplete="username"']) ?>
    <button type="submit" class="btn btn-bwa w-100"><?= e(__('auth.send_link')) ?></button>
</form>
<div class="text-center mt-3"><a class="small" href="<?= e(route('admin.login')) ?>"><i class="bi bi-arrow-left"></i> <?= e(__('auth.back_to_login')) ?></a></div>
