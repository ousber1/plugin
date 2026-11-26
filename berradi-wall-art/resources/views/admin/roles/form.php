<?php
$isEdit = $role !== null;
$this->layout('layouts/admin', ['title' => $isEdit ? __('admin.edit_role') : __('admin.add_role')]);
?>
<form method="post" action="<?= e($isEdit ? route('admin.roles.update', ['id' => $role['id']]) : route('admin.roles.store')) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="admin-card mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-4"><?= $this->insert('partials/field', ['name' => 'name', 'label' => __('admin.role_name'), 'value' => $role['name'] ?? '', 'required' => true]) ?></div>
                <div class="col-md-3">
                    <?php if ($isEdit): ?>
                        <label class="form-label"><?= e(__('admin.slug')) ?></label>
                        <input class="form-control" value="<?= e($role['slug']) ?>" disabled>
                    <?php else: ?>
                        <?= $this->insert('partials/field', ['name' => 'slug', 'label' => __('admin.slug'), 'required' => true, 'help' => __('admin.slug_help')]) ?>
                    <?php endif; ?>
                </div>
                <div class="col-md-5"><?= $this->insert('partials/field', ['name' => 'description', 'label' => __('admin.description'), 'value' => $role['description'] ?? '']) ?></div>
            </div>
        </div>
    </div>
    <div class="row g-3 mb-4">
        <?php foreach ($grouped as $module => $perms): ?>
            <div class="col-md-6 col-xl-4">
                <div class="perm-module">
                    <div class="d-flex justify-content-between align-items-center">
                        <h4 class="mb-2"><?= e(__('permissions.module_' . $module)) ?></h4>
                        <input class="form-check-input" type="checkbox" data-check-group="<?= e($module) ?>" aria-label="<?= e(__('admin.select_all')) ?>">
                    </div>
                    <?php foreach ($perms as $p): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="permissions[]" value="<?= (int) $p['id'] ?>" id="perm<?= (int) $p['id'] ?>" data-group="<?= e($module) ?>" <?= in_array((int) $p['id'], $granted, true) ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="perm<?= (int) $p['id'] ?>"><?= e(__('permissions.' . str_replace('.', '_', $p['slug']))) ?> <code class="text-muted-bwa" style="font-size:.7rem"><?= e($p['slug']) ?></code></label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-bwa" type="submit"><?= e(__('common.save')) ?></button>
        <a class="btn btn-outline-bwa" href="<?= e(route('admin.roles.index')) ?>"><?= e(__('common.cancel')) ?></a>
    </div>
</form>
