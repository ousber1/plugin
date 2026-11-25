<?php /** Groupes de clients. Variables : $groups, $priceLists */ ?>
<div class="hoc-page-head">
  <div><h1 class="h3 fw-bold mb-1">Groupes de clients</h1><p class="text-body-secondary mb-0">Chaque groupe détermine une liste de prix et une remise par défaut.</p></div>
  <a href="<?= e(url('customers')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Clients</a>
</div>
<div class="card">
  <div class="table-responsive"><table class="table align-middle mb-0">
    <thead><tr><th>Nom</th><th>Liste de prix</th><th style="width:130px">Remise %</th><th>Description</th><th class="text-end">Clients</th><th></th></tr></thead>
    <tbody>
    <?php foreach (array_merge($groups, [null]) as $g): $fid = 'grp' . ($g['id'] ?? 'new'); ?>
      <tr<?= $g ? '' : ' class="table-light"' ?>>
        <td><input class="form-control form-control-sm" form="<?= $fid ?>" name="name" value="<?= e($g['name'] ?? '') ?>" placeholder="<?= $g ? '' : 'Nouveau groupe…' ?>" maxlength="100" required></td>
        <td><select class="form-select form-select-sm" form="<?= $fid ?>" name="price_list_id">
          <?php foreach ($priceLists as $pl): ?><option value="<?= (int) $pl['id'] ?>" <?= (int) ($g['price_list_id'] ?? 1) === (int) $pl['id'] ? 'selected' : '' ?>><?= e($pl['name']) ?></option><?php endforeach; ?>
        </select></td>
        <td><input class="form-control form-control-sm" form="<?= $fid ?>" name="discount_percent" inputmode="decimal" value="<?= e(number_fr($g['discount_percent'] ?? 0)) ?>"></td>
        <td><input class="form-control form-control-sm" form="<?= $fid ?>" name="description" value="<?= e($g['description'] ?? '') ?>" maxlength="255"></td>
        <td class="text-end"><?= $g ? (int) $g['customers_count'] : '' ?></td>
        <td class="text-end text-nowrap">
          <form method="post" action="<?= e(url('customers/groups')) ?>" id="<?= $fid ?>" class="d-inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) ($g['id'] ?? 0) ?>">
            <button class="btn btn-sm <?= $g ? 'btn-outline-dark' : 'btn-dark' ?>" title="Enregistrer"><i class="bi <?= $g ? 'bi-check2' : 'bi-plus-lg' ?>"></i></button></form>
          <?php if ($g && can('customers.delete')): ?><form method="post" action="<?= e(url('customers/groups/' . $g['id'] . '/delete')) ?>" class="d-inline" data-confirm="Supprimer ce groupe ?"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger" title="Supprimer"><i class="bi bi-trash"></i></button></form><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
</div>
