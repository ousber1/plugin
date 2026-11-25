<?php /** Journal des mouvements. Variables : $movements, $f, $p, $itemName */ ?>
<div class="hoc-page-head">
  <div><h1 class="h3 fw-bold mb-1">Mouvements de stock</h1><p class="text-body-secondary mb-0"><?= $itemName ? 'Historique de « ' . e($itemName) . ' »' : 'Journal complet et non modifiable des entrées et sorties.' ?></p></div>
  <div class="d-flex gap-2">
    <a href="<?= e(url('stock/movements/export', current_query())) ?>" class="btn btn-outline-secondary"><i class="bi bi-download"></i> Exporter</a>
    <?php if (can('stock.movements')): ?><a href="<?= e(url('stock/movements/create', array_filter(['product' => $f['product'], 'material' => $f['material']]))) ?>" class="btn btn-dark"><i class="bi bi-plus-lg"></i> Nouveau mouvement</a><?php endif; ?>
  </div>
</div>
<div class="card">
  <form class="card-body hoc-filters border-bottom" method="get" action="<?= e(url('stock/movements')) ?>"><?= route_field('stock/movements') ?>
    <?php if ($f['product']): ?><input type="hidden" name="product" value="<?= (int) $f['product'] ?>"><?php endif; ?>
    <?php if ($f['material']): ?><input type="hidden" name="material" value="<?= (int) $f['material'] ?>"><?php endif; ?>
    <div><label class="form-label small mb-1" for="kind">Nature</label><select class="form-select" id="kind" name="kind"><option value="">Toutes</option><option value="material" <?= $f['kind'] === 'material' ? 'selected' : '' ?>>Matières</option><option value="product" <?= $f['kind'] === 'product' ? 'selected' : '' ?>>Produits</option></select></div>
    <div><label class="form-label small mb-1" for="type">Mouvement</label><select class="form-select" id="type" name="type"><option value="">Tous</option>
      <?php foreach (\App\Services\StockService::TYPES as $k => [$l]): ?><option value="<?= $k ?>" <?= $f['type'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div><label class="form-label small mb-1" for="from">Du</label><input class="form-control" type="date" id="from" name="from" value="<?= e($f['from']) ?>"></div>
    <div><label class="form-label small mb-1" for="to">Au</label><input class="form-control" type="date" id="to" name="to" value="<?= e($f['to']) ?>"></div>
    <div><button class="btn btn-outline-dark"><i class="bi bi-funnel"></i> Filtrer</button>
      <?php if ($f['product'] || $f['material']): ?><a class="btn btn-link" href="<?= e(url('stock/movements')) ?>">Tous les articles</a><?php endif; ?></div>
  </form>
  <?php \App\Core\View::partial('stock_movements', ['movements' => $movements, 'showItem' => true]); ?>
  <?php \App\Core\View::partial('pagination', ['p' => $p]); ?>
</div>
