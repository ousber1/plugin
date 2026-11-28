<p class="text-muted-bwa"><?= e(__('installer.admin_intro')) ?></p>
<form method="post" action="<?= e(route('installer.store', ['step' => 8])) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="row">
        <div class="col-md-6"><?= $this->insert('partials/field', ['name' => 'name', 'label' => __('installer.admin_name'), 'required' => true]) ?></div>
        <div class="col-md-6"><?= $this->insert('partials/field', ['name' => 'email', 'label' => __('auth.email'), 'type' => 'email', 'required' => true, 'attrs' => 'autocomplete="username"']) ?></div>
        <div class="col-md-6"><?= $this->insert('partials/field', ['name' => 'password', 'label' => __('auth.password'), 'type' => 'password', 'required' => true, 'autocomplete' => 'new-password', 'help' => __('auth.password_rules')]) ?></div>
        <div class="col-md-6"><?= $this->insert('partials/field', ['name' => 'password_confirmation', 'label' => __('auth.password_confirm'), 'type' => 'password', 'required' => true, 'autocomplete' => 'new-password']) ?></div>
    </div>
    <button class="btn btn-bwa" type="submit"><?= e(__('installer.create_admin')) ?></button>
</form>
