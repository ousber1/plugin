<?php
$this->layout('layouts/admin', ['title' => $q['quotation_number']]);
$manage = can('quotations.manage');
$editable = in_array($q['status'], ['draft', 'sent'], true);
$share = \App\Core\Session::getFlash('share_url');
$expired = $q['valid_until'] < date('Y-m-d');
$qty = fn ($v) => rtrim(rtrim((string) $v, '0'), '.');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <a class="small text-muted-bwa" href="<?= e(route('admin.quotations.index')) ?>"><i class="bi bi-arrow-<?= is_rtl() ? 'right' : 'left' ?> me-1"></i><?= e(__('admin.nav_quotations')) ?></a>
        <div class="d-flex align-items-center gap-2 mt-2">
            <span class="badge-status <?= status_class(in_array($q['status'], ['accepted', 'converted'], true) ? 'approved' : (in_array($q['status'], ['rejected', 'expired'], true) ? 'cancelled' : $q['status'])) ?>"><?= e(__('finance.qs_' . $q['status'])) ?></span>
            <span class="small text-muted-bwa"><?= e(format_date($q['issue_date'])) ?> → <span class="<?= $expired && in_array($q['status'], ['draft', 'sent'], true) ? 'text-danger' : '' ?>"><?= e(format_date($q['valid_until'])) ?></span></span>
        </div>
    </div>
    <div class="page-actions">
        <a class="btn btn-sm btn-outline-bwa" href="<?= e(route('admin.quotations.pdf', ['id' => $q['id']])) ?>" target="_blank" rel="noopener"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</a>
        <?php if ($manage && $editable): ?><a class="btn btn-sm btn-outline-bwa" href="<?= e(route('admin.quotations.edit', ['id' => $q['id']])) ?>"><i class="bi bi-pencil me-1"></i><?= e(__('common.edit')) ?></a><?php endif; ?>
        <?php if ($manage): ?><form method="post" action="<?= e(route('admin.quotations.duplicate', ['id' => $q['id']])) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-outline-bwa" type="submit"><i class="bi bi-copy me-1"></i><?= e(__('admin.duplicate')) ?></button></form><?php endif; ?>
    </div>
