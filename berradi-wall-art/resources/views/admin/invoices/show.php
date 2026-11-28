<?php
use App\Helpers\Money;

$cn = $inv['invoice_type'] === 'credit_note';
$this->layout('layouts/admin', ['title' => $inv['invoice_number'] ?: __('finance.draft_n', ['n' => $inv['id']])]);
$manage = can('invoices.manage');
$draft = $inv['status'] === 'draft';
$qty = fn ($v) => rtrim(rtrim((string) $v, '0'), '.');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <a class="small text-muted-bwa" href="<?= e(route('admin.invoices.index')) ?>"><i class="bi bi-arrow-<?= is_rtl() ? 'right' : 'left' ?> me-1"></i><?= e(__('admin.nav_invoices')) ?></a>
        <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
            <span class="badge-status is-dark"><?= e(__($cn ? 'finance.credit_note' : 'finance.invoice')) ?></span>
            <span class="badge-status <?= status_class($inv['status'] === 'issued' ? 'unpaid' : ($inv['status'] === 'partially_paid' ? 'partial' : $inv['status'])) ?>"><?= e(__('finance.is_' . $inv['status'])) ?></span>
            <?php if ($integrity === true): ?><span class="badge-status is-success" title="<?= e(__('finance.integrity_help')) ?>"><i class="bi bi-shield-check me-1"></i><?= e(__('finance.integrity_ok')) ?></span>
            <?php elseif ($integrity === false): ?><span class="badge-status is-danger"><i class="bi bi-shield-exclamation me-1"></i><?= e(__('finance.integrity_ko')) ?></span><?php endif; ?>
            <?php if ($order): ?><a class="small" href="<?= e(route('admin.orders.show', ['id' => $order['id']])) ?>"><?= e($order['order_number']) ?></a><?php endif; ?>
            <?php if ($original): ?><span class="small text-muted-bwa"><?= e(__('pdf.credits_invoice')) ?> <a href="<?= e(route('admin.invoices.show', ['id' => $original['id']])) ?>"><?= e($original['invoice_number']) ?></a></span><?php endif; ?>
        </div>
    </div>
    <div class="page-actions">
        <?php if ($pdf): ?><a class="btn btn-sm btn-outline-bwa" href="<?= e(route('admin.invoices.pdf', ['id' => $inv['id']])) ?>" target="_blank" rel="noopener"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</a><?php endif; ?>
        <?php if ($manage && $draft): ?>
            <a class="btn btn-sm btn-outline-bwa" href="<?= e(route('admin.invoices.edit', ['id' => $inv['id']])) ?>"><i class="bi bi-pencil me-1"></i><?= e(__('common.edit')) ?></a>
            <form method="post" action="<?= e(route('admin.invoices.destroy', ['id' => $inv['id']])) ?>" data-confirm="<?= e(__('finance.delete_draft_confirm')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button></form>
        <?php endif; ?>
        <?php if ($manage && !$draft && $inv['customer_email']): ?>
            <form method="post" action="<?= e(route('admin.invoices.send', ['id' => $inv['id']])) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-outline-bwa" type="submit"><i class="bi bi-envelope me-1"></i><?= e($inv['sent_at'] ? __('finance.resend') : __('finance.email_customer')) ?></button></form>
        <?php endif; ?>
    </div>
</div>
<?php if ($draft && $missingLegal !== []): ?>
    <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-1"></i><?= e(__('finance.missing_legal')) ?> <?= e(implode(', ', array_map(fn ($k) => __('finance.legal_' . $k), $missingLegal))) ?>. <?php if (can('settings.manage')): ?><a href="<?= e(route('admin.settings', ['tab' => 'company'])) ?>"><?= e(__('admin.manage')) ?></a><?php endif; ?></div>
