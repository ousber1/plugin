<?php /** Catalogue. Variables : $products, $p, $f, $categories, $counts */
$types = \App\Controllers\ProductController::TYPES;
$services = \App\Controllers\ProductController::SERVICE_TYPES;
$showCost = can('products.view_cost'); ?>
<div class="hoc-page-head">
  <div><h1 class="h3 fw-bold mb-1"><?= e($title) ?></h1>
    <p class="text-body-secondary mb-0"><?= (int) $counts['products'] ?> produit(s) · <?= (int) $counts['services'] ?> service(s) actifs<?= (int) $counts['low'] ? ' · <a href="' . e(url('products', ['stock' => 'low'])) . '" class="text-danger">' . (int) $counts['low'] . ' en stock faible</a>' : '' ?></p></div>
  <div class="d-flex flex-wrap gap-2">
    <a href="<?= e(url('categories')) ?>" class="btn btn-outline-secondary"><i class="bi bi-tags"></i> Catégories</a>
    <?php if (can('products.import')): ?><a href="<?= e(url('products/import')) ?>" class="btn btn-outline-secondary"><i class="bi bi-upload"></i> Importer</a><?php endif; ?>
    <?php if (can('products.export')): ?><a href="<?= e(url('products/export', current_query())) ?>" class="btn btn-outline-secondary"><i class="bi bi-download"></i> Exporter</a><?php endif; ?>
    <?php if (can('products.create')): ?>
      <div class="btn-group">
        <a href="<?= e(url('products/create')) ?>" class="btn btn-dark"><i class="bi bi-plus-lg"></i> Nouveau</a>
        <button class="btn btn-dark dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-label="Choisir le type"></button>
        <ul class="dropdown-menu dropdown-menu-end"><?php foreach ($types as $k => $l): ?><li><a class="dropdown-item" href="<?= e(url('products/create', ['type' => $k])) ?>"><?= e($l) ?></a></li><?php endforeach; ?></ul>
      </div>
    <?php endif; ?>
  </div>
</div>
<ul class="nav nav-pills mb-3">
  <?php foreach (['' => 'Tout', 'products' => 'Produits', 'services' => 'Services'] as $k => $l): ?>
    <li class="nav-item"><a class="nav-link<?= $f['kind'] === $k ? ' active' : '' ?>" href="<?= e(url('products', array_filter(['kind' => $k]))) ?>"><?= e($l) ?></a></li>
  <?php endforeach; ?>
