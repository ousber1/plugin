<?php $this->layout('emails/layout', ['subject' => $subject]) ?>
<p><?= e(__('emails.hello', ['name' => (string) $order['customer_name']])) ?></p>
<p><?= e(__('emails.order_status_text', ['number' => (string) $order['order_number'], 'status' => \App\Services\Sales\OrderStatus::label((string) $order['status'])])) ?></p>
<?php if ($comment !== ''): ?>
    <p style="background:#F8F7F4;border-left:3px solid #C6A66B;padding:12px 16px;margin:16px 0;white-space:pre-line"><?= e($comment) ?></p>
<?php endif; ?>
<?php if (!empty($shipment)): ?>
    <p><strong><?= e(__('emails.shipment_info')) ?></strong><br>
        <?= e(__('emails.courier')) ?> : <?= e((string) $shipment['courier']) ?>
        <?php if (!empty($shipment['tracking_number'])): ?><br><?= e(__('emails.tracking_number')) ?> : <strong><?= e((string) $shipment['tracking_number']) ?></strong><?php endif; ?>
        <?php if (!empty($shipment['cod_amount']) && (float) $shipment['cod_amount'] > 0): ?><br><?= e(__('emails.cod_amount', ['amount' => money($shipment['cod_amount'])])) ?><?php endif; ?>
    </p>
    <?php if (!empty($shipment['tracking_url'])): ?><p><a href="<?= e((string) $shipment['tracking_url']) ?>" style="color:#8C7140"><?= e(__('emails.courier_tracking')) ?></a></p><?php endif; ?>
<?php endif; ?>
<p><a href="<?= e($trackUrl) ?>" style="display:inline-block;background:#171717;color:#fff;padding:12px 22px;text-decoration:none;letter-spacing:.08em;font-size:13px"><?= e(__('emails.track_order')) ?></a></p>
