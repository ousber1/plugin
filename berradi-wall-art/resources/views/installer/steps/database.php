<p class="text-muted-bwa"><?= e(__('installer.database_intro')) ?></p>
<form method="post" action="<?= e(route('installer.store', ['step' => 4])) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="row">
        <div class="col-md-8"><?= $this->insert('partials/field', ['name' => 'db_host', 'label' => __('installer.db_host'), 'value' => $db['host'], 'required' => true]) ?></div>
        <div class="col-md-4"><?= $this->insert('partials/field', ['name' => 'db_port', 'label' => __('installer.db_port'), 'type' => 'number', 'value' => $db['port'], 'required' => true]) ?></div>
        <div class="col-md-12"><?= $this->insert('partials/field', ['name' => 'db_database', 'label' => __('installer.db_name'), 'value' => $db['database'], 'required' => true, 'help' => __('installer.db_name_help')]) ?></div>
        <div class="col-md-6"><?= $this->insert('partials/field', ['name' => 'db_username', 'label' => __('installer.db_user'), 'value' => $db['username'], 'required' => true, 'attrs' => 'autocomplete="off"']) ?></div>
        <div class="col-md-6"><?= $this->insert('partials/field', ['name' => 'db_password', 'label' => __('installer.db_password'), 'type' => 'password', 'autocomplete' => 'new-password']) ?></div>
        <div class="col-12"><?= $this->insert('partials/field', ['name' => 'app_url', 'label' => __('installer.app_url'), 'type' => 'url', 'value' => $app_url, 'required' => true, 'help' => __('installer.app_url_help')]) ?></div>
    </div>
    <button class="btn btn-bwa" type="submit"><?= e(__('installer.test_connection')) ?></button>
</form>
