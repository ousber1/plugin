<?php $this->layout('emails/layout', ['subject' => $subject]) ?>
<p><?= e(__('emails.hello', ['name' => (string) $order['customer_name']])) ?></p>
<p><?= e(__('emails.artwork_proof_text', ['number' => (string) $order['order_number']])) ?></p>
<?php if ($message !== ''): ?>
    <p style="background:#F8F7F4;border-left:3px solid #C6A66B;padding:12px 16px;margin:16px 0;white-space:pre-line"><?= e($message) ?></p>
<?php endif; ?>
<p><a href="<?= e($proofUrl) ?>" style="display:inline-block;background:#171717;color:#fff;padding:12px 22px;text-decoration:none;letter-spacing:.08em;font-size:13px"><?= e(__('emails.artwork_proof_cta')) ?></a></p>
<p style="color:#6F6A62;font-size:13px"><?= e(__('emails.artwork_proof_note')) ?></p>
