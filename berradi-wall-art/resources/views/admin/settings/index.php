<?php
$this->layout('layouts/admin', ['title' => __('admin.nav_settings')]);
$icons = [
    'company' => 'bi-building', 'localization' => 'bi-translate', 'taxes' => 'bi-percent', 'documents' => 'bi-file-earmark-text',
    'shipping' => 'bi-truck', 'orders' => 'bi-shield-check', 'mail' => 'bi-envelope', 'seo' => 'bi-search',
];
$f = function (string $name, string $type = 'text', array $extra = []) use ($values): string {
    return $this->insert('partials/field', array_merge([
        'name' => $name,
        'label' => __('settings.' . $name),
        'type' => $type,
        'value' => $values[$name] ?? '',
        'help' => \App\Core\Translator::has('settings.' . $name . '_help') ? __('settings.' . $name . '_help') : null,
    ], $extra));
};
?>
<ul class="nav settings-tabs border-bottom mb-4 flex-nowrap overflow-auto">
    <?php foreach ($tabs as $t): ?>
        <li class="nav-item">
            <a class="nav-link text-nowrap <?= $t === $tab ? 'active' : '' ?>" href="<?= e(route('admin.settings', ['tab' => $t])) ?>"><i class="bi <?= e($icons[$t] ?? 'bi-gear') ?> me-1"></i><?= e(__('settings.tab_' . $t)) ?></a>
        </li>
    <?php endforeach; ?>
</ul>

