<?php /** Profil. Variables : $user, $logins */ $minLen = (int) setting('password_min_length', 10); ?>
<div class="hoc-page-head">
  <div>
    <h1 class="h3 fw-bold mb-1">Mon profil</h1>
    <p class="text-body-secondary mb-0"><?= e($user['role_name']) ?> · identifiant <strong><?= e($user['username']) ?></strong></p>
  </div>
</div>
<div class="row g-4">
  <div class="col-lg-6">
    <form method="post" action="<?= e(url('profile')) ?>" class="card h-100">
      <div class="card-header fw-semibold">Informations personnelles</div>
      <div class="card-body row g-3">
        <?= csrf_field() ?>
        <div class="col-12"><label class="form-label" for="full_name">Nom complet</label>
          <input class="form-control" id="full_name" name="full_name" value="<?= e(old('full_name', $user['full_name'])) ?>" required maxlength="150"></div>
        <div class="col-md-7"><label class="form-label" for="email">E-mail</label>
          <input class="form-control" id="email" name="email" type="email" value="<?= e(old('email', $user['email'])) ?>" required maxlength="190"></div>
        <div class="col-md-5"><label class="form-label" for="phone">Téléphone</label>
          <input class="form-control" id="phone" name="phone" type="tel" value="<?= e(old('phone', $user['phone'])) ?>"></div>
        <div class="col-md-6"><label class="form-label" for="theme">Thème</label>
          <select class="form-select" id="theme" name="theme">
            <?php foreach (['auto' => 'Automatique (système)', 'light' => 'Clair', 'dark' => 'Sombre'] as $k => $l): ?>
              <option value="<?= $k ?>" <?= $user['theme'] === $k ? 'selected' : '' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select></div>
      </div>
      <div class="card-footer bg-transparent text-end"><button class="btn btn-dark" onclick="try{localStorage.removeItem('hoc-theme')}catch(e){}"><i class="bi bi-check2"></i> Enregistrer</button></div>
    </form>
  </div>
  <div class="col-lg-6">
    <form method="post" action="<?= e(url('profile/password')) ?>" class="card h-100" autocomplete="off">
      <div class="card-header fw-semibold">Changer le mot de passe</div>
      <div class="card-body row g-3">
        <?= csrf_field() ?>
        <div class="col-12"><label class="form-label" for="current_password">Mot de passe actuel</label>
          <input class="form-control" id="current_password" name="current_password" type="password" required autocomplete="current-password"></div>
        <div class="col-md-6"><label class="form-label" for="password">Nouveau mot de passe</label>
          <input class="form-control" id="password" name="password" type="password" required minlength="<?= $minLen ?>" autocomplete="new-password"></div>
        <div class="col-md-6"><label class="form-label" for="password_confirm">Confirmation</label>
          <input class="form-control" id="password_confirm" name="password_confirm" type="password" required autocomplete="new-password"></div>
        <div class="col-12 form-text mt-1"><?= $minLen ?> caractères minimum, avec majuscules, minuscules et chiffres.
          Dernière modification : <?= e(format_date($user['password_changed_at'], true)) ?>.</div>
      </div>
      <div class="card-footer bg-transparent text-end"><button class="btn btn-dark"><i class="bi bi-key"></i> Modifier le mot de passe</button></div>
    </form>
  </div>
  <div class="col-12">
    <div class="card">
      <div class="card-header fw-semibold">Dernières connexions</div>
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead><tr><th>Date</th><th>Adresse IP</th><th>Navigateur</th><th>Résultat</th></tr></thead>
          <tbody>
          <?php if (!$logins): ?><tr><td colspan="4" class="text-body-secondary">Aucune connexion enregistrée.</td></tr><?php endif; ?>
          <?php foreach ($logins as $l): ?>
            <tr>
              <td class="text-nowrap"><?= e(format_date($l['created_at'], true)) ?></td>
              <td><code><?= e($l['ip_address']) ?></code></td>
              <td class="small text-body-secondary text-truncate" style="max-width:380px"><?= e($l['user_agent']) ?></td>
              <td><?= $l['success'] ? '<span class="badge text-bg-success">Réussie</span>' : '<span class="badge text-bg-danger">Échouée</span>' ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
