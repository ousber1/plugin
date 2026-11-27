<?php $this->layout('layouts/storefront', ['seo' => $seo]); ?>
<section class="section-sm">
    <div class="container" style="max-width:980px">
        <div class="text-center mb-5">
            <i class="bi bi-check-circle text-gold" style="font-size:3.2rem"></i>
            <h1 class="page-title mt-2"><?= e(__('checkout.thanks_title')) ?></h1>
            <p class="page-lead mx-auto"><?= e(__('checkout.thanks_text', ['number' => (string) $order['order_number']])) ?></p>
        </div>
        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card-bwa mb-4">
                    <div class="card-header"><?= e(__('checkout.next_steps')) ?></div>
                    <div class="card-body">
                        <?php if (!empty($payUrl)): ?>
                            <div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-2">
                                <span><?= e(__('payment.balance_due', ['amount' => money((string) $order['balance_due'])])) ?></span>
                                <a class="btn btn-bwa btn-sm" href="<?= e($payUrl) ?>"><i class="bi bi-credit-card me-1"></i><?= e(__('payment.pay_now')) ?></a>
                            </div>
                        <?php endif; ?>
                        <?php if (($method['code'] ?? '') === 'bank_transfer'): ?>
                            <div class="alert alert-info">
                                <strong><?= e(__('checkout.bank_title')) ?></strong><br>
                                <?= e(__('checkout.bank_amount', ['amount' => money((string) $order['grand_total']), 'number' => (string) $order['order_number']])) ?>
                                <?php if ((string) setting('company_rib', '') !== ''): ?>
                                    <div class="mt-2 small"><?= e(setting('company_bank_name', '')) ?> — RIB : <strong dir="ltr"><?= e(setting('company_rib')) ?></strong><br><?= e(setting('company_legal_name') ?: setting('company_name')) ?></div>
                                <?php else: ?>
                                    <div class="mt-2 small"><?= e(__('checkout.bank_details_follow')) ?></div>
                                <?php endif; ?>
                            </div>
                        <?php elseif (!empty($method['instructions'])): ?>
                            <div class="alert alert-info"><?= e($method['instructions']) ?></div>
                        <?php endif; ?>
                        <ol class="next-steps">
                            <li><?= e(__('checkout.step_call')) ?></li>
                            <?php if (array_filter($items, fn ($i) => !empty($i['custom_design_id']))): ?><li><?= e(__('checkout.step_artwork')) ?></li><?php endif; ?>
                            <li><?= e(__('checkout.step_production', ['min' => format_date($order['estimated_delivery_min']), 'max' => format_date($order['estimated_delivery_max'])])) ?></li>
                        </ol>
                        <div class="d-flex flex-wrap gap-2 mt-3">
                            <a class="btn btn-bwa" href="<?= e($trackUrl) ?>"><i class="bi bi-geo me-1"></i><?= e(__('checkout.track_order')) ?></a>
                            <?php $wa = whatsapp_link(null, __('checkout.whatsapp_order', ['number' => (string) $order['order_number']])); if ($wa !== ''): ?>
                                <a class="btn btn-outline-bwa" href="<?= e($wa) ?>" target="_blank" rel="noopener"><i class="bi bi-whatsapp me-1"></i>WhatsApp</a>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($order['customer_email'])): ?><p class="small text-muted-bwa mt-3 mb-0"><?= e(__('checkout.email_sent', ['email' => (string) $order['customer_email']])) ?></p><?php endif; ?>
                    </div>
                </div>
                <div class="card-bwa">
                    <div class="card-header"><?= e(__('checkout.delivery')) ?></div>
                    <div class="card-body small">
                        <strong><?= e($order['customer_name']) ?></strong> · <span dir="ltr"><?= e($order['customer_phone']) ?></span><br>
                        <?= e($order['shipping_address']) ?>, <?= e($order['shipping_city']) ?> <?= e((string) $order['shipping_postal_code']) ?>
                        <?php if ($order['delivery_instructions']): ?><br><em><?= e($order['delivery_instructions']) ?></em><?php endif; ?>
                        <div class="mt-2"><?= e(__('checkout.payment')) ?> : <?= e($method['name'] ?? '') ?></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="card-bwa summary-card">
                    <div class="card-body">
                        <h2 class="h5"><?= e(__('checkout.order_number', ['number' => (string) $order['order_number']])) ?></h2>
                        <?= $this->insert('storefront/partials/order_items', ['items' => $items]) ?>
                        <?= $this->insert('storefront/partials/order_totals', ['order' => $order]) ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