<form method="post" action="<?= e(route('admin.settings.update', ['tab' => $tab])) ?>" enctype="multipart/form-data" novalidate>
    <?= csrf_field() ?>
    <div class="admin-card">
        <div class="card-header"><h2><?= e(__('settings.tab_' . $tab)) ?></h2></div>
        <div class="card-body">
            <?php if ($tab === 'company'): ?>
                <div class="row">
                    <div class="col-md-6"><?= $f('company_name', 'text', ['required' => true]) ?></div>
                    <div class="col-md-6"><?= $f('company_legal_name') ?></div>
                    <div class="col-md-4"><?= $f('company_email', 'email', ['required' => true]) ?></div>
                    <div class="col-md-4"><?= $f('company_phone', 'tel', ['required' => true]) ?></div>
                    <div class="col-md-4"><?= $f('company_whatsapp', 'tel', ['placeholder' => '+212 6 00 00 00 00']) ?></div>
                    <div class="col-md-8"><?= $f('company_address') ?></div>
                    <div class="col-md-4"><?= $f('company_city') ?></div>
                </div>
                <h3 class="h6 mt-3 mb-3 text-uppercase small text-muted-bwa"><?= e(__('settings.legal_ids')) ?></h3>
                <div class="row">
                    <div class="col-md-4"><?= $f('company_ice') ?></div>
                    <div class="col-md-4"><?= $f('company_rc') ?></div>
                    <div class="col-md-4"><?= $f('company_if') ?></div>
                    <div class="col-md-4"><?= $f('company_patente') ?></div>
                    <div class="col-md-4"><?= $f('company_cnss') ?></div>
                    <div class="col-md-4"><?= $f('company_bank_name') ?></div>
                    <div class="col-md-8"><?= $f('company_rib') ?></div>
                </div>
                <h3 class="h6 mt-3 mb-3 text-uppercase small text-muted-bwa"><?= e(__('settings.online_presence')) ?></h3>
                <div class="row">
                    <div class="col-md-6"><?= $f('company_website', 'url', ['placeholder' => 'https://']) ?></div>
                    <div class="col-md-6"><?= $f('social_instagram', 'url', ['placeholder' => 'https://instagram.com/...']) ?></div>
                    <div class="col-md-6"><?= $f('social_facebook', 'url', ['placeholder' => 'https://facebook.com/...']) ?></div>
                    <div class="col-md-6"><?= $f('social_tiktok', 'url', ['placeholder' => 'https://tiktok.com/@...']) ?></div>
                </div>
                <h3 class="h6 mt-3 mb-3 text-uppercase small text-muted-bwa"><?= e(__('settings.company_logo')) ?></h3>
                <div class="d-flex flex-wrap align-items-center gap-4">
                    <div class="border p-3 bg-white" style="min-width:160px;min-height:80px">
                        <img id="logoPreview" src="<?= e($values['company_logo'] ? upload_url((string) $values['company_logo']) : '') ?>" alt="" class="<?= $values['company_logo'] ? '' : 'd-none' ?>" style="max-height:70px;max-width:220px">
                    </div>
                    <div>
                        <input type="file" class="form-control<?= invalid('company_logo') ?>" name="company_logo" accept="image/png,image/jpeg,image/webp" data-preview="#logoPreview">
                        <?php if (error('company_logo')): ?><div class="invalid-feedback d-block"><?= e(error('company_logo')) ?></div><?php endif; ?>
                        <div class="form-text"><?= e(__('settings.logo_help')) ?></div>
                        <?php if ($values['company_logo']): ?>
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" name="remove_logo" value="1" id="remove_logo">
                                <label class="form-check-label small" for="remove_logo"><?= e(__('settings.remove_logo')) ?></label>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            <?php elseif ($tab === 'localization'): ?>
                <div class="row">
                    <div class="col-md-6"><?= $f('default_language', 'select', ['options' => config('app.locales')]) ?></div>
                    <div class="col-md-6"><?= $f('currency_code', 'select', ['options' => ['MAD' => 'MAD — Dirham marocain']]) ?></div>
                    <div class="col-md-6"><?= $f('currency_symbol', 'select', ['options' => ['DH' => 'DH', 'MAD' => 'MAD', 'د.م.' => 'د.م.']]) ?></div>
                    <div class="col-md-6"><?= $f('currency_position', 'select', ['options' => ['after' => __('settings.after_amount'), 'before' => __('settings.before_amount')]]) ?></div>
                </div>
                <p class="small text-muted-bwa mb-0"><?= e(__('settings.preview')) ?>: <strong><?= e(money('1250.5')) ?></strong></p>

            <?php elseif ($tab === 'taxes'): ?>
                <div class="row">
                    <div class="col-md-6"><?= $f('tax_enabled', 'checkbox') ?></div>
                    <div class="col-md-6"><?= $f('tax_prices_include_tax', 'checkbox') ?></div>
                    <div class="col-md-6">
                        <?= $f('tax_default_rate', 'text', ['attrs' => 'inputmode="decimal"']) ?>
                    </div>
                    <div class="col-md-6"><?= $f('tax_label') ?></div>
                </div>

            <?php elseif ($tab === 'documents'): ?>
                <div class="row">
                    <?php foreach (['order_prefix', 'invoice_prefix', 'credit_note_prefix', 'quotation_prefix', 'payment_prefix', 'purchase_prefix'] as $p): ?>
                        <div class="col-md-4"><?= $f($p) ?></div>
                    <?php endforeach; ?>
                    <div class="col-md-6"><?= $f('invoice_due_days', 'number', ['attrs' => 'min="0" max="365"']) ?></div>
                    <div class="col-md-6"><?= $f('quotation_validity_days', 'number', ['attrs' => 'min="1" max="365"']) ?></div>
                    <div class="col-12"><?= $f('invoice_payment_terms', 'textarea', ['rows' => 2]) ?></div>
                    <div class="col-12"><?= $f('quotation_terms', 'textarea', ['rows' => 3]) ?></div>
                    <div class="col-12"><?= $f('invoice_footer_note', 'textarea', ['rows' => 2]) ?></div>
                </div>
                <p class="small text-muted-bwa mb-0"><?= e(__('settings.numbering_help')) ?></p>

            <?php elseif ($tab === 'shipping'): ?>
                <div class="row">
                    <div class="col-md-6"><?= $f('shipping_flat_rate', 'text', ['attrs' => 'inputmode="decimal"']) ?></div>
                    <div class="col-md-6"><?= $f('shipping_free_threshold', 'text', ['attrs' => 'inputmode="decimal"']) ?></div>
                    <div class="col-md-4"><?= $f('production_days', 'number') ?></div>
                    <div class="col-md-4"><?= $f('shipping_min_days', 'number') ?></div>
                    <div class="col-md-4"><?= $f('shipping_max_days', 'number') ?></div>
                </div>
                <p class="small text-muted-bwa mb-0"><?= e(__('settings.zones_help')) ?></p>

            <?php elseif ($tab === 'orders'): ?>
                <div class="row">
                    <div class="col-12"><?= $f('guest_checkout_enabled', 'checkbox') ?></div>
                    <div class="col-md-6"><?= $f('low_dpi_warning', 'number') ?></div>
                    <div class="col-md-6"><?= $f('min_dpi_block', 'number') ?></div>
                    <div class="col-md-6"><?= $f('upload_max_mb', 'number') ?></div>
                    <div class="col-md-6"><?= $f('upload_max_files', 'number') ?></div>
                    <div class="col-md-6"><?= $f('retention_unordered_days', 'number') ?></div>
                    <div class="col-md-6"><?= $f('retention_ordered_days', 'number') ?></div>
                </div>
                <p class="small text-muted-bwa mb-0"><i class="bi bi-shield-lock me-1"></i><?= e(__('settings.privacy_help')) ?></p>

            <?php elseif ($tab === 'mail'): ?>
                <div class="row">
                    <div class="col-md-4"><?= $f('mail_driver', 'select', ['options' => ['mail' => 'PHP mail()', 'smtp' => 'SMTP', 'log' => __('settings.mail_log')]]) ?></div>
                    <div class="col-md-5"><?= $f('mail_host', 'text', ['placeholder' => 'mail.example.com']) ?></div>
                    <div class="col-md-3"><?= $f('mail_port', 'number') ?></div>
                    <div class="col-md-4"><?= $f('mail_encryption', 'select', ['options' => ['ssl' => 'SSL (465)', 'tls' => 'TLS (587)', 'none' => __('settings.none')]]) ?></div>
                    <div class="col-md-4"><?= $f('mail_username', 'text', ['attrs' => 'autocomplete="off"']) ?></div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label" for="mail_password"><?= e(__('settings.mail_password')) ?></label>
                            <input type="password" class="form-control" id="mail_password" name="mail_password" autocomplete="new-password" placeholder="<?= $values['mail_password_set'] ? '••••••••' : '' ?>">
                            <div class="form-text"><?= e($values['mail_password_set'] ? __('settings.password_kept') : __('settings.password_encrypted')) ?></div>
                            <?php if ($values['mail_password_set']): ?>
                                <div class="form-check"><input class="form-check-input" type="checkbox" name="clear_mail_password" value="1" id="clear_mail_password"><label class="form-check-label small" for="clear_mail_password"><?= e(__('settings.clear_password')) ?></label></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-4"><?= $f('mail_from_address', 'email') ?></div>
                    <div class="col-md-4"><?= $f('mail_from_name') ?></div>
                    <div class="col-md-4"><?= $f('admin_notification_email', 'email') ?></div>
                </div>

            <?php elseif ($tab === 'seo'): ?>
                <div class="row">
                    <div class="col-12"><div class="alert alert-light border small"><i class="bi bi-search me-1"></i><?= e(__('seo_admin.moved_hint')) ?> <?php if (can('marketing.manage')): ?><a href="<?= e(route('admin.seo.index')) ?>"><?= e(__('admin.nav_seo')) ?></a><?php endif; ?></div></div>
                    <div class="col-md-8">
                        <label class="form-label"><?= e(__('settings.seo_og_image')) ?></label>
                        <input type="file" class="form-control<?= invalid('seo_og_image') ?>" name="seo_og_image" accept="image/png,image/jpeg,image/webp" data-preview="#ogPreview">
                        <?php if (error('seo_og_image')): ?><div class="invalid-feedback d-block"><?= e(error('seo_og_image')) ?></div><?php endif; ?>
                        <img id="ogPreview" src="<?= e($values['seo_og_image'] ? upload_url((string) $values['seo_og_image']) : '') ?>" alt="" class="mt-2 border <?= $values['seo_og_image'] ? '' : 'd-none' ?>" style="max-height:120px">
                        <?php if ($values['seo_og_image']): ?>
                            <div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="remove_og_image" value="1" id="remove_og"><label class="form-check-label small" for="remove_og"><?= e(__('settings.remove_logo')) ?></label></div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-4 mt-3 mt-md-0"><?= $f('maintenance_mode', 'checkbox') ?></div>
                </div>
            <?php endif; ?>
        </div>
        <div class="card-footer bg-transparent border-top d-flex justify-content-between align-items-center p-3">
            <span class="small text-muted-bwa"><?= e(__('settings.saved_instantly')) ?></span>
            <button type="submit" class="btn btn-bwa"><?= e(__('common.save')) ?></button>
        </div>
    </div>
</form>

<?php if ($tab === 'mail'): ?>
    <form method="post" action="<?= e(route('admin.settings.test_mail')) ?>" class="mt-3">
        <?= csrf_field() ?>
        <button class="btn btn-outline-bwa btn-sm" type="submit"><i class="bi bi-send me-1"></i><?= e(__('settings.send_test')) ?></button>
    </form>
<?php endif; ?>
