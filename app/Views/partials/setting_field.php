<?php
/**
 * Champ de paramètre généré depuis SettingsSchema.
 * Variables : $key, $label, $type, $opt, $value
 */
$id = 'f_' . $key;
$col = (int) ($opt['col'] ?? 12);
$current = old($key, $value);
?>
<div class="col-md-<?= $col ?>">
<?php if ($type === 'bool'): ?>
  <div class="form-check form-switch mt-md-2">
    <input class="form-check-input" type="checkbox" role="switch" id="<?= e($id) ?>" name="<?= e($key) ?>" value="1" <?= $value ? 'checked' : '' ?>>
    <label class="form-check-label" for="<?= e($id) ?>"><?= e($label) ?></label>
  </div>
<?php elseif ($type === 'image'): ?>
  <label class="form-label" for="<?= e($id) ?>"><?= e($label) ?></label>
  <div class="d-flex align-items-center gap-3 flex-wrap">
    <div class="hoc-image-preview">
      <?php if ($value): ?><img src="<?= e(asset((string) $value)) ?>" alt="<?= e($label) ?>"><?php else: ?><i class="bi bi-image text-body-secondary fs-3"></i><?php endif; ?>
    </div>
    <div class="flex-grow-1">
      <input class="form-control" type="file" id="<?= e($id) ?>" name="<?= e($key) ?>" accept="image/png,image/jpeg,image/webp">
      <?php if (!empty($opt['help'])): ?><div class="form-text"><?= e($opt['help']) ?></div><?php endif; ?>
    </div>
    <?php if ($value): ?>
      <button type="submit" class="btn btn-outline-danger btn-sm" form="remove_<?= e($key) ?>"><i class="bi bi-trash"></i> Supprimer</button>
    <?php endif; ?>
  </div>
<?php else: ?>
  <label class="form-label" for="<?= e($id) ?>"><?= e($label) ?><?= !empty($opt['required']) ? ' *' : '' ?></label>
  <?php if ($type === 'textarea'): ?>
    <textarea class="form-control" id="<?= e($id) ?>" name="<?= e($key) ?>" rows="3" maxlength="<?= (int) ($opt['max'] ?? 1000) ?>"><?= e($current) ?></textarea>
  <?php elseif ($type === 'select'): ?>
    <select class="form-select" id="<?= e($id) ?>" name="<?= e($key) ?>">
      <?php foreach ($opt['options'] as $v => $optLabel): ?>
        <option value="<?= e($v) ?>" <?= (string) $current === (string) $v ? 'selected' : '' ?>><?= e($optLabel) ?></option>
      <?php endforeach; ?>
    </select>
  <?php elseif ($type === 'color'): ?>
    <div class="d-flex gap-2">
      <input class="form-control form-control-color" type="color" id="<?= e($id) ?>" name="<?= e($key) ?>" value="<?= e($current ?: '#ff3d00') ?>">
      <code class="align-self-center"><?= e($current) ?></code>
    </div>
  <?php elseif ($type === 'password'): ?>
    <input class="form-control" type="password" id="<?= e($id) ?>" name="<?= e($key) ?>" autocomplete="new-password"
           placeholder="<?= $value ? '•••••••• (enregistré)' : '' ?>">
  <?php else:
    $inputType = match ($type) { 'email' => 'email', 'url' => 'url', 'phone' => 'tel', 'int', 'decimal' => 'text', default => 'text' };
    $mode = in_array($type, ['int', 'decimal'], true) ? ' inputmode="decimal"' : ''; ?>
    <input class="form-control" type="<?= $inputType ?>" id="<?= e($id) ?>" name="<?= e($key) ?>" value="<?= e($type === 'decimal' ? str_replace('.', ',', (string) $current) : $current) ?>"<?= $mode ?>
           <?= isset($opt['max']) && !in_array($type, ['int', 'decimal'], true) ? 'maxlength="' . (int) $opt['max'] . '"' : '' ?>>
  <?php endif; ?>
  <?php if (!empty($opt['help'])): ?><div class="form-text"><?= e($opt['help']) ?></div><?php endif; ?>
<?php endif; ?>
</div>
