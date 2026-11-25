<?php /** Rôle + matrice de permissions. Variables : $role, $permissions, $granted, $modules */
$isAdmin = ($role['code'] ?? '') === 'admin'; ?>
<div class="hoc-page-head">
  <div>
    <h1 class="h3 fw-bold mb-1"><?= e($title) ?></h1>
    <p class="text-body-secondary mb-0"><?= $isAdmin ? 'Le rôle Administrateur dispose de toutes les permissions : elles ne sont pas modifiables.' : 'Cochez les actions autorisées pour ce rôle.' ?></p>
  </div>
  <a href="<?= e(url('roles')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Retour</a>
</div>
<form method="post" action="<?= e(url($role ? 'roles/' . $role['id'] : 'roles')) ?>">
  <?= csrf_field() ?>
  <div class="card mb-4"><div class="card-body row g-3">
    <div class="col-md-5"><label class="form-label" for="name">Nom du rôle *</label>
      <input class="form-control" id="name" name="name" value="<?= e(old('name', $role['name'] ?? '')) ?>" required maxlength="100"></div>
    <div class="col-md-7"><label class="form-label" for="description">Description</label>
      <input class="form-control" id="description" name="description" value="<?= e(old('description', $role['description'] ?? '')) ?>" maxlength="255"></div>
  </div></div>

  <div class="d-flex justify-content-between align-items-center mb-2">
    <h2 class="h5 fw-bold mb-0">Permissions</h2>
    <?php if (!$isAdmin): ?>
      <div class="btn-group btn-group-sm">
        <button type="button" class="btn btn-outline-secondary" data-check-all="1">Tout cocher</button>
        <button type="button" class="btn btn-outline-secondary" data-check-all="0">Tout décocher</button>
      </div>
    <?php endif; ?>
  </div>
  <div class="row g-3">
    <?php foreach ($permissions as $module => $perms): ?>
      <div class="col-md-6 col-xl-4">
        <div class="hoc-perm-module" data-module>
          <h3><?= e($modules[$module] ?? $module) ?>
            <?php if (!$isAdmin): ?><button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" data-toggle-module>Tout</button><?php endif; ?></h3>
          <?php foreach ($perms as $p): $pid = (int) $p['id']; ?>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="permissions[]" value="<?= $pid ?>" id="p<?= $pid ?>"
                <?= $isAdmin || in_array($pid, $granted, true) ? 'checked' : '' ?> <?= $isAdmin ? 'disabled' : '' ?>>
              <label class="form-check-label small" for="p<?= $pid ?>"><?= e($p['name']) ?> <code class="small text-body-secondary"><?= e($p['code']) ?></code></label>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="d-flex justify-content-end gap-2 mt-4">
    <a href="<?= e(url('roles')) ?>" class="btn btn-outline-secondary">Annuler</a>
    <button class="btn btn-dark"><i class="bi bi-check2"></i> Enregistrer</button>
  </div>
</form>
<script>
  document.querySelectorAll('[data-check-all]').forEach(function (b) {
    b.addEventListener('click', function () {
      var on = b.dataset.checkAll === '1';
      document.querySelectorAll('input[name="permissions[]"]').forEach(function (c) { c.checked = on; });
    });
  });
  document.querySelectorAll('[data-toggle-module]').forEach(function (b) {
    b.addEventListener('click', function () {
      var boxes = b.closest('[data-module]').querySelectorAll('input[type=checkbox]');
      var all = Array.prototype.every.call(boxes, function (c) { return c.checked; });
      boxes.forEach(function (c) { c.checked = !all; });
    });
  });
</script>
