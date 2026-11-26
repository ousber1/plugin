<?php $logo = (string) setting('company_logo', ''); ?>
<?php if ($logo !== '' && empty($textOnly)): ?>
    <span class="brand-mark"><img src="<?= e(upload_url($logo)) ?>" alt="<?= e(setting('company_name', 'BERRADI WALL ART')) ?>"></span>
<?php else: ?>
    <span class="brand-mark"><span class="brand-name">BERRADI</span><span class="brand-sub">WALL ART</span></span>
<?php endif; ?>
