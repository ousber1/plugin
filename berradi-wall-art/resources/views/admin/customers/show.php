<?php
$name = trim($c['first_name'] . ' ' . $c['last_name']);
$this->layout('layouts/admin', ['title' => $name]);
$canManage = can('customers.manage');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <a class="small text-muted-bwa" href="<?= e(route('admin.customers.index')) ?>"><i class="bi bi-arrow-<?= is_rtl() ? 'right' : 'left' ?> me-1"></i><?= e(__('admin.nav_customers')) ?></a>
    <?php if ($canManage): ?>
        <div class="page-actions">
            <?php if ($c['phone']): ?><a class="btn btn-sm btn-outline-bwa" href="<?= e(whatsapp_link((string) $c['phone'])) ?>" target="_blank" rel="noopener"><i class="bi bi-whatsapp me-1"></i>WhatsApp</a><?php endif; ?>
            <?php if (can('quotations.manage')): ?><a class="btn btn-sm btn-outline-bwa" href="<?= e(route('admin.quotations.create', ['customer' => $c['id']])) ?>"><i class="bi bi-file-earmark-text me-1"></i><?= e(__('finance.new_quote')) ?></a><?php endif; ?>
            <a class="btn btn-sm btn-bwa" href="<?= e(route('admin.customers.edit', ['id' => $c['id']])) ?>"><i class="bi bi-pencil me-1"></i><?= e(__('common.edit')) ?></a>
        </div>
    <?php endif; ?>
</div>
<div class="kpi-grid mb-4">
    <div class="kpi-tile"><div class="kpi-label"><i class="bi bi-bag"></i><?= e(__('admin.orders')) ?></div><div class="kpi-value"><?= (int) $stats['orders'] ?></div></div>
    <div class="kpi-tile"><div class="kpi-label"><i class="bi bi-cash-stack"></i><?= e(__('admin.total_spent')) ?></div><div class="kpi-value"><?= e(money($stats['spent'])) ?></div><div class="kpi-sub"><?= e(__('admin.excl_cancelled')) ?></div></div>
    <div class="kpi-tile"><div class="kpi-label"><i class="bi bi-hourglass-split"></i><?= e(__('admin.balance_due')) ?></div><div class="kpi-value"><?= e(money($stats['balance'])) ?></div></div>
    <div class="kpi-tile"><div class="kpi-label"><i class="bi bi-calendar3"></i><?= e(__('admin.last_order')) ?></div><div class="kpi-value" style="font-size:1.1rem"><?= $stats['last_order'] ? e(format_date($stats['last_order'])) : '—' ?></div><?php if ($stats['first_order']): ?><div class="kpi-sub"><?= e(__('admin.first_order')) ?> : <?= e(format_date($stats['first_order'])) ?></div><?php endif; ?></div>
