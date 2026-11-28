<?php $this->layout('layouts/storefront', ['seo' => $seo]); ?>
<section class="section-sm">
    <div class="container" style="max-width:640px">
        <div class="card-bwa text-center">
            <div class="card-body p-4 p-md-5">
                <i class="bi bi-shield-lock text-gold" style="font-size:2.8rem"></i>
                <h1 class="h3 mt-2"><?= e(__('payment.title')) ?></h1>
                <p class="text-muted-bwa mb-4"><?= e(__('payment.redirect_text', ['bank' => (string) $method['name']])) ?></p>
                <dl class="row small text-start mx-auto mb-4" style="max-width:360px">
                    <dt class="col-6 fw-normal text-muted-bwa"><?= e(__('payment.order')) ?></dt><dd class="col-6 text-end fw-semibold" dir="ltr"><?= e((string) $order['order_number']) ?></dd>
                    <dt class="col-6 fw-normal text-muted-bwa"><?= e(__('payment.amount')) ?></dt><dd class="col-6 text-end fw-semibold"><?= e(money((string) $form['fields']['amount'])) ?></dd>
                </dl>
                <form method="post" action="<?= e($form['action']) ?>" data-autopost>
                    <?php foreach ($form['fields'] as $name => $value): ?>
                        <input type="hidden" name="<?= e($name) ?>" value="<?= e($value) ?>">
                    <?php endforeach; ?>
                    <button class="btn btn-bwa btn-lg w-100" type="submit"><i class="bi bi-credit-card me-2"></i><?= e(__('payment.continue')) ?></button>
                </form>
                <p class="small text-muted-bwa mt-3 mb-1"><i class="bi bi-lock me-1"></i><?= e(__('payment.secure_note')) ?></p>
                <a class="small" href="<?= e($trackUrl) ?>"><?= e(__('payment.later')) ?></a>
            </div>
        </div>
    </div>
</section>
