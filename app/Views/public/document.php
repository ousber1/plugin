<?php /** Consultation publique d'un document. Variables : $data, $token, $type, $doc, $canAccept */ ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div><h1 class="h4 fw-bold mb-0"><?= e($data['title']) ?> <?= e($data['number'] ?? '') ?></h1>
    <span class="text-body-secondary small">Montant : <?= e(money($doc['total'] ?? $doc['amount'] ?? 0)) ?><?= $type === 'quotation' && !empty($doc['valid_until']) ? ' · valable jusqu\'au ' . e(format_date($doc['valid_until'])) : '' ?></span></div>
  <div class="d-flex gap-2">
    <a class="btn btn-dark" href="<?= e(url('d/' . $token . '/pdf', ['download' => 1])) ?>"><i class="bi bi-download"></i> Télécharger le PDF</a>
    <a class="btn btn-outline-dark" href="<?= e(url('d/' . $token . '/pdf')) ?>" target="_blank"><i class="bi bi-file-earmark-pdf"></i> Ouvrir</a>
  </div>
</div>
<?php if ($type === 'quotation' && in_array($doc['status'], ['accepted', 'converted'], true)): ?>
  <div class="alert alert-success"><i class="bi bi-check-circle"></i> Ce devis a été accepté<?= $doc['accepted_at'] ? ' le ' . e(format_date($doc['accepted_at'], true)) : '' ?>. Merci pour votre confiance.</div>
<?php endif; ?>
<?php if ($canAccept): ?>
  <div class="card border-success mb-3"><div class="card-body">
    <h2 class="h6 fw-bold"><i class="bi bi-pen"></i> Accepter ce devis en ligne</h2>
    <form method="post" action="<?= e(url('d/' . $token . '/accept')) ?>" class="row g-2 align-items-end"><?= csrf_field() ?>
      <div class="col-md-5"><label class="form-label small" for="signer">Nom et fonction du signataire *</label><input class="form-control" id="signer" name="signer" required minlength="3" maxlength="120" placeholder="Ex. Karim Alaoui, gérant"></div>
      <div class="col-md-5"><div class="form-check"><input class="form-check-input" type="checkbox" name="agree" value="1" id="agree" required><label class="form-check-label small" for="agree">J'accepte le devis et ses conditions (« Bon pour accord »).</label></div></div>
      <div class="col-md-2 d-grid"><button class="btn btn-success">Accepter</button></div>
    </form></div></div>
<?php endif; ?>
<iframe class="hoc-doc-preview" src="<?= e(url('d/' . $token . '/html')) ?>" title="Document"></iframe>
<p class="small text-body-secondary mt-3 mb-0">Ce lien personnel donne accès à ce seul document. Pour toute question : <?= e(setting('business_email', '')) ?> <?= e(setting('business_phone', '')) ?></p>
