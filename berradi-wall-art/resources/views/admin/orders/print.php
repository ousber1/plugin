<?php
use App\Services\Sales\OrderStatus;
?><!doctype html>
<html lang="<?= e(locale()) ?>" dir="<?= is_rtl() ? 'rtl' : 'ltr' ?>">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title><?= e(__('orders.work_order')) ?> <?= e($o['order_number']) ?></title>
<style>
    body { font-family: "Inter", system-ui, sans-serif; color: #171717; background: #fff; margin: 0; padding: 24px; font-size: 13px; }
    .sheet { max-width: 820px; margin: 0 auto; }
    header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #171717; padding-bottom: 12px; margin-bottom: 16px; gap: 16px; }
    h1 { font-size: 20px; margin: 0; letter-spacing: .04em; }
    .brand { font-weight: 700; letter-spacing: .2em; }
    .muted { color: #6F6A62; }
    table { width: 100%; border-collapse: collapse; margin: 12px 0; }
    th, td { text-align: start; padding: 8px 6px; border-bottom: 1px solid #E5E1D8; vertical-align: top; }
    th { font-size: 11px; text-transform: uppercase; letter-spacing: .08em; color: #6F6A62; }
    .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    .box { border: 1px solid #E5E1D8; padding: 12px; border-radius: 4px; }
    .check { display: inline-block; width: 14px; height: 14px; border: 1px solid #171717; margin-inline-end: 6px; vertical-align: middle; }
    .toolbar { text-align: end; margin-bottom: 12px; }
    .toolbar button { background: #171717; color: #fff; border: 0; padding: 8px 16px; cursor: pointer; }
    @media print { .toolbar { display: none; } body { padding: 0; } }
    @media (max-width: 600px) { .grid { grid-template-columns: 1fr; } header { flex-direction: column; } }
</style>
</head>
<body>
<div class="sheet">
    <div class="toolbar"><button type="button" data-print><?= e(__('orders.print')) ?></button></div>
    <header>
        <div><div class="brand"><?= e((string) setting('company_name', 'BERRADI WALL ART')) ?></div><div class="muted"><?= e(__('orders.work_order')) ?></div></div>
        <div style="text-align:end"><h1><?= e($o['order_number']) ?></h1><div class="muted"><?= e(format_date($o['placed_at'], true)) ?> · <?= e(OrderStatus::label($o['status'])) ?></div></div>
    </header>
    <div class="grid">
        <div class="box"><strong><?= e(__('orders.deliver_to')) ?></strong><br><?= e($o['customer_name']) ?><br><span dir="ltr"><?= e($o['customer_phone']) ?></span><br><?= e($o['shipping_address']) ?><br><?= e($o['shipping_city']) ?> <?= e((string) $o['shipping_postal_code']) ?><?= $o['delivery_instructions'] ? '<br><em>' . e($o['delivery_instructions']) . '</em>' : '' ?></div>
        <div class="box"><strong><?= e(__('admin.payment')) ?></strong><br><?= e(__('account.payment_' . $o['payment_status'])) ?><br><?= e(__('admin.balance_due')) ?> : <strong><?= e(money($o['balance_due'])) ?></strong>
            <?php foreach ($shipments as $s): ?><br><?= e($s['courier']) ?> <?= e((string) $s['tracking_number']) ?><?php endforeach; ?></div>
    </div>
    <table>
        <thead><tr><th></th><th><?= e(__('orders.item')) ?></th><th><?= e(__('orders.specs')) ?></th><th><?= e(__('orders.qty')) ?></th></tr></thead>
        <tbody>
        <?php foreach ($items as $it): ?>
            <tr><td><span class="check"></span></td><td><strong><?= e($it['title']) ?></strong><br><span class="muted"><?= e((string) $it['sku']) ?></span></td>
                <td><?= $it['width_cm'] ? e(cm($it['width_cm']) . ' × ' . cm($it['height_cm']) . ' cm') . '<br>' : '' ?><?= e((string) $it['material_name']) ?><?= $it['frame_name'] ? '<br>' . e($it['frame_name']) : '' ?><?= (int) $it['panels'] > 1 ? '<br>' . e(__('orders.n_panels', ['n' => (int) $it['panels']])) : '' ?></td>
                <td><strong><?= (int) $it['quantity'] ?></strong></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php if ($o['customer_notes']): ?><div class="box"><strong><?= e(__('orders.customer_note')) ?></strong><br><?= nl2br(e($o['customer_notes'])) ?></div><?php endif; ?>
    <?php if ($o['internal_notes']): ?><div class="box" style="margin-top:12px"><strong><?= e(__('admin.internal_notes')) ?></strong><br><?= nl2br(e($o['internal_notes'])) ?></div><?php endif; ?>
    <p class="muted" style="margin-top:24px"><span class="check"></span><?= e(__('orders.qc_ok')) ?> &nbsp; <span class="check"></span><?= e(__('orders.packed')) ?></p>
</div>
<script src="<?= e(asset('js/print.js')) ?>"></script>
</body>
</html>
