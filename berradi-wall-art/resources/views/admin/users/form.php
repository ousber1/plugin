<?php
$isEdit = $user !== null;
$this->layout('layouts/admin', ['title' => $isEdit ? __('admin.edit_user') : __('admin.add_user')]);
$roleOptions = [];
foreach ($roles as $r) {
    $roleOptions[$r['id']] = $r['name'];
}
?>
<div class="admin-card" style="max-width:860px">
    <div class="card-header"><h2><?= e($isEdit ? $user['name'] : __('admin.add_user')) ?></h2></div>
    <div class="card-body">
        <form method="post" action="<?= e($isEdit ? route('admin.users.update', ['id' => $user['id']]) : route('admin.users.store')) ?>" novalidate>
            <?= csrf_field() ?>
            <div class="row">
                <div class="col-md-6"><?= $this->insert('partials/field', ['name' => 'name', 'label' => __('admin.full_name'), 'value' => $user['name'] ?? '', 'required' => true]) ?></div>
                <div class="col-md-6"><?= $this->insert('partials/field', ['name' => 'email', 'label' => __('auth.email'), 'type' => 'email', 'value' => $user['email'] ?? '', 'required' => true]) ?></div>
                <div class="col-md-6"><?= $this->insert('partials/field', ['name' => 'phone', 'label' => __('auth.phone'), 'type' => 'tel', 'value' => $user['phone'] ?? '']) ?></div>
                <div class="col-md-6"><?= $this->insert('partials/field', ['name' => 'role_id', 'label' => __('admin.role'), 'type' => 'select', 'value' => $user['role_id'] ?? '', 'options' => $roleOptions, 'required' => true]) ?></div>
                <div class="col-md-6"><?= $this->insert('partials/field', ['name' => 'locale', 'label' => __('admin.language'), 'type' => 'select', 'value' => $user['locale'] ?? 'fr', 'options' => config('app.locales')]) ?></div>
                <div class="col-md-6 pt-md-4"><?= $this->insert('partials/field', ['name' => 'is_active', 'label' => __('admin.active'), 'type' => 'checkbox', 'value' => $user['is_active'] ?? 1]) ?></div>
                <div class="col-md-6"><?= $this->insert('partials/field', ['name' => 'password', 'label' => $isEdit ? __('admin.new_password_optional') : __('auth.password'), 'type' => 'password', 'required' => !$isEdit, 'autocomplete' => 'new-password', 'help' => __('auth.password_rules')]) ?></div>
                <div class="col-md-6"><?= $this->insert('partials/field', ['name' => 'password_confirmation', 'label' => __('auth.password_confirm'), 'type' => 'password', 'required' => !$isEdit, 'autocomplete' => 'new-password']) ?></div>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-bwa" type="submit"><?= e(__('common.save')) ?></button>
                <a class="btn btn-outline-bwa" href="<?= e(route('admin.users.index')) ?>"><?= e(__('common.cancel')) ?></a>
            </div>
        </form>
    </div>
</div>
