<?php
/**
 * Éditeur commun des devis et factures (lignes, remises, frais, totaux calculés par le serveur).
 * Variables : $kind ('quotation'|'invoice'), $doc (array), $items (lignes au format formulaire),
 *             $customer (array|null), $taxes, $units, $action, $cancelUrl
 */
use App\Services\DocumentService;
$isQuote = $kind === 'quotation';
$oldItems = old('items_json', '');
$initial = $oldItems !== '' ? (json_decode((string) $oldItems, true) ?: []) : $items;
$v = static fn(string $k, $d = '') => old($k, $doc[$k] ?? $d);
$defaultRate = (float) (\App\Core\Database::instance()->fetchColumn('SELECT rate FROM taxes WHERE id = ?', [(int) setting('default_tax_id', 1)]) ?? 0);
$cfg = [
    'items' => array_values($initial), 'catalog' => url('documents/catalog'), 'compute' => url('documents/compute'), 'prices' => url('documents/prices'),
    'customerInfo' => url('pos/customer/0'), 'taxes' => array_map(static fn($t) => (float) $t['rate'], $taxes),
    'units' => array_map(static fn($u) => ['id' => (int) $u['id'], 'symbol' => $u['symbol']], $units), 'defaultRate' => $defaultRate,
    'taxEnabled' => (bool) setting('tax_enabled', true),
];
?>
<form method="post" action="<?= e($action) ?>" id="docForm" novalidate>
  <?= csrf_field() ?>
  <input type="hidden" name="items_json" id="itemsJson" value="">
  <div class="row g-3">
    <div class="col-xl-9">
      <div class="card mb-3"><div class="card-body"><div class="row g-3">
        <div class="col-md-6"><label class="form-label">Client *</label>
          <div class="hoc-lookup" data-lookup="<?= e(url('customers/lookup')) ?>" id="customerBox">
            <input class="form-control" data-lookup-input autocomplete="off" placeholder="Nom, société, code ou téléphone…" value="<?= e($customer ? (($customer['code'] ?? '') . ' — ' . \App\Services\CustomerService::displayName($customer)) : '') ?>">
            <input type="hidden" name="customer_id" data-lookup-value required value="<?= e((string) ($customer['id'] ?? '')) ?>">
          </div>
          <div class="form-text" id="customerHint"><?= can('customers.create') ? '<a href="' . e(url('customers/create')) . '" target="_blank">+ Nouveau client</a>' : '' ?></div>
        </div>
        <?php if ($isQuote): ?>
          <div class="col-md-6"><label class="form-label" for="title">Objet du devis</label><input class="form-control" id="title" name="title" maxlength="190" value="<?= e($v('title')) ?>" placeholder="Ex. Habillage vitrine, enseigne lumineuse…"></div>
        <?php endif; ?>
        <div class="col-6 col-md-3"><label class="form-label" for="issue_date">Date</label><input class="form-control" type="date" id="issue_date" name="issue_date" value="<?= e($v('issue_date', date('Y-m-d'))) ?>" required></div>
        <?php if ($isQuote): ?>
          <div class="col-6 col-md-3"><label class="form-label" for="valid_until">Valable jusqu'au</label><input class="form-control" type="date" id="valid_until" name="valid_until" value="<?= e($v('valid_until', date('Y-m-d', strtotime('+' . (int) setting('quotation_validity_days', 30) . ' days')))) ?>"></div>
          <div class="col-6 col-md-3"><label class="form-label" for="delivery_date">Livraison prévue</label><input class="form-control" type="date" id="delivery_date" name="delivery_date" value="<?= e($v('delivery_date')) ?>"></div>
          <div class="col-6 col-md-3"><label class="form-label" for="production_days">Délai de réalisation (jours)</label><input class="form-control" type="number" min="0" max="365" id="production_days" name="production_days" value="<?= e((string) $v('production_days')) ?>"></div>
        <?php else: ?>
          <div class="col-6 col-md-3"><label class="form-label" for="due_date">Échéance</label><input class="form-control" type="date" id="due_date" name="due_date" value="<?= e($v('due_date', date('Y-m-d', strtotime('+' . (int) setting('invoice_due_days', 30) . ' days')))) ?>"></div>
        <?php endif; ?>
      </div></div></div>

      <div class="card mb-3">
        <div class="card-header d-flex flex-wrap gap-2 align-items-center justify-content-between">
          <span class="fw-semibold">Lignes</span>
          <div class="d-flex flex-wrap gap-2 align-items-center">
            <div class="hoc-lookup" style="min-width:300px" id="catalogBox" data-lookup="<?= e(url('documents/catalog')) ?>">
              <input class="form-control form-control-sm" data-lookup-input autocomplete="off" placeholder="Ajouter un produit, service ou pack…" aria-label="Rechercher dans le catalogue">
              <input type="hidden" data-lookup-value>
            </div>
            <button type="button" class="btn btn-sm btn-outline-dark" id="addCustom"><i class="bi bi-plus-lg"></i> Ligne libre</button>
          </div>
        </div>
        <div class="table-responsive"><table class="table align-middle mb-0 hoc-doc-lines">
          <thead><tr><th style="min-width:260px">Désignation</th><th style="width:110px">Qté</th><th style="width:130px">PU HT</th><th style="width:90px">Rem. %</th><th style="width:100px">TVA</th><th class="text-end" style="width:130px">Total HT</th><th style="width:90px"></th></tr></thead>
          <tbody id="docLines"></tbody>
        </table></div>
        <div class="card-body py-2 small text-body-secondary" id="emptyLines">Aucune ligne : recherchez un article du catalogue ou ajoutez une ligne libre.</div>
      </div>

      <div class="card mb-3"><div class="card-body"><div class="row g-3">
        <div class="col-md-6"><label class="form-label" for="notes">Notes (imprimées sur le document)</label><textarea class="form-control" id="notes" name="notes" rows="3" maxlength="5000"><?= e($v('notes')) ?></textarea></div>
        <div class="col-md-6"><label class="form-label" for="payment_terms">Conditions de paiement</label><textarea class="form-control" id="payment_terms" name="payment_terms" rows="3" maxlength="2000" placeholder="<?= e((string) setting('default_payment_terms', '')) ?>"><?= e($v('payment_terms')) ?></textarea><div class="form-text">Laisser vide pour utiliser les conditions par défaut des paramètres.</div></div>
        <div class="col-12"><label class="form-label" for="internal_notes">Notes internes (non imprimées)</label><textarea class="form-control" id="internal_notes" name="internal_notes" rows="2" maxlength="5000"><?= e($v('internal_notes')) ?></textarea></div>
      </div></div></div>
    </div>

    <div class="col-xl-3">
      <div class="card mb-3 hoc-sticky"><div class="card-header fw-semibold">Totaux</div><div class="card-body">
        <label class="form-label small">Remise globale</label>
        <div class="input-group input-group-sm mb-3">
          <select class="form-select" name="discount_type" id="discountType" style="max-width:110px">
            <?php foreach (['none' => 'Aucune', 'percent' => '%', 'amount' => 'DH HT'] as $k => $l): ?><option value="<?= $k ?>" <?= $v('discount_type', 'none') === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?>
          </select>
          <input class="form-control" type="number" step="0.01" min="0" name="discount_value" id="discountValue" value="<?= e((string) (float) $v('discount_value', 0)) ?>">
        </div>
        <?php foreach (DocumentService::FEES as $k => $label): ?>
          <label class="form-label small mb-1" for="fee_<?= $k ?>"><?= e($label) ?> (HT)</label>
          <input class="form-control form-control-sm mb-2 js-fee" type="number" step="0.01" min="0" id="fee_<?= $k ?>" name="fees[<?= $k ?>]" value="<?= e((string) (float) (((array) old('fees', []))[$k] ?? $doc[$k] ?? 0)) ?>">
        <?php endforeach; ?>
        <hr>
        <div id="totalsBox" class="small">
          <div class="hoc-mini"><span>Sous-total HT</span><span data-t="subtotal">—</span></div>
          <div class="hoc-mini"><span>Remise</span><span data-t="discount_total">—</span></div>
          <div class="hoc-mini"><span>Frais HT</span><span data-t="fees_total">—</span></div>
          <div class="hoc-mini"><span>Total HT</span><span data-t="net_total">—</span></div>
          <div id="vatLines"></div>
          <div class="hoc-mini fw-bold fs-5"><span>Total TTC</span><span data-t="total">—</span></div>
        </div>
        <div class="alert alert-danger small py-2 mt-2 d-none" id="calcError" role="alert"></div>
        <div class="d-grid gap-2 mt-3">
          <button class="btn btn-accent" type="submit"><i class="bi bi-check2"></i> Enregistrer</button>
          <a class="btn btn-outline-secondary" href="<?= e($cancelUrl) ?>">Annuler</a>
        </div>
      </div></div>
    </div>
  </div>
</form>
<script type="application/json" id="docFormConfig"><?= json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
