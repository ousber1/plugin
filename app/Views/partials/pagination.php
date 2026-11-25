<?php /** Pagination. Variable : $p (App\Core\Paginator) */ ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 px-3 py-2 border-top small">
  <span class="text-body-secondary"><?= $p->total ? e($p->from()) . '–' . e($p->to()) . ' sur ' . e($p->total) : 'Aucun résultat' ?></span>
  <?php if ($p->pages() > 1): ?>
    <nav aria-label="Pagination">
      <ul class="pagination pagination-sm mb-0">
        <li class="page-item<?= $p->page <= 1 ? ' disabled' : '' ?>"><a class="page-link" href="<?= e($p->url(max(1, $p->page - 1))) ?>" aria-label="Précédent">&laquo;</a></li>
        <?php foreach ($p->window() as $n): ?>
          <?php if ($n === null): ?><li class="page-item disabled"><span class="page-link">…</span></li>
          <?php else: ?><li class="page-item<?= $n === $p->page ? ' active' : '' ?>"><a class="page-link" href="<?= e($p->url($n)) ?>"<?= $n === $p->page ? ' aria-current="page"' : '' ?>><?= $n ?></a></li><?php endif; ?>
        <?php endforeach; ?>
        <li class="page-item<?= $p->page >= $p->pages() ? ' disabled' : '' ?>"><a class="page-link" href="<?= e($p->url(min($p->pages(), $p->page + 1))) ?>" aria-label="Suivant">&raquo;</a></li>
      </ul>
    </nav>
  <?php endif; ?>
</div>
