<?php
/**
 * Écran du point de vente. Variables : $session, $categories, $hasPacks, $methods, $taxes, $units, $walkIn, $config, $perms
 * L'application cliente (assets/js/pos.js) lit sa configuration dans #posConfig.
 */
$cfg = [
    'urls' => [
        'products' => url('pos/products'), 'prices' => url('pos/prices'), 'customer' => url('pos/customer/0'),
        'checkout' => url('pos/checkout'), 'suspend' => url('pos/suspend'), 'drafts' => url('pos/drafts'),
        'resume' => url('pos/drafts/0'), 'deleteDraft' => url('pos/drafts/0/delete'), 'customers' => url('customers/lookup'),
        'newCustomer' => url('customers/create'),
    ],
    'methods' => array_map(static fn($m) => ['id' => (int) $m['id'], 'code' => $m['code'], 'name' => $m['name'],
        'cash' => (bool) $m['affects_cash'], 'ref' => (bool) $m['requires_reference'], 'credit' => (bool) $m['is_credit']], $methods),
    'taxes' => array_map(static fn($t) => ['rate' => (float) $t['rate'], 'name' => $t['name']], $taxes),
    'units' => array_map(static fn($u) => ['id' => (int) $u['id'], 'name' => $u['name'], 'symbol' => $u['symbol']], $units),
    'walkIn' => $walkIn, 'config' => $config, 'perms' => $perms, 'sessionOpen' => (bool) $session,
];
?>
<script type="application/json" id="posConfig"><?= json_encode($cfg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?></script>

<div class="pos-wrap">
  <!-- Catalogue -->
  <section class="pos-catalog" aria-label="Catalogue">
    <div class="pos-searchbar">
      <div class="position-relative flex-grow-1">
        <i class="bi bi-upc-scan pos-search-icon"></i>
        <input type="search" class="form-control form-control-lg" id="posSearch" placeholder="Rechercher ou scanner un code-barres… (F2)" autocomplete="off" aria-label="Rechercher un produit">
      </div>
      <button class="btn btn-outline-dark btn-lg" type="button" id="posCustomBtn" title="Article libre / travail sur mesure"><i class="bi bi-pencil-square"></i><span class="d-none d-xl-inline"> Article libre</span></button>
    </div>
    <div class="pos-chips" id="posChips" role="tablist">
      <button class="pos-chip active" data-cat="quick"><i class="bi bi-lightning-charge"></i> Rapides</button>
      <button class="pos-chip" data-cat=""><i class="bi bi-grid"></i> Tout</button>
      <?php if ($hasPacks): ?><button class="pos-chip" data-cat="packs"><i class="bi bi-boxes"></i> Packs</button><?php endif; ?>
      <?php foreach ($categories as $c): ?>
        <button class="pos-chip" data-cat="<?= (int) $c['id'] ?>" style="--chip:<?= e($c['color']) ?>"><?php if ($c['icon']): ?><i class="bi <?= e($c['icon']) ?>"></i> <?php endif; ?><?= e($c['name']) ?></button>
      <?php endforeach; ?>
    </div>
    <div class="pos-grid" id="posGrid" aria-live="polite"></div>
  </section>

  <!-- Panier -->
  <aside class="pos-cart" aria-label="Panier">
    <div class="pos-customer">
      <div class="hoc-lookup flex-grow-1" id="posCustomerLookup" data-lookup="<?= e(url('customers/lookup')) ?>">
        <i class="bi bi-person pos-search-icon"></i>
        <input class="form-control" type="search" placeholder="Client comptoir — rechercher un client…" autocomplete="off" data-lookup-input aria-label="Client">
        <input type="hidden" data-lookup-value>
      </div>
      <button class="btn btn-outline-secondary" type="button" id="posCustomerReset" title="Client comptoir"><i class="bi bi-person-x"></i></button>
      <?php if ($perms['newCustomer']): ?><a class="btn btn-outline-secondary" href="<?= e(url('customers/create')) ?>" target="_blank" title="Nouveau client"><i class="bi bi-person-plus"></i></a><?php endif; ?>
    </div>
    <div class="pos-customer-info" id="posCustomerInfo"></div>

    <div class="pos-lines" id="posLines"></div>

    <div class="pos-footer">
      <div class="pos-discount <?= $perms['discount'] ? '' : 'd-none' ?>">
        <label class="small text-body-secondary" for="posDiscountValue">Remise globale</label>
        <div class="input-group input-group-sm">
          <input class="form-control" id="posDiscountValue" inputmode="decimal" placeholder="0">
          <select class="form-select" id="posDiscountType" style="max-width:90px" aria-label="Type de remise"><option value="percent">%</option><option value="amount"><?= e($config['currency']) ?></option></select>
        </div>
      </div>
      <div class="pos-totals">
        <div><span>Sous-total HT</span><span id="posSubtotal">0,00</span></div>
        <div id="posDiscountRow" class="d-none"><span>Remise</span><span id="posDiscount">0,00</span></div>
        <div><span>TVA</span><span id="posTax">0,00</span></div>
        <div class="pos-total"><span>Total TTC</span><span id="posTotal">0,00</span></div>
      </div>
      <div class="pos-actions">
        <button class="btn btn-outline-secondary" type="button" id="posSuspend" title="Mettre en attente"><i class="bi bi-pause-circle"></i><span> Attente</span></button>
        <button class="btn btn-outline-secondary position-relative" type="button" id="posDrafts" title="Ventes en attente"><i class="bi bi-inboxes"></i><span> Reprendre</span></button>
        <button class="btn btn-outline-danger" type="button" id="posClear" title="Vider le panier"><i class="bi bi-trash"></i></button>
        <button class="btn btn-accent btn-lg pos-pay" type="button" id="posPay" disabled><i class="bi bi-cash-coin"></i> Encaisser <small>(F9)</small></button>
      </div>
      <button class="btn btn-link btn-sm p-0 mt-1 text-decoration-none" type="button" id="posNotesToggle"><i class="bi bi-chat-left-text"></i> Note sur la vente</button>
      <textarea class="form-control form-control-sm mt-1 d-none" id="posNotes" rows="2" maxlength="2000" placeholder="Note interne (bon à tirer, délai, consignes…)"></textarea>
    </div>
  </aside>
</div>

<?php if (!$session): ?>
  <!-- Caisse fermée -->
  <div class="pos-overlay" id="posClosed">
    <div class="card shadow-lg" style="max-width:420px">
      <div class="card-body p-4 text-center">
        <div class="display-6 text-danger mb-2"><i class="bi bi-lock"></i></div>
        <h2 class="h4 fw-bold">Caisse fermée</h2>
        <?php if ($perms['openCash']): ?>
          <p class="text-body-secondary">Comptez le fond de caisse (espèces présentes dans le tiroir) pour ouvrir la session.</p>
          <form method="post" action="<?= e(url('cash/open')) ?>" class="text-start"><?= csrf_field() ?><input type="hidden" name="back" value="pos">
            <label class="form-label" for="opening_balance">Fond de caisse (<?= e($config['currency']) ?>)</label>
            <input class="form-control form-control-lg mb-3" id="opening_balance" name="opening_balance" inputmode="decimal" value="0" required autofocus>
            <button class="btn btn-dark btn-lg w-100"><i class="bi bi-unlock"></i> Ouvrir la caisse</button>
          </form>
        <?php else: ?>
          <p class="text-body-secondary mb-0">Demandez à un responsable d'ouvrir la caisse.</p>
        <?php endif; ?>
        <?php if (!$config['requireSession']): ?><button class="btn btn-link mt-2" type="button" onclick="document.getElementById('posClosed').remove()">Continuer sans caisse (paiements hors espèces)</button><?php endif; ?>
      </div>
    </div>
  </div>
<?php endif; ?>

<!-- Article libre -->
<div class="modal fade" id="customModal" tabindex="-1" aria-labelledby="customTitle" aria-hidden="true"><div class="modal-dialog"><form class="modal-content" id="customForm">
  <div class="modal-header"><h2 class="modal-title h5" id="customTitle">Article libre / travail sur mesure</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
  <div class="modal-body row g-3">
    <div class="col-12"><label class="form-label" for="cDesc">Désignation *</label><input class="form-control" id="cDesc" maxlength="500" required placeholder="Ex. Plaque PVC 3 mm 60×40 cm imprimée + pose"></div>
    <div class="col-4"><label class="form-label" for="cQty">Quantité</label><input class="form-control" id="cQty" inputmode="decimal" value="1"></div>
    <div class="col-4"><label class="form-label" for="cUnit">Unité</label><select class="form-select" id="cUnit"><?php foreach ($units as $u): ?><option value="<?= (int) $u['id'] ?>" data-symbol="<?= e($u['symbol']) ?>"><?= e($u['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-4"><label class="form-label" for="cTax">TVA</label><select class="form-select" id="cTax"><?php foreach ($taxes as $t): ?><option value="<?= e((string) (float) $t['rate']) ?>" <?= (float) $t['rate'] === $config['defaultTax'] ? 'selected' : '' ?>><?= e($t['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-6"><label class="form-label" for="cPrice">Prix unitaire</label><input class="form-control" id="cPrice" inputmode="decimal" required></div>
    <div class="col-6"><label class="form-label" for="cPriceMode">Saisi</label><select class="form-select" id="cPriceMode"><option value="ttc">TTC</option><option value="ht">HT</option></select></div>
    <?php if ($perms['newProduct']): ?><div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" id="cSave"><label class="form-check-label" for="cSave">Ajouter ce service au catalogue</label></div></div><?php endif; ?>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button><button class="btn btn-dark">Ajouter au panier</button></div>
</form></div></div>

<!-- Dimensions (travaux au m²) -->
<div class="modal fade" id="areaModal" tabindex="-1" aria-labelledby="areaTitle" aria-hidden="true"><div class="modal-dialog modal-sm"><form class="modal-content" id="areaForm">
  <div class="modal-header"><h2 class="modal-title h5" id="areaTitle">Dimensions</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
  <div class="modal-body row g-2">
    <div class="col-12 small text-body-secondary" id="areaProduct"></div>
    <div class="col-6"><label class="form-label" for="aW">Largeur (cm)</label><input class="form-control" id="aW" inputmode="decimal" required></div>
    <div class="col-6"><label class="form-label" for="aH">Hauteur (cm)</label><input class="form-control" id="aH" inputmode="decimal" required></div>
    <div class="col-12"><label class="form-label" for="aQ">Exemplaires</label><input class="form-control" id="aQ" inputmode="numeric" value="1"></div>
    <div class="col-12"><div class="alert alert-light border small mb-0 py-2" id="areaPreview">Surface : —</div></div>
  </div>
  <div class="modal-footer"><button class="btn btn-dark w-100">Ajouter</button></div>
</form></div></div>

<!-- Encaissement -->
<div class="modal fade" id="payModal" tabindex="-1" aria-labelledby="payTitle" aria-hidden="true"><div class="modal-dialog modal-lg"><div class="modal-content">
  <div class="modal-header"><h2 class="modal-title h5" id="payTitle">Encaissement</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
  <div class="modal-body">
    <div class="pos-pay-total"><span>Total à payer</span><strong id="payTotal">0,00</strong></div>
    <div class="row g-3 mt-1">
      <div class="col-md-7"><div id="payMethods" class="d-grid gap-2"></div></div>
      <div class="col-md-5">
        <div class="pos-pay-summary">
          <div><span>Reçu</span><strong id="payReceived">0,00</strong></div>
          <div id="payDueRow"><span>Reste dû</span><strong id="payDue">0,00</strong></div>
          <div id="payChangeRow" class="pos-change d-none"><span>Monnaie à rendre</span><strong id="payChange">0,00</strong></div>
        </div>
        <div class="small text-body-secondary mt-2" id="payCreditInfo"></div>
        <div class="alert alert-danger small mt-2 d-none" id="payError" role="alert"></div>
      </div>
    </div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Retour</button>
    <button class="btn btn-accent btn-lg px-4" type="button" id="payConfirm"><i class="bi bi-check2-circle"></i> Valider la vente</button></div>
</div></div></div>

<!-- Vente validée -->
<div class="modal fade" id="doneModal" tabindex="-1" data-bs-backdrop="static" aria-labelledby="doneTitle" aria-hidden="true"><div class="modal-dialog"><div class="modal-content text-center">
  <div class="modal-body p-4">
    <div class="display-5 text-success mb-2"><i class="bi bi-check-circle-fill"></i></div>
    <h2 class="h4 fw-bold" id="doneTitle">Vente enregistrée</h2>
    <div class="text-body-secondary mb-3" id="doneNumber"></div>
    <div class="pos-change-big d-none" id="doneChange"></div>
    <div class="alert alert-warning small d-none" id="doneDue"></div>
    <div class="d-flex flex-wrap justify-content-center gap-2 mt-3">
      <button class="btn btn-outline-dark" type="button" data-print="80"><i class="bi bi-printer"></i> Ticket 80 mm</button>
      <button class="btn btn-outline-dark" type="button" data-print="58"><i class="bi bi-printer"></i> Ticket 58 mm</button>
      <a class="btn btn-outline-dark" id="doneA4" target="_blank" href="#"><i class="bi bi-file-earmark-text"></i> Bon A4</a>
      <a class="btn btn-outline-secondary" id="doneShow" href="#"><i class="bi bi-eye"></i> Détail</a>
    </div>
  </div>
  <div class="modal-footer justify-content-center"><button class="btn btn-dark btn-lg px-5" type="button" id="doneNew" data-bs-dismiss="modal"><i class="bi bi-plus-lg"></i> Nouvelle vente (Entrée)</button></div>
</div></div></div>

<!-- Ventes en attente -->
<div class="modal fade" id="draftsModal" tabindex="-1" aria-labelledby="draftsTitle" aria-hidden="true"><div class="modal-dialog modal-lg"><div class="modal-content">
  <div class="modal-header"><h2 class="modal-title h5" id="draftsTitle">Ventes en attente</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
  <div class="modal-body p-0" id="draftsList"></div>
</div></div></div>
