<?php /** Formulaire de connexion. */ ?>
<div class="d-lg-none hoc-logo-mark mb-3">HO</div>
<h2 class="h3 fw-bold mb-1">Connexion</h2>
<p class="text-body-secondary mb-4">Accédez à votre espace de gestion.</p>

<form method="post" action="<?= e(url('login')) ?>" novalidate autocomplete="on">
  <?= csrf_field() ?>
  <div class="mb-3">
    <label for="identifier" class="form-label">Nom d'utilisateur ou e-mail</label>
    <div class="input-group">
      <span class="input-group-text"><i class="bi bi-person"></i></span>
      <input type="text" class="form-control form-control-lg" id="identifier" name="identifier"
             value="<?= e(old('identifier')) ?>" required autofocus autocomplete="username" maxlength="190">
    </div>
  </div>
  <div class="mb-4">
    <label for="password" class="form-label">Mot de passe</label>
    <div class="input-group">
      <span class="input-group-text"><i class="bi bi-lock"></i></span>
      <input type="password" class="form-control form-control-lg" id="password" name="password"
             required autocomplete="current-password">
      <button class="btn btn-outline-secondary" type="button" id="togglePwd" aria-label="Afficher le mot de passe">
        <i class="bi bi-eye"></i>
      </button>
    </div>
  </div>
  <button type="submit" class="btn btn-dark btn-lg w-100 hoc-btn-primary">
    <i class="bi bi-box-arrow-in-right me-1"></i> Se connecter
  </button>
</form>
<script>
  document.getElementById('togglePwd').addEventListener('click', function () {
    var i = document.getElementById('password');
    i.type = i.type === 'password' ? 'text' : 'password';
    this.firstElementChild.className = i.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
  });
</script>
