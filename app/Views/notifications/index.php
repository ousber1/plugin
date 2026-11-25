<?php /** Centre de notifications. Variables : $notifications, $unreadOnly, $unread, $p */
$icons = ['info' => 'info-circle', 'success' => 'check-circle', 'warning' => 'exclamation-triangle', 'danger' => 'exclamation-octagon']; ?>
<div class="hoc-page-head">
  <div>
    <h1 class="h3 fw-bold mb-1">Notifications</h1>
    <p class="text-body-secondary mb-0"><?= (int) $unread ?> non lue(s).</p>
  </div>
  <?php if ($unread): ?>
    <form method="post" action="<?= e(url('notifications/read-all')) ?>"><?= csrf_field() ?>
      <button class="btn btn-outline-dark"><i class="bi bi-check2-all"></i> Tout marquer comme lu</button></form>
  <?php endif; ?>
</div>
<ul class="nav nav-pills mb-3">
  <li class="nav-item"><a class="nav-link<?= !$unreadOnly ? ' active' : '' ?>" href="<?= e(url('notifications')) ?>">Toutes</a></li>
  <li class="nav-item"><a class="nav-link<?= $unreadOnly ? ' active' : '' ?>" href="<?= e(url('notifications', ['filter' => 'unread'])) ?>">Non lues</a></li>
</ul>
<div class="card">
  <?php if (!$notifications): ?>
    <div class="hoc-empty"><i class="bi bi-bell-slash"></i>Aucune notification<?= $unreadOnly ? ' non lue' : '' ?>.
      <div class="small mt-1">Les alertes (stock faible, factures en retard, échéances de production, maintenance…) apparaîtront ici.</div></div>
  <?php else: ?>
    <ul class="list-group list-group-flush">
      <?php foreach ($notifications as $n): $sev = isset($icons[$n['severity']]) ? $n['severity'] : 'info'; ?>
        <li class="list-group-item d-flex gap-3 align-items-start<?= $n['is_read'] ? '' : ' hoc-notif-item unread border-0' ?>">
          <i class="bi bi-<?= $icons[$sev] ?> text-<?= $sev ?> fs-5"></i>
          <div class="flex-grow-1">
            <div class="fw-semibold"><?= e($n['title']) ?></div>
            <?php if ($n['message']): ?><div class="small"><?= e($n['message']) ?></div><?php endif; ?>
            <small class="text-body-secondary"><?= e(format_date($n['created_at'], true)) ?></small>
          </div>
          <?php if ($n['link'] && preg_match('#^[a-z0-9_\-/?=&]+$#i', $n['link'])): ?>
            <a class="btn btn-sm btn-outline-dark" href="<?= e(url($n['link'])) ?>">Ouvrir</a>
          <?php endif; ?>
          <?php if (!$n['is_read']): ?>
            <form method="post" action="<?= e(url('notifications/' . $n['id'] . '/read')) ?>"><?= csrf_field() ?>
              <button class="btn btn-sm btn-outline-secondary" title="Marquer comme lue"><i class="bi bi-check2"></i><span class="visually-hidden">Marquer comme lue</span></button></form>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
  <?php \App\Core\View::partial('pagination', ['p' => $p]); ?>
</div>
