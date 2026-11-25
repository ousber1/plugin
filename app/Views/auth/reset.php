<?php /** Nouveau mot de passe. Variables : $token, $minLength */ ?>
<div class="d-lg-none hoc-logo-mark mb-3">HO</div>
<h2 class="h3 fw-bold mb-1">Nouveau mot de passe</h2>
<p class="text-body-secondary mb-4"><?= (int) $minLength ?> caractères minimum, avec majuscules, minuscules et chiffres.</p>
<form method="post" action="<?= e(url('password/reset')) ?>" autocomplete="off">
  <?= csrf_field() ?>
  <input type="hidden" name="token" value="<?= e($token) ?>">
  <div class="mb-3">
    <label for="password" class="form-label">Nouveau mot de passe</label>
    <input type="password" class="form-control form-control-lg" id="password" name="password" required minlength="<?= (int) $minLength ?>" autocomplete="new-password" autofocus>
  </div>
  <div class="mb-4">
    <label for="password_confirm" class="form-label">Confirmation</label>
    <input type="password" class="form-control form-control-lg" id="password_confirm" name="password_confirm" required autocomplete="new-password">
  </div>
  <button type="submit" class="btn btn-dark btn-lg w-100"><i class="bi bi-check2 me-1"></i> Enregistrer</button>
</form>
