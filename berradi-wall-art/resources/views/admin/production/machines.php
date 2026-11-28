<?php
$this->layout('layouts/admin', ['title' => __('admin.nav_machines')]);
$types = ['printer' => __('production.mt_printer'), 'cutter' => __('production.mt_cutter'), 'laminator' => __('production.mt_laminator'), 'framing' => __('production.mt_framing'), 'other' => __('production.mt_other')];
?>
<p class="small text-muted-bwa"><?= e(__('production.machines_help')) ?></p>
<?php if (errors() !== []): ?><div class="alert alert-danger"><?php foreach (errors() as $m): ?><div><?= e(is_array($m) ? (string) ($m[0] ?? '') : (string) $m) ?></div><?php endforeach; ?></div><?php endif; ?>
<?php foreach (array_merge($machines, [null]) as $m): $isNew = $m === null; $m ??= ['id' => 0, 'code' => '', 'name' => '', 'machine_type' => 'printer', 'status' => 'active', 'hourly_cost' => '0.00', 'notes' => '', 'open_jobs' => 0]; ?>
    <details class="admin-card option-row mb-2" id="machine-<?= (int) $m['id'] ?>">
        <summary class="card-body d-flex flex-wrap align-items-center gap-3">
            <?php if ($isNew): ?><span class="fw-semibold text-gold"><i class="bi bi-plus-lg me-1"></i><?= e(__('production.add_machine')) ?></span>
            <?php else: ?>
                <span class="fw-semibold"><?= e($m['name']) ?></span><code class="small"><?= e($m['code']) ?></code>
                <span class="small text-muted-bwa"><?= e($types[$m['machine_type']] ?? $m['machine_type']) ?> · <?= e(money($m['hourly_cost'])) ?>/h · <?= e(__('production.n_open', ['n' => (int) $m['open_jobs']])) ?></span>
                <span class="badge-status <?= status_class($m['status']) ?> ms-auto"><?= e(__('production.ms_' . $m['status'])) ?></span>
            <?php endif; ?>
        </summary>
        <form method="post" action="<?= e($isNew ? route('admin.machines.store') : route('admin.machines.update', ['id' => $m['id']])) ?>" class="card-body border-top" novalidate>
            <?= csrf_field() ?>
            <div class="row g-2">
                <div class="col-6 col-md-2"><label class="form-label small"><?= e(__('admin.code')) ?></label><input class="form-control form-control-sm" name="code" value="<?= e($m['code']) ?>" required maxlength="40"></div>
                <div class="col-6 col-md-4"><label class="form-label small"><?= e(__('admin.name')) ?></label><input class="form-control form-control-sm" name="name" value="<?= e($m['name']) ?>" required maxlength="150"></div>
                <div class="col-6 col-md-2"><label class="form-label small"><?= e(__('admin.type')) ?></label><select class="form-select form-select-sm" name="machine_type"><?php foreach ($types as $k => $l): ?><option value="<?= $k ?>" <?= $m['machine_type'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
                <div class="col-6 col-md-2"><label class="form-label small"><?= e(__('production.hourly_cost')) ?></label><input class="form-control form-control-sm" name="hourly_cost" value="<?= e($m['hourly_cost']) ?>" inputmode="decimal" required></div>
                <div class="col-6 col-md-2"><label class="form-label small"><?= e(__('admin.status')) ?></label><select class="form-select form-select-sm" name="status"><?php foreach (['active', 'maintenance', 'inactive'] as $s): ?><option value="<?= $s ?>" <?= $m['status'] === $s ? 'selected' : '' ?>><?= e(__('production.ms_' . $s)) ?></option><?php endforeach; ?></select></div>
                <div class="col-12"><label class="form-label small"><?= e(__('admin.internal_notes')) ?></label><input class="form-control form-control-sm" name="notes" value="<?= e((string) $m['notes']) ?>" maxlength="2000"></div>
            </div>
            <div class="d-flex justify-content-end mt-2"><button class="btn btn-sm btn-bwa" type="submit"><?= e($isNew ? __('common.create') : __('common.save')) ?></button></div>
        </form>
    </details>
<?php endforeach; ?>
