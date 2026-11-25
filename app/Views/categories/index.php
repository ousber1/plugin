<?php /** Catégories. Variables : $categories, $parents */
$types = \App\Controllers\CategoryController::TYPES;
$canEdit = can('products.edit'); ?>
<div class="hoc-page-head">
  <div><h1 class="h3 fw-bold mb-1">Catégories</h1><p class="text-body-secondary mb-0">Organisation du catalogue, des filtres du POS et des rapports par catégorie.</p></div>
  <a href="<?= e(url('products')) ?>" class="btn btn-outline-secondary"><i class="bi bi-box"></i> Catalogue</a>
</div>
<div class="card">
  <div class="table-responsive"><table class="table align-middle mb-0">
    <thead><tr><th style="width:56px">Coul.</th><th>Nom</th><th>Parente</th><th>Type</th><th style="width:150px">Icône</th><th style="width:80px">Ordre</th><th class="text-center">Active</th><th class="text-end">Produits</th><th></th></tr></thead>
    <tbody>
    <?php foreach (array_merge($categories, $canEdit ? [null] : []) as $c): $fid = 'cat' . ($c['id'] ?? 'new'); $dis = $canEdit ? '' : 'disabled'; ?>
      <tr<?= $c ? '' : ' class="table-light"' ?>>
        <td><input type="color" class="form-control form-control-color form-control-sm" form="<?= $fid ?>" name="color" value="<?= e($c['color'] ?? '#6c757d') ?>" <?= $dis ?> aria-label="Couleur"></td>
        <td><div class="d-flex align-items-center gap-2"><?= $c && $c['parent_id'] ? '<i class="bi bi-arrow-return-right text-body-secondary"></i>' : '' ?>
          <?php if ($c && $c['icon']): ?><i class="bi <?= e($c['icon']) ?>" style="color:<?= e($c['color']) ?>"></i><?php endif; ?>
          <input class="form-control form-control-sm" form="<?= $fid ?>" name="name" value="<?= e($c['name'] ?? '') ?>" placeholder="<?= $c ? '' : 'Nouvelle catégorie…' ?>" maxlength="100" required <?= $dis ?>></div></td>
        <td><select class="form-select form-select-sm" form="<?= $fid ?>" name="parent_id" <?= $dis ?>><option value="0">—</option>
          <?php foreach ($parents as $pa): if ($c && (int) $pa['id'] === (int) $c['id']) { continue; } ?><option value="<?= (int) $pa['id'] ?>" <?= (int) ($c['parent_id'] ?? 0) === (int) $pa['id'] ? 'selected' : '' ?>><?= e($pa['name']) ?></option><?php endforeach; ?></select></td>
        <td><select class="form-select form-select-sm" form="<?= $fid ?>" name="type" <?= $dis ?>><?php foreach ($types as $k => $l): ?><option value="<?= $k ?>" <?= ($c['type'] ?? 'both') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></td>
        <td><input class="form-control form-control-sm" form="<?= $fid ?>" name="icon" value="<?= e($c['icon'] ?? '') ?>" placeholder="bi-printer" maxlength="43" <?= $dis ?>></td>
        <td><input class="form-control form-control-sm" type="number" form="<?= $fid ?>" name="sort_order" value="<?= (int) ($c['sort_order'] ?? 0) ?>" <?= $dis ?>></td>
        <td class="text-center"><input class="form-check-input" type="checkbox" form="<?= $fid ?>" name="is_active" value="1" <?= ($c['is_active'] ?? 1) ? 'checked' : '' ?> <?= $dis ?> aria-label="Active"></td>
        <td class="text-end"><?php if ($c): ?><a href="<?= e(url('products', ['category' => $c['id']])) ?>"><?= (int) $c['products_count'] ?></a><?php endif; ?></td>
        <td class="text-end text-nowrap"><?php if ($canEdit): ?>
          <form method="post" action="<?= e(url('categories')) ?>" id="<?= $fid ?>" class="d-inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) ($c['id'] ?? 0) ?>">
            <button class="btn btn-sm <?= $c ? 'btn-outline-dark' : 'btn-dark' ?>" title="Enregistrer"><i class="bi <?= $c ? 'bi-check2' : 'bi-plus-lg' ?>"></i></button></form>
          <?php if ($c && can('products.delete')): ?><form method="post" action="<?= e(url('categories/' . $c['id'] . '/delete')) ?>" class="d-inline" data-confirm="Supprimer la catégorie « <?= e($c['name']) ?> » ?"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger" title="Supprimer"><i class="bi bi-trash"></i></button></form><?php endif; ?>
        <?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <div class="card-footer bg-transparent small text-body-secondary">Icônes : noms <a href="https://icons.getbootstrap.com/" target="_blank" rel="noopener">Bootstrap Icons</a> (ex. <code>bi-printer</code>, <code>bi-scissors</code>). Une catégorie contenant des produits ne peut pas être supprimée : désactivez-la.</div>
</div>
