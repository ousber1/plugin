<?php /** Rapport de caisse X (en cours) / Z (clôturée). Variables : $session, $summary, $movements, $byUser, $types */
$closed = $session['status'] === 'closed'; $s = $summary;
$denoms = $session['denominations'] ? (json_decode((string) $session['denominations'], true) ?: []) : []; ?>
<div class="d-flex justify-content-between align-items-start mb-4">
  <div><div class="doc-title">RAPPORT DE CAISSE <?= $closed ? 'Z' : 'X' ?></div>
    <div>Session <strong><?= e($session['number']) ?></strong> — <?= e($session['register_name']) ?></div>
    <div class="small text-secondary">Ouverte le <?= e(format_date($session['opened_at'], true)) ?> par <?= e($session['opened_name'] ?? '') ?><?= $closed ? ' · clôturée le ' . e(format_date($session['closed_at'], true)) . ' par ' . e($session['closed_name'] ?? '') : ' · EN COURS' ?></div></div>
  <div class="text-end small text-secondary">Édité le <?= e(format_date(date('Y-m-d H:i:s'), true)) ?></div>
</div>
<div class="row g-3">
  <div class="col-6"><table class="table table-sm table-bordered">
    <thead><tr><th colspan="2">Espèces</th></tr></thead><tbody>
      <tr><td>Fond de caisse</td><td class="text-end"><?= e(money($session['opening_balance'])) ?></td></tr>
      <?php foreach (['sale', 'payment', 'deposit', 'refund', 'withdrawal', 'transfer_out', 'expense', 'supplier_payment'] as $t): if (!isset($s['by_type'][$t])) { continue; } ?>
        <tr><td><?= e($types[$t] ?? $t) ?> <small class="text-secondary">(tous modes)</small></td><td class="text-end"><?= e(money($s['by_type'][$t]['total'])) ?></td></tr>
      <?php endforeach; ?>
      <tr class="fw-bold"><td>Espèces attendues</td><td class="text-end"><?= e(money($closed ? $session['expected_balance'] : $s['expected'])) ?></td></tr>
      <?php if ($closed): ?><tr class="fw-bold"><td>Espèces comptées</td><td class="text-end"><?= e(money($session['counted_balance'])) ?></td></tr>
        <tr class="fw-bold <?= abs((float) $session['difference']) < 0.01 ? '' : 'text-danger' ?>"><td>Écart</td><td class="text-end"><?= e(money($session['difference'])) ?></td></tr><?php endif; ?>
    </tbody></table>
    <?php if ($denoms): ?><table class="table table-sm table-bordered small"><thead><tr><th>Billets / pièces</th><th class="text-end">Nombre</th><th class="text-end">Montant</th></tr></thead><tbody>
      <?php foreach ($denoms as $d => $n): ?><tr><td><?= e(str_replace('.', ',', (string) $d)) ?> <?= e(setting('currency_symbol', 'DH')) ?></td><td class="text-end"><?= (int) $n ?></td><td class="text-end"><?= e(number_fr((float) $d * (int) $n)) ?></td></tr><?php endforeach; ?>
    </tbody></table><?php endif; ?></div>
  <div class="col-6"><table class="table table-sm table-bordered">
    <thead><tr><th>Mode de paiement</th><th class="text-end">Entrées</th><th class="text-end">Sorties</th><th class="text-end">Net</th></tr></thead><tbody>
      <?php foreach ($s['by_method'] as $m): ?><tr><td><?= e($m['method']) ?></td><td class="text-end"><?= e(number_fr($m['inflow'])) ?></td><td class="text-end"><?= e(number_fr($m['outflow'])) ?></td><td class="text-end fw-semibold"><?= e(number_fr($m['net'])) ?></td></tr><?php endforeach; ?>
    </tbody></table>
    <table class="table table-sm table-bordered"><tbody>
      <tr><td>Tickets</td><td class="text-end"><?= (int) $s['sales']['count'] ?></td></tr>
      <tr><td>Chiffre d'affaires TTC</td><td class="text-end"><?= e(money($s['sales']['total'])) ?></td></tr>
      <tr><td>dont à crédit (non encaissé)</td><td class="text-end"><?= e(money($s['sales']['credit'])) ?></td></tr>
      <tr><td>Ventes annulées</td><td class="text-end"><?= (int) $s['sales']['cancelled'] ?></td></tr>
    </tbody></table>
    <?php if ($byUser): ?><table class="table table-sm table-bordered small"><thead><tr><th>Vendeur</th><th class="text-end">Tickets</th><th class="text-end">Total</th></tr></thead><tbody>
      <?php foreach ($byUser as $u): ?><tr><td><?= e($u['full_name'] ?? '—') ?></td><td class="text-end"><?= (int) $u['n'] ?></td><td class="text-end"><?= e(number_fr($u['total'])) ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?></div>
</div>
<?php if ($session['opening_notes'] || $session['closing_notes']): ?><p class="small"><?= $session['opening_notes'] ? 'Ouverture : ' . e($session['opening_notes']) . '<br>' : '' ?><?= $session['closing_notes'] ? 'Clôture : ' . e($session['closing_notes']) : '' ?></p><?php endif; ?>
<h2 class="h6 fw-bold mt-3">Détail des mouvements</h2>
<table class="table table-sm table-bordered small"><thead><tr><th>Heure</th><th>Type</th><th>Mode</th><th>Libellé</th><th>Par</th><th class="text-end">Montant</th></tr></thead><tbody>
  <?php foreach (array_reverse($movements) as $m): ?><tr><td><?= e(date('d/m H:i', strtotime((string) $m['created_at']))) ?></td><td><?= e($types[$m['type']] ?? $m['type']) ?></td><td><?= e($m['method'] ?? 'Espèces') ?></td><td><?= e($m['description'] ?? '') ?></td><td><?= e($m['full_name'] ?? '') ?></td><td class="text-end"><?= e(number_fr($m['amount'])) ?></td></tr><?php endforeach; ?>
</tbody></table>
<div class="row mt-4 small"><div class="col-6"><div class="border rounded p-2" style="height:70px">Signature caissier</div></div><div class="col-6"><div class="border rounded p-2" style="height:70px">Signature responsable</div></div></div>
