<?php /** Caisse. Variables : $session, $summary?, $movements?, $history, $denominations */
$types = \App\Controllers\CashController::MOVEMENT_TYPES; ?>
<div class="hoc-page-head">
  <div><h1 class="h3 fw-bold mb-1">Caisse</h1><p class="text-body-secondary mb-0"><?= $session ? 'Session ' . e($session['number']) . ' ouverte le ' . e(format_date($session['opened_at'], true)) . ' par ' . e($session['opened_by_name'] ?? '') : 'Aucune session ouverte.' ?></p></div>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary" href="<?= e(url('cash/sessions')) ?>"><i class="bi bi-clock-history"></i> Historique</a>
    <?php if ($session): ?><a class="btn btn-outline-secondary" href="<?= e(url('cash/sessions/' . $session['id'])) ?>" target="_blank"><i class="bi bi-printer"></i> Rapport X</a><?php endif; ?>
    <?php if (can('pos.access')): ?><a class="btn btn-accent" href="<?= e(url('pos')) ?>"><i class="bi bi-cart3"></i> Point de vente</a><?php endif; ?>
  </div>
</div>

<?php if (!$session): ?>
  <div class="row g-4">
    <div class="col-lg-5"><?php if (can('cash.open')): ?>
      <form method="post" action="<?= e(url('cash/open')) ?>" class="card"><div class="card-header fw-semibold"><i class="bi bi-unlock"></i> Ouvrir la caisse</div><div class="card-body"><?= csrf_field() ?>
        <label class="form-label" for="opening_balance">Fond de caisse (espèces dans le tiroir)</label>
        <div class="input-group input-group-lg mb-3"><input class="form-control" id="opening_balance" name="opening_balance" inputmode="decimal" value="<?= $history ? e(number_fr($history[0]['counted_balance'])) : '0' ?>" required><span class="input-group-text"><?= e(setting('currency_symbol', 'DH')) ?></span></div>
        <?php if ($history): ?><div class="form-text mb-2">Montant compté à la dernière clôture : <?= e(money($history[0]['counted_balance'])) ?>.</div><?php endif; ?>
        <label class="form-label" for="notes">Note</label><input class="form-control mb-3" id="notes" name="notes" maxlength="500">
        <button class="btn btn-dark btn-lg w-100">Ouvrir la caisse</button></div></form>
    <?php else: ?><div class="alert alert-info">Vous n'êtes pas autorisé à ouvrir la caisse.</div><?php endif; ?></div>
    <div class="col-lg-7"><?php \App\Core\View::partial('cash_history', ['history' => $history]); ?></div>
  </div>
