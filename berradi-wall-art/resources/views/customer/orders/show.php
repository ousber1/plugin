<?php $this->layout('layouts/storefront', ['title' => (string) $order['order_number'], 'noindex' => true]) ?>
<section class="section-sm">
    <div class="container">
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
            <div>
                <a class="small text-muted-bwa" href="<?= e(route('customer.orders')) ?>"><i class="bi bi-arrow-<?= is_rtl() ? 'right' : 'left' ?> me-1"></i><?= e(__('account.orders')) ?></a>
                <h1 class="mb-0 mt-1"><?= e($order['order_number']) ?></h1>
                <span class="small text-muted-bwa"><?= e(__('track.placed_on', ['date' => format_date($order['placed_at'], true)])) ?></span>
            </div>
            <a class="btn btn-outline-bwa btn-sm" href="<?= e(route('page.contact', ['order' => $order['order_number']])) ?>"><i class="bi bi-chat-dots me-1"></i><?= e(__('account.contact_support')) ?></a>
        </div>
        <div class="row g-4">
            <div class="col-lg-3"><?= $this->insert('customer/account/_nav', ['current' => 'customer.orders']) ?></div>
            <div class="col-lg-9">
                <div class="card-bwa mb-4"><div class="card-body"><?= $this->insert('storefront/partials/order_timeline', ['order' => $order]) ?></div></div>
                <div class="row g-4">
                    <div class="col-md-7">
                        <div class="card-bwa h-100">
                            <div class="card-header"><?= e(__('account.items')) ?></div>
                            <div class="card-body">
                                <?= $this->insert('storefront/partials/order_items', ['items' => $items]) ?>
                                <?= $this->insert('storefront/partials/order_totals', ['order' => $order]) ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-5">
                        <div class="card-bwa mb-4">
                            <div class="card-header"><?= e(__('checkout.delivery')) ?></div>
                            <div class="card-body small">
                                <strong><?= e($order['customer_name']) ?></strong><br><span dir="ltr"><?= e($order['customer_phone']) ?></span><br>
                                <?= e($order['shipping_address']) ?><br><?= e($order['shipping_city']) ?> <?= e((string) $order['shipping_postal_code']) ?>
                                <?php foreach ($shipments as $s): ?>
                                    <div class="shipment-box mt-3"><strong><?= e($s['courier']) ?></strong><?php if ($s['tracking_number']): ?><br><?= e(__('track.tracking_number')) ?> <span dir="ltr"><?= e($s['tracking_number']) ?></span><?php endif; ?></div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="card-bwa mb-4">
                            <div class="card-header"><?= e(__('checkout.payment')) ?></div>
                            <div class="card-body small">
                                <?= e($method['name'] ?? '') ?><br>
                                <span class="badge-status mt-2 d-inline-block <?= $order['payment_status'] === 'paid' ? 'is-success' : '' ?>"><?= e(__('account.payment_' . $order['payment_status'])) ?></span>
                                <?php if ($invoices !== []): ?>
                                    <div class="mt-3">
                                        <?php foreach ($invoices as $inv): ?>
                                            <?php if (app()->router()->has('customer.invoices.download')): ?>
                                                <a class="d-block" href="<?= e(route('customer.invoices.download', ['id' => $inv['id']])) ?>"><i class="bi bi-file-earmark-pdf me-1"></i><?= e($inv['invoice_number']) ?></a>
                                            <?php else: ?>
                                                <span class="d-block"><i class="bi bi-file-earmark-text me-1"></i><?= e($inv['invoice_number']) ?></span>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted-bwa mt-2 mb-0"><?= e(__('account.invoice_after_payment')) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($cancellable): ?>
                            <div class="card-bwa">
                                <div class="card-body">
                                    <form method="post" action="<?= e(route('customer.orders.cancel', ['number' => $order['order_number']])) ?>" data-confirm="<?= e(__('account.confirm_cancel')) ?>">
                                        <?= csrf_field() ?>
                                        <label class="form-label small" for="reason"><?= e(__('account.cancel_reason')) ?></label>
                                        <textarea class="form-control mb-2" id="reason" name="reason" rows="2" maxlength="500"></textarea>
                                        <button class="btn btn-outline-danger btn-sm w-100" type="submit"><?= e(__('account.cancel_order')) ?></button>
                                    </form>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
