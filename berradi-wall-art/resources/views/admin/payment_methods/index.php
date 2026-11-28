<?php $this->layout('layouts/admin', ['title' => __('admin.nav_payment_methods')]); ?>
<?php if (errors() !== []): ?><div class="alert alert-danger"><?php foreach (errors() as $m): ?><div><?= e(is_array($m) ? (string) ($m[0] ?? '') : (string) $m) ?></div><?php endforeach; ?></div><?php endif; ?>
<?php
$in = fn ($n, $v, $extra = '') => '<input class="form-control form-control-sm" name="' . $n . '" value="' . e((string) $v) . '" ' . $extra . '>';
$ta = fn ($n, $v, $extra = '') => '<textarea class="form-control form-control-sm" name="' . $n . '" rows="2" ' . $extra . '>' . e((string) $v) . '</textarea>';
?>
<div class="admin-card mb-3">
    <div class="card-body small">
        <strong><?= e(__('payment_methods.checkout_now')) ?></strong>
        <?php if ($checkoutPreview === []): ?>
            <span class="badge-status is-danger ms-1"><?= e(__('checkout.no_payment_method')) ?></span>
        <?php else: ?>
            <?php foreach ($checkoutPreview as $p): ?><span class="badge-status is-success ms-1"><?= e($p['name']) ?></span><?php endforeach; ?>
        <?php endif; ?>
        <div class="text-muted-bwa mt-1"><?= e(__('payment_methods.help')) ?></div>
    </div>
</div>