</div>
<div class="row g-4">
    <div class="col-lg-4">
        <div class="admin-card mb-4"><div class="card-header"><h2><?= e(__('admin.contact')) ?></h2><span class="badge <?= (int) $c['is_guest'] ? 'text-bg-light border' : 'text-bg-dark' ?>"><?= e(__((int) $c['is_guest'] ? 'admin.guest' : 'admin.registered')) ?></span></div><div class="card-body">
            <div class="stat-line"><span><?= e(__('auth.email')) ?></span><span><?= $c['email'] ? '<a href="mailto:' . e($c['email']) . '">' . e($c['email']) . '</a>' : '—' ?></span></div>
            <div class="stat-line"><span><?= e(__('auth.phone')) ?></span><span dir="ltr"><?= e((string) ($c['phone'] ?: '—')) ?></span></div>
            <?php if ($c['company_name']): ?><div class="stat-line"><span><?= e(__('admin.company')) ?></span><span><?= e($c['company_name']) ?></span></div><?php endif; ?>
            <?php if ($c['ice']): ?><div class="stat-line"><span>ICE</span><span><?= e($c['ice']) ?></span></div><?php endif; ?>
            <div class="stat-line"><span><?= e(__('admin.language')) ?></span><span><?= $c['locale'] === 'ar' ? 'العربية' : 'Français' ?></span></div>
            <div class="stat-line"><span><?= e(__('admin.newsletter')) ?></span><span><?= $newsletter === 'subscribed' ? e(__('admin.subscribed')) : '—' ?></span></div>
            <div class="stat-line"><span><?= e(__('admin.marketing_opt_in')) ?></span><span><?= (int) $c['marketing_opt_in'] ? e(__('common.yes')) : e(__('common.no')) ?></span></div>
            <div class="stat-line"><span><?= e(__('admin.created')) ?></span><span><?= e(format_date($c['created_at'], true)) ?></span></div>
            <?php if ($c['last_login_at']): ?><div class="stat-line"><span><?= e(__('admin.last_login')) ?></span><span><?= e(format_date($c['last_login_at'], true)) ?></span></div><?php endif; ?>
            <div class="stat-line"><span><?= e(__('admin.saved_designs')) ?></span><span><?= (int) $designs ?></span></div>
            <div class="stat-line"><span><?= e(__('admin.stored_photos')) ?></span><span><?= (int) $photos ?></span></div>
        </div></div>
        <?php if ($c['notes']): ?><div class="admin-card mb-4"><div class="card-header"><h2><?= e(__('admin.internal_notes')) ?></h2></div><div class="card-body small" style="white-space:pre-line"><?= e($c['notes']) ?></div></div><?php endif; ?>
        <div class="admin-card"><div class="card-header"><h2><?= e(__('account.addresses')) ?></h2></div><div class="card-body">
            <?php foreach ($addresses as $a): ?>
                <div class="small mb-3"><strong><?= e($a['full_name']) ?></strong><?= (int) $a['is_default'] ? ' <span class="badge text-bg-light border">' . e(__('admin.default')) . '</span>' : '' ?><br><?= e($a['address_line1']) ?><?= $a['address_line2'] ? '<br>' . e($a['address_line2']) : '' ?><br><?= e($a['city']) ?> <?= e((string) $a['postal_code']) ?><br><span dir="ltr"><?= e($a['phone']) ?></span></div>
            <?php endforeach; ?>
            <?php if ($addresses === []): ?><p class="small text-muted-bwa mb-0"><?= e(__('admin.no_addresses')) ?></p><?php endif; ?>
        </div></div>
    </div>
    <div class="col-lg-8">
        <div class="admin-card mb-4"><div class="card-header"><h2><?= e(__('admin.orders')) ?></h2></div>
            <div class="table-responsive"><table class="table table-bwa">
                <thead><tr><th>N°</th><th><?= e(__('admin.date')) ?></th><th><?= e(__('admin.status')) ?></th><th><?= e(__('admin.payment')) ?></th><th class="text-end"><?= e(__('admin.total')) ?></th></tr></thead>
                <tbody>
                <?php foreach ($orders as $o): ?>
                    <tr><td class="fw-semibold text-nowrap"><?= e($o['order_number']) ?></td><td class="small"><?= e(format_date($o['placed_at'])) ?></td>
                        <td><span class="badge-status <?= in_array($o['status'], ['cancelled', 'returned'], true) ? 'is-danger' : ($o['status'] === 'delivered' ? 'is-success' : '') ?>"><?= e(\App\Services\Sales\OrderStatus::label((string) $o['status'])) ?></span></td>
                        <td class="small"><?= e(__('account.payment_' . $o['payment_status'])) ?></td><td class="text-end text-nowrap"><?= e(money($o['grand_total'])) ?></td></tr>
                <?php endforeach; ?>
                <?php if ($orders === []): ?><tr><td colspan="5"><div class="empty-state"><i class="bi bi-bag"></i><?= e(__('admin.no_orders_yet')) ?></div></td></tr><?php endif; ?>
                </tbody>
            </table></div>
        </div>
        <?php if ($canManage): ?>
            <div class="admin-card border-danger-subtle"><div class="card-header"><h2 class="text-danger"><?= e(__('admin.privacy_zone')) ?></h2></div><div class="card-body">
                <p class="small text-muted-bwa"><?= e(__('admin.anonymize_help')) ?></p>
                <form method="post" action="<?= e(route('admin.customers.anonymize', ['id' => $c['id']])) ?>" data-confirm="<?= e(__('admin.anonymize_confirm')) ?>">
                    <?= csrf_field() ?>
                    <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-incognito me-1"></i><?= e(__('admin.anonymize')) ?></button>
                </form>
            </div></div>
        <?php endif; ?>
    </div>
</div>
