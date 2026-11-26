<?php $this->layout('emails/layout', ['subject' => $subject]) ?>
<?php if (empty($internal)): ?>
    <p><?= e(__('emails.hello', ['name' => (string) $order['customer_name']])) ?></p>
    <p><?= e(__('emails.order_cancelled_text', ['number' => (string) $order['order_number']])) ?></p>
<?php else: ?>
    <p><strong><?= e(__('emails.order_cancelled_admin', ['number' => (string) $order['order_number'], 'name' => (string) $order['customer_name']])) ?></strong></p>
<?php endif; ?>
<?= $this->insert('emails/_order_lines', ['order' => $order, 'items' => $items]) ?>
