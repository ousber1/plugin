<?php $this->layout('emails/layout', ['subject' => $subject]) ?>
<p><?= e(__('emails.hello', ['name' => (string) $name])) ?></p>
<p><?= e(__('emails.reset_text')) ?></p>
<p style="text-align:center;margin:32px 0">
    <a href="<?= e($link) ?>" style="background:#171717;color:#FFFFFF;padding:14px 28px;text-decoration:none;letter-spacing:2px;font-size:13px;display:inline-block"><?= e(__('auth.reset_button')) ?></a>
</p>
<p style="font-size:13px;color:#6F6A62"><?= e(__('emails.reset_expire', ['minutes' => (int) $minutes])) ?></p>
<p style="font-size:12px;color:#6F6A62;word-break:break-all"><?= e($link) ?></p>
