<?php
$this->layout('layouts/admin', ['title' => __('admin.nav_customers')]);
$canManage = can('customers.manage');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <form method="get" class="filter-bar" role="search">
        <input class="form-control" type="search" name="q" value="<?= e($q) ?>" placeholder="<?= e(__('admin.search_customers')) ?>" aria-label="<?= e(__('admin.search_customers')) ?>">
        <select class="form-select" name="type" aria-label="<?= e(__('admin.customer_type')) ?>">
            <option value=""><?= e(__('admin.all_customers')) ?></option>
            <option value="registered" <?= $type === 'registered' ? 'selected' : '' ?>><?= e(__('admin.registered')) ?></option>
            <option value="guest" <?= $type === 'guest' ? 'selected' : '' ?>><?= e(__('admin.guest')) ?></option>
        </select>
        <select class="form-select" name="sort" aria-label="<?= e(__('admin.sort')) ?>">
            <?php foreach (['created_at' => 'admin.sort_recent', 'spent' => 'admin.sort_spent', 'orders' => 'admin.sort_orders', 'name' => 'admin.sort_name'] as $k => $label): ?>
                <option value="<?= $k ?>" <?= $sort === $k ? 'selected' : '' ?>><?= e(__($label)) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-outline-bwa" type="submit"><?= e(__('admin.apply')) ?></button>
    </form>
    <?php if ($canManage): ?><a class="btn btn-bwa" href="<?= e(route('admin.customers.create')) ?>"><i class="bi bi-person-plus me-1"></i><?= e(__('admin.new_customer')) ?></a><?php endif; ?>
</div>
<div class="admin-card">
    <div class="table-responsive">
        <table class="table table-bwa">
            <thead><tr><th><?= e(__('admin.customer')) ?></th><th><?= e(__('auth.phone')) ?></th><th><?= e(__('admin.customer_type')) ?></th><th class="text-end"><?= e(__('admin.orders')) ?></th><th class="text-end"><?= e(__('admin.total_spent')) ?></th><th><?= e(__('admin.last_order')) ?></th><th><?= e(__('admin.created')) ?></th></tr></thead>
            <tbody>
            <?php foreach ($customers->items as $c): ?>
                <tr>
                    <td><a class="fw-semibold" href="<?= e(route('admin.customers.show', ['id' => $c['id']])) ?>"><?= e(trim($c['first_name'] . ' ' . $c['last_name'])) ?></a><?= $c['company_name'] ? '<div class="small text-muted-bwa">' . e($c['company_name']) . '</div>' : '' ?><div class="small text-muted-bwa"><?= e((string) $c['email']) ?></div></td>
                    <td class="text-nowrap" dir="ltr"><?= e((string) $c['phone']) ?></td>
                    <td><span class="badge <?= (int) $c['is_guest'] ? 'text-bg-light border' : 'text-bg-dark' ?>"><?= e(__((int) $c['is_guest'] ? 'admin.guest' : 'admin.registered')) ?></span><?= !(int) $c['is_active'] ? ' <span class="badge text-bg-secondary">' . e(__('admin.inactive')) . '</span>' : '' ?></td>
                    <td class="text-end"><?= (int) $c['orders'] ?></td>
                    <td class="text-end text-nowrap"><?= e(money($c['spent'])) ?></td>
                    <td class="small"><?= $c['last_order'] ? e(format_date($c['last_order'])) : '—' ?></td>
                    <td class="small"><?= e(format_date($c['created_at'])) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($customers->items === []): ?>
                <tr><td colspan="7"><div class="empty-state"><i class="bi bi-people"></i><?= e(__('common.no_results')) ?></div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($customers->hasPages()): ?><div class="p-3 d-flex justify-content-between align-items-center"><span class="small text-muted-bwa"><?= $customers->from() ?>–<?= $customers->to() ?> / <?= $customers->total ?></span><?= $customers->links() ?></div><?php endif; ?>
</div>
