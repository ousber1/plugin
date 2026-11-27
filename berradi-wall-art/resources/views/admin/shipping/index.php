<?php $this->layout('layouts/admin', ['title' => __('admin.nav_shipping')]); ?>
<?php if (errors() !== []): ?><div class="alert alert-danger"><?php foreach (errors() as $m): ?><div><?= e(is_array($m) ? (string) ($m[0] ?? '') : (string) $m) ?></div><?php endforeach; ?></div><?php endif; ?>
<div class="alert alert-light border small"><?= e(__('shipping.defaults', ['flat' => money($defaults['flat']), 'free' => $defaults['free'] !== '' ? money($defaults['free']) : '—', 'min' => $defaults['min'], 'max' => $defaults['max'], 'prod' => $defaults['production']])) ?>
    <?php if (can('settings.manage')): ?><a href="<?= e(route('admin.settings', ['tab' => 'shipping'])) ?>"><?= e(__('admin.manage')) ?></a><?php endif; ?></div>
<?php $num = fn ($n, $v, $extra = '') => '<input class="form-control form-control-sm" name="' . $n . '" value="' . e((string) $v) . '" ' . $extra . '>'; ?>
<?php foreach (array_merge($zones, [null]) as $z): $isNew = $z === null; $z ??= ['id' => 0, 'name' => '', 'description' => '', 'default_price' => '', 'free_shipping_threshold' => '', 'min_days' => 1, 'max_days' => 3, 'sort_order' => 0, 'is_active' => 1, 'n' => 0]; ?>
    <div class="admin-card mb-3" id="zone-<?= (int) $z['id'] ?>">
        <details class="option-row">
            <summary class="card-body d-flex flex-wrap align-items-center gap-3">
                <?php if ($isNew): ?><span class="fw-semibold text-gold"><i class="bi bi-plus-lg me-1"></i><?= e(__('shipping.new_zone')) ?></span>
                <?php else: ?><span class="fw-semibold"><?= e($z['name']) ?></span><span class="small text-muted-bwa"><?= e(__('shipping.zone_summary', ['price' => money($z['default_price']), 'min' => (int) $z['min_days'], 'max' => (int) $z['max_days'], 'n' => (int) $z['n']])) ?><?= $z['free_shipping_threshold'] !== null && $z['free_shipping_threshold'] !== '' ? ' · ' . e(__('shipping.free_from', ['amount' => money($z['free_shipping_threshold'])])) : '' ?></span><?php if (!(int) $z['is_active']): ?><span class="badge-status is-muted"><?= e(__('admin.inactive')) ?></span><?php endif; ?><?php endif; ?>
            </summary>
            <form method="post" action="<?= e($isNew ? route('admin.shipping.zones.store') : route('admin.shipping.zones.update', ['id' => $z['id']])) ?>" class="card-body border-top" novalidate>
                <?= csrf_field() ?>
                <div class="row g-2">
                    <div class="col-md-4"><label class="form-label small"><?= e(__('admin.name')) ?></label><?= $num('name', $z['name'], 'required maxlength="120"') ?></div>
                    <div class="col-md-8"><label class="form-label small"><?= e(__('product.description')) ?></label><?= $num('description', (string) $z['description'], 'maxlength="255"') ?></div>
                    <div class="col-6 col-md-3"><label class="form-label small"><?= e(__('shipping.default_price')) ?></label><?= $num('default_price', $z['default_price'], 'inputmode="decimal"') ?></div>
                    <div class="col-6 col-md-3"><label class="form-label small"><?= e(__('shipping.free_threshold')) ?></label><?= $num('free_shipping_threshold', (string) $z['free_shipping_threshold'], 'inputmode="decimal"') ?></div>
                    <div class="col-4 col-md-2"><label class="form-label small"><?= e(__('shipping.min_days')) ?></label><?= $num('min_days', $z['min_days'], 'type="number" min="0"') ?></div>
                    <div class="col-4 col-md-2"><label class="form-label small"><?= e(__('shipping.max_days')) ?></label><?= $num('max_days', $z['max_days'], 'type="number" min="0"') ?></div>
                    <div class="col-4 col-md-2"><label class="form-label small"><?= e(__('admin.sort_order')) ?></label><?= $num('sort_order', $z['sort_order'], 'type="number"') ?></div>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-2">
                    <label class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" <?= (int) $z['is_active'] ? 'checked' : '' ?>><span class="form-check-label small"><?= e(__('admin.active')) ?></span></label>
                    <button class="btn btn-sm btn-bwa" type="submit"><?= e($isNew ? __('common.create') : __('common.save')) ?></button>
                </div>
            </form>
        </details>
        <?php if (!$isNew): ?>
            <div class="table-responsive border-top"><table class="table table-bwa mb-0">
                <thead><tr><th><?= e(__('checkout.city')) ?></th><th class="text-end"><?= e(__('admin.p_price')) ?></th><th class="text-end"><?= e(__('shipping.free_threshold')) ?></th><th class="text-end"><?= e(__('shipping.days')) ?></th><th></th></tr></thead>
                <tbody>
                <?php $formRate = null; foreach ($rates[(int) $z['id']] ?? [] as $r): ?>
                    <?php if ($edit !== (int) $r['id']): ?>
                        <tr class="<?= (int) $r['is_active'] ? '' : 'opacity-50' ?>"><td><?= e($r['city']) ?></td><td class="text-end text-nowrap"><?= e(money($r['price'])) ?></td><td class="text-end text-nowrap small"><?= $r['free_shipping_threshold'] !== null ? e(money($r['free_shipping_threshold'])) : '—' ?></td><td class="text-end small"><?= (int) $r['min_days'] ?>–<?= (int) $r['max_days'] ?></td>
                            <td class="text-end text-nowrap"><a class="btn btn-sm btn-link" href="?rate=<?= (int) $r['id'] ?>#zone-<?= (int) $z['id'] ?>" aria-label="<?= e(__('common.edit')) ?>"><i class="bi bi-pencil"></i></a>
                                <form class="d-inline" method="post" action="<?= e(route('admin.shipping.rates.destroy', ['id' => $r['id']])) ?>" data-confirm="<?= e(__('common.confirm_delete')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-link text-danger" type="submit" aria-label="<?= e(__('account.delete_now')) ?>"><i class="bi bi-trash"></i></button></form></td></tr>
                    <?php else: $formRate = $r; endif; ?>
                <?php endforeach; ?>
                </tbody>
            </table></div>
            <?php $r = $formRate ?? ['id' => 0, 'city' => '', 'price' => (string) $z['default_price'], 'free_shipping_threshold' => '', 'min_days' => $z['min_days'], 'max_days' => $z['max_days'], 'is_active' => 1]; $rNew = $formRate === null; ?>
            <div class="card-body border-top">
                <form method="post" action="<?= e($rNew ? route('admin.shipping.rates.store') : route('admin.shipping.rates.update', ['id' => $r['id']])) ?>" class="row g-2 align-items-end" novalidate>
                    <?= csrf_field() ?><input type="hidden" name="shipping_zone_id" value="<?= (int) $z['id'] ?>">
                    <div class="col-md-3"><label class="form-label small"><?= e($rNew ? __('shipping.add_city') : __('checkout.city')) ?></label><?= $num('city', $r['city'], 'maxlength="100"') ?></div>
                    <div class="col-4 col-md-2"><label class="form-label small"><?= e(__('admin.p_price')) ?></label><?= $num('price', $r['price'], 'inputmode="decimal"') ?></div>
                    <div class="col-4 col-md-2"><label class="form-label small"><?= e(__('shipping.free_threshold')) ?></label><?= $num('free_shipping_threshold', (string) $r['free_shipping_threshold'], 'inputmode="decimal"') ?></div>
                    <div class="col-2 col-md-1"><label class="form-label small"><?= e(__('shipping.min_days')) ?></label><?= $num('min_days', $r['min_days'], 'type="number" min="0"') ?></div>
                    <div class="col-2 col-md-1"><label class="form-label small"><?= e(__('shipping.max_days')) ?></label><?= $num('max_days', $r['max_days'], 'type="number" min="0"') ?></div>
                    <div class="col-6 col-md-1"><label class="form-check form-switch mb-1"><input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" <?= (int) $r['is_active'] ? 'checked' : '' ?>><span class="form-check-label small"><?= e(__('admin.active')) ?></span></label></div>
                    <div class="col-6 col-md-2"><button class="btn btn-sm btn-outline-bwa w-100" type="submit"><?= e($rNew ? __('common.create') : __('common.save')) ?></button></div>
                </form>
            </div>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
<p class="small text-muted-bwa"><?= e(__('shipping.help')) ?></p>
