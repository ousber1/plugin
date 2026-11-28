<?php $this->layout('layouts/storefront', ['title' => __('account.orders'), 'noindex' => true]) ?>
<section class="section-sm">
    <div class="container">
        <h1 class="mb-4"><?= e(__('account.orders')) ?></h1>
        <div class="row g-4">
            <div class="col-lg-3"><?= $this->insert('customer/account/_nav', ['current' => 'customer.orders']) ?></div>
            <div class="col-lg-9">
                <?php if ($orders->items === []): ?>
                    <div class="empty-state card-bwa"><i class="bi bi-bag"></i><?= e(__('account.no_orders')) ?><div class="mt-3"><a class="btn btn-bwa" href="<?= e(route('shop.index')) ?>"><?= e(__('nav.shop')) ?></a></div></div>
                <?php else: ?>
                    <div class="card-bwa">
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead><tr><th><?= e(__('account.order')) ?></th><th><?= e(__('admin.date')) ?></th><th><?= e(__('admin.status')) ?></th><th><?= e(__('account.items')) ?></th><th class="text-end"><?= e(__('account.total')) ?></th><th></th></tr></thead>
                                <tbody>
                                <?php foreach ($orders->items as $o): ?>
                                    <tr>
                                        <td class="fw-semibold"><?= e($o['order_number']) ?></td>
                                        <td><?= e(format_date($o['placed_at'])) ?></td>
                                        <td><span class="badge-status <?= in_array($o['status'], ['cancelled', 'returned'], true) ? 'is-danger' : ($o['status'] === 'delivered' ? 'is-success' : '') ?>"><?= e(\App\Services\Sales\OrderStatus::label((string) $o['status'])) ?></span></td>
                                        <td><?= (int) ($counts[(int) $o['id']] ?? 0) ?></td>
                                        <td class="text-end"><?= e(money((string) $o['grand_total'])) ?></td>
                                        <td class="text-end"><a class="btn btn-sm btn-outline-bwa" href="<?= e(route('customer.orders.show', ['number' => $o['order_number']])) ?>"><?= e(__('account.details')) ?></a></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php if ($orders->hasPages()): ?><div class="d-flex justify-content-center mt-4"><?= $orders->links() ?></div><?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
