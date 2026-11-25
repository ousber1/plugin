<?php
/** Liste des clients. Variables : $customers, $p, $f, $groups, $cities, $total */
$types = \App\Controllers\CustomerController::TYPES;
?>
<div class="hoc-page-head">
  <div>
    <h1 class="h3 fw-bold mb-1">Clients</h1>
    <p class="text-body-secondary mb-0"><?= (int) $total ?> client(s) enregistré(s).</p>
  </div>
  <div class="d-flex flex-wrap gap-2">
    <a href="<?= e(url('customers/groups')) ?>" class="btn btn-outline-secondary"><i class="bi bi-collection"></i> Groupes</a>
    <?php if (can('customers.import')): ?><a href="<?= e(url('customers/import')) ?>" class="btn btn-outline-secondary"><i class="bi bi-upload"></i> Importer</a><?php endif; ?>
    <?php if (can('customers.export')): ?><a href="<?= e(url('customers/export', current_query())) ?>" class="btn btn-outline-secondary"><i class="bi bi-download"></i> Exporter</a><?php endif; ?>
    <?php if (can('customers.create')): ?><a href="<?= e(url('customers/create')) ?>" class="btn btn-dark"><i class="bi bi-person-plus"></i> Nouveau client</a><?php endif; ?>
  </div>
</div>

<div class="card">
  <form class="card-body hoc-filters border-bottom" method="get" action="<?= e(url('customers')) ?>"><?= route_field('customers') ?>
    <div class="flex-grow-1"><input class="form-control" type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Nom, société, code, téléphone, e-mail, ICE…" aria-label="Rechercher"></div>
    <select class="form-select w-auto" name="type" aria-label="Type">
      <option value="">Tous les types</option>
      <?php foreach ($types as $k => $l): ?><option value="<?= e($k) ?>" <?= $f['type'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
    </select>
    <select class="form-select w-auto" name="group" aria-label="Groupe">
      <option value="0">Tous les groupes</option>
      <?php foreach ($groups as $g): ?><option value="<?= (int) $g['id'] ?>" <?= $f['group'] === (int) $g['id'] ? 'selected' : '' ?>><?= e($g['name']) ?></option><?php endforeach; ?>
    </select>
    <?php if ($cities): ?>
      <select class="form-select w-auto" name="city" aria-label="Ville">
        <option value="">Toutes les villes</option>
        <?php foreach ($cities as $city): ?><option value="<?= e($city) ?>" <?= $f['city'] === $city ? 'selected' : '' ?>><?= e($city) ?></option><?php endforeach; ?>
      </select>
    <?php endif; ?>
    <select class="form-select w-auto" name="status" aria-label="Statut">
      <option value="active" <?= $f['status'] === 'active' ? 'selected' : '' ?>>Actifs</option>
      <option value="inactive" <?= $f['status'] === 'inactive' ? 'selected' : '' ?>>Inactifs</option>
      <option value="all" <?= $f['status'] === 'all' ? 'selected' : '' ?>>Tous</option>
    </select>
    <button class="btn btn-outline-dark"><i class="bi bi-funnel"></i> Filtrer</button>
  </form>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead><tr>
        <th><?= sort_link('Code', 'code', $f) ?></th>
        <th><?= sort_link('Client', 'name', $f) ?></th>
        <th><?= sort_link('Type', 'type', $f) ?></th>
        <th>Contact</th>
        <th><?= sort_link('Ville', 'city', $f) ?></th>
        <th class="text-end">Solde</th>
        <th class="text-end">Actions</th>
      </tr></thead>
      <tbody>
      <?php if (!$customers): ?>
        <tr><td colspan="7" class="hoc-empty"><i class="bi bi-people"></i>Aucun client trouvé.
          <?php if (can('customers.create')): ?><div class="mt-2"><a href="<?= e(url('customers/create')) ?>" class="btn btn-sm btn-dark">Créer un client</a></div><?php endif; ?></td></tr>
      <?php endif; ?>
      <?php foreach ($customers as $c): $name = \App\Services\CustomerService::displayName($c); ?>
        <tr>
          <td><code><?= e($c['code']) ?></code></td>
          <td>
            <a href="<?= e(url('customers/' . $c['id'])) ?>" class="fw-semibold text-reset text-decoration-none"><?= e($name) ?></a>
            <?php if (!$c['is_active']): ?><span class="badge text-bg-secondary">Inactif</span><?php endif; ?>
            <?php if ($c['company_name']): ?><br><small class="text-body-secondary"><?= e($c['full_name']) ?></small><?php endif; ?>
          </td>
          <td><span class="badge text-bg-light border"><?= e($types[$c['type']] ?? $c['type']) ?></span><?= $c['group_name'] ? '<br><small class="text-body-secondary">' . e($c['group_name']) . '</small>' : '' ?></td>
          <td class="small">
            <?php if ($c['phone']): ?><a href="tel:<?= e(preg_replace('/[^\d+]/', '', $c['phone'])) ?>" class="text-reset"><?= e($c['phone']) ?></a><br><?php endif; ?>
            <?php if ($c['email']): ?><span class="text-body-secondary"><?= e($c['email']) ?></span><?php endif; ?>
          </td>
          <td><?= e($c['city'] ?? '—') ?></td>
          <td class="text-end fw-semibold <?= $c['balance'] > 0.004 ? 'text-danger' : ($c['balance'] < -0.004 ? 'text-success' : 'text-body-secondary') ?>"><?= e(money($c['balance'])) ?></td>
          <td class="text-end text-nowrap">
            <?php $wa = $c['whatsapp'] ?: $c['phone']; if ($wa): ?>
              <a href="<?= e(whatsapp_link($wa)) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-success" title="WhatsApp"><i class="bi bi-whatsapp"></i><span class="visually-hidden">WhatsApp</span></a>
            <?php endif; ?>
            <a href="<?= e(url('customers/' . $c['id'])) ?>" class="btn btn-sm btn-outline-dark" title="Fiche"><i class="bi bi-eye"></i><span class="visually-hidden">Fiche</span></a>
            <?php if (can('customers.edit')): ?><a href="<?= e(url('customers/' . $c['id'] . '/edit')) ?>" class="btn btn-sm btn-outline-dark" title="Modifier"><i class="bi bi-pencil"></i><span class="visually-hidden">Modifier</span></a><?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php \App\Core\View::partial('pagination', ['p' => $p]); ?>
</div>
