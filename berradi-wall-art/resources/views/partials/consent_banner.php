<?php $privacy = (string) setting('track_privacy_url', ''); ?>
<div class="consent-banner" data-consent role="dialog" aria-live="polite" aria-labelledby="consentTitle" hidden>
    <div class="consent-inner">
        <p class="consent-title" id="consentTitle"><i class="bi bi-cookie me-1"></i><?= e(__('consent.title')) ?></p>
        <p class="consent-text"><?= e(__('consent.text')) ?>
            <?php if ($privacy !== '' && preg_match('#^(/|https://)#', $privacy)): ?><a href="<?= e($privacy) ?>"><?= e(__('consent.privacy')) ?></a><?php endif; ?>
        </p>
        <div class="consent-details" data-consent-details hidden>
            <label class="form-check"><input class="form-check-input" type="checkbox" checked disabled><span class="form-check-label"><strong><?= e(__('consent.necessary')) ?></strong> — <?= e(__('consent.necessary_text')) ?></span></label>
            <label class="form-check"><input class="form-check-input" type="checkbox" data-consent-analytics><span class="form-check-label"><strong><?= e(__('consent.analytics')) ?></strong> — <?= e(__('consent.analytics_text')) ?></span></label>
            <label class="form-check"><input class="form-check-input" type="checkbox" data-consent-marketing><span class="form-check-label"><strong><?= e(__('consent.marketing')) ?></strong> — <?= e(__('consent.marketing_text')) ?></span></label>
            <button type="button" class="btn btn-bwa btn-sm mt-2" data-consent-action="save"><?= e(__('consent.save')) ?></button>
        </div>
        <div class="consent-actions">
            <button type="button" class="btn btn-outline-bwa btn-sm" data-consent-action="customize"><?= e(__('consent.customize')) ?></button>
            <button type="button" class="btn btn-outline-bwa btn-sm" data-consent-action="reject"><?= e(__('consent.reject')) ?></button>
            <button type="button" class="btn btn-bwa btn-sm" data-consent-action="accept"><?= e(__('consent.accept')) ?></button>
        </div>
    </div>
</div>
