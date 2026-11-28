<?php $this->layout('layouts/admin', ['title' => __('admin.nav_coupons')]); ?>
<?php if (errors() !== []): ?><div class="alert alert-danger"><?php foreach (errors() as $m): ?><div><?= e(is_array($m) ? (string) ($m[0] ?? '') : (string) $m) ?></div><?php endforeach; ?></div><?php endif; ?>
<?php
$in = fn ($n, $v, $extra = '') => '<input class="form-control form-control-sm" name="' . $n . '" value="' . e((string) $v) . '" ' . $extra . '>';
$dtl = fn ($d) => $d ? date('Y-m-d\TH:i', strtotime((string) $d)) : '';
?>
<?php foreach (array_merge($rows, [null]) as $c): $isNew = $c === null; $c ??= ['id' => 0, 'code' => '', 'description' => '', 'discount_type' => 'percent', 'discount_value' => '', 'min_order_amount' => '', 'max_discount_amount' => '', 'product_id' => null, 'category_id' => null, 'usage_limit' => '', 'usage_limit_per_customer' => '', 'used_count' => 0, 'given' => '0', 'starts_at' => null, 'ends_at' => null, 'is_active' => 1, 'product_title' => null, 'category_name' => null];
    $expired = $c['ends_at'] !== null && strtotime((string) $c['ends_at']) < time(); ?>
    <details class="admin-card option-row mb-2" id="coupon-<?= (int) $c['id'] ?>">
        <summary class="card-body d-flex flex-wrap align-items-center gap-3">
            <?php if ($isNew): ?><span class="fw-semibold text-gold"><i class="bi bi-plus-lg me-1"></i><?= e(__('coupons.new')) ?></span>
            <?php else: ?>
                <code class="fw-semibold fs-6"><?= e($c['code']) ?></code>
                <span class="small"><?= e($c['discount_type'] === 'percent' ? rtrim(rtrim((string) $c['discount_value'], '0'), '.') . ' %' : ($c['discount_type'] === 'fixed' ? money($c['discount_value']) : __('coupons.free_shipping'))) ?><?= $c['product_title'] ? ' · ' . e($c['product_title']) : '' ?><?= $c['category_name'] ? ' · ' . e($c['category_name']) : '' ?></span>
                <span class="small text-muted-bwa"><?= e(__('coupons.used', ['n' => (int) $c['used_count'], 'limit' => $c['usage_limit'] ?: '∞', 'amount' => money($c['given'])])) ?></span>
                <span class="ms-auto badge-status <?= !(int) $c['is_active'] || $expired ? 'is-muted' : 'is-success' ?>"><?= e(!(int) $c['is_active'] ? __('admin.inactive') : ($expired ? __('coupons.expired') : __('admin.active'))) ?></span>
            <?php endif; ?>
        </summary>
        <form method="post" action="<?= e($isNew ? route('admin.coupons.store') : route('admin.coupons.update', ['id' => $c['id']])) ?>" class="card-body border-top" novalidate>
            <?= csrf_field() ?>
            <div class="row g-2">
                <div class="col-6 col-md-3"><label class="form-label small"><?= e(__('coupons.code')) ?></label><?= $in('code', $c['code'], 'maxlength="50" style="text-transform:uppercase"') ?></div>
                <div class="col-6 col-md-3"><label class="form-label small"><?= e(__('admin.type')) ?></label><select class="form-select form-select-sm" name="discount_type"><?php foreach (['percent', 'fixed', 'free_shipping'] as $tp): ?><option value="<?= $tp ?>" <?= $c['discount_type'] === $tp ? 'selected' : '' ?>><?= e(__('coupons.t_' . $tp)) ?></option><?php endforeach; ?></select></div>
                <div class="col-6 col-md-2"><label class="form-label small"><?= e(__('coupons.value')) ?></label><?= $in('discount_value', $c['discount_type'] === 'free_shipping' ? '' : $c['discount_value'], 'inputmode="decimal"') ?></div>
                <div class="col-6 col-md-2"><label class="form-label small"><?= e(__('coupons.min_order')) ?></label><?= $in('min_order_amount', (string) $c['min_order_amount'], 'inputmode="decimal"') ?></div>
                <div class="col-6 col-md-2"><label class="form-label small"><?= e(__('coupons.max_discount')) ?></label><?= $in('max_discount_amount', (string) $c['max_discount_amount'], 'inputmode="decimal"') ?></div>
                <div class="col-md-6"><label class="form-label small"><?= e(__('product.description')) ?></label><?= $in('description', (string) $c['description'], 'maxlength="255"') ?></div>
                <div class="col-6 col-md-3"><label class="form-label small"><?= e(__('coupons.limit')) ?></label><?= $in('usage_limit', (string) $c['usage_limit'], 'type="number" min="1"') ?></div>
                <div class="col-6 col-md-3"><label class="form-label small"><?= e(__('coupons.limit_customer')) ?></label><?= $in('usage_limit_per_customer', (string) $c['usage_limit_per_customer'], 'type="number" min="1"') ?></div>
                <div class="col-md-3"><label class="form-label small"><?= e(__('coupons.product')) ?></label><select class="form-select form-select-sm" name="product_id"><option value=""><?= e(__('coupons.all')) ?></option><?php foreach ($products as $p): ?><option value="<?= (int) $p['id'] ?>" <?= (int) $c['product_id'] === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['title']) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-3"><label class="form-label small"><?= e(__('admin.category')) ?></label><select class="form-select form-select-sm" name="category_id"><option value=""><?= e(__('coupons.all')) ?></option><?php foreach ($categories as $k): ?><option value="<?= (int) $k['id'] ?>" <?= (int) $c['category_id'] === (int) $k['id'] ? 'selected' : '' ?>><?= e($k['name']) ?></option><?php endforeach; ?></select></div>
                <div class="col-6 col-md-3"><label class="form-label small"><?= e(__('admin.starts_at')) ?></label><input class="form-control form-control-sm" type="datetime-local" name="starts_at" value="<?= e($dtl($c['starts_at'])) ?>"></div>
                <div class="col-6 col-md-3"><label class="form-label small"><?= e(__('admin.ends_at')) ?></label><input class="form-control form-control-sm" type="datetime-local" name="ends_at" value="<?= e($dtl($c['ends_at'])) ?>"></div>
            </div>
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-2">
                <label class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" <?= (int) $c['is_active'] ? 'checked' : '' ?>><span class="form-check-label small"><?= e(__('admin.active')) ?></span></label>
                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-bwa" type="submit"><?= e($isNew ? __('common.create') : __('common.save')) ?></button>
                </div>
            </div>
        </form>
        <?php if (!$isNew): ?><form method="post" action="<?= e(route('admin.coupons.destroy', ['id' => $c['id']])) ?>" class="px-3 pb-3" data-confirm="<?= e(__('common.confirm_delete')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-link text-danger p-0" type="submit"><?= e((int) $c['used_count'] > 0 ? __('coupons.deactivate') : __('account.delete_now')) ?></button></form><?php endif; ?>
    </details>
<?php endforeach; ?>
<p class="small text-muted-bwa"><?= e(__('coupons.help')) ?></p>
