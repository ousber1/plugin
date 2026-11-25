<?php /** Historique des sessions. Variables : $sessions, $p */ ?>
<div class="hoc-page-head"><div><h1 class="h3 fw-bold mb-1">Historique de caisse</h1><p class="text-body-secondary mb-0">Sessions d'ouverture / clôture et écarts de comptage.</p></div>
  <a class="btn btn-outline-secondary" href="<?= e(url('cash')) ?>"><i class="bi bi-arrow-left"></i> Caisse</a></div>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
  <thead><tr><th>Session</th><th>Ouverture</th><th>Clôture</th><th class="text-end">Fond</th><th class="text-end">Ventes</th><th class="text-end">Attendu</th><th class="text-end">Compté</th><th class="text-end">Écart</th><th></th></tr></thead>
  <tbody>
  <?php if (!$sessions): ?><tr><td colspan="9" class="hoc-empty"><i class="bi bi-cash-stack"></i>Aucune session.</td></tr><?php endif; ?>
  <?php foreach ($sessions as $s): $d = (float) $s['difference']; ?>
    <tr><td><code><?= e($s['number']) ?></code><?= $s['status'] === 'open' ? ' <span class="badge text-bg-success">ouverte</span>' : '' ?></td>
      <td class="small"><?= e(format_date($s['opened_at'], true)) ?><br><span class="text-body-secondary"><?= e($s['opened_name'] ?? '') ?></span></td>
      <td class="small"><?= $s['closed_at'] ? e(format_date($s['closed_at'], true)) . '<br><span class="text-body-secondary">' . e($s['closed_name'] ?? '') . '</span>' : '—' ?></td>
      <td class="text-end"><?= e(money($s['opening_balance'])) ?></td><td class="text-end"><?= e(money($s['sales_total'])) ?></td>
      <td class="text-end"><?= $s['status'] === 'closed' ? e(money($s['expected_balance'])) : '—' ?></td><td class="text-end"><?= $s['status'] === 'closed' ? e(money($s['counted_balance'])) : '—' ?></td>
      <td class="text-end fw-semibold <?= $s['status'] !== 'closed' ? '' : (abs($d) < 0.01 ? 'text-success' : 'text-danger') ?>"><?= $s['status'] === 'closed' ? e(($d > 0 ? '+' : '') . number_fr($d)) : '—' ?></td>
      <td class="text-end"><a class="btn btn-sm btn-outline-dark" href="<?= e(url('cash/sessions/' . $s['id'])) ?>" target="_blank" title="Rapport"><i class="bi bi-printer"></i></a></td></tr>
  <?php endforeach; ?>
  </tbody></table></div>
  <?php \App\Core\View::partial('pagination', ['p' => $p]); ?></div>