<?php foreach (array_merge($methods, [null]) as $m): $isNew = $m === null;
    $m ??= ['id' => 0, 'code' => '', 'name' => '', 'description' => '', 'instructions' => '', 'is_online' => 0, 'provider' => null, 'is_active' => 1, 'sort_order' => 10,
        'min_order_amount' => null, 'max_order_amount' => null, 'test_mode' => 1, 'orders_count' => 0, 'payments_count' => 0, 'fields' => [], 'config_values' => [], 'configured' => false, 'unsupported' => null, 'ar' => []];
    $online = (int) $m['is_online'] === 1; ?>
    <details class="admin-card option-row mb-2" id="method-<?= (int) $m['id'] ?>">
        <summary class="card-body d-flex flex-wrap align-items-center gap-3">
            <?php if ($isNew): ?><span class="fw-semibold text-gold"><i class="bi bi-plus-lg me-1"></i><?= e(__('payment_methods.new')) ?></span>
            <?php else: ?>
                <i class="bi <?= $online ? 'bi-credit-card' : ($m['code'] === 'cod' ? 'bi-cash-stack' : ($m['code'] === 'bank_transfer' ? 'bi-bank' : 'bi-wallet2')) ?> text-gold fs-5"></i>
                <span class="fw-semibold" dir="auto"><?= e($m['name']) ?></span>
                <?php if ($online): ?>
                    <?php if ($m['unsupported']): ?><span class="badge-status is-muted"><?= e(__('payment_methods.not_available')) ?></span>
                    <?php elseif ($m['configured']): ?><span class="badge-status <?= (int) $m['test_mode'] ? 'is-warning' : 'is-info' ?>"><?= e((int) $m['test_mode'] ? __('payment_methods.mode_test') : __('payment_methods.mode_live')) ?></span>
                    <?php else: ?><span class="badge-status is-muted"><?= e(__('payment_methods.not_configured')) ?></span><?php endif; ?>
                <?php endif; ?>
                <?php if ($m['min_order_amount'] !== null || $m['max_order_amount'] !== null): ?>
                    <span class="small text-muted-bwa"><?= e(__('payment_methods.limits', ['min' => $m['min_order_amount'] !== null ? money((string) $m['min_order_amount']) : '0', 'max' => $m['max_order_amount'] !== null ? money((string) $m['max_order_amount']) : '∞'])) ?></span>
                <?php endif; ?>
                <span class="small text-muted-bwa"><?= e(__('payment_methods.usage', ['orders' => (int) $m['orders_count']])) ?></span>
                <span class="ms-auto badge-status <?= (int) $m['is_active'] ? 'is-success' : 'is-muted' ?>"><?= e((int) $m['is_active'] ? __('admin.active') : __('admin.inactive')) ?></span>
            <?php endif; ?>
        </summary>
        <form method="post" action="<?= e($isNew ? route('admin.payment_methods.store') : route('admin.payment_methods.update', ['id' => $m['id']])) ?>" class="card-body border-top" novalidate autocomplete="off">
            <?= csrf_field() ?>
            <?php if ($m['unsupported']): ?><div class="alert alert-light border small"><i class="bi bi-info-circle me-1"></i><?= e(__($m['unsupported'])) ?></div><?php endif; ?>
            <div class="row g-2">
                <div class="col-md-6"><label class="form-label small"><?= e(__('payment_methods.name_fr')) ?></label><?= $in('name', $m['name'], 'required maxlength="120"') ?></div>
                <div class="col-md-6"><label class="form-label small"><?= e(__('payment_methods.name_ar')) ?></label><?= $in('ar_name', $m['ar']['name'] ?? '', 'maxlength="120" dir="rtl"') ?></div>
                <div class="col-md-6"><label class="form-label small"><?= e(__('payment_methods.description_fr')) ?></label><?= $ta('description', $m['description'], 'maxlength="500"') ?></div>
                <div class="col-md-6"><label class="form-label small"><?= e(__('payment_methods.description_ar')) ?></label><?= $ta('ar_description', $m['ar']['description'] ?? '', 'maxlength="500" dir="rtl"') ?></div>
                <div class="col-md-6"><label class="form-label small"><?= e(__('payment_methods.instructions_fr')) ?></label><?= $ta('instructions', $m['instructions'], 'maxlength="2000"') ?></div>
                <div class="col-md-6"><label class="form-label small"><?= e(__('payment_methods.instructions_ar')) ?></label><?= $ta('ar_instructions', $m['ar']['instructions'] ?? '', 'maxlength="2000" dir="rtl"') ?></div>
                <div class="col-4 col-md-2"><label class="form-label small"><?= e(__('payment_methods.min_amount')) ?></label><?= $in('min_order_amount', (string) $m['min_order_amount'], 'inputmode="decimal" placeholder="0"') ?></div>
                <div class="col-4 col-md-2"><label class="form-label small"><?= e(__('payment_methods.max_amount')) ?></label><?= $in('max_order_amount', (string) $m['max_order_amount'], 'inputmode="decimal" placeholder="∞"') ?></div>
                <div class="col-4 col-md-2"><label class="form-label small"><?= e(__('admin.sort_order')) ?></label><?= $in('sort_order', $m['sort_order'], 'type="number" min="0" max="999"') ?></div>
            </div>
            <?php if ($m['code'] === 'bank_transfer'): ?><p class="small text-muted-bwa mt-2 mb-0"><i class="bi bi-bank me-1"></i><?= e(__('payment_methods.bank_hint')) ?> <?php if (can('settings.manage')): ?><a href="<?= e(route('admin.settings', ['tab' => 'company'])) ?>"><?= e(__('admin.manage')) ?></a><?php endif; ?></p><?php endif; ?>

            <?php if ($online && $m['fields'] !== []): ?>
                <fieldset class="border rounded p-3 mt-3">
                    <legend class="float-none w-auto px-2 small fw-semibold mb-0"><i class="bi bi-key me-1"></i><?= e(__('payment_methods.credentials')) ?></legend>
                    <div class="row g-2">
                        <?php foreach ($m['fields'] as $f): $set = ($m['config_values'][$f['key']] ?? '') !== ''; ?>
                            <div class="col-md-6">
                                <label class="form-label small"><?= e(__($f['label'])) ?></label>
                                <?php if ($f['secret']): ?>
                                    <input class="form-control form-control-sm" type="password" name="cfg_<?= e($f['key']) ?>" value="" autocomplete="new-password" placeholder="<?= e($set ? __('payment_methods.secret_kept') : '') ?>">
                                    <?php if ($set): ?><div class="form-check mt-1"><input class="form-check-input" type="checkbox" name="clear_<?= e($f['key']) ?>" value="1" id="clear_<?= e($f['key'] . $m['id']) ?>"><label class="form-check-label small" for="clear_<?= e($f['key'] . $m['id']) ?>"><?= e(__('settings.clear_password')) ?></label></div><?php endif; ?>
                                <?php else: ?>
                                    <?= $in('cfg_' . $f['key'], $m['config_values'][$f['key']] ?? '', 'dir="ltr"') ?>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        <div class="col-12">
                            <label class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" role="switch" name="test_mode" value="1" <?= (int) $m['test_mode'] ? 'checked' : '' ?>><span class="form-check-label small"><?= e(__('payment_methods.test_mode')) ?></span></label>
                        </div>
                        <div class="col-12 small text-muted-bwa">
                            <?= e(__('payment_methods.callback_hint')) ?> <code dir="ltr" class="user-select-all"><?= e($callbackUrl) ?></code><br>
                            <?= e(__('payment_methods.cmi_steps')) ?>
                        </div>
                    </div>
                </fieldset>
            <?php endif; ?>

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
                <label class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" <?= (int) $m['is_active'] ? 'checked' : '' ?> <?= $m['unsupported'] ? 'disabled' : '' ?>><span class="form-check-label small"><?= e(__('payment_methods.active_checkout')) ?></span></label>
                <button class="btn btn-sm btn-bwa" type="submit"><?= e($isNew ? __('common.create') : __('common.save')) ?></button>
            </div>
        </form>
        <?php if (!$isNew): ?><form method="post" action="<?= e(route('admin.payment_methods.destroy', ['id' => $m['id']])) ?>" class="px-3 pb-3" data-confirm="<?= e(__('payment_methods.delete_confirm')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-link text-danger p-0" type="submit"><i class="bi bi-trash me-1"></i><?= e(__('payment_methods.delete')) ?></button></form><?php endif; ?>
    </details>
<?php endforeach; ?>
<p class="small text-muted-bwa"><i class="bi bi-shield-check me-1"></i><?= e(__('payment_methods.security_note')) ?></p>
