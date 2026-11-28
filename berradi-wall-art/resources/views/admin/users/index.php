<?php $this->layout('layouts/admin', ['title' => __('admin.nav_users')]) ?>
<div class="admin-card">
    <div class="card-header flex-wrap">
        <form class="d-flex flex-wrap gap-2" method="get">
            <input type="search" class="form-control form-control-sm" style="max-width:240px" name="q" value="<?= e($search) ?>" placeholder="<?= e(__('common.search')) ?>">
            <select class="form-select form-select-sm" style="max-width:200px" name="role">
                <option value=""><?= e(__('admin.all_roles')) ?></option>
                <?php foreach ($roles as $r): ?>
                    <option value="<?= (int) $r['id'] ?>" <?= $roleId === (int) $r['id'] ? 'selected' : '' ?>><?= e($r['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-sm btn-outline-bwa" type="submit"><?= e(__('common.filter')) ?></button>
        </form>
        <a href="<?= e(route('admin.users.create')) ?>" class="btn btn-sm btn-bwa"><i class="bi bi-plus-lg me-1"></i><?= e(__('admin.add_user')) ?></a>
    </div>
    <div class="table-responsive">
        <table class="table table-bwa">
            <thead><tr>
                <th><?= e(__('admin.full_name')) ?></th><th><?= e(__('auth.email')) ?></th><th><?= e(__('admin.role')) ?></th>
                <th><?= e(__('admin.last_login')) ?></th><th><?= e(__('admin.status')) ?></th><th class="text-end"><?= e(__('common.actions')) ?></th>
            </tr></thead>
            <tbody>
            <?php foreach ($users->items as $u): ?>
                <tr>
                    <td><div class="d-flex align-items-center gap-2"><span class="avatar"><?= e(\App\Helpers\Str::initials((string) $u['name'])) ?></span><span class="fw-semibold"><?= e($u['name']) ?></span></div></td>
                    <td><?= e($u['email']) ?></td>
                    <td><span class="badge-status <?= $u['role_slug'] === 'super_admin' ? 'is-dark' : '' ?>"><?= e($u['role_name']) ?></span></td>
                    <td class="small text-muted-bwa"><?= e($u['last_login_at'] ? format_date($u['last_login_at'], true) : '—') ?></td>
                    <td><span class="badge-status <?= (int) $u['is_active'] ? 'is-success' : 'is-muted' ?>"><?= e((int) $u['is_active'] ? __('admin.active') : __('admin.inactive')) ?></span></td>
                    <td class="text-end text-nowrap">
                        <?php if ($u['role_slug'] !== 'super_admin' || \App\Services\Auth::isSuperAdmin()): ?>
                            <a class="btn btn-sm btn-outline-bwa" href="<?= e(route('admin.users.edit', ['id' => $u['id']])) ?>"><i class="bi bi-pencil"></i></a>
                            <?php if ((int) $u['id'] !== (int) auth_user()['id']): ?>
                                <form class="d-inline" method="post" action="<?= e(route('admin.users.toggle', ['id' => $u['id']])) ?>" data-confirm="<?= e(__('common.confirm')) ?>">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm btn-outline-bwa" type="submit" title="<?= e((int) $u['is_active'] ? __('admin.deactivate') : __('admin.activate')) ?>"><i class="bi <?= (int) $u['is_active'] ? 'bi-pause' : 'bi-play' ?>"></i></button>
                                </form>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($users->items === []): ?>
                <tr><td colspan="6"><div class="empty-state"><i class="bi bi-people"></i><?= e(__('common.no_results')) ?></div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($users->hasPages()): ?><div class="p-3 d-flex justify-content-between align-items-center"><span class="small text-muted-bwa"><?= $users->from() ?>–<?= $users->to() ?> / <?= $users->total ?></span><?= $users->links() ?></div><?php endif; ?>
</div>
