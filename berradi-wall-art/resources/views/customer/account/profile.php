<?php $this->layout('layouts/storefront', ['title' => __('account.profile'), 'noindex' => true]) ?>
<section class="section-sm">
    <div class="container">
        <h1 class="mb-4"><?= e(__('account.profile')) ?></h1>
        <div class="row g-4">
            <div class="col-lg-3"><?= $this->insert('customer/account/_nav', ['current' => 'profile']) ?></div>
            <div class="col-lg-9">
                <div class="card-bwa mb-4">
                    <div class="card-header"><?= e(__('account.personal_info')) ?></div>
                    <div class="card-body">
                        <form method="post" action="<?= e(route('customer.profile.update')) ?>" novalidate>
                            <?= csrf_field() ?>
                            <div class="row">
                                <div class="col-md-6"><?= $this->insert('partials/field', ['name' => 'first_name', 'label' => __('auth.first_name'), 'value' => $customer['first_name'], 'required' => true]) ?></div>
                                <div class="col-md-6"><?= $this->insert('partials/field', ['name' => 'last_name', 'label' => __('auth.last_name'), 'value' => $customer['last_name'], 'required' => true]) ?></div>
                                <div class="col-md-6"><?= $this->insert('partials/field', ['name' => 'email', 'label' => __('auth.email'), 'type' => 'email', 'value' => $customer['email'], 'required' => true]) ?></div>
                                <div class="col-md-6"><?= $this->insert('partials/field', ['name' => 'phone', 'label' => __('auth.phone'), 'type' => 'tel', 'value' => $customer['phone'], 'required' => true]) ?></div>
                                <div class="col-md-6"><?= $this->insert('partials/field', ['name' => 'company_name', 'label' => __('account.company'), 'value' => $customer['company_name']]) ?></div>
                                <div class="col-md-6"><?= $this->insert('partials/field', ['name' => 'ice', 'label' => __('account.ice'), 'value' => $customer['ice'], 'help' => __('account.ice_help')]) ?></div>
                                <div class="col-md-6"><?= $this->insert('partials/field', ['name' => 'locale', 'label' => __('admin.language'), 'type' => 'select', 'value' => $customer['locale'], 'options' => config('app.locales')]) ?></div>
                                <div class="col-md-6 pt-md-4"><?= $this->insert('partials/field', ['name' => 'marketing_opt_in', 'label' => __('auth.marketing_opt_in'), 'type' => 'checkbox', 'value' => $customer['marketing_opt_in']]) ?></div>
                            </div>
                            <button class="btn btn-bwa" type="submit"><?= e(__('common.save')) ?></button>
                        </form>
                    </div>
                </div>
                <div class="card-bwa">
                    <div class="card-header"><?= e(__('auth.change_password')) ?></div>
                    <div class="card-body">
                        <form method="post" action="<?= e(route('customer.password.change')) ?>" novalidate>
                            <?= csrf_field() ?>
                            <div class="row">
                                <div class="col-md-4"><?= $this->insert('partials/field', ['name' => 'current_password', 'label' => __('auth.current_password'), 'type' => 'password', 'required' => true]) ?></div>
                                <div class="col-md-4"><?= $this->insert('partials/field', ['name' => 'password', 'label' => __('auth.new_password'), 'type' => 'password', 'required' => true, 'autocomplete' => 'new-password']) ?></div>
                                <div class="col-md-4"><?= $this->insert('partials/field', ['name' => 'password_confirmation', 'label' => __('auth.password_confirm'), 'type' => 'password', 'required' => true, 'autocomplete' => 'new-password']) ?></div>
                            </div>
                            <button class="btn btn-outline-bwa" type="submit"><?= e(__('auth.change_password')) ?></button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
