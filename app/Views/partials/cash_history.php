<?php /** Dernières sessions clôturées. Variable : $history */ ?>
<div class="card h-100"><div class="card-header fw-semibold d-flex justify-content-between">Dernières clôtures <a class="small text-decoration-none" href="<?= e(url('cash/sessions')) ?>">Tout voir</a></div>
  <?php if (!$history): ?><div class="hoc-empty"><i class="bi bi-cash-stack"></i>Aucune session clôturée.</div><?php else: ?>
  <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Session</th><th>Clôture</th><th class="text-end">Attendu</th><th class="text-end">Compté</th><th class="text-end">Écart</th></tr></thead><tbody>
    <?php foreach ($history as $h): $d = (float) $h['difference']; ?><tr>
      <td><a href="<?= e(url('cash/sessions/' . $h['id'])) ?>" target="_blank"><code><?= e($h['number']) ?></code></a></td>
      <td class="small"><?= e(format_date($h['closed_at'], true)) ?><br><span class="text-body-secondary"><?= e($h['closed_name'] ?? '') ?></span></td>
      <td class="text-end"><?= e(money($h['expected_balance'])) ?></td><td class="text-end"><?= e(money($h['counted_balance'])) ?></td>
      <td class="text-end fw-semibold <?= abs($d) < 0.01 ? 'text-success' : 'text-danger' ?>"><?= abs($d) < 0.01 ? '<i class="bi bi-check2"></i> 0,00' : e(($d > 0 ? '+' : '') . number_fr($d)) ?></td></tr>
    <?php endforeach; ?></tbody></table></div><?php endif; ?>
</div>
