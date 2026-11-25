<?php /** Lien de document invalide ou expiré. */ ?>
<div class="text-center py-5">
  <i class="bi bi-link-45deg display-3 text-body-secondary"></i>
  <h1 class="h4 fw-bold mt-3">Lien invalide ou expiré</h1>
  <p class="text-body-secondary">Ce lien de consultation n'est plus valable. Contactez-nous pour recevoir à nouveau le document.</p>
  <p class="mb-0"><?= e(setting('business_email', '')) ?> · <?= e(setting('business_phone', '')) ?></p>
</div>
