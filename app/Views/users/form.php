<?php
/** Création / modification d'un utilisateur. Variables : $user, $roles, $overrides, $permissions, $rolePerms */
$isEdit = $user !== null;
$v = static fn(string $k, $d = '') => old($k, $user[$k] ?? $d);
$modules = \App\Controllers\RoleController::MODULES;
$minLen = (int) setting('password_min_length', 10);
?>
<div class="hoc-page-head">
  <div>
    <h1 class="h3 fw-bold mb-1"><?= e($title) ?></h1>
    <p class="text-body-secondary mb-0"><?= $isEdit ? e($user['full_name']) . ' · ' . e($user['role_name']) : 'Créez un accès pour un membre de l\'équipe.' ?></p>
  </div>
  <a href="<?= e(url('users')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Retour</a>
</div>

<form method="post" action="<?= e(url($isEdit ? 'users/' . $user['id'] : 'users')) ?>" autocomplete="off">
  <?= csrf_field() ?>
  <div class="row g-4">
    <div class="col-lg-5">
      <div class="card mb-4">
        <div class="card-header fw-semibold">Informations</div>
        <div class="card-body row g-3">
          <div class="col-12"><label class="form-label" for="full_name">Nom complet *</label>
            <input class="form-control" id="full_name" name="full_name" value="<?= e($v('full_name')) ?>" required maxlength="150"></div>
          <div class="col-md-6"><label class="form-label" for="username">Nom d'utilisateur *</label>
            <input class="form-control" id="username" name="username" value="<?= e($v('username')) ?>" required maxlength="60" pattern="[A-Za-z0-9_.\-]+"></div>
          <div class="col-md-6"><label class="form-label" for="phone">Téléphone</label>
            <input class="form-control" id="phone" name="phone" type="tel" value="<?= e($v('phone')) ?>"></div>
          <div class="col-12"><label class="form-label" for="email">E-mail *</label>
            <input class="form-control" id="email" name="email" type="email" value="<?= e($v('email')) ?>" required maxlength="190"></div>
          <div class="col-12"><label class="form-label" for="role_id">Rôle *</label>
            <select class="form-select" id="role_id" name="role_id">
              <?php foreach ($roles as $r): ?><option value="<?= (int) $r['id'] ?>" <?= (int) $v('role_id', 3) === (int) $r['id'] ? 'selected' : '' ?>><?= e($r['name']) ?></option><?php endforeach; ?>
            </select></div>
          <div class="col-12"><div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?= (int) $v('is_active', 1) ? 'checked' : '' ?>>
            <label class="form-check-label" for="is_active">Compte actif</label></div></div>
          <?php if (!$isEdit): ?>
            <div class="col-md-6"><label class="form-label" for="password">Mot de passe *</label>
              <input class="form-control" id="password" name="password" type="password" required minlength="<?= $minLen ?>" autocomplete="new-password"></div>
            <div class="col-md-6"><label class="form-label" for="password_confirm">Confirmation *</label>
              <input class="form-control" id="password_confirm" name="password_confirm" type="password" required autocomplete="new-password"></div>
            <div class="col-12 form-text mt-0"><?= $minLen ?> caractères minimum, avec majuscules, minuscules et chiffres.</div>
          <?php endif; ?>
        </div>
      </div>
      <?php if ($isEdit): ?>
        <div class="card mb-4 small">
          <div class="card-body">
            <div class="hoc-mini"><span>Créé le</span><span><?= e(format_date($user['created_at'], true)) ?></span></div>
            <div class="hoc-mini"><span>Dernière connexion</span><span><?= $user['last_login_at'] ? e(format_date($user['last_login_at'], true)) . ' (' . e($user['last_login_ip']) . ')' : 'Jamais' ?></span></div>
            <div class="hoc-mini"><span>Mot de passe modifié le</span><span><?= e(format_date($user['password_changed_at'], true)) ?></span></div>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <div class="col-lg-7">
      <div class="card">
        <div class="card-header fw-semibold">Permissions particulières</div>
        <div class="card-body">
          <p class="small text-body-secondary">Par défaut, l'utilisateur hérite des permissions de son rôle (<i class="bi bi-check2 text-success"></i> = accordé par le rôle).
            Vous pouvez accorder ou retirer une permission précise à cet utilisateur uniquement.</p>
          <div class="accordion" id="permAcc">
            <?php foreach ($permissions as $module => $perms): $aid = 'pm_' . e($module); ?>
              <div class="accordion-item">
                <h2 class="accordion-header"><button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $aid ?>">
                  <?= e($modules[$module] ?? $module) ?>
                  <?php $n = count(array_filter($perms, static fn($p) => isset($overrides[(int) $p['id']]))); if ($n): ?><span class="badge text-bg-warning ms-2"><?= $n ?> modifiée(s)</span><?php endif; ?>
                </button></h2>
                <div id="<?= $aid ?>" class="accordion-collapse collapse" data-bs-parent="#permAcc">
                  <div class="accordion-body p-0">
                    <table class="table table-sm align-middle mb-0">
                      <tbody>
                      <?php foreach ($perms as $p): $pid = (int) $p['id']; $mode = isset($overrides[$pid]) ? ($overrides[$pid] ? 'allow' : 'deny') : 'inherit'; ?>
                        <tr>
                          <td class="small"><?= e($p['name']) ?> <?= in_array($pid, $rolePerms, true) ? '<i class="bi bi-check2 text-success" title="Accordé par le rôle"></i>' : '' ?></td>
                          <td class="text-end" style="width:220px">
                            <select class="form-select form-select-sm" name="perm[<?= $pid ?>]" aria-label="<?= e($p['name']) ?>">
                              <option value="inherit" <?= $mode === 'inherit' ? 'selected' : '' ?>>Selon le rôle</option>
                              <option value="allow" <?= $mode === 'allow' ? 'selected' : '' ?>>Toujours autoriser</option>
                              <option value="deny" <?= $mode === 'deny' ? 'selected' : '' ?>>Toujours refuser</option>
                            </select>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
          <p class="small text-body-secondary mt-2 mb-0">Le rôle Administrateur dispose toujours de tous les droits.</p>
        </div>
      </div>
    </div>
  </div>
  <div class="d-flex justify-content-end gap-2 mt-4">
    <a href="<?= e(url('users')) ?>" class="btn btn-outline-secondary">Annuler</a>
    <button class="btn btn-dark"><i class="bi bi-check2"></i> Enregistrer</button>
  </div>
</form>

<?php if ($isEdit): ?>
  <div class="card mt-4" style="max-width: 640px">
    <div class="card-header fw-semibold">Réinitialiser le mot de passe</div>
    <form method="post" action="<?= e(url('users/' . $user['id'] . '/password')) ?>" class="card-body row g-3 align-items-end" autocomplete="off">
      <?= csrf_field() ?>
      <div class="col-md-5"><label class="form-label" for="np">Nouveau mot de passe</label>
        <input class="form-control" id="np" name="password" type="password" required minlength="<?= $minLen ?>" autocomplete="new-password"></div>
      <div class="col-md-4"><label class="form-label" for="npc">Confirmation</label>
        <input class="form-control" id="npc" name="password_confirm" type="password" required autocomplete="new-password"></div>
      <div class="col-md-3"><button class="btn btn-outline-dark w-100">Définir</button></div>
      <div class="col-12 form-text mt-1">Communiquez-le à l'utilisateur de vive voix ; il pourra le changer depuis son profil.</div>
    </form>
  </div>
<?php endif; ?>