</ul>
<div class="card">
  <form class="card-body hoc-filters border-bottom" method="get" action="<?= e(url('products')) ?>"><?= route_field('products') ?>
    <input type="hidden" name="kind" value="<?= e($f['kind']) ?>">
    <div class="flex-grow-1"><input class="form-control" type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Nom, code, code-barres…" aria-label="Rechercher" autofocus></div>
    <select class="form-select w-auto" name="category" aria-label="Catégorie"><option value="0">Toutes les catégories</option>
      <?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>" <?= $f['category'] === (int) $c['id'] ? 'selected' : '' ?>><?= $c['parent_id'] ? '— ' : '' ?><?= e($c['name']) ?></option><?php endforeach; ?></select>
    <select class="form-select w-auto" name="type" aria-label="Type"><option value="">Tous les types</option>
      <?php foreach ($types as $k => $l): ?><option value="<?= $k ?>" <?= $f['type'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
    <select class="form-select w-auto" name="stock" aria-label="Stock"><option value="">Tout stock</option>
      <option value="low" <?= $f['stock'] === 'low' ? 'selected' : '' ?>>Stock faible</option><option value="out" <?= $f['stock'] === 'out' ? 'selected' : '' ?>>Rupture</option></select>
    <select class="form-select w-auto" name="status" aria-label="Statut">
      <?php foreach (['active' => 'Actifs', 'inactive' => 'Inactifs', 'all' => 'Tous'] as $k => $l): ?><option value="<?= $k ?>" <?= $f['status'] === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
    <button class="btn btn-outline-dark"><i class="bi bi-funnel"></i> Filtrer</button>
  </form>
  <div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th style="width:56px"></th><th><?= sort_link('Code', 'code', $f) ?></th><th><?= sort_link('Désignation', 'name', $f) ?></th><th><?= sort_link('Catégorie', 'category', $f) ?></th>
      <th class="text-end"><?= sort_link('Prix HT', 'price', $f) ?></th><?php if ($showCost): ?><th class="text-end">Marge</th><?php endif; ?><th class="text-end"><?= sort_link('Stock', 'stock', $f) ?></th><th class="text-end">Actions</th></tr></thead>
    <tbody>
    <?php if (!$products): ?><tr><td colspan="8" class="hoc-empty"><i class="bi bi-box"></i>Aucun article trouvé.</td></tr><?php endif; ?>
    <?php foreach ($products as $pr): $isService = in_array($pr['product_type'], $services, true);
      $low = $pr['track_stock'] && (float) $pr['stock_quantity'] <= (float) $pr['min_stock']; ?>
      <tr>
        <td><div class="hoc-thumb"><?php if ($pr['image']): ?><img src="<?= e(asset($pr['image'])) ?>" alt="" loading="lazy"><?php else: ?><i class="bi <?= $isService ? 'bi-brush' : 'bi-box' ?>"></i><?php endif; ?></div></td>
        <td><code><?= e($pr['code']) ?></code></td>
        <td><a class="fw-semibold text-reset text-decoration-none" href="<?= e(url('products/' . $pr['id'])) ?>"><?= e($pr['name']) ?></a>
          <?= $pr['is_active'] ? '' : ' <span class="badge text-bg-secondary">Inactif</span>' ?><?= $pr['is_quick'] ? ' <i class="bi bi-lightning-charge-fill text-warning" title="Bouton rapide POS"></i>' : '' ?>
          <br><small class="text-body-secondary"><?= e($types[$pr['product_type']] ?? $pr['product_type']) ?></small></td>
        <td><?php if ($pr['category_name']): ?><span class="hoc-status-dot" style="background:<?= e($pr['category_color']) ?>"></span><?= e($pr['category_name']) ?><?php else: ?>—<?php endif; ?></td>
        <td class="text-end text-nowrap"><strong><?= e(money($pr['selling_price'])) ?></strong><br><small class="text-body-secondary">/ <?= e($pr['unit_symbol']) ?> · TVA <?= e(number_fr($pr['tax_rate'] ?? 0, 0)) ?> %</small></td>
        <?php if ($showCost): ?><td class="text-end <?= (float) $pr['margin_percent'] < 0 ? 'text-danger' : '' ?>"><?= (float) $pr['selling_price'] > 0 && ((float) $pr['estimated_cost'] > 0 || (float) $pr['purchase_price'] > 0) ? e(number_fr($pr['margin_percent'], 1)) . ' %' : '<span class="text-body-secondary" title="Aucun coût renseigné">—</span>' ?></td><?php endif; ?>
        <td class="text-end text-nowrap"><?php if ($pr['track_stock']): ?><span class="fw-semibold <?= (float) $pr['stock_quantity'] <= 0 ? 'text-danger' : ($low ? 'text-warning-emphasis' : '') ?>"><?= $low ? '<i class="bi bi-exclamation-triangle"></i> ' : '' ?><?= e(number_fr($pr['stock_quantity'], 2)) ?></span><?php else: ?><span class="text-body-secondary small">Non suivi</span><?php endif; ?></td>
        <td class="text-end text-nowrap">
          <a href="<?= e(url('products/' . $pr['id'])) ?>" class="btn btn-sm btn-outline-dark" title="Fiche"><i class="bi bi-eye"></i></a>
          <?php if (can('products.edit')): ?><a href="<?= e(url('products/' . $pr['id'] . '/edit')) ?>" class="btn btn-sm btn-outline-dark" title="Modifier"><i class="bi bi-pencil"></i></a><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?php \App\Core\View::partial('pagination', ['p' => $p]); ?>
</div>
