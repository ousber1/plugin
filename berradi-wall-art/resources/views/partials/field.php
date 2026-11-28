<?php
/**
 * Reusable form field.
 * Vars: name, label, type='text', value='', required=false, help=null, attrs='', form=null, options=[] (select), col=null, placeholder=''
 */
$type ??= 'text';
$value = old($name, $value ?? '');
$id = $id ?? 'f_' . preg_replace('/[^a-z0-9_]/i', '_', $name);
$err = error($name);
$required ??= false;
$attrs ??= '';
if (!empty($form)) {
    $attrs = 'form="' . e($form) . '" ' . $attrs;
}
?>
<div class="mb-3">
    <?php if ($type !== 'checkbox'): ?>
        <label class="form-label" for="<?= e($id) ?>"><?= e($label) ?><?= $required ? ' <span class="text-gold">*</span>' : '' ?></label>
    <?php endif; ?>
    <?php if ($type === 'textarea'): ?>
        <textarea class="form-control<?= $err ? ' is-invalid' : '' ?>" id="<?= e($id) ?>" name="<?= e($name) ?>" rows="<?= (int) ($rows ?? 3) ?>" <?= $required ? 'required' : '' ?> <?= $attrs ?>><?= e($value) ?></textarea>
    <?php elseif ($type === 'select'): ?>
        <select class="form-select<?= $err ? ' is-invalid' : '' ?>" id="<?= e($id) ?>" name="<?= e($name) ?>" <?= $required ? 'required' : '' ?> <?= $attrs ?>>
            <?php foreach (($options ?? []) as $optValue => $optLabel): ?>
                <option value="<?= e($optValue) ?>" <?= (string) $optValue === (string) $value ? 'selected' : '' ?>><?= e($optLabel) ?></option>
            <?php endforeach; ?>
        </select>
    <?php elseif ($type === 'checkbox'): ?>
        <div class="form-check form-switch">
            <input type="hidden" name="<?= e($name) ?>" value="0"<?= !empty($form) ? ' form="' . e($form) . '"' : '' ?>>
            <input class="form-check-input<?= $err ? ' is-invalid' : '' ?>" type="checkbox" role="switch" id="<?= e($id) ?>" name="<?= e($name) ?>" value="1" <?= in_array($value, [true, 1, '1', 'on'], true) ? 'checked' : '' ?> <?= $attrs ?>>
            <label class="form-check-label" for="<?= e($id) ?>"><?= e($label) ?></label>
        </div>
    <?php elseif ($type === 'password'): ?>
        <div class="password-toggle">
            <input type="password" class="form-control<?= $err ? ' is-invalid' : '' ?>" id="<?= e($id) ?>" name="<?= e($name) ?>" <?= $required ? 'required' : '' ?> autocomplete="<?= e($autocomplete ?? 'current-password') ?>" <?= $attrs ?>>
            <button type="button" class="toggle-btn" data-toggle-password="<?= e($id) ?>" aria-label="<?= e(__('common.show_password')) ?>"><i class="bi bi-eye"></i></button>
        </div>
    <?php else: ?>
        <input type="<?= e($type) ?>" class="form-control<?= $err ? ' is-invalid' : '' ?>" id="<?= e($id) ?>" name="<?= e($name) ?>" value="<?= e($value) ?>" <?= $required ? 'required' : '' ?> <?= !empty($placeholder) ? 'placeholder="' . e($placeholder) . '"' : '' ?> <?= $attrs ?>>
    <?php endif; ?>
    <?php if ($err): ?>
        <div class="invalid-feedback d-block"><?= e($err) ?></div>
    <?php elseif (!empty($help)): ?>
        <div class="form-text"><?= e($help) ?></div>
    <?php endif; ?>
</div>
