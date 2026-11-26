<?php $this->layout('layouts/storefront', ['title' => __('auth.my_account'), 'noindex' => true]) ?>
<section class="section-sm">
    <div class="container">
        <div class="mb-4">
            <span class="eyebrow"><?= e(__('auth.my_account')) ?></span>
            <h1 class="mt-1"><?= e(__('account.hello', ['name' => (string) $customer['first_name']])) ?></h1>
        </div>
        <div class="row g-4">
            <div class="col-lg-3"><?= $this->insert('customer/account/_nav', ['current' => 'dashboard']) ?></div>
            <div class="col-lg-9">
                <div class="row g-3 mb-4">
                    <div class="col-sm-6"><div class="card-bwa stat-mini"><div class="stat-label"><?= e(__('account.orders_count')) ?></div><div class="stat-value mt-2"><?= (int) $ordersCount ?></div></div></div>
                    <div class="col-sm-6"><div class="card-bwa stat-mini"><div class="stat-label"><?= e(__('account.member_since')) ?></div><div class="stat-value mt-2" style="font-size:1.4rem"><?= e(format_date($customer['created_at'])) ?></div></div></div>
                </div>
                <div class="card-bwa">
                    <div class="card-header"><?= e(__('account.recent_orders')) ?></div>
                    <?php if ($recentOrders === []): ?>
                        <div class="empty-state"><i class="bi bi-bag"></i><?= e(__('account.no_orders')) ?>
                            <?php if (app()->router()->has('shop.index')): ?><div class="mt-3"><a class="btn btn-bwa" href="<?= e(route('shop.index')) ?>"><?= e(__('nav.shop')) ?></a></div><?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table mb-0">
                                <thead><tr><th><?= e(__('account.order')) ?></th><th><?= e(__('admin.date')) ?></th><th><?= e(__('admin.status')) ?></th><th class="text-end"><?= e(__('account.total')) ?></th></tr></thead>
                                <tbody>
                                <?php foreach ($recentOrders as $o): ?>
                                    <tr><td class="fw-semibold"><?= e($o['order_number']) ?></td><td><?= e(format_date($o['placed_at'])) ?></td><td><span class="badge-status"><?= e(__('orders.status_' . $o['status'])) ?></span></td><td class="text-end"><?= e(money($o['grand_total'])) ?></td></tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>