<?php else: $s = $summary; ?>
  <div class="row g-3 mb-4">
    <div class="col-6 col-xl-3"><div class="card hoc-kpi h-100"><div class="card-body"><div class="hoc-kpi-label">Espèces attendues</div><div class="hoc-kpi-value"><?= e(money($s['expected'])) ?></div><div class="hoc-kpi-sub">Fond <?= e(money($session['opening_balance'])) ?> + mouvements espèces</div></div></div></div>
    <div class="col-6 col-xl-3"><div class="card hoc-kpi h-100"><div class="card-body"><div class="hoc-kpi-label">Ventes de la session</div><div class="hoc-kpi-value"><?= e(money($s['sales']['total'])) ?></div><div class="hoc-kpi-sub"><?= (int) $s['sales']['count'] ?> ticket(s)<?= $s['sales']['cancelled'] ? ' · ' . (int) $s['sales']['cancelled'] . ' annulé(s)' : '' ?></div></div></div></div>
    <div class="col-6 col-xl-3"><div class="card hoc-kpi h-100"><div class="card-body"><div class="hoc-kpi-label">Ventes à crédit</div><div class="hoc-kpi-value <?= $s['sales']['credit'] > 0 ? 'text-warning-emphasis' : '' ?>"><?= e(money($s['sales']['credit'])) ?></div><div class="hoc-kpi-sub">Non encaissé (comptes clients)</div></div></div></div>
    <div class="col-6 col-xl-3"><div class="card hoc-kpi h-100"><div class="card-body"><div class="hoc-kpi-label">Sorties</div><div class="hoc-kpi-value"><?= e(money(-(($s['by_type']['withdrawal']['total'] ?? 0) + ($s['by_type']['transfer_out']['total'] ?? 0) + ($s['by_type']['refund']['total'] ?? 0) + ($s['by_type']['expense']['total'] ?? 0)))) ?></div><div class="hoc-kpi-sub">Retraits, remboursements, dépenses</div></div></div></div>
  </div>
  <div class="row g-3">
    <div class="col-xl-7">
      <div class="card mb-3"><div class="card-header fw-semibold">Encaissements par mode de paiement</div>
        <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Mode</th><th class="text-end">Opérations</th><th class="text-end">Entrées</th><th class="text-end">Sorties</th><th class="text-end">Net</th></tr></thead><tbody>
          <?php if (!$s['by_method']): ?><tr><td colspan="5" class="text-body-secondary">Aucun mouvement.</td></tr><?php endif; ?>
          <?php foreach ($s['by_method'] as $m): ?><tr><td><?= e($m['method']) ?><?= (int) $m['affects_cash'] === 1 ? ' <span class="badge text-bg-light border">tiroir</span>' : '' ?></td><td class="text-end"><?= (int) $m['operations'] ?></td>
            <td class="text-end"><?= e(money($m['inflow'])) ?></td><td class="text-end"><?= e(money($m['outflow'])) ?></td><td class="text-end fw-semibold"><?= e(money($m['net'])) ?></td></tr><?php endforeach; ?>
        </tbody></table></div></div>
      <div class="card"><div class="card-header fw-semibold">Mouvements de la session</div>
        <div class="table-responsive" style="max-height:480px"><table class="table table-sm align-middle mb-0"><thead><tr><th>Heure</th><th>Type</th><th>Mode</th><th>Libellé</th><th class="text-end">Montant</th></tr></thead><tbody>
          <?php if (!$movements): ?><tr><td colspan="5" class="text-body-secondary">Aucun mouvement.</td></tr><?php endif; ?>
          <?php foreach ($movements as $m): ?><tr><td class="small"><?= e(date('H:i', strtotime((string) $m['created_at']))) ?></td><td class="small"><?= e($types[$m['type']] ?? $m['type']) ?></td><td class="small"><?= e($m['method'] ?? 'Espèces') ?></td>
            <td class="small"><?php if ($m['reference_type'] === 'sale'): ?><a href="<?= e(url('sales/' . $m['reference_id'])) ?>"><?= e($m['description']) ?></a><?php else: ?><?= e($m['description']) ?><?php endif; ?> <span class="text-body-secondary">· <?= e($m['full_name'] ?? '') ?></span></td>
            <td class="text-end fw-semibold <?= (float) $m['amount'] < 0 ? 'text-danger' : '' ?>"><?= e(money($m['amount'])) ?></td></tr><?php endforeach; ?>
        </tbody></table></div></div>
    </div>
    <div class="col-xl-5">
      <?php if (can('cash.movements')): ?>
      <form method="post" action="<?= e(url('cash/movements')) ?>" class="card mb-3"><div class="card-header fw-semibold">Dépôt / retrait</div><div class="card-body row g-2"><?= csrf_field() ?>
        <div class="col-md-6"><label class="form-label small" for="mtype">Opération</label><select class="form-select" id="mtype" name="type"><option value="withdrawal">Retrait</option><option value="deposit">Dépôt (apport)</option><option value="transfer_out">Transfert vers banque / coffre</option></select></div>
        <div class="col-md-6"><label class="form-label small" for="mamount">Montant</label><input class="form-control" id="mamount" name="amount" inputmode="decimal" required></div>
        <div class="col-12"><label class="form-label small" for="mdesc">Motif *</label><input class="form-control" id="mdesc" name="description" required minlength="3" maxlength="255" placeholder="Ex. achat de fournitures, versement en banque…"></div>
        <div class="col-12"><button class="btn btn-outline-dark w-100">Enregistrer</button></div>
        <div class="col-12 form-text">Les dépenses détaillées (catégorie, justificatif) seront saisies dans le module Dépenses (Phase 7).</div>
      </div></form>
      <?php endif; ?>
      <?php if (can('cash.close')): ?>
      <form method="post" action="<?= e(url('cash/close')) ?>" class="card border-dark" id="closeForm"><div class="card-header fw-semibold"><i class="bi bi-lock"></i> Clôturer la caisse</div><div class="card-body"><?= csrf_field() ?>
        <p class="small text-body-secondary">Comptez les espèces du tiroir. Le total se calcule automatiquement.</p>
        <div class="row g-2 mb-2">
          <?php foreach ($denominations as $d): ?>
            <div class="col-4 col-md-3"><div class="input-group input-group-sm"><span class="input-group-text" style="min-width:56px"><?= e(str_replace('.', ',', $d)) ?></span><input class="form-control" type="number" min="0" name="denom[<?= e($d) ?>]" data-d="<?= e($d) ?>" placeholder="0" aria-label="Nombre de <?= e($d) ?>"></div></div>
          <?php endforeach; ?>
        </div>
        <label class="form-label small" for="counted">Total compté</label>
        <div class="input-group mb-2"><input class="form-control form-control-lg" id="counted" name="counted" inputmode="decimal" placeholder="Total des billets et pièces"><span class="input-group-text"><?= e(setting('currency_symbol', 'DH')) ?></span></div>
        <div class="alert alert-light border small py-2" id="closeDiff" data-expected="<?= e((string) $s['expected']) ?>">Attendu : <strong><?= e(money($s['expected'])) ?></strong></div>
        <label class="form-label small" for="cnotes">Commentaire (justification d'écart)</label><input class="form-control mb-3" id="cnotes" name="notes" maxlength="500">
        <button class="btn btn-dark w-100" onclick="return confirm('Clôturer la caisse ? Aucune vente en espèces ne sera possible avant une nouvelle ouverture.')">Clôturer et imprimer le rapport Z</button>
      </div></form>
      <script>
        (function () {
          var f = document.getElementById('closeForm'), total = document.getElementById('counted'), box = document.getElementById('closeDiff'), exp = parseFloat(box.dataset.expected);
          var show = function () {
            var c = parseFloat((total.value || '').replace(/\s/g, '').replace(',', '.'));
            if (isNaN(c)) { box.innerHTML = 'Attendu : <strong>' + exp.toFixed(2).replace('.', ',') + '</strong>'; box.className = 'alert alert-light border small py-2'; return; }
            var d = Math.round((c - exp) * 100) / 100;
            box.className = 'alert small py-2 ' + (Math.abs(d) < 0.01 ? 'alert-success' : 'alert-warning');
            box.innerHTML = 'Attendu ' + exp.toFixed(2).replace('.', ',') + ' · compté ' + c.toFixed(2).replace('.', ',') + ' · <strong>écart ' + (d > 0 ? '+' : '') + d.toFixed(2).replace('.', ',') + '</strong>';
          };
          f.addEventListener('input', function (e) {
            if (e.target.dataset.d) {
              var s = 0; f.querySelectorAll('[data-d]').forEach(function (i) { s += (parseInt(i.value, 10) || 0) * parseFloat(i.dataset.d); });
              total.value = (Math.round(s * 100) / 100).toFixed(2).replace('.', ',');
            }
            show();
          });
        })();
      </script>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>
