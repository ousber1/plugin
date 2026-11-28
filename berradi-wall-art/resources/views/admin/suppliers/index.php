<?php $this->layout('layouts/admin', ['title' => __('admin.nav_suppliers')]); ?>
<?php if (errors() !== []): ?><div class="alert alert-danger"><?php foreach (errors() as $m): ?><div><?= e(is_array($m) ? (string) ($m[0] ?? '') : (string) $m) ?></div><?php endforeach; ?></div><?php endif; ?>
<?php foreach (array_merge($rows, [null]) as $s): $isNew = $s === null; $s ??= ['id' => 0, 'name' => '', 'contact_name' => '', 'email' => '', 'phone' => '', 'address' => '', 'city' => '', 'ice' => '', 'notes' => '', 'is_active' => 1, 'purchases' => 0, 'purchased' => 0, 'owed' => 0]; ?>
    <details class="admin-card option-row mb-2" id="supplier-<?= (int) $s['id'] ?>">
        <summary class="card-body d-flex flex-wrap align-items-center gap-3">
            <?php if ($isNew): ?><span class="fw-semibold text-gold"><i class="bi bi-plus-lg me-1"></i><?= e(__('inventory.new_supplier')) ?></span>
            <?php else: ?>
                <span class="fw-semibold"><?= e($s['name']) ?></span>
                <span class="small text-muted-bwa"><?= e((string) $s['city']) ?><?= $s['phone'] ? ' · <span dir="ltr">' . e($s['phone']) . '</span>' : '' ?></span>
                <span class="small ms-auto"><?= e(__('inventory.n_purchases', ['n' => (int) $s['purchases']])) ?> · <?= e(money($s['purchased'])) ?><?php if ((float) $s['owed'] > 0): ?> · <span class="text-danger"><?= e(__('inventory.owed', ['amount' => money($s['owed'])])) ?></span><?php endif; ?></span>
                <?php if (!(int) $s['is_active']): ?><span class="badge-status is-muted"><?= e(__('admin.inactive')) ?></span><?php endif; ?>
            <?php endif; ?>
        </summary>
        <form method="post" action="<?= e($isNew ? route('admin.suppliers.store') : route('admin.suppliers.update', ['id' => $s['id']])) ?>" class="card-body border-top" novalidate>
            <?= csrf_field() ?>
            <div class="row g-2">
                <?php foreach (['name' => [4, __('admin.name'), ''], 'contact_name' => [4, __('inventory.contact_name'), ''], 'phone' => [2, __('auth.phone'), 'dir="ltr"'], 'email' => [2, __('auth.email'), 'dir="ltr"'], 'address' => [5, __('checkout.address'), ''], 'city' => [3, __('checkout.city'), ''], 'ice' => [2, 'ICE', '']] as $k => [$col, $label, $extra]): ?>
                    <div class="col-md-<?= $col ?>"><label class="form-label small"><?= e($label) ?></label><input class="form-control form-control-sm" name="<?= $k ?>" value="<?= e((string) $s[$k]) ?>" <?= $k === 'name' ? 'required' : '' ?> <?= $extra ?>></div>
                <?php endforeach; ?>
                <div class="col-md-2 pt-md-4"><label class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" <?= (int) $s['is_active'] ? 'checked' : '' ?>><span class="form-check-label small"><?= e(__('admin.active')) ?></span></label></div>
                <div class="col-12"><label class="form-label small"><?= e(__('admin.internal_notes')) ?></label><input class="form-control form-control-sm" name="notes" value="<?= e((string) $s['notes']) ?>"></div>
            </div>
            <div class="d-flex justify-content-end mt-2"><button class="btn btn-sm btn-bwa" type="submit"><?= e($isNew ? __('common.create') : __('common.save')) ?></button></div>
        </form>
    </details>
<?php endforeach; ?>
