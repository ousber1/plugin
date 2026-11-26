<?php $this->layout('layouts/storefront', ['title' => __('artwork.proof_title'), 'noindex' => true]); ?>
<section class="section">
    <div class="container text-center" style="max-width:640px">
        <i class="bi <?= $approved ? 'bi-check2-circle' : 'bi-chat-dots' ?> text-gold" style="font-size:3rem"></i>
        <h1 class="page-title mt-3"><?= e($approved ? __('artwork.done_approved_title') : __('artwork.done_changes_title')) ?></h1>
        <p class="page-lead"><?= e($approved ? __('artwork.done_approved') : __('artwork.done_changes')) ?></p>
        <a class="btn btn-outline-bwa mt-3" href="<?= e(route('order.track')) ?>"><?= e(__('emails.track_order')) ?></a>
    </div>
</section>
