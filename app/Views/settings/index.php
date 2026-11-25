<?php
/**
 * Paramètres. Variables : $tabs, $tab, $fields, $values (+ $taxes, $sequences, $notificationSettings, $roles)
 */
$genericTabs = ['business', 'currency', 'documents', 'pos', 'stock', 'pdf', 'appearance', 'mail', 'security'];
?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
  <div>
    <h1 class="h3 fw-bold mb-1">Paramètres</h1>
    <p class="text-body-secondary mb-0">Configuration de l'entreprise et du logiciel.</p>
  </div>
</div>

<div class="row g-4">
  <div class="col-lg-3">
    <div class="list-group hoc-settings-nav">
      <?php foreach ($tabs as $slug => [$label, $icon]): ?>
        <a href="<?= e(url('settings/' . $slug)) ?>" class="list-group-item list-group-item-action d-flex align-items-center gap-2<?= $slug === $tab ? ' active' : '' ?>">
          <i class="bi <?= e($icon) ?>"></i><?= e($label) ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="col-lg-9">
    <?php if (in_array($tab, $genericTabs, true)): ?>
      <form method="post" action="<?= e(url('settings/' . $tab)) ?>" enctype="multipart/form-data" class="card">
        <div class="card-header fw-semibold"><?= e($tabs[$tab][0]) ?></div>
        <div class="card-body">
          <?= csrf_field() ?>
          <div class="row g-3">
            <?php foreach ($fields as $key => [$label, $type, $opt]):
              \App\Core\View::partial('setting_field', ['key' => $key, 'label' => $label, 'type' => $type, 'opt' => $opt, 'value' => $values[$key] ?? '']);
            endforeach; ?>
          </div>
        </div>
        <div class="card-footer bg-transparent d-flex justify-content-end gap-2">
          <?php if ($tab === 'mail'): ?>
            <button type="submit" class="btn btn-outline-secondary" form="mailTestForm"><i class="bi bi-send"></i> Envoyer un e-mail de test</button>
          <?php endif; ?>
          <button type="submit" class="btn btn-dark"><i class="bi bi-check2"></i> Enregistrer</button>
        </div>
      </form>
      <?php foreach ($fields as $key => [$label, $type]): if ($type === 'image' && !empty($values[$key])): ?>
        <form method="post" action="<?= e(url('settings/image-remove')) ?>" id="remove_<?= e($key) ?>" data-confirm="Supprimer cette image ?">
          <?= csrf_field() ?><input type="hidden" name="key" value="<?= e($key) ?>">
        </form>
      <?php endif; endforeach; ?>
      <?php if ($tab === 'mail'): ?>
        <form method="post" action="<?= e(url('settings/mail-test')) ?>" id="mailTestForm"><?= csrf_field() ?></form>
        <p class="small text-body-secondary mt-2">L'e-mail de test est envoyé à votre adresse (<?= e(auth_user()['email']) ?>). Enregistrez d'abord vos modifications.</p>
      <?php endif; ?>
    <?php endif; ?>

    <?php if ($tab === 'currency'): ?>
      <div class="card mt-4">
        <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
          Taux de TVA
          <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#taxNew"><i class="bi bi-plus-lg"></i> Ajouter</button>
        </div>
        <div class="collapse" id="taxNew">
          <form method="post" action="<?= e(url('settings/taxes')) ?>" class="card-body border-bottom row g-2 align-items-end">
            <?= csrf_field() ?>
            <div class="col-md-5"><label class="form-label small">Nom</label><input class="form-control" name="name" maxlength="60" required placeholder="Ex. TVA 20 %"></div>
            <div class="col-md-3"><label class="form-label small">Taux (%)</label><input class="form-control" name="rate" inputmode="decimal" required></div>
            <div class="col-md-2"><input type="hidden" name="is_active" value="1"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_default" value="1" id="newDef"><label class="form-check-label small" for="newDef">Par défaut</label></div></div>
            <div class="col-md-2"><button class="btn btn-dark w-100">Ajouter</button></div>
          </form>
        </div>
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead><tr><th>Nom</th><th style="width:120px">Taux %</th><th class="text-center">Actif</th><th class="text-center">Par défaut</th><th class="text-end">Produits</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($taxes as $t): $fid = 'tax' . (int) $t['id']; ?>
              <tr>
                <td><input class="form-control form-control-sm" form="<?= $fid ?>" name="name" value="<?= e($t['name']) ?>" maxlength="60"></td>
                <td><input class="form-control form-control-sm" form="<?= $fid ?>" name="rate" value="<?= e(number_fr($t['rate'], 2)) ?>" inputmode="decimal"></td>
                <td class="text-center"><input class="form-check-input" type="checkbox" form="<?= $fid ?>" name="is_active" value="1" <?= $t['is_active'] ? 'checked' : '' ?>></td>
                <td class="text-center"><input class="form-check-input" type="radio" form="<?= $fid ?>" name="is_default" value="1" <?= (int) $t['id'] === $defaultTaxId ? 'checked' : '' ?>></td>
                <td class="text-end text-body-secondary"><?= (int) $t['used'] ?></td>
                <td class="text-end">
                  <form method="post" action="<?= e(url('settings/taxes')) ?>" id="<?= $fid ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $t['id'] ?>"></form>
                  <button class="btn btn-sm btn-outline-dark" form="<?= $fid ?>"><i class="bi bi-check2"></i></button>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="card-footer bg-transparent small text-body-secondary">Un taux utilisé par des documents n'est jamais supprimé : désactivez-le pour qu'il ne soit plus proposé. Les documents existants conservent leur taux.</div>
      </div>
    <?php endif; ?>

    <?php if ($tab === 'numbering'): ?>
      <form method="post" action="<?= e(url('settings/numbering')) ?>" class="card">
        <div class="card-header fw-semibold">Numérotation des documents</div>
        <?= csrf_field() ?>
        <div class="table-responsive">
          <table class="table align-middle mb-0 hoc-seq-table">
            <thead><tr><th>Document</th><th>Préfixe</th><th>Format</th><th>Chiffres</th><th>Prochain n°</th><th>Remise à zéro</th><th>Aperçu</th></tr></thead>
            <tbody>
            <?php foreach ($sequences as $s): $n = 'seq[' . e($s['doc_type']) . ']'; ?>
              <tr data-seq>
                <td class="fw-semibold small"><?= e($s['label']) ?></td>
                <td><input class="form-control form-control-sm text-uppercase" name="<?= $n ?>[prefix]" value="<?= e($s['prefix']) ?>" maxlength="10" style="width:80px" data-p></td>
                <td><input class="form-control form-control-sm" name="<?= $n ?>[format]" value="<?= e($s['format']) ?>" maxlength="60" style="min-width:190px" data-f></td>
                <td><input class="form-control form-control-sm" type="number" min="1" max="10" name="<?= $n ?>[padding]" value="<?= (int) $s['padding'] ?>" style="width:70px" data-d></td>
                <td><input class="form-control form-control-sm" type="number" min="1" name="<?= $n ?>[next_number]" value="<?= (int) $s['next_number'] ?>" style="width:100px" data-n></td>
                <td>
                  <select class="form-select form-select-sm" name="<?= $n ?>[reset_policy]" style="width:130px">
                    <?php foreach (['yearly' => 'Annuelle', 'monthly' => 'Mensuelle', 'never' => 'Jamais'] as $v => $l): ?>
                      <option value="<?= $v ?>" <?= $s['reset_policy'] === $v ? 'selected' : '' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
                <td><code data-preview><?= e(\App\Core\DocumentNumber::preview($s['doc_type'])) ?></code></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="card-footer bg-transparent d-flex flex-wrap justify-content-between align-items-center gap-2">
          <div class="small text-body-secondary">Jetons : <code>{PREFIX}</code> <code>{YYYY}</code> <code>{YY}</code> <code>{MM}</code> <code>{SEQ}</code> — <code>{SEQ}</code> est obligatoire.
            Le prochain numéro ne peut pas être abaissé (évite les doublons).</div>
          <button class="btn btn-dark"><i class="bi bi-check2"></i> Enregistrer</button>
        </div>
      </form>
      <script>
        document.querySelectorAll('[data-seq]').forEach(function (row) {
          var upd = function () {
            var d = new Date(), p = row.querySelector('[data-p]').value.toUpperCase(), f = row.querySelector('[data-f]').value,
                pad = parseInt(row.querySelector('[data-d]').value, 10) || 1, n = String(parseInt(row.querySelector('[data-n]').value, 10) || 1);
            while (n.length < pad) { n = '0' + n; }
            row.querySelector('[data-preview]').textContent = f.replace('{PREFIX}', p).replace('{YYYY}', d.getFullYear())
              .replace('{YY}', String(d.getFullYear()).slice(2)).replace('{MM}', ('0' + (d.getMonth() + 1)).slice(-2)).replace('{SEQ}', n);
          };
          row.querySelectorAll('input').forEach(function (i) { i.addEventListener('input', upd); });
        });
      </script>
    <?php endif; ?>

    <?php if ($tab === 'templates'):
      $editId = (int) ($_GET['edit'] ?? ($templates[0]['id'] ?? 0));
      $cur = null; foreach ($templates as $t) { if ((int) $t['id'] === $editId) { $cur = $t; } }
      $cur ??= $templates[0] ?? null;
      $labels = ['quotation' => 'Devis', 'invoice' => 'Facture', 'deposit' => 'Facture d\'acompte', 'balance' => 'Facture de solde', 'credit_note' => 'Avoir', 'receipt' => 'Reçu de paiement', 'sales_order' => 'Bon de commande']; ?>
      <div class="card mb-3"><div class="card-header fw-semibold">Modèles de documents</div>
        <div class="card-body pb-2"><p class="small text-body-secondary">Personnalisez le titre, les textes, les conditions et l'apparence de chaque document PDF. Le logo, la signature et le cachet se chargent dans l'onglet « Impression &amp; PDF » ; les coordonnées et mentions légales dans « Entreprise ».</p>
          <div class="d-flex flex-wrap gap-2"><?php foreach ($templates as $t): ?><a class="btn btn-sm <?= $cur && (int) $cur['id'] === (int) $t['id'] ? 'btn-dark' : 'btn-outline-dark' ?>" href="<?= e(url('settings/templates', ['edit' => $t['id']])) ?>"><?= e($labels[$t['doc_type']] ?? $t['name']) ?></a><?php endforeach; ?></div></div></div>
      <?php if ($cur): ?>
      <form method="post" action="<?= e(url('settings/templates')) ?>" class="card"><?= csrf_field() ?><input type="hidden" name="template_id" value="<?= (int) $cur['id'] ?>">
        <div class="card-header fw-semibold"><?= e($labels[$cur['doc_type']] ?? $cur['name']) ?></div>
        <div class="card-body row g-3">
          <div class="col-md-8"><label class="form-label" for="tTitle">Titre imprimé *</label><input class="form-control" id="tTitle" name="title" required maxlength="100" value="<?= e(old('title', $cur['title'])) ?>"></div>
          <div class="col-md-4"><label class="form-label" for="tColor">Couleur principale</label><input class="form-control form-control-color w-100" type="color" id="tColor" name="accent_color" value="<?= e(old('accent_color', $cur['accent_color'])) ?>"></div>
          <div class="col-12"><label class="form-label" for="tHead">Texte d'introduction (sous l'en-tête)</label><textarea class="form-control" id="tHead" name="header_text" rows="2" maxlength="2000"><?= e(old('header_text', $cur['header_text'] ?? '')) ?></textarea></div>
          <div class="col-12"><label class="form-label" for="tTerms">Conditions générales</label><textarea class="form-control" id="tTerms" name="terms" rows="4" maxlength="3000"><?= e(old('terms', $cur['terms'] ?? '')) ?></textarea></div>
          <div class="col-12"><label class="form-label" for="tFoot">Ligne supplémentaire du pied de page</label><input class="form-control" id="tFoot" name="footer_text" maxlength="500" value="<?= e(old('footer_text', $cur['footer_text'] ?? '')) ?>" placeholder="Ex. Merci pour votre confiance !"></div>
          <div class="col-12 d-flex flex-wrap gap-4">
            <?php foreach (['show_logo' => 'Logo', 'show_qr' => 'QR code de vérification', 'show_signature' => 'Signature', 'show_stamp' => 'Cachet'] as $k => $l): ?>
              <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" id="t<?= $k ?>" name="<?= $k ?>" value="1" <?= (int) $cur[$k] ? 'checked' : '' ?>><label class="form-check-label" for="t<?= $k ?>"><?= e($l) ?></label></div>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="card-footer bg-transparent d-flex justify-content-end"><button class="btn btn-dark"><i class="bi bi-check2"></i> Enregistrer le modèle</button></div>
      </form>
      <?php endif; ?>
    <?php endif; ?>

    <?php if ($tab === 'notifications'): ?>
      <form method="post" action="<?= e(url('settings/notifications')) ?>" class="card">
        <div class="card-header fw-semibold">Notifications</div>
        <?= csrf_field() ?>
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead><tr><th>Type</th><th class="text-center">Actif</th><th>Destinataires</th><th>Anticipation (jours)</th></tr></thead>
            <tbody>
            <?php foreach ($notificationSettings as $ns): $n = 'notif[' . e($ns['type']) . ']'; ?>
              <tr>
                <td><?= e($ns['label']) ?></td>
                <td class="text-center"><input class="form-check-input" type="checkbox" name="<?= $n ?>[enabled]" value="1" <?= $ns['is_enabled'] ? 'checked' : '' ?>></td>
                <td>
                  <select class="form-select form-select-sm" name="<?= $n ?>[role]">
                    <option value="0">Tous les utilisateurs</option>
                    <?php foreach ($roles as $r): ?><option value="<?= (int) $r['id'] ?>" <?= (int) $ns['notify_role_id'] === (int) $r['id'] ? 'selected' : '' ?>><?= e($r['name']) ?></option><?php endforeach; ?>
                  </select>
                </td>
                <td><?php if ($ns['days_before'] !== null): ?><input class="form-control form-control-sm" type="number" min="0" max="90" name="<?= $n ?>[days]" value="<?= (int) $ns['days_before'] ?>" style="width:90px"><?php else: ?><span class="text-body-secondary">—</span><?php endif; ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="card-footer bg-transparent d-flex justify-content-between align-items-center">
          <span class="small text-body-secondary">L'administrateur reçoit toujours toutes les notifications.</span>
          <button class="btn btn-dark"><i class="bi bi-check2"></i> Enregistrer</button>
        </div>
      </form>
    <?php endif; ?>
  </div>
</div>
