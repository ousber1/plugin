<?php $this->layout('layouts/storefront', ['seo' => $seo]); ?>
<section class="page-hero">
    <div class="container">
        <span class="eyebrow d-block"><?= e(__('track.eyebrow')) ?></span>
        <h1 class="page-title mt-2"><?= e(__('track.title')) ?></h1>
        <p class="page-lead"><?= e(__('track.intro')) ?></p>
    </div>
</section>
<section class="section-sm">
    <div class="container" style="max-width:860px">
        <?php if ($order === null): ?>
            <div class="card-bwa">
                <div class="card-body p-4">
                    <form method="post" action="<?= e(route('order.track.lookup')) ?>" novalidate>
                        <?= csrf_field() ?>
                        <div class="row">
                            <div class="col-md-5"><?= $this->insert('partials/field', ['name' => 'number', 'label' => __('track.number'), 'required' => true, 'placeholder' => 'BWA-' . date('Y') . '-000123']) ?></div>
                            <div class="col-md-7"><?= $this->insert('partials/field', ['name' => 'contact', 'label' => __('track.contact'), 'required' => true, 'help' => __('track.contact_help')]) ?></div>
                        </div>
                        <button class="btn btn-bwa" type="submit"><?= e(__('track.submit')) ?></button>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="card-bwa">
                <div class="card-header d-flex flex-wrap justify-content-between gap-2">
                    <span><?= e(__('checkout.order_number', ['number' => (string) $order['order_number']])) ?></span>
                    <span class="small text-muted-bwa"><?= e(__('track.placed_on', ['date' => format_date($order['placed_at'])])) ?></span>
                </div>
                <div class="card-body">
                    <?php if (!empty($paymentResult)): ?>
                        <?php if ($paymentResult === 'ok' && (float) $order['balance_due'] <= 0): ?>
                            <div class="alert alert-success"><i class="bi bi-check-circle me-1"></i><?= e(__('payment.back_paid')) ?></div>
                        <?php elseif ($paymentResult === 'ok'): ?>
                            <div class="alert alert-info"><i class="bi bi-hourglass-split me-1"></i><?= e(__('payment.back_pending')) ?></div>
                        <?php else: ?>
                            <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-1"></i><?= e(__('payment.back_failed')) ?></div>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if (!empty($payUrl)): ?>
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3 p-3 border rounded">
                            <span class="small"><?= e(__('payment.balance_due', ['amount' => money((string) $order['balance_due'])])) ?></span>
                            <a class="btn btn-bwa btn-sm" href="<?= e($payUrl) ?>"><i class="bi bi-credit-card me-1"></i><?= e(__('payment.pay_now')) ?></a>
                        </div>
                    <?php endif; ?>
                    <?= $this->insert('storefront/partials/order_timeline', ['order' => $order]) ?>
                    <?php if (!in_array($order['status'], ['cancelled', 'returned', 'delivered'], true) && $order['estimated_delivery_max']): ?>
                        <p class="small mt-2 mb-0"><i class="bi bi-truck me-1 text-gold"></i><?= e(__('cart.delivery_between', ['min' => format_date($order['estimated_delivery_min']), 'max' => format_date($order['estimated_delivery_max'])])) ?></p>
                    <?php endif; ?>
                    <?php foreach ($shipments as $s): ?>
                        <div class="shipment-box mt-3">
                            <strong><?= e($s['courier']) ?></strong>
                            <?php if ($s['tracking_number']): ?> · <?= e(__('track.tracking_number')) ?> <span dir="ltr"><?= e($s['tracking_number']) ?></span><?php endif; ?>
                            <?php if ($s['tracking_url'] && preg_match('#^https?://#', (string) $s['tracking_url'])): ?> · <a href="<?= e($s['tracking_url']) ?>" target="_blank" rel="noopener noreferrer"><?= e(__('track.courier_link')) ?></a><?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <h2 class="h6 mt-4"><?= e(__('track.history')) ?></h2>
                    <ul class="history-list list-unstyled small mb-0">
                        <?php foreach (array_reverse($history) as $h): ?>
                            <li><span class="text-muted-bwa"><?= e(format_date($h['created_at'], true)) ?></span> — <?= e(\App\Services\Sales\OrderStatus::label((string) $h['to_status'])) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <p class="text-center mt-4"><a href="<?= e(route('page.contact')) ?>" class="btn-link-bwa"><?= e(__('track.question')) ?></a></p>
        <?php endif; ?>
    </div>
</section>
