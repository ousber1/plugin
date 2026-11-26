<?php $this->layout('emails/layout', ['subject' => $subject]) ?>
<p><?= e(__('emails.hello', ['name' => (string) $name])) ?></p>
<p><?= e(__('emails.test_text')) ?></p>
