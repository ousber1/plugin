<?php $this->layout('emails/layout', ['subject' => $subject]) ?>
<p><strong><?= e((string) $order['order_number']) ?></strong> — <?= e((string) $order['customer_name']) ?></p>
<p style="white-space:pre-line"><?= e($message) ?></p>
