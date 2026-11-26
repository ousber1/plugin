<?php
$this->layout('layouts/storefront', ['seo' => $seo]);
$phone = (string) setting('company_phone', '');
$email = (string) setting('company_email', '');
$address = trim((string) setting('company_address', '') . ', ' . (string) setting('company_city', ''), ', ');
$wa = whatsapp_link(null, __('common.whatsapp_greeting'));
?>
<section class="page-hero">
    <div class="container">
        <?= $this->insert('storefront/partials/breadcrumbs', ['crumbs' => [[__('nav.home'), '/'], [__('nav.contact'), null]]]) ?>
        <span class="eyebrow d-block mt-4"><?= e(__('pages.contact_eyebrow')) ?></span>
        <h1 class="page-title mt-2"><?= e(__('nav.contact')) ?></h1>
        <p class="page-lead"><?= e(__('pages.contact_intro')) ?></p>
    </div>
</section>
<section class="section-sm">
    <div class="container">
        <div class="row g-4 g-lg-5">
            <div class="col-lg-4">
                <div class="contact-cards">
                    <?php if ($wa !== ''): ?>
                        <a class="contact-card" href="<?= e($wa) ?>" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i><span><strong>WhatsApp</strong><small><?= e(__('pages.whatsapp_fast')) ?></small></span></a>
                    <?php endif; ?>
                    <?php if ($phone !== ''): ?>
                        <a class="contact-card" href="tel:<?= e(preg_replace('/[^0-9+]/', '', $phone)) ?>"><i class="bi bi-telephone"></i><span><strong><?= e(__('auth.phone')) ?></strong><small dir="ltr"><?= e($phone) ?></small></span></a>
                    <?php endif; ?>
                    <?php if ($email !== ''): ?>
                        <a class="contact-card" href="mailto:<?= e($email) ?>"><i class="bi bi-envelope"></i><span><strong><?= e(__('auth.email')) ?></strong><small><?= e($email) ?></small></span></a>
                    <?php endif; ?>
                    <?php if ($address !== ''): ?>
                        <div class="contact-card"><i class="bi bi-geo-alt"></i><span><strong><?= e(__('pages.address')) ?></strong><small><?= e($address) ?></small></span></div>
                    <?php endif; ?>
                    <div class="contact-card"><i class="bi bi-clock"></i><span><strong><?= e(__('pages.hours')) ?></strong><small><?= nl2br(e($hours)) ?></small></span></div>
                </div>
            </div>
            <div class="col-lg-8">
                <div class="card-bwa">
                    <div class="card-body p-4 p-md-5">
                        <h2 class="h3 mb-4"><?= e(__('pages.write_us')) ?></h2>
                        <form method="post" action="<?= e(route('page.contact.send')) ?>" novalidate data-once>
                            <?= csrf_field() ?>
                            <div class="hp-field" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
                            <div class="row">
                                <div class="col-md-6"><?= $this->insert('partials/field', ['name' => 'name', 'label' => __('pages.your_name'), 'required' => true, 'attrs' => 'autocomplete="name"', 'value' => auth_customer() ? trim(auth_customer()['first_name'] . ' ' . auth_customer()['last_name']) : '']) ?></div>
                                <div class="col-md-6"><?= $this->insert('partials/field', ['name' => 'email', 'label' => __('auth.email'), 'type' => 'email', 'required' => true, 'attrs' => 'autocomplete="email"', 'value' => auth_customer()['email'] ?? '']) ?></div>
                                <div class="col-md-6"><?= $this->insert('partials/field', ['name' => 'phone', 'label' => __('auth.phone'), 'type' => 'tel', 'attrs' => 'autocomplete="tel"', 'value' => auth_customer()['phone'] ?? '']) ?></div>
                                <div class="col-md-6"><?= $this->insert('partials/field', ['name' => 'subject', 'label' => __('pages.subject'), 'type' => 'select', 'required' => true, 'value' => $orderRef !== '' ? 'order' : 'custom', 'options' => [
                                    'custom' => __('pages.subject_custom'), 'order' => __('pages.subject_order'), 'quote' => __('pages.subject_quote'), 'b2b' => __('pages.subject_b2b'), 'other' => __('pages.subject_other'),
                                ]]) ?></div>
                                <div class="col-12"><?= $this->insert('partials/field', ['name' => 'message', 'label' => __('pages.message'), 'type' => 'textarea', 'rows' => 6, 'required' => true, 'value' => $orderRef !== '' ? __('pages.about_order', ['number' => $orderRef]) . "\n" : '']) ?></div>
                            </div>
                            <button type="submit" class="btn btn-bwa btn-lg"><?= e(__('pages.send')) ?></button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
