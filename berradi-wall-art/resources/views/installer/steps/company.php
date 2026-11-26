<p class="text-muted-bwa"><?= e(__('installer.company_intro')) ?></p>
<form method="post" action="<?= e(route('installer.store', ['step' => 7])) ?>" enctype="multipart/form-data" novalidate>
    <?= csrf_field() ?>
    <div class="row">
        <div class="col-md-6"><?= $this->insert('partials/field', ['name' => 'company_name', 'label' => __('settings.company_name'), 'value' => $company['company_name'], 'required' => true]) ?></div>
        <div class="col-md-6"><?= $this->insert('partials/field', ['name' => 'company_email', 'label' => __('settings.company_email'), 'type' => 'email', 'value' => $company['company_email'], 'required' => true]) ?></div>
        <div class="col-md-6"><?= $this->insert('partials/field', ['name' => 'company_phone', 'label' => __('settings.company_phone'), 'type' => 'tel', 'value' => $company['company_phone'], 'required' => true]) ?></div>
        <div class="col-md-6"><?= $this->insert('partials/field', ['name' => 'company_whatsapp', 'label' => __('settings.company_whatsapp'), 'type' => 'tel', 'value' => $company['company_whatsapp'], 'placeholder' => '+212 6 00 00 00 00']) ?></div>
        <div class="col-md-8"><?= $this->insert('partials/field', ['name' => 'company_address', 'label' => __('settings.company_address'), 'value' => $company['company_address']]) ?></div>
        <div class="col-md-4"><?= $this->insert('partials/field', ['name' => 'company_city', 'label' => __('settings.company_city'), 'value' => $company['company_city']]) ?></div>
        <div class="col-md-4"><?= $this->insert('partials/field', ['name' => 'currency_code', 'label' => __('settings.currency_code'), 'type' => 'select', 'value' => $company['currency_code'], 'options' => ['MAD' => 'MAD']]) ?></div>
        <div class="col-md-4"><?= $this->insert('partials/field', ['name' => 'currency_symbol', 'label' => __('settings.currency_symbol'), 'type' => 'select', 'value' => $company['currency_symbol'], 'options' => ['DH' => 'DH', 'MAD' => 'MAD', 'د.م.' => 'د.م.']]) ?></div>
        <div class="col-md-4"><?= $this->insert('partials/field', ['name' => 'default_language', 'label' => __('settings.default_language'), 'type' => 'select', 'value' => $company['default_language'], 'options' => config('app.locales')]) ?></div>
        <div class="col-md-4"><?= $this->insert('partials/field', ['name' => 'tax_default_rate', 'label' => __('settings.tax_default_rate'), 'value' => $company['tax_default_rate'], 'required' => true]) ?></div>
        <div class="col-md-4"><?= $this->insert('partials/field', ['name' => 'shipping_flat_rate', 'label' => __('settings.shipping_flat_rate'), 'value' => $company['shipping_flat_rate'], 'required' => true]) ?></div>
        <div class="col-md-4"><?= $this->insert('partials/field', ['name' => 'shipping_free_threshold', 'label' => __('settings.shipping_free_threshold'), 'value' => $company['shipping_free_threshold']]) ?></div>
        <div class="col-md-4"><?= $this->insert('partials/field', ['name' => 'order_prefix', 'label' => __('settings.order_prefix'), 'value' => $company['order_prefix'], 'required' => true]) ?></div>
        <div class="col-md-4"><?= $this->insert('partials/field', ['name' => 'invoice_prefix', 'label' => __('settings.invoice_prefix'), 'value' => $company['invoice_prefix'], 'required' => true]) ?></div>
        <div class="col-md-4"><?= $this->insert('partials/field', ['name' => 'quotation_prefix', 'label' => __('settings.quotation_prefix'), 'value' => $company['quotation_prefix'], 'required' => true]) ?></div>
        <div class="col-12 mb-3">
            <label class="form-label"><?= e(__('settings.company_logo')) ?></label>
            <input type="file" class="form-control<?= invalid('company_logo') ?>" name="company_logo" accept="image/png,image/jpeg,image/webp">
            <?php if (error('company_logo')): ?><div class="invalid-feedback d-block"><?= e(error('company_logo')) ?></div><?php endif; ?>
            <div class="form-text"><?= e(__('settings.logo_help')) ?></div>
        </div>
    </div>
    <button class="btn btn-bwa" type="submit"><?= e(__('installer.continue')) ?></button>
</form>
