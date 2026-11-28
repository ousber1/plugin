<?php
use App\Services\Sales\OrderStatus;
use App\Services\Sales\ShipmentService;

$this->layout('layouts/admin', ['title' => $o['order_number']]);
$closed = in_array($o['status'], [OrderStatus::SHIPPED, OrderStatus::DELIVERED, OrderStatus::CANCELLED, OrderStatus::RETURNED], true);
$canShip = can('shipments.manage') && in_array($o['status'], ShipmentService::SHIPPABLE, true);
$artPending = 0;
foreach ($art as $imgs) {
    foreach ($imgs as $c) {
        $artPending += $c['approval_status'] !== 'approved' ? 1 : 0;
    }
}
$cod = $method !== null && $method['code'] === 'cod';
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <a class="small text-muted-bwa" href="<?= e(route('admin.orders.index')) ?>"><i class="bi bi-arrow-<?= is_rtl() ? 'right' : 'left' ?> me-1"></i><?= e(__('admin.nav_orders')) ?></a>
        <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
            <span class="badge-status <?= status_class($o['status']) ?>"><?= e(OrderStatus::label($o['status'])) ?></span>
            <span class="badge-status <?= status_class($o['payment_status']) ?>"><?= e(__('account.payment_' . $o['payment_status'])) ?></span>
            <span class="small text-muted-bwa"><?= e(format_date($o['placed_at'], true)) ?> · <?= e(__('orders.source_' . $o['source'])) ?><?= $method ? ' · ' . e($method['name']) : '' ?></span>
        </div>
    </div>
    <div class="page-actions">
        <a class="btn btn-sm btn-outline-bwa" href="<?= e(route('admin.orders.print', ['id' => $o['id']])) ?>" target="_blank" rel="noopener"><i class="bi bi-printer me-1"></i><?= e(__('orders.work_order')) ?></a>
        <?php if ($o['customer_phone']): ?><a class="btn btn-sm btn-outline-bwa" href="<?= e(whatsapp_link((string) $o['customer_phone'], __('orders.wa_message', ['number' => $o['order_number']]))) ?>" target="_blank" rel="noopener"><i class="bi bi-whatsapp me-1"></i>WhatsApp</a><?php endif; ?>
    </div>
</div>

