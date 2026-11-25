<?php /** Liste des utilisateurs. Variables : $users, $roles, $p, $filters */ ?>
<div class="hoc-page-head">
  <div>
    <h1 class="h3 fw-bold mb-1">Utilisateurs</h1>
    <p class="text-body-secondary mb-0">Comptes d'accès et rôles de l'équipe.</p>
  </div>
  <?php if (can('users.manage')): ?>
    <a href="<?= e(url('users/create')) ?>" class="btn btn-dark"><i class="bi bi-person-plus"></i> Nouvel utilisateur</a>
  <?php endif; ?>
</div>

<div class="card">
  <form class="card-body hoc-filters border-bottom" method="get" action="<?= e(url('users')) ?>"><?= route_field('users') ?>
    <div class="flex-grow-1"><input class="form-control" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Rechercher : nom, identifiant, e-mail, téléphone…" aria-label="Rechercher"></div>
    <select class="form-select w-auto" name="role" aria-label="Rôle">
      <option value="0">Tous les rôles</option>
      <?php foreach ($roles as $r): ?><option value="<?= (int) $r['id'] ?>" <?= $filters['role'] === (int) $r['id'] ? 'selected' : '' ?>><?= e($r['name']) ?></option><?php endforeach; ?>
    </select>
    <select class="form-select w-auto" name="status" aria-label="Statut">
      <option value="">Tous les statuts</option>
      <?php foreach (['active' => 'Actifs', 'inactive' => 'Désactivés', 'locked' => 'Verrouillés'] as $v => $l): ?>
        <option value="<?= $v ?>" <?= $filters['status'] === $v ? 'selected' : '' ?>><?= $l ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-outline-dark"><i class="bi bi-funnel"></i> Filtrer</button>
    <?php if ($filters['q'] !== '' || $filters['role'] || $filters['status'] !== ''): ?><a class="btn btn-link" href="<?= e(url('users')) ?>">Réinitialiser</a><?php endif; ?>
  </form>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead><tr><th>Utilisateur</th><th>Rôle</th><th>Statut</th><th>Dernière connexion</th><th class="text-end">Actions</th></tr></thead>
      <tbody>
      <?php if (!$users): ?><tr><td colspan="5" class="hoc-empty"><i class="bi bi-people"></i>Aucun utilisateur trouvé.</td></tr><?php endif; ?>
      <?php foreach ($users as $u):
        $locked = $u['locked_until'] !== null && strtotime((string) $u['locked_until']) > time();
        $self = (int) $u['id'] === (int) auth_user()['id']; ?>
        <tr>
          <td>
            <div class="fw-semibold"><?= e($u['full_name']) ?><?= $self ? ' <span class="badge text-bg-light border">vous</span>' : '' ?></div>
            <small class="text-body-secondary"><?= e($u['username']) ?> · <?= e($u['email']) ?><?= $u['phone'] ? ' · ' . e($u['phone']) : '' ?></small>
          </td>
          <td><span class="badge <?= $u['role_code'] === 'admin' ? 'text-bg-dark' : 'text-bg-light border' ?>"><?= e($u['role_name']) ?></span></td>
          <td>
            <?php if ($locked): ?><span class="badge text-bg-warning"><i class="bi bi-lock"></i> Verrouillé</span>
            <?php elseif ($u['is_active']): ?><span class="badge text-bg-success"><i class="bi bi-check"></i> Actif</span>
            <?php else: ?><span class="badge text-bg-secondary"><i class="bi bi-slash-circle"></i> Désactivé</span><?php endif; ?>
          </td>
          <td class="small text-body-secondary"><?= $u['last_login_at'] ? e(format_date($u['last_login_at'], true)) : 'Jamais' ?></td>
          <td class="text-end text-nowrap">
            <?php if (can('users.manage')): ?>
              <a href="<?= e(url('users/' . $u['id'] . '/edit')) ?>" class="btn btn-sm btn-outline-dark" title="Modifier"><i class="bi bi-pencil"></i><span class="visually-hidden">Modifier</span></a>
              <?php if ($locked): ?>
                <form method="post" action="<?= e(url('users/' . $u['id'] . '/unlock')) ?>" class="d-inline"><?= csrf_field() ?>
                  <button class="btn btn-sm btn-outline-warning" title="Déverrouiller"><i class="bi bi-unlock"></i><span class="visually-hidden">Déverrouiller</span></button></form>
              <?php endif; ?>
              <?php if (!$self): ?>
                <form method="post" action="<?= e(url('users/' . $u['id'] . '/toggle')) ?>" class="d-inline" data-confirm="<?= $u['is_active'] ? 'Désactiver ce compte ? L\'utilisateur ne pourra plus se connecter.' : 'Réactiver ce compte ?' ?>"><?= csrf_field() ?>
                  <button class="btn btn-sm btn-outline-secondary" title="<?= $u['is_active'] ? 'Désactiver' : 'Activer' ?>"><i class="bi bi-<?= $u['is_active'] ? 'pause-circle' : 'play-circle' ?>"></i><span class="visually-hidden"><?= $u['is_active'] ? 'Désactiver' : 'Activer' ?></span></button></form>
                <form method="post" action="<?= e(url('users/' . $u['id'] . '/delete')) ?>" class="d-inline" data-confirm="Supprimer définitivement l'accès de <?= e($u['full_name']) ?> ? Son historique sera conservé."><?= csrf_field() ?>
                  <button class="btn btn-sm btn-outline-danger" title="Supprimer"><i class="bi bi-trash"></i><span class="visually-hidden">Supprimer</span></button></form>
              <?php endif; ?>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php \App\Core\View::partial('pagination', ['p' => $p]); ?>
</div>
