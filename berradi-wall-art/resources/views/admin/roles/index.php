<?php $this->layout('layouts/admin', ['title' => __('admin.nav_roles')]) ?>
<div class="admin-card">
    <div class="card-header">
        <h2><?= e(__('admin.roles_intro')) ?></h2>
        <a class="btn btn-sm btn-bwa" href="<?= e(route('admin.roles.create')) ?>"><i class="bi bi-plus-lg me-1"></i><?= e(__('admin.add_role')) ?></a>
    </div>
    <div class="table-responsive">
        <table class="table table-bwa">
            <thead><tr><th><?= e(__('admin.role')) ?></th><th><?= e(__('admin.description')) ?></th><th><?= e(__('admin.permissions')) ?></th><th><?= e(__('admin.nav_users')) ?></th><th class="text-end"><?= e(__('common.actions')) ?></th></tr></thead>
            <tbody>
            <?php foreach ($roles as $r): ?>
                <tr>
                    <td><span class="fw-semibold"><?= e($r['name']) ?></span> <code class="small ms-1"><?= e($r['slug']) ?></code></td>
                    <td class="small text-muted-bwa"><?= e($r['description']) ?></td>
                    <td><?= $r['slug'] === 'super_admin' ? '<span class="badge-status is-dark">' . e(__('admin.all_permissions')) . '</span>' : (int) $r['permissions_count'] . ' / ' . (int) $totalPermissions ?></td>
                    <td><?= (int) $r['users_count'] ?></td>
                    <td class="text-end text-nowrap">
                        <?php if ($r['slug'] !== 'super_admin'): ?>
                            <a class="btn btn-sm btn-outline-bwa" href="<?= e(route('admin.roles.edit', ['id' => $r['id']])) ?>"><i class="bi bi-pencil"></i></a>
                        <?php endif; ?>
                        <?php if ((int) $r['is_system'] === 0 && (int) $r['users_count'] === 0): ?>
                            <form class="d-inline" method="post" action="<?= e(route('admin.roles.destroy', ['id' => $r['id']])) ?>" data-confirm="<?= e(__('common.confirm_delete')) ?>">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
