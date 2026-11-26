<?php $this->layout('emails/layout', ['subject' => $subject]) ?>
<p><?= e(__('emails.hello', ['name' => (string) $order['customer_name']])) ?></p>
<p><?= e(__($invoice['invoice_type'] === 'credit_note' ? 'emails.credit_note_text' : 'emails.invoice_text', ['number' => (string) $invoice['invoice_number'], 'total' => money($invoice['total_ttc'])])) ?></p>
<p><a href="<?= e($url) ?>" style="display:inline-block;background:#171717;color:#fff;padding:12px 22px;text-decoration:none;letter-spacing:.08em;font-size:13px"><?= e(__('emails.invoice_cta')) ?></a></p>
<p style="color:#6F6A62;font-size:13px"><?= e(__('emails.private_link')) ?></p>
