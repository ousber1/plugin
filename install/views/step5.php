<?php /** Étape 5 : compte administrateur principal. */ ?>
<h2 class="h5 fw-bold mb-1">Compte administrateur</h2>
<p class="text-body-secondary">Ce compte dispose de tous les droits. D'autres utilisateurs (manager, caissier, production, designer, stock) pourront être créés ensuite.</p>

<div class="row g-3">
  <div class="col-md-6">
    <label class="form-label" for="full_name">Nom complet *</label>
    <input class="form-control" id="full_name" name="full_name" value="<?= $e($val('admin', 'full_name')) ?>" required autocomplete="name">
  </div>
  <div class="col-md-6">
    <label class="form-label" for="username">Nom d'utilisateur *</label>
    <input class="form-control" id="username" name="username" value="<?= $e($val('admin', 'username', 'admin')) ?>" required autocomplete="username">
  </div>
  <div class="col-md-6">
    <label class="form-label" for="email">E-mail *</label>
    <input class="form-control" id="email" name="email" type="email" value="<?= $e($val('admin', 'email')) ?>" required autocomplete="email">
  </div>
  <div class="col-md-6">
    <label class="form-label" for="phone">Téléphone</label>
    <input class="form-control" id="phone" name="phone" type="tel" value="<?= $e($val('admin', 'phone')) ?>" autocomplete="tel">
  </div>
  <div class="col-md-6">
    <label class="form-label" for="password">Mot de passe *</label>
    <input class="form-control" id="password" name="password" type="password" required autocomplete="new-password" minlength="10">
    <div class="form-text">10 caractères minimum, avec majuscules, minuscules et chiffres.</div>
  </div>
  <div class="col-md-6">
    <label class="form-label" for="password_confirm">Confirmation *</label>
    <input class="form-control" id="password_confirm" name="password_confirm" type="password" required autocomplete="new-password">
  </div>
</div>

<div class="d-flex justify-content-between mt-4">
  <a href="index.php?step=4" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Retour</a>
  <button type="submit" class="btn btn-dark btn-lg">Continuer <i class="bi bi-arrow-right"></i></button>
</div>