<?php if ($artPending > 0 && !in_array($o['status'], [OrderStatus::CANCELLED, OrderStatus::RETURNED], true)): ?>
    <div class="alert alert-warning d-flex align-items-center gap-2"><i class="bi bi-palette"></i><span><?= e(__('orders.artwork_banner', ['n' => $artPending])) ?></span></div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-xl-8">
        <!-- items -->
        <div class="admin-card mb-4">
            <div class="card-header"><h2><?= e(__('orders.items')) ?></h2></div>
            <div class="table-responsive">
                <table class="table table-bwa">
                    <thead><tr><th></th><th><?= e(__('orders.item')) ?></th><th class="text-end"><?= e(__('orders.qty')) ?></th><th class="text-end"><?= e(__('orders.unit_price')) ?></th><th class="text-end"><?= e(__('admin.total')) ?></th></tr></thead>
                    <tbody>
                    <?php foreach ($items as $it): $isDesign = $it['custom_design_id'] !== null; ?>
                        <tr>
                            <td style="width:64px">
                                <?php if ($isDesign): ?>
                                    <img class="thumb-sm" src="<?= e(route('admin.orders.item_preview', ['id' => $o['id'], 'itemId' => $it['id']])) ?>" alt="" loading="lazy">
                                <?php elseif (!$isDesign && $it['preview_path']): ?>
                                    <img class="thumb-sm" src="<?= e(upload_url($it['preview_path'])) ?>" alt="" loading="lazy">
                                <?php else: ?><span class="thumb-sm"><i class="bi bi-image"></i></span><?php endif; ?>
                            </td>
                            <td>
                                <div class="fw-semibold"><?= e($it['title']) ?></div>
                                <div class="small text-muted-bwa">
                                    <?= e(implode(' · ', array_filter([$it['width_cm'] ? cm($it['width_cm']) . ' × ' . cm($it['height_cm']) . ' cm' : null, $it['material_name'], $it['frame_name'], (int) $it['panels'] > 1 ? __('orders.n_panels', ['n' => (int) $it['panels']]) : null]))) ?>
                                    <?= $it['sku'] ? '<br><code class="small">' . e($it['sku']) . '</code>' : '' ?>
                                </div>
                                <?= $this->insert('partials/personalization', ['options' => $it['options']]) ?>
                                <?php if ($isDesign): $imgs = $art[(int) $it['id']] ?? []; $ok = count(array_filter($imgs, fn ($c) => $c['approval_status'] === 'approved')); ?>
                                    <div class="mt-1 small">
                                        <span class="badge-status <?= $ok === count($imgs) ? 'is-success' : '' ?>"><i class="bi bi-palette me-1"></i><?= e(__('orders.artwork_n', ['ok' => $ok, 'n' => count($imgs)])) ?></span>
                                        <?php if (can('artwork.view')): ?><a class="ms-2" href="<?= e(route('admin.artwork.show', ['itemId' => $it['id']])) ?>"><?= e(__('orders.review_artwork')) ?></a><?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="text-end"><?= (int) $it['quantity'] ?></td>
                            <td class="text-end text-nowrap"><?= e(money($it['unit_price'])) ?></td>
                            <td class="text-end text-nowrap fw-semibold"><?= e(money($it['line_total'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-body border-top">
                <div class="ms-auto" style="max-width:340px">
                    <div class="stat-line"><span><?= e(__('cart.subtotal')) ?></span><span><?= e(money($o['subtotal'])) ?></span></div>
                    <?php if ((float) $o['discount_total'] > 0): ?><div class="stat-line"><span><?= e(__('cart.discount')) ?><?= $o['coupon_code'] ? ' (' . e($o['coupon_code']) . ')' : '' ?></span><span>− <?= e(money($o['discount_total'])) ?></span></div><?php endif; ?>
                    <div class="stat-line"><span><?= e(__('cart.shipping')) ?></span><span><?= e(money($o['shipping_total'])) ?></span></div>
                    <div class="stat-line"><span><?= e(__('orders.tax')) ?></span><span><?= e(money($o['tax_total'])) ?></span></div>
                    <div class="stat-line fw-bold"><span><?= e(__('cart.total')) ?></span><span><?= e(money($o['grand_total'])) ?></span></div>
                    <div class="stat-line"><span><?= e(__('orders.paid')) ?></span><span><?= e(money($o['amount_paid'])) ?></span></div>
                    <div class="stat-line"><span><?= e(__('admin.balance_due')) ?></span><span class="<?= (float) $o['balance_due'] > 0 ? 'text-danger fw-semibold' : '' ?>"><?= e(money($o['balance_due'])) ?></span></div>
                    <?php if (can('finance.view') || can('reports.view')): ?><div class="stat-line small text-muted-bwa"><span><?= e(__('orders.cost_estimate')) ?></span><span><?= e(money($o['cost_total'])) ?></span></div><?php endif; ?>
                </div>
            </div>
        </div>

        <!-- production -->
        <?php if ($jobs !== []): ?>
            <div class="admin-card mb-4" id="production">
                <div class="card-header"><h2><?= e(__('admin.nav_production')) ?></h2></div>
                <div class="table-responsive"><table class="table table-bwa">
                    <thead><tr><th><?= e(__('production.job')) ?></th><th><?= e(__('orders.item')) ?></th><th><?= e(__('production.stage')) ?></th><th><?= e(__('admin.status')) ?></th><th><?= e(__('production.deadline')) ?></th></tr></thead>
                    <tbody><?php foreach ($jobs as $j): ?>
                        <tr><td class="text-nowrap"><?php if (can('production.view') || can('production.manage')): ?><a href="<?= e(route('admin.production.show', ['id' => $j['id']])) ?>"><?= e($j['job_number']) ?></a><?php else: ?><?= e($j['job_number']) ?><?php endif; ?></td>
                            <td class="small"><?= e((string) $j['title']) ?></td><td class="small"><?= e(__('production.stage_' . $j['current_stage'])) ?></td>
                            <td><span class="badge-status <?= status_class($j['status']) ?>"><?= e(__('production.job_' . $j['status'])) ?></span></td>
                            <td class="small"><?= $j['deadline_at'] ? e(format_date($j['deadline_at'], true)) : '—' ?></td></tr>
                    <?php endforeach; ?></tbody>
                </table></div>
            </div>
        <?php endif; ?>

        <!-- shipping -->
        <div class="admin-card mb-4" id="shipping">
            <div class="card-header"><h2><?= e(__('orders.shipping')) ?></h2></div>
            <div class="card-body">
                <?php foreach ($shipments as $s): ?>
                    <div class="border rounded p-3 mb-3">
                        <div class="d-flex flex-wrap justify-content-between gap-2">
                            <div><strong><?= e($s['courier']) ?></strong><?= $s['tracking_number'] ? ' · <code>' . e($s['tracking_number']) . '</code>' : '' ?>
                                <?php if ($s['tracking_url']): ?> · <a href="<?= e($s['tracking_url']) ?>" target="_blank" rel="noopener noreferrer"><?= e(__('orders.courier_tracking')) ?></a><?php endif; ?>
                                <div class="small text-muted-bwa"><?= e(__('orders.packages', ['n' => (int) $s['packages']])) ?> · <?= e(__('orders.real_cost')) ?> <?= e(money($s['shipping_cost'])) ?><?= (float) $s['cod_amount'] > 0 ? ' · ' . e(__('orders.cod', ['amount' => money($s['cod_amount'])])) : '' ?><?= $s['shipped_at'] ? ' · ' . e(format_date($s['shipped_at'], true)) : '' ?></div>
                            </div>
                            <span class="badge-status <?= status_class($s['status']) ?>"><?= e(__('orders.ship_' . $s['status'])) ?></span>
                        </div>
                        <?php if (can('shipments.manage') && !in_array($s['status'], ['delivered', 'returned'], true)): ?>
                            <form method="post" action="<?= e(route('admin.orders.shipment.status', ['id' => $o['id'], 'shipmentId' => $s['id']])) ?>" class="d-flex flex-wrap gap-2 align-items-center mt-2">
                                <?= csrf_field() ?>
                                <select class="form-select form-select-sm w-auto" name="status" aria-label="<?= e(__('admin.status')) ?>">
                                    <?php foreach (ShipmentService::STATUSES as $st): ?><option value="<?= $st ?>" <?= $s['status'] === $st ? 'selected' : '' ?>><?= e(__('orders.ship_' . $st)) ?></option><?php endforeach; ?>
                                </select>
                                <label class="form-check small mb-0"><input class="form-check-input" type="checkbox" name="notify" value="1" checked> <?= e(__('orders.notify_customer')) ?></label>
                                <button class="btn btn-sm btn-outline-bwa" type="submit"><?= e(__('common.save')) ?></button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <?php if ($canShip): ?>
                    <form method="post" action="<?= e(route('admin.orders.shipment', ['id' => $o['id']])) ?>" novalidate>
                        <?= csrf_field() ?>
                        <h3 class="h6 fw-semibold"><?= e(__('orders.new_shipment')) ?></h3>
                        <div class="row g-2">
                            <div class="col-md-4"><label class="form-label small" for="courier"><?= e(__('orders.courier')) ?></label><input class="form-control" id="courier" name="courier" list="couriers" required><datalist id="couriers"><?php foreach ($couriers as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist></div>
                            <div class="col-md-4"><label class="form-label small" for="tracking_number"><?= e(__('orders.tracking_number')) ?></label><input class="form-control" id="tracking_number" name="tracking_number" dir="ltr"></div>
                            <div class="col-md-4"><label class="form-label small" for="tracking_url"><?= e(__('orders.tracking_url')) ?></label><input class="form-control" id="tracking_url" name="tracking_url" type="url" placeholder="https://" dir="ltr"></div>
                            <div class="col-4 col-md-3"><label class="form-label small" for="shipping_cost"><?= e(__('orders.real_cost')) ?></label><input class="form-control" id="shipping_cost" name="shipping_cost" inputmode="decimal"></div>
                            <div class="col-4 col-md-3"><label class="form-label small" for="cod_amount"><?= e(__('orders.cod_label')) ?></label><input class="form-control" id="cod_amount" name="cod_amount" inputmode="decimal" value="<?= $cod ? e($o['balance_due']) : '' ?>"></div>
                            <div class="col-4 col-md-2"><label class="form-label small" for="packages"><?= e(__('orders.packages_label')) ?></label><input class="form-control" id="packages" name="packages" type="number" min="1" max="50" value="1"></div>
                            <div class="col-md-4 d-flex flex-column justify-content-end">
                                <label class="form-check small"><input class="form-check-input" type="checkbox" name="mark_shipped" value="1" checked> <?= e(__('orders.mark_shipped')) ?></label>
                                <label class="form-check small"><input class="form-check-input" type="checkbox" name="notify" value="1" checked> <?= e(__('orders.notify_customer')) ?></label>
                            </div>
                        </div>
                        <button class="btn btn-bwa mt-2" type="submit"><i class="bi bi-truck me-1"></i><?= e(__('orders.create_shipment')) ?></button>
                    </form>
                <?php elseif ($shipments === []): ?>
                    <p class="small text-muted-bwa mb-0"><?= e(__('orders.no_shipment_yet')) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- payments -->
        <div class="admin-card mb-4" id="payments">
            <div class="card-header"><h2><?= e(__('orders.payments')) ?></h2></div>
            <div class="card-body">
                <?php if ($payments === []): ?><p class="small text-muted-bwa"><?= e(__('orders.no_payment')) ?></p><?php endif; ?>
                <?php foreach ($payments as $p): ?>
                    <div class="stat-line"><span><code class="small"><?= e($p['payment_number']) ?></code> · <?= e((string) $p['method_name']) ?><?= $p['reference'] ? ' · ' . e($p['reference']) : '' ?> <span class="small text-muted-bwa"><?= $p['paid_at'] ? e(format_date($p['paid_at'], true)) : '' ?></span></span>
                        <span class="<?= $p['payment_type'] === 'refund' ? 'text-danger' : '' ?>"><?= $p['payment_type'] === 'refund' ? '− ' : '' ?><?= e(money($p['amount'])) ?> <span class="badge-status <?= status_class($p['status']) ?>"><?= e($p['status']) ?></span></span></div>
                <?php endforeach; ?>
                <?php if (can('payments.manage') && (float) $o['amount_paid'] > 0): ?>
                    <details class="mt-3">
                        <summary class="small text-danger"><?= e(__('finance.refund')) ?></summary>
                        <form method="post" action="<?= e(route('admin.orders.refund', ['orderId' => $o['id']])) ?>" class="row g-2 align-items-end mt-1" novalidate>
                            <?= csrf_field() ?>
                            <div class="col-md-3"><label class="form-label small" for="refund_amount"><?= e(__('orders.amount')) ?></label><input class="form-control" id="refund_amount" name="amount" inputmode="decimal"></div>
                            <div class="col-md-3"><label class="form-label small" for="refund_method"><?= e(__('orders.payment_method')) ?></label><select class="form-select" id="refund_method" name="payment_method_id"><?php foreach ($methods as $m): ?><option value="<?= (int) $m['id'] ?>"><?= e($m['name']) ?></option><?php endforeach; ?></select></div>
                            <div class="col-md-4"><label class="form-label small" for="refund_reason"><?= e(__('finance.reason')) ?></label><input class="form-control" id="refund_reason" name="reason" maxlength="1000"></div>
                            <div class="col-md-2"><button class="btn btn-outline-danger w-100" type="submit" data-confirm-click="<?= e(__('finance.refund_confirm')) ?>"><?= e(__('finance.refund_btn')) ?></button></div>
                            <div class="col-12 form-text"><?= e(__('finance.refund_help', ['max' => money($o['amount_paid'])])) ?></div>
                        </form>
                    </details>
                <?php endif; ?>
                <?php if (can('payments.manage') && (float) $o['balance_due'] > 0 && $o['status'] !== OrderStatus::CANCELLED): ?>
                    <form method="post" action="<?= e(route('admin.orders.payment', ['id' => $o['id']])) ?>" class="row g-2 align-items-end mt-2" novalidate>
                        <?= csrf_field() ?>
                        <div class="col-md-3"><label class="form-label small" for="amount"><?= e(__('orders.amount')) ?></label><input class="form-control" id="amount" name="amount" inputmode="decimal" value="<?= e($o['balance_due']) ?>" required></div>
                        <div class="col-md-3"><label class="form-label small" for="payment_method_id"><?= e(__('orders.payment_method')) ?></label><select class="form-select" id="payment_method_id" name="payment_method_id"><?php foreach ($methods as $m): ?><option value="<?= (int) $m['id'] ?>" <?= (int) $m['id'] === (int) $o['payment_method_id'] ? 'selected' : '' ?>><?= e($m['name']) ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-3"><label class="form-label small" for="reference"><?= e(__('orders.reference')) ?></label><input class="form-control" id="reference" name="reference" maxlength="120"></div>
                        <div class="col-md-3"><button class="btn btn-outline-bwa w-100" type="submit" data-confirm-click="<?= e(__('orders.payment_confirm')) ?>"><?= e(__('orders.record')) ?></button></div>
                        <div class="col-12 form-text"><?= e(__('orders.payment_help')) ?></div>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- invoices -->
        <?php if (can('invoices.view') || can('invoices.manage')): ?>
            <div class="admin-card mb-4" id="invoices">
                <div class="card-header"><h2><?= e(__('finance.invoices')) ?></h2>
                    <?php if (can('invoices.manage') && $activeInvoice === null && $o['status'] !== OrderStatus::CANCELLED): ?>
                        <form method="post" action="<?= e(route('admin.invoices.from_order', ['orderId' => $o['id']])) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-outline-bwa" type="submit"><i class="bi bi-file-earmark-plus me-1"></i><?= e(__('finance.create_invoice')) ?></button></form>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php foreach ($invoices as $iv): ?>
                        <div class="stat-line"><span><a href="<?= e(route('admin.invoices.show', ['id' => $iv['id']])) ?>"><?= e($iv['invoice_number'] ?: __('finance.draft_n', ['n' => $iv['id']])) ?></a> · <?= e(__($iv['invoice_type'] === 'credit_note' ? 'finance.credit_note' : 'finance.invoice')) ?></span>
                            <span><span class="badge-status <?= status_class($iv['status'] === 'issued' ? 'unpaid' : ($iv['status'] === 'partially_paid' ? 'partial' : $iv['status'])) ?>"><?= e(__('finance.is_' . $iv['status'])) ?></span> <?= $iv['invoice_type'] === 'credit_note' ? '−' : '' ?><?= e(money($iv['total_ttc'])) ?></span></div>
                    <?php endforeach; ?>
                    <?php if ($invoices === []): ?><p class="small text-muted-bwa mb-0"><?= e(__('finance.no_invoice_yet')) ?></p><?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- history -->
        <div class="admin-card" id="history">
            <div class="card-header"><h2><?= e(__('orders.history')) ?></h2></div>
            <div class="card-body">
                <?php foreach ($history as $h): ?>
                    <div class="d-flex gap-3 pb-3 mb-3 border-bottom">
                        <i class="bi bi-circle-fill text-gold small mt-1"></i>
                        <div class="flex-grow-1">
                            <div><strong><?= e(OrderStatus::label($h['to_status'])) ?></strong><?= $h['from_status'] ? ' <span class="small text-muted-bwa">← ' . e(OrderStatus::label($h['from_status'])) . '</span>' : '' ?></div>
                            <?php if ($h['comment']): ?><div class="small" style="white-space:pre-line"><?= e($h['comment']) ?></div><?php endif; ?>
                            <div class="small text-muted-bwa"><?= e(format_date($h['created_at'], true)) ?> · <?= e($h['user_name'] ?? __('orders.by_customer_or_system')) ?><?= (int) $h['customer_notified'] ? ' · <i class="bi bi-envelope-check"></i> ' . e(__('orders.notified')) : '' ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <!-- status -->
        <?php if ((can('orders.status') || can('orders.manage')) && $allowed !== []): ?>
            <div class="admin-card mb-4">
                <div class="card-header"><h2><?= e(__('orders.change_status')) ?></h2></div>
                <form method="post" action="<?= e(route('admin.orders.status', ['id' => $o['id']])) ?>" class="card-body" data-status-form data-cancel-confirm="<?= e(__('orders.cancel_confirm')) ?>">
                    <?= csrf_field() ?>
                    <label class="form-label small" for="status"><?= e(__('orders.next_status')) ?></label>
                    <select class="form-select mb-2" id="status" name="status">
                        <?php foreach ($allowed as $s): ?><option value="<?= e($s) ?>"><?= e(OrderStatus::label($s)) ?></option><?php endforeach; ?>
                    </select>
                    <label class="form-label small" for="comment"><?= e(__('orders.comment')) ?></label>
                    <textarea class="form-control mb-2" id="comment" name="comment" rows="2" maxlength="1000" placeholder="<?= e(__('orders.comment_ph')) ?>"></textarea>
                    <label class="form-check small mb-3"><input class="form-check-input" type="checkbox" name="notify" value="1" <?= $o['customer_email'] ? 'checked' : 'disabled' ?>> <?= e($o['customer_email'] ? __('orders.notify_customer') : __('orders.no_email')) ?></label>
                    <button class="btn btn-bwa w-100" type="submit"><?= e(__('orders.update_status')) ?></button>
                    <?php if ($o['status'] === OrderStatus::APPROVED_FOR_PRODUCTION && $jobs === [] && can('production.manage')): ?>
                        <p class="small text-muted-bwa mt-2 mb-0"><?= e(__('orders.no_jobs')) ?></p>
                    <?php endif; ?>
                </form>
            </div>
        <?php endif; ?>
        <?php if ($o['status'] === OrderStatus::APPROVED_FOR_PRODUCTION && $jobs === [] && can('production.manage')): ?>
            <form method="post" action="<?= e(route('admin.production.create_jobs', ['orderId' => $o['id']])) ?>" class="mb-4"><?= csrf_field() ?><button class="btn btn-outline-bwa w-100" type="submit"><i class="bi bi-printer me-1"></i><?= e(__('production.create_jobs')) ?></button></form>
        <?php endif; ?>

        <!-- customer & delivery -->
        <div class="admin-card mb-4" id="details">
            <div class="card-header"><h2><?= e(__('orders.customer_delivery')) ?></h2><?php if ($o['customer_id'] && (can('customers.view') || can('customers.manage'))): ?><a class="small" href="<?= e(route('admin.customers.show', ['id' => $o['customer_id']])) ?>"><?= e(__('orders.customer_file', ['n' => $customerOrders])) ?></a><?php endif; ?></div>
            <div class="card-body">
                <?php if (can('orders.manage')): ?>
                    <form method="post" action="<?= e(route('admin.orders.update', ['id' => $o['id']])) ?>" novalidate>
                        <?= csrf_field() ?>
                        <?php $ro = $closed ? 'readonly' : ''; ?>
                        <?= $this->insert('partials/field', ['name' => 'full_name', 'label' => __('checkout.full_name'), 'value' => $o['customer_name'], 'attrs' => $ro]) ?>
                        <div class="row g-2">
                            <div class="col-6"><?= $this->insert('partials/field', ['name' => 'phone', 'label' => __('auth.phone'), 'value' => $o['customer_phone'], 'attrs' => $ro . ' dir="ltr"']) ?></div>
                            <div class="col-6"><?= $this->insert('partials/field', ['name' => 'email', 'label' => __('auth.email'), 'value' => (string) $o['customer_email'], 'attrs' => $ro . ' dir="ltr"']) ?></div>
                        </div>
                        <?= $this->insert('partials/field', ['name' => 'address', 'label' => __('checkout.address'), 'value' => $o['shipping_address'], 'attrs' => $ro]) ?>
                        <div class="row g-2">
                            <div class="col-7"><?= $this->insert('partials/field', ['name' => 'city', 'label' => __('checkout.city'), 'value' => $o['shipping_city'], 'attrs' => $ro]) ?></div>
                            <div class="col-5"><?= $this->insert('partials/field', ['name' => 'postal_code', 'label' => __('checkout.postal_code'), 'value' => (string) $o['shipping_postal_code'], 'attrs' => $ro]) ?></div>
                        </div>
                        <?= $this->insert('partials/field', ['name' => 'delivery_instructions', 'label' => __('checkout.instructions'), 'type' => 'textarea', 'rows' => 2, 'value' => (string) $o['delivery_instructions'], 'attrs' => $ro]) ?>
                        <?= $this->insert('partials/field', ['name' => 'internal_notes', 'label' => __('admin.internal_notes'), 'type' => 'textarea', 'rows' => 3, 'value' => (string) $o['internal_notes']]) ?>
                        <?php if ($closed): ?><p class="small text-muted-bwa"><?= e(__('orders.details_locked')) ?></p><?php endif; ?>
                        <button class="btn btn-outline-bwa w-100" type="submit"><?= e(__('common.save')) ?></button>
                    </form>
                <?php else: ?>
                    <p class="mb-1 fw-semibold"><?= e($o['customer_name']) ?></p>
                    <p class="small mb-1" dir="ltr"><?= e($o['customer_phone']) ?></p>
                    <p class="small mb-2"><?= e((string) $o['customer_email']) ?></p>
                    <p class="small mb-0"><?= e($o['shipping_address']) ?><br><?= e($o['shipping_city']) ?> <?= e((string) $o['shipping_postal_code']) ?></p>
                    <?php if ($o['delivery_instructions']): ?><p class="small text-muted-bwa mt-2 mb-0"><?= e($o['delivery_instructions']) ?></p><?php endif; ?>
                <?php endif; ?>
                <?php if ($o['estimated_delivery_min']): ?><p class="small text-muted-bwa mt-3 mb-0"><i class="bi bi-calendar-event me-1"></i><?= e(__('orders.estimated', ['from' => format_date($o['estimated_delivery_min']), 'to' => format_date((string) $o['estimated_delivery_max'])])) ?></p><?php endif; ?>
            </div>
        </div>
        <?php if ($o['customer_notes']): ?>
            <div class="admin-card"><div class="card-header"><h2><?= e(__('orders.customer_note')) ?></h2></div><div class="card-body small" style="white-space:pre-line"><?= e($o['customer_notes']) ?></div></div>
        <?php endif; ?>
    </div>
</div>