</div>
<?php if ($share): ?><div class="alert alert-info"><i class="bi bi-link-45deg me-1"></i><?= e(__('finance.share_link')) ?><input class="form-control form-control-sm mt-2" value="<?= e((string) $share) ?>" readonly dir="ltr" data-select-on-focus></div><?php endif; ?>
<div class="row g-4">
    <div class="col-xl-8">
        <div class="admin-card mb-4">
            <div class="card-header"><h2><?= e($q['customer_company'] ?: $q['customer_name']) ?></h2><span class="small text-muted-bwa"><?= e((string) $q['customer_email']) ?> <?= $q['customer_phone'] ? '· <span dir="ltr">' . e($q['customer_phone']) . '</span>' : '' ?></span></div>
            <div class="table-responsive"><table class="table table-bwa">
                <thead><tr><th><?= e(__('pdf.description')) ?></th><th class="text-end"><?= e(__('pdf.qty')) ?></th><th class="text-end"><?= e(__('pdf.unit_ht')) ?></th><th class="text-end"><?= e(__('inventory.vat')) ?></th><th class="text-end"><?= e(__('pdf.total_ht')) ?></th></tr></thead>
                <tbody><?php foreach ($lines as $l): ?>
                    <tr><td><?= e($l['description']) ?><?= $l['material_name'] ? '<div class="small text-muted-bwa">' . e($l['material_name']) . '</div>' : '' ?><?= (float) $l['discount_amount'] > 0 ? '<div class="small text-muted-bwa">' . e(__('pdf.line_discount')) . ' −' . e(money($l['discount_amount'])) . '</div>' : '' ?></td>
                        <td class="text-end"><?= e($qty($l['quantity'])) ?></td><td class="text-end text-nowrap"><?= e(money($l['unit_price'])) ?></td><td class="text-end"><?= e($qty($l['tax_rate'])) ?> %</td><td class="text-end text-nowrap"><?= e(money($l['line_total_ht'])) ?></td></tr>
                <?php endforeach; ?></tbody>
            </table></div>
            <div class="card-body border-top"><div class="ms-auto" style="max-width:320px">
                <div class="stat-line"><span><?= e(__('pdf.total_ht')) ?></span><span><?= e(money(\App\Helpers\Money::sub((string) $q['subtotal'], (string) $q['discount_total']))) ?></span></div>
                <div class="stat-line"><span><?= e((string) setting('tax_label', 'TVA')) ?></span><span><?= e(money($q['tax_total'])) ?></span></div>
                <div class="stat-line fw-bold"><span><?= e(__('pdf.total_ttc')) ?></span><span><?= e(money($q['total'])) ?></span></div>
            </div></div>
        </div>
        <?php if ($q['terms']): ?><div class="admin-card mb-4"><div class="card-header"><h2><?= e(__('pdf.conditions')) ?></h2></div><div class="card-body small" style="white-space:pre-line"><?= e($q['terms']) ?></div></div><?php endif; ?>
        <?php if ($q['notes']): ?><div class="admin-card"><div class="card-header"><h2><?= e(__('admin.internal_notes')) ?></h2></div><div class="card-body small" style="white-space:pre-line"><?= e($q['notes']) ?></div></div><?php endif; ?>
    </div>
    <div class="col-xl-4">
        <?php if ($order): ?>
            <div class="alert alert-success"><i class="bi bi-check2-circle me-1"></i><?= e(__('finance.converted_to')) ?> <a href="<?= e(route('admin.orders.show', ['id' => $order['id']])) ?>"><?= e($order['order_number']) ?></a></div>
        <?php endif; ?>
        <?php if ($manage && in_array($q['status'], ['draft', 'sent'], true)): ?>
            <form method="post" action="<?= e(route('admin.quotations.send', ['id' => $q['id']])) ?>" class="admin-card mb-4">
                <?= csrf_field() ?>
                <div class="card-header"><h2><?= e($q['status'] === 'sent' ? __('finance.resend_quote') : __('finance.send_quote')) ?></h2></div>
                <div class="card-body">
                    <?php if ($expired): ?><p class="small text-danger"><?= e(__('finance.err_quote_expired')) ?></p><?php endif; ?>
                    <label class="form-label small" for="message"><?= e(__('artwork.proof_message')) ?></label>
                    <textarea class="form-control mb-2" id="message" name="message" rows="2" maxlength="2000"></textarea>
                    <button class="btn btn-bwa w-100" type="submit" <?= $expired ? 'disabled' : '' ?>><i class="bi bi-send me-1"></i><?= e($q['customer_email'] ? __('finance.send_email') : __('finance.make_link')) ?></button>
                    <div class="form-text"><?= e(__('finance.send_help')) ?></div>
                </div>
            </form>
        <?php endif; ?>
        <?php if ($manage && in_array($q['status'], ['sent', 'accepted'], true) && can('orders.create')): ?>
            <form method="post" action="<?= e(route('admin.quotations.convert', ['id' => $q['id']])) ?>" class="admin-card mb-4" novalidate>
                <?= csrf_field() ?>
                <div class="card-header"><h2><?= e(__('finance.convert')) ?></h2></div>
                <div class="card-body">
                    <?= $this->insert('partials/field', ['name' => 'address', 'label' => __('checkout.address'), 'value' => (string) ($customer && $q['customer_address'] ? $q['customer_address'] : $q['customer_address']), 'required' => true]) ?>
                    <div class="row g-2">
                        <div class="col-6"><?= $this->insert('partials/field', ['name' => 'city', 'label' => __('checkout.city'), 'required' => true]) ?></div>
                        <div class="col-6"><?= $this->insert('partials/field', ['name' => 'phone', 'label' => __('auth.phone'), 'value' => (string) $q['customer_phone'], 'required' => true, 'attrs' => 'dir="ltr"']) ?></div>
                    </div>
                    <?= $this->insert('partials/field', ['name' => 'payment_method_id', 'label' => __('orders.payment_method'), 'type' => 'select', 'options' => ['' => '—'] + array_column($methods, 'name', 'id')]) ?>
                    <button class="btn btn-outline-bwa w-100" type="submit"><i class="bi bi-arrow-right-circle me-1"></i><?= e(__('finance.convert_btn')) ?></button>
                    <div class="form-text"><?= e(__('finance.convert_help')) ?></div>
                </div>
            </form>
        <?php endif; ?>
        <?php if ($manage && !in_array($q['status'], ['converted', 'cancelled'], true)): ?>
            <div class="admin-card"><div class="card-header"><h2><?= e(__('finance.customer_answer')) ?></h2></div><div class="card-body d-flex flex-wrap gap-2">
                <?php foreach (['accepted' => 'btn-outline-bwa', 'rejected' => 'btn-outline-bwa', 'cancelled' => 'btn-outline-danger'] as $st => $cls): if ($st === $q['status']) { continue; } ?>
                    <form method="post" action="<?= e(route('admin.quotations.status', ['id' => $q['id']])) ?>" <?= $st === 'cancelled' ? 'data-confirm="' . e(__('finance.cancel_quote_confirm')) . '"' : '' ?>><?= csrf_field() ?><input type="hidden" name="status" value="<?= $st ?>"><button class="btn btn-sm <?= $cls ?>" type="submit"><?= e(__('finance.mark_' . $st)) ?></button></form>
                <?php endforeach; ?>
            </div></div>
        <?php endif; ?>
        <div class="small text-muted-bwa mt-3">
            <?php foreach (['sent_at' => 'finance.sent_on', 'accepted_at' => 'finance.accepted_on', 'rejected_at' => 'finance.rejected_on'] as $col => $key): if ($q[$col]): ?><div><?= e(__($key, ['date' => format_date($q[$col], true)])) ?></div><?php endif; endforeach; ?>
        </div>
    </div>
</div>
