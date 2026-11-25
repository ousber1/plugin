<?php /** Rôles. Variables : $roles, $totalPermissions */ ?>
<div class="hoc-page-head">
  <div>
    <h1 class="h3 fw-bold mb-1">Rôles et permissions</h1>
    <p class="text-body-secondary mb-0">Définissez ce que chaque profil peut voir et faire.</p>
  </div>
  <?php if (can('users.manage')): ?>
    <a href="<?= e(url('roles/create')) ?>" class="btn btn-dark"><i class="bi bi-plus-lg"></i> Nouveau rôle</a>
  <?php endif; ?>
</div>
<div class="row g-3">
  <?php foreach ($roles as $r): $isAdmin = $r['code'] === 'admin'; ?>
    <div class="col-md-6 col-xl-4">
      <div class="card h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start mb-2">
            <h2 class="h5 fw-bold mb-0"><?= e($r['name']) ?></h2>
            <?= $r['is_system'] ? '<span class="badge text-bg-light border">Système</span>' : '<span class="badge text-bg-info">Personnalisé</span>' ?>
          </div>
          <p class="small text-body-secondary mb-3"><?= e($r['description'] ?: '—') ?></p>
          <div class="hoc-mini"><span>Utilisateurs</span><strong><?= (int) $r['users_count'] ?></strong></div>
          <div class="hoc-mini"><span>Permissions</span><strong><?= $isAdmin ? 'Toutes' : (int) $r['perms_count'] . ' / ' . (int) $totalPermissions ?></strong></div>
          <div class="progress mt-2" style="height:4px" aria-hidden="true"><div class="progress-bar hoc-bar" style="width:<?= $isAdmin ? 100 : round($r['perms_count'] / max(1, $totalPermissions) * 100) ?>%"></div></div>
        </div>
        <?php if (can('users.manage')): ?>
          <div class="card-footer bg-transparent d-flex gap-2">
            <a href="<?= e(url('roles/' . $r['id'] . '/edit')) ?>" class="btn btn-sm btn-outline-dark"><i class="bi bi-pencil"></i> <?= $isAdmin ? 'Voir' : 'Modifier' ?></a>
            <?php if (!$r['is_system']): ?>
              <form method="post" action="<?= e(url('roles/' . $r['id'] . '/delete')) ?>" data-confirm="Supprimer le rôle « <?= e($r['name']) ?> » ?"><?= csrf_field() ?>
                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> Supprimer</button></form>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>
