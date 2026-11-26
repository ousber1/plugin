<?php $this->layout('emails/layout', ['subject' => $subject]) ?>
<p><strong><?= e(__('emails.order_admin_intro', ['number' => (string) $order['order_number']])) ?></strong></p>
<p style="font-size:14px"><?= e($order['customer_name']) ?> · <?= e($order['customer_phone']) ?><?= $order['customer_email'] ? ' · ' . e($order['customer_email']) : '' ?><br>
    <?= e($order['shipping_address']) ?>, <?= e($order['shipping_city']) ?><br>
    <?= e(__('checkout.payment')) ?> : <?= e($method['name'] ?? '') ?></p>
<?= $this->insert('emails/_order_lines', ['order' => $order, 'items' => $items]) ?>
<?php if (!empty($order['customer_notes'])): ?><p style="font-size:14px"><em><?= e($order['customer_notes']) ?></em></p><?php endif; ?>
<p style="text-align:center;margin:24px 0"><a href="<?= e($adminUrl) ?>" style="background:#C6A66B;color:#171717;padding:12px 24px;text-decoration:none;font-size:13px;display:inline-block"><?= e(__('emails.open_admin')) ?></a></p>
