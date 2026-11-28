<?php
/** Flash alert (toast) + validation summary. */
$alert = \App\Core\Session::getFlash('alert');
$errs = errors();
?>
<div class="toast-stack" role="status" aria-live="polite">
    <?php if (is_array($alert) && !empty($alert['message'])): ?>
        <div class="alert alert-<?= e(in_array($alert['type'] ?? '', ['success', 'danger', 'warning', 'info'], true) ? $alert['type'] : 'info') ?> alert-dismissible mb-2">
            <?= e($alert['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?= e(__('common.close')) ?>"></button>
        </div>
    <?php endif; ?>
    <?php if ($errs !== [] && empty($hideErrorToast)): ?>
        <div class="alert alert-danger alert-dismissible mb-2">
            <?= e(__('validation.failed')) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?= e(__('common.close')) ?>"></button>
        </div>
    <?php endif; ?>
</div>