<?php endif; ?>
<?php if (!$pdf): ?><div class="alert alert-warning"><?= e(__('finance.err_pdf_unavailable')) ?></div><?php endif; ?>
<?php if ($inv['status'] === 'cancelled'): ?><div class="alert alert-danger"><?= e(__('finance.cancelled_by_credit', ['date' => format_date((string) $inv['cancelled_at'], true), 'reason' => (string) $inv['cancel_reason']])) ?></div><?php endif; ?>
<div class="row g-4">
    <div class="col-xl-8">
        <div class="admin-card mb-4">
            <div class="card-header"><h2><?= e($inv['customer_company'] ?: $inv['customer_name']) ?></h2><span class="small text-muted-bwa"><?= $inv['customer_ice'] ? 'ICE ' . e($inv['customer_ice']) . ' · ' : '' ?><?= $inv['issue_date'] ? e(format_date($inv['issue_date'])) : e(__('finance.not_issued')) ?></span></div>
            <div class="table-responsive"><table class="table table-bwa">
                <thead><tr><th><?= e(__('pdf.description')) ?></th><th class="text-end"><?= e(__('pdf.qty')) ?></th><th class="text-end"><?= e(__('pdf.unit_ht')) ?></th><th class="text-end"><?= e(__('inventory.vat')) ?></th><th class="text-end"><?= e(__('pdf.total_ht')) ?></th><th class="text-end"><?= e(__('pdf.total_ttc')) ?></th></tr></thead>
                <tbody><?php foreach ($items as $l): ?>
                    <tr><td><?= e($l['description']) ?><?= $l['specifications'] ? '<div class="small text-muted-bwa">' . e($l['specifications']) . '</div>' : '' ?></td>
                        <td class="text-end"><?= e($qty($l['quantity'])) ?></td><td class="text-end text-nowrap"><?= e(money($l['unit_price_ht'])) ?></td><td class="text-end"><?= e($qty($l['tax_rate'])) ?> %</td>
                        <td class="text-end text-nowrap"><?= e(money($l['total_ht'])) ?></td><td class="text-end text-nowrap"><?= e(money($l['total_ttc'])) ?></td></tr>
                <?php endforeach; ?></tbody>
            </table></div>
            <div class="card-body border-top"><div class="ms-auto" style="max-width:340px">
                <div class="stat-line"><span><?= e(__('pdf.subtotal_ht')) ?></span><span><?= e(money($inv['subtotal_ht'])) ?></span></div>
                <?php if ((float) $inv['discount_total'] > 0): ?><div class="stat-line"><span><?= e(__('pdf.discount_ht')) ?></span><span>−<?= e(money($inv['discount_total'])) ?></span></div><?php endif; ?>
                <?php if ((float) $inv['shipping_ht'] > 0): ?><div class="stat-line"><span><?= e(__('pdf.shipping_ht')) ?></span><span><?= e(money($inv['shipping_ht'])) ?></span></div><?php endif; ?>
                <div class="stat-line"><span><?= e((string) setting('tax_label', 'TVA')) ?></span><span><?= e(money($inv['tax_total'])) ?></span></div>
                <div class="stat-line fw-bold"><span><?= e(__('pdf.total_ttc')) ?></span><span><?= $cn ? '−' : '' ?><?= e(money($inv['total_ttc'])) ?></span></div>
                <?php if (!$cn && !$draft): ?>
                    <?php if (Money::toCents($credited) > 0): ?><div class="stat-line"><span><?= e(__('finance.credited')) ?></span><span>−<?= e(money($credited)) ?></span></div><?php endif; ?>
                    <div class="stat-line"><span><?= e(__('pdf.paid')) ?></span><span><?= e(money($inv['amount_paid'])) ?></span></div>
                    <div class="stat-line"><span><?= e(__('pdf.balance')) ?></span><span class="<?= Money::toCents($balance) > 0 ? 'text-danger fw-semibold' : '' ?>"><?= e(money($balance)) ?></span></div>
                <?php endif; ?>
            </div></div>
        </div>
        <?php if ($creditNotes !== []): ?>
            <div class="admin-card mb-4"><div class="card-header"><h2><?= e(__('finance.credit_notes')) ?></h2></div><div class="card-body">
                <?php foreach ($creditNotes as $c): ?><div class="stat-line"><span><a href="<?= e(route('admin.invoices.show', ['id' => $c['id']])) ?>"><?= e((string) $c['invoice_number']) ?></a> · <?= e(format_date((string) $c['issue_date'])) ?> · <span class="text-muted-bwa"><?= e((string) $c['notes']) ?></span></span><span class="text-danger">−<?= e(money($c['total_ttc'])) ?></span></div><?php endforeach; ?>
            </div></div>
        <?php endif; ?>
        <?php if ($inv['notes'] && !$cn): ?><div class="admin-card mb-4"><div class="card-body small" style="white-space:pre-line"><?= e($inv['notes']) ?></div></div><?php endif; ?>
    </div>
    <div class="col-xl-4">
        <?php if ($manage && $draft): ?>
            <form method="post" action="<?= e(route('admin.invoices.issue', ['id' => $inv['id']])) ?>" class="admin-card mb-4" data-confirm="<?= e(__('finance.issue_confirm')) ?>">
                <?= csrf_field() ?>
                <div class="card-header"><h2><?= e(__('finance.issue_title')) ?></h2></div>
                <div class="card-body">
                    <p class="small text-muted-bwa"><?= e(__('finance.issue_help')) ?></p>
                    <?php if ($inv['customer_email']): ?><label class="form-check small mb-2"><input class="form-check-input" type="checkbox" name="send" value="1" checked> <?= e(__('finance.send_after_issue', ['email' => $inv['customer_email']])) ?></label><?php endif; ?>
                    <button class="btn btn-bwa w-100" type="submit"><i class="bi bi-patch-check me-1"></i><?= e(__('finance.issue_btn')) ?></button>
                </div>
            </form>
        <?php endif; ?>
        <?php if (!$cn && $inv['order_id'] === null && in_array($inv['status'], ['issued', 'partially_paid'], true) && can('payments.manage') && Money::toCents($balance) > 0): ?>
            <form method="post" action="<?= e(route('admin.invoices.payment', ['id' => $inv['id']])) ?>" class="admin-card mb-4" novalidate>
                <?= csrf_field() ?>
                <div class="card-header"><h2><?= e(__('finance.record_payment')) ?></h2></div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-6"><label class="form-label small" for="amount"><?= e(__('orders.amount')) ?></label><input class="form-control" id="amount" name="amount" value="<?= e($balance) ?>" inputmode="decimal"></div>
                        <div class="col-6"><label class="form-label small" for="payment_method_id"><?= e(__('orders.payment_method')) ?></label><select class="form-select" id="payment_method_id" name="payment_method_id"><?php foreach ($methods as $m): ?><option value="<?= (int) $m['id'] ?>"><?= e($m['name']) ?></option><?php endforeach; ?></select></div>
                        <div class="col-12"><label class="form-label small" for="reference"><?= e(__('orders.reference')) ?></label><input class="form-control" id="reference" name="reference" maxlength="120"></div>
                    </div>
                    <button class="btn btn-outline-bwa w-100 mt-2" type="submit"><?= e(__('orders.record')) ?></button>
                </div>
            </form>
        <?php elseif (!$cn && $inv['order_id'] !== null && !$draft): ?>
            <p class="small text-muted-bwa"><i class="bi bi-info-circle me-1"></i><?= e(__('finance.paid_via_order')) ?></p>
        <?php endif; ?>
        <?php if ($payments !== []): ?>
            <div class="admin-card mb-4"><div class="card-header"><h2><?= e(__('orders.payments')) ?></h2></div><div class="card-body">
                <?php foreach ($payments as $p): ?><div class="stat-line small"><span><?= e(format_date((string) $p['paid_at'])) ?> · <?= e((string) $p['method_name']) ?><?= $p['reference'] ? ' · ' . e($p['reference']) : '' ?></span><span><?= $p['payment_type'] === 'refund' ? '−' : '' ?><?= e(money($p['amount'])) ?></span></div><?php endforeach; ?>
            </div></div>
        <?php endif; ?>
        <?php if ($manage && !$cn && in_array($inv['status'], ['issued', 'partially_paid', 'paid'], true)): ?>
            <form method="post" action="<?= e(route('admin.invoices.credit', ['id' => $inv['id']])) ?>" class="admin-card mb-4" data-credit-mode data-confirm="<?= e(__('finance.credit_confirm')) ?>" novalidate>
                <?= csrf_field() ?>
                <div class="card-header"><h2><?= e(__('finance.credit_title')) ?></h2></div>
                <div class="card-body">
                    <p class="small text-muted-bwa"><?= e(__('finance.credit_help')) ?></p>
                    <?php $fullPossible = Money::toCents($credited) === 0; ?>
                    <?php if ($fullPossible): ?><label class="form-check small"><input class="form-check-input" type="radio" name="mode" value="full" checked> <?= e(__('finance.credit_full', ['amount' => money($inv['total_ttc'])])) ?></label><?php endif; ?>
                    <label class="form-check small mb-2"><input class="form-check-input" type="radio" name="mode" value="partial" <?= $fullPossible ? '' : 'checked' ?>> <?= e(__('finance.credit_partial')) ?></label>
                    <input class="form-control mb-2" name="amount" inputmode="decimal" placeholder="<?= e(__('finance.amount_ttc')) ?>" data-credit-amount aria-label="<?= e(__('finance.amount_ttc')) ?>">
                    <label class="form-label small" for="reason"><?= e(__('finance.reason')) ?></label>
                    <input class="form-control mb-2" id="reason" name="reason" maxlength="500" required>
                    <button class="btn btn-outline-danger w-100" type="submit"><?= e(__('finance.credit_btn')) ?></button>
                </div>
            </form>
        <?php endif; ?>
        <div class="small text-muted-bwa">
            <?php if ($inv['issued_at']): ?><div><?= e(__('finance.issued_on', ['date' => format_date((string) $inv['issued_at'], true)])) ?></div><?php endif; ?>
            <?php if ($inv['sent_at']): ?><div><?= e(__('finance.sent_on', ['date' => format_date((string) $inv['sent_at'], true)])) ?></div><?php endif; ?>
            <?php if ($inv['due_date'] && !$cn): ?><div><?= e(__('pdf.due_date')) ?> : <?= e(format_date((string) $inv['due_date'])) ?></div><?php endif; ?>
            <?php if ($inv['integrity_hash']): ?><div class="text-break mt-1"><code class="small">SHA-256 <?= e(substr((string) $inv['integrity_hash'], 0, 16)) ?>…</code></div><?php endif; ?>
        </div>
    </div>
</div>
