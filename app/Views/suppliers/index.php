<?php /** Liste des fournisseurs. Variables : $suppliers, $p, $f, $cities */ ?>
<div class="hoc-page-head">
  <div><h1 class="h3 fw-bold mb-1">Fournisseurs</h1><p class="text-body-secondary mb-0">Partenaires d'approvisionnement : matières, consommables, sous-traitance.</p></div>
  <div class="d-flex gap-2">
    <a href="<?= e(url('suppliers/export', current_query())) ?>" class="btn btn-outline-secondary"><i class="bi bi-download"></i> Exporter</a>
    <?php if (can('suppliers.create')): ?><a href="<?= e(url('suppliers/create')) ?>" class="btn btn-dark"><i class="bi bi-plus-lg"></i> Nouveau fournisseur</a><?php endif; ?>
  </div>
</div>
<div class="card">
  <form class="card-body hoc-filters border-bottom" method="get" action="<?= e(url('suppliers')) ?>"><?= route_field('suppliers') ?>
    <div class="flex-grow-1"><input class="form-control" type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Nom, société, code, téléphone, e-mail, ICE…" aria-label="Rechercher"></div>
    <?php if ($cities): ?><select class="form-select w-auto" name="city" aria-label="Ville"><option value="">Toutes les villes</option>
      <?php foreach ($cities as $city): ?><option value="<?= e($city) ?>" <?= $f['city'] === $city ? 'selected' : '' ?>><?= e($city) ?></option><?php endforeach; ?></select><?php endif; ?>
    <select class="form-select w-auto" name="status" aria-label="Statut">
      <?php foreach (['active' => 'Actifs', 'inactive' => 'Inactifs', 'all' => 'Tous'] as $k => $l): ?><option value="<?= $k ?>" <?= $f['status'] === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?>
    </select>
    <button class="btn btn-outline-dark"><i class="bi bi-funnel"></i> Filtrer</button>
  </form>
  <div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th><?= sort_link('Code', 'code', $f) ?></th><th><?= sort_link('Fournisseur', 'name', $f) ?></th><th>Contact</th><th><?= sort_link('Ville', 'city', $f) ?></th><th class="text-end">Matières</th><th class="text-end">Dette</th><th class="text-end">Actions</th></tr></thead>
    <tbody>
    <?php if (!$suppliers): ?><tr><td colspan="7" class="hoc-empty"><i class="bi bi-truck"></i>Aucun fournisseur trouvé.</td></tr><?php endif; ?>
    <?php foreach ($suppliers as $s): ?>
      <tr>
        <td><code><?= e($s['code']) ?></code></td>
        <td><a class="fw-semibold text-reset text-decoration-none" href="<?= e(url('suppliers/' . $s['id'])) ?>"><?= e($s['company'] ?: $s['name']) ?></a>
          <?= $s['is_active'] ? '' : ' <span class="badge text-bg-secondary">Inactif</span>' ?><?= $s['company'] ? '<br><small class="text-body-secondary">' . e($s['name']) . '</small>' : '' ?></td>
        <td class="small"><?= e($s['phone'] ?? '') ?><br><span class="text-body-secondary"><?= e($s['email'] ?? '') ?></span></td>
        <td><?= e($s['city'] ?? '—') ?></td>
        <td class="text-end"><?= (int) $s['materials_count'] ?></td>
        <td class="text-end fw-semibold <?= $s['debt'] > 0.004 ? 'text-danger' : 'text-body-secondary' ?>"><?= e(money($s['debt'])) ?></td>
        <td class="text-end text-nowrap">
          <?php $wa = $s['whatsapp'] ?: $s['phone']; if ($wa): ?><a href="<?= e(whatsapp_link($wa)) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-success" title="WhatsApp"><i class="bi bi-whatsapp"></i></a><?php endif; ?>
          <a href="<?= e(url('suppliers/' . $s['id'])) ?>" class="btn btn-sm btn-outline-dark" title="Fiche"><i class="bi bi-eye"></i></a>
          <?php if (can('suppliers.edit')): ?><a href="<?= e(url('suppliers/' . $s['id'] . '/edit')) ?>" class="btn btn-sm btn-outline-dark" title="Modifier"><i class="bi bi-pencil"></i></a><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?php \App\Core\View::partial('pagination', ['p' => $p]); ?>
</div>
