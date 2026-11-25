<?php
/**
 * Fenêtres d'envoi d'un document : e-mail (PDF joint + lien) et WhatsApp (lien sécurisé).
 * Variables : $type, $id, $send (DocumentController::sendDefaults), $reminder (bool, relance facture)
 */
$reminder = $reminder ?? false;
$base = 'documents/' . $type . '/' . $id;
$sfx = $reminder ? 'R' : '';
?>
<div class="modal fade" id="mailModal<?= $sfx ?>" tabindex="-1" aria-labelledby="mailTitle<?= $sfx ?>" aria-hidden="true"><div class="modal-dialog modal-lg"><form class="modal-content" method="post" action="<?= e(url($base . '/email')) ?>">
  <?= csrf_field() ?><?php if ($reminder): ?><input type="hidden" name="reminder" value="1"><?php endif; ?>
  <div class="modal-header"><h2 class="modal-title h5" id="mailTitle<?= $sfx ?>"><i class="bi bi-envelope"></i> <?= $reminder ? 'Relance par e-mail' : 'Envoyer par e-mail' ?></h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
  <div class="modal-body">
    <div class="mb-3"><label class="form-label" for="mTo<?= $sfx ?>">Destinataire *</label><input class="form-control" type="email" id="mTo<?= $sfx ?>" name="to" required value="<?= e($send['email']) ?>" placeholder="client@exemple.ma"></div>
    <div class="mb-3"><label class="form-label" for="mSubj<?= $sfx ?>">Objet *</label><input class="form-control" id="mSubj<?= $sfx ?>" name="subject" required maxlength="200" value="<?= e($send['subject']) ?>"></div>
    <div><label class="form-label" for="mMsg<?= $sfx ?>">Message</label><textarea class="form-control" id="mMsg<?= $sfx ?>" name="message" rows="8" maxlength="5000"><?= e($send['message']) ?></textarea>
      <div class="form-text"><i class="bi bi-paperclip"></i> Le PDF est joint automatiquement, avec un lien de consultation en ligne sécurisé.</div></div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button><button class="btn btn-accent"><i class="bi bi-send"></i> Envoyer</button></div>
</form></div></div>
<div class="modal fade" id="waModal<?= $sfx ?>" tabindex="-1" aria-labelledby="waTitle<?= $sfx ?>" aria-hidden="true"><div class="modal-dialog"><form class="modal-content" method="post" action="<?= e(url($base . '/whatsapp')) ?>" target="_blank" onsubmit="setTimeout(function(){location.reload();},1500)">
  <?= csrf_field() ?><?php if ($reminder): ?><input type="hidden" name="reminder" value="1"><?php endif; ?>
  <div class="modal-header"><h2 class="modal-title h5" id="waTitle<?= $sfx ?>"><i class="bi bi-whatsapp text-success"></i> <?= $reminder ? 'Relance WhatsApp' : 'Envoyer par WhatsApp' ?></h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
  <div class="modal-body">
    <div class="mb-3"><label class="form-label" for="wPhone<?= $sfx ?>">Numéro WhatsApp *</label><input class="form-control" id="wPhone<?= $sfx ?>" name="phone" required value="<?= e($send['phone']) ?>" placeholder="06 12 34 56 78"></div>
    <div><label class="form-label" for="wMsg<?= $sfx ?>">Message</label><textarea class="form-control" id="wMsg<?= $sfx ?>" name="message" rows="6" maxlength="2000"><?= e($send['wa']) ?></textarea>
      <div class="form-text">Le lien sécurisé vers le document (consultation et PDF) est ajouté à la fin du message.</div></div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button><button class="btn btn-success"><i class="bi bi-whatsapp"></i> Ouvrir WhatsApp</button></div>
</form></div></div>
