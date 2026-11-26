<?php $this->layout('emails/layout', ['subject' => $subject]) ?>
<p><?= e(__('emails.hello', ['name' => (string) $order['customer_name']])) ?></p>
<p><?= e(__('emails.order_thanks', ['number' => (string) $order['order_number']])) ?></p>
<?= $this->insert('emails/_order_lines', ['order' => $order, 'items' => $items]) ?>
<p style="font-size:14px"><strong><?= e(__('checkout.payment')) ?> :</strong> <?= e($method['name'] ?? '') ?></p>
<?php if (($method['code'] ?? '') === 'bank_transfer' && (string) setting('company_rib', '') !== ''): ?>
    <p style="font-size:14px;background:#F4EEE2;padding:12px"><?= e(__('checkout.bank_amount', ['amount' => money((string) $order['grand_total']), 'number' => (string) $order['order_number']])) ?><br>
        <?= e(setting('company_bank_name', '')) ?> — RIB : <strong><?= e(setting('company_rib')) ?></strong></p>
<?php elseif (!empty($method['instructions'])): ?>
    <p style="font-size:14px;background:#F4EEE2;padding:12px"><?= e($method['instructions']) ?></p>
<?php endif; ?>
<p style="font-size:14px"><strong><?= e(__('checkout.delivery')) ?> :</strong> <?= e($order['shipping_address']) ?>, <?= e($order['shipping_city']) ?><br>
    <?= e(__('cart.delivery_between', ['min' => format_date($order['estimated_delivery_min']), 'max' => format_date($order['estimated_delivery_max'])])) ?></p>
<p style="text-align:center;margin:28px 0">
    <a href="<?= e($trackUrl) ?>" style="background:#171717;color:#FFFFFF;padding:14px 28px;text-decoration:none;letter-spacing:2px;font-size:13px;display:inline-block"><?= e(__('checkout.track_order')) ?></a>
</p>
<p style="font-size:13px;color:#6F6A62"><?= e(__('emails.order_next')) ?></p>
