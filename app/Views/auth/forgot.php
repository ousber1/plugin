<?php /** Demande de réinitialisation du mot de passe. */ ?>
<div class="d-lg-none hoc-logo-mark mb-3">HO</div>
<h2 class="h3 fw-bold mb-1">Mot de passe oublié</h2>
<p class="text-body-secondary mb-4">Saisissez l'adresse e-mail de votre compte : vous recevrez un lien pour choisir un nouveau mot de passe.</p>
<form method="post" action="<?= e(url('password/forgot')) ?>" novalidate>
  <?= csrf_field() ?>
  <div class="mb-4">
    <label for="email" class="form-label">Adresse e-mail</label>
    <div class="input-group">
      <span class="input-group-text"><i class="bi bi-envelope"></i></span>
      <input type="email" class="form-control form-control-lg" id="email" name="email" required autofocus autocomplete="email" maxlength="190">
    </div>
  </div>
  <button type="submit" class="btn btn-dark btn-lg w-100"><i class="bi bi-send me-1"></i> Envoyer le lien</button>
</form>
<p class="text-center mt-4 mb-0"><a href="<?= e(url('login')) ?>" class="text-decoration-none"><i class="bi bi-arrow-left"></i> Retour à la connexion</a></p>
<p class="small text-body-secondary text-center mt-3">Pas d'accès à votre e-mail ? Un administrateur peut réinitialiser votre mot de passe.</p>
