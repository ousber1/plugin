<?php $this->layout('layouts/admin', ['title' => __('admin.nav_tracking')]); ?>
<?php if (errors() !== []): ?><div class="alert alert-danger"><?php foreach (errors() as $m): ?><div><?= e(is_array($m) ? (string) ($m[0] ?? '') : (string) $m) ?></div><?php endforeach; ?></div><?php endif; ?>
<?php
$in = fn ($n, $v, $extra = '') => '<input class="form-control form-control-sm' . invalid($n) . '" name="' . e($n) . '" value="' . e((string) old($n, $v)) . '" dir="ltr" autocomplete="off" ' . $extra . '>';
$providers = [
    'analytics' => [
        ['track_ga4_id', 'ga4', 'Google Analytics 4', 'G-XXXXXXXXXX', 'tracking_admin.help_ga4'],
        ['track_gtm_id', 'gtm', 'Google Tag Manager', 'GTM-XXXXXXX', 'tracking_admin.help_gtm'],
        ['track_clarity_id', 'clarity', 'Microsoft Clarity', 'abcd1234ef', 'tracking_admin.help_clarity'],
    ],
    'marketing' => [
        ['track_meta_pixel_id', 'meta', 'Meta Pixel (Facebook / Instagram)', '123456789012345', 'tracking_admin.help_meta'],
        ['track_tiktok_id', 'tiktok', 'TikTok Pixel', 'C1A2B3C4D5E6F7G8H9I0', 'tracking_admin.help_tiktok'],
        ['track_ads_id', 'ads', 'Google Ads', 'AW-123456789', 'tracking_admin.help_ads'],
        ['track_snapchat_id', 'snapchat', 'Snap Pixel', 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx', 'tracking_admin.help_snapchat'],
        ['track_pinterest_id', 'pinterest', 'Pinterest Tag', '2612345678901', 'tracking_admin.help_pinterest'],
    ],
];
?>
<form method="post" action="<?= e(route('admin.tracking.save')) ?>" novalidate autocomplete="off">
    <?= csrf_field() ?>
    <div class="admin-card mb-3"><div class="card-body">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
            <strong><?= e(__('tracking_admin.active_now')) ?></strong>
            <?php if ($enabled === []): ?><span class="badge-status is-muted"><?= e(__('tracking_admin.none')) ?></span><?php endif; ?>
            <?php foreach ($enabled as $p => $id): ?><span class="badge-status is-success"><?= e(strtoupper($p)) ?></span><?php endforeach; ?>
        </div>
        <label class="form-check form-switch mb-1"><input class="form-check-input" type="checkbox" role="switch" name="track_consent_enabled" value="1" <?= $values['track_consent_enabled'] ? 'checked' : '' ?>><span class="form-check-label"><?= e(__('tracking_admin.consent')) ?></span></label>
        <p class="small text-muted-bwa mb-2"><?= e(__('tracking_admin.consent_help')) ?></p>
        <div class="row g-2">
            <div class="col-md-6"><label class="form-label small"><?= e(__('tracking_admin.privacy_url')) ?></label><?= $in('track_privacy_url', $values['track_privacy_url'], 'placeholder="/terms"') ?></div>
        </div>
    </div></div>

    <?php foreach ($providers as $group => $list): ?>
        <div class="admin-card mb-3"><div class="card-body">
            <h2 class="h6 mb-1"><?= e(__('tracking_admin.group_' . $group)) ?></h2>
            <p class="small text-muted-bwa"><?= e(__('tracking_admin.group_' . $group . '_help')) ?></p>
            <div class="row g-3">
                <?php foreach ($list as [$key, $code, $label, $ph, $help]): ?>
                    <div class="col-md-6">
                        <label class="form-label small tracking-provider"><span class="dot<?= isset($enabled[$code]) ? ' is-on' : '' ?>"></span><?= e($label) ?></label>
                        <?= $in($key, $values[$key], 'placeholder="' . e($ph) . '"') ?>
                        <?php if (error($key)): ?><div class="invalid-feedback"><?= e(error($key)) ?></div><?php endif; ?>
                        <div class="form-text"><?= e(__($help)) ?></div>
                        <?php if ($code === 'ads'): ?>
                            <label class="form-label small mt-2"><?= e(__('tracking_admin.ads_label')) ?></label><?= $in('track_ads_purchase_label', $values['track_ads_purchase_label'], 'placeholder="AbC-D_efG-h12_34-567"') ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div></div>
    <?php endforeach; ?>

    <div class="admin-card mb-3"><div class="card-body">
        <h2 class="h6 mb-1"><i class="bi bi-hdd-network me-1"></i><?= e(__('tracking_admin.capi')) ?></h2>
        <p class="small text-muted-bwa"><?= e(__('tracking_admin.capi_help')) ?></p>
        <div class="row g-2">
            <div class="col-md-8">
                <label class="form-label small"><?= e(__('tracking_admin.capi_token')) ?></label>
                <input class="form-control form-control-sm<?= invalid('track_meta_capi_token') ?>" type="password" name="track_meta_capi_token" value="" autocomplete="new-password" dir="ltr" placeholder="<?= e($tokenSet ? __('payment_methods.secret_kept') : 'EAAG…') ?>">
                <?php if ($tokenSet): ?><div class="form-check mt-1"><input class="form-check-input" type="checkbox" name="clear_capi_token" value="1" id="clearCapi"><label class="form-check-label small" for="clearCapi"><?= e(__('settings.clear_password')) ?></label></div><?php endif; ?>
            </div>
            <div class="col-md-4"><label class="form-label small"><?= e(__('tracking_admin.test_code')) ?></label><?= $in('track_meta_test_code', $values['track_meta_test_code'], 'placeholder="TEST12345"') ?></div>
        </div>
        <div class="d-flex flex-wrap gap-3 small mt-3">
            <?php foreach (['queued', 'sent', 'failed'] as $st): ?><span><?= e(__('tracking_admin.capi_' . $st)) ?> : <strong><?= (int) ($capiStats[$st] ?? 0) ?></strong></span><?php endforeach; ?>
            <span class="text-muted-bwa">(30 <?= e(__('tracking_admin.days')) ?>)</span>
        </div>
        <?php if ($capiRecent !== []): ?>
            <div class="table-responsive mt-2"><table class="table table-bwa table-sm mb-0">
                <thead><tr><th><?= e(__('tracking_admin.event')) ?></th><th><?= e(__('orders.number')) ?></th><th><?= e(__('admin.status')) ?></th><th><?= e(__('tracking_admin.error')) ?></th><th></th></tr></thead>
                <tbody><?php foreach ($capiRecent as $ev): ?>
                    <tr><td><?= e($ev['event_name']) ?></td><td dir="ltr"><?= e((string) $ev['order_number']) ?></td>
                        <td><span class="badge-status <?= $ev['status'] === 'sent' ? 'is-success' : ($ev['status'] === 'failed' ? 'is-danger' : 'is-muted') ?>"><?= e(__('tracking_admin.capi_' . $ev['status'])) ?></span> <span class="small text-muted-bwa">×<?= (int) $ev['attempts'] ?></span></td>
                        <td class="small text-break" dir="ltr"><?= e((string) $ev['last_error']) ?></td><td class="small text-nowrap"><?= e(format_date((string) $ev['created_at'], true)) ?></td></tr>
                <?php endforeach; ?></tbody>
            </table></div>
        <?php endif; ?>
    </div></div>

    <div class="admin-card mb-3"><div class="card-body">
        <h2 class="h6 mb-1"><?= e(__('tracking_admin.extra_hosts')) ?></h2>
        <p class="small text-muted-bwa"><?= e(__('tracking_admin.extra_hosts_help')) ?></p>
        <textarea class="form-control form-control-sm font-monospace<?= invalid('track_extra_hosts') ?>" name="track_extra_hosts" rows="2" dir="ltr" placeholder="https://static.hotjar.com"><?= e((string) old('track_extra_hosts', $values['track_extra_hosts'])) ?></textarea>
        <?php if (error('track_extra_hosts')): ?><div class="invalid-feedback d-block"><?= e(error('track_extra_hosts')) ?></div><?php endif; ?>
    </div></div>

    <div class="admin-card mb-3"><div class="card-body small">
        <h2 class="h6"><i class="bi bi-list-check me-1"></i><?= e(__('tracking_admin.events_title')) ?></h2>
        <div class="table-responsive"><table class="table table-bwa table-sm mb-0">
            <thead><tr><th><?= e(__('tracking_admin.when')) ?></th><th>GA4 / GTM</th><th>Meta</th><th>TikTok</th><th>Snap</th><th>Pinterest</th></tr></thead>
            <tbody>
            <?php foreach ([['ev_view', 'view_item', 'ViewContent', 'ViewContent', 'VIEW_CONTENT', 'pagevisit'], ['ev_cart', 'add_to_cart', 'AddToCart', 'AddToCart', 'ADD_CART', 'addtocart'],
                ['ev_checkout', 'begin_checkout', 'InitiateCheckout', 'InitiateCheckout', 'START_CHECKOUT', '—'], ['ev_purchase', 'purchase', 'Purchase', 'CompletePayment', 'PURCHASE', 'checkout'],
                ['ev_search', 'search', 'Search', 'Search', 'SEARCH', 'search'], ['ev_lead', 'generate_lead', 'Lead', 'SubmitForm', 'SUBSCRIBE', 'lead'],
                ['ev_signup', 'sign_up', 'CompleteRegistration', 'CompleteRegistration', 'SIGN_UP', 'signup'], ['ev_contact', 'contact', 'Contact', 'Contact', '—', '—']] as $row): ?>
                <tr><td><?= e(__('tracking_admin.' . $row[0])) ?></td><?php for ($i = 1; $i <= 5; $i++): ?><td><code><?= e($row[$i]) ?></code></td><?php endfor; ?></tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <p class="text-muted-bwa mt-2 mb-0"><?= e(__('tracking_admin.events_help')) ?></p>
    </div></div>

    <button class="btn btn-bwa" type="submit"><?= e(__('common.save')) ?></button>
</form>
<form method="post" action="<?= e(route('admin.tracking.test')) ?>" class="mt-2"><?= csrf_field() ?><button class="btn btn-sm btn-outline-bwa" type="submit"><i class="bi bi-send me-1"></i><?= e(__('tracking_admin.test')) ?></button></form>
