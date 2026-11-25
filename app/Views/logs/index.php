<?php /** Journal d'activité. Variables : $logs, $p, $filters, $users, $modules, $actions */ ?>
<div class="hoc-page-head">
  <div>
    <h1 class="h3 fw-bold mb-1">Journal d'activité</h1>
    <p class="text-body-secondary mb-0">Traçabilité de toutes les actions sensibles (lecture seule).</p>
  </div>
</div>
<div class="card">
  <form class="card-body hoc-filters border-bottom" method="get" action="<?= e(url('logs')) ?>"><?= route_field('logs') ?>
    <div class="flex-grow-1"><label class="form-label small mb-1" for="q">Recherche</label>
      <input class="form-control" id="q" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Description ou adresse IP"></div>
    <div><label class="form-label small mb-1" for="user">Utilisateur</label>
      <select class="form-select" id="user" name="user"><option value="0">Tous</option>
        <?php foreach ($users as $u): ?><option value="<?= (int) $u['id'] ?>" <?= $filters['user'] === (int) $u['id'] ? 'selected' : '' ?>><?= e($u['full_name']) ?></option><?php endforeach; ?>
      </select></div>
    <div><label class="form-label small mb-1" for="module">Module</label>
      <select class="form-select" id="module" name="module"><option value="">Tous</option>
        <?php foreach ($modules as $m): ?><option value="<?= e($m) ?>" <?= $filters['module'] === $m ? 'selected' : '' ?>><?= e($m) ?></option><?php endforeach; ?>
      </select></div>
    <div><label class="form-label small mb-1" for="action">Action</label>
      <select class="form-select" id="action" name="action"><option value="">Toutes</option>
        <?php foreach ($actions as $a): ?><option value="<?= e($a) ?>" <?= $filters['action'] === $a ? 'selected' : '' ?>><?= e($a) ?></option><?php endforeach; ?>
      </select></div>
    <div><label class="form-label small mb-1" for="from">Du</label><input class="form-control" type="date" id="from" name="from" value="<?= e($filters['from']) ?>"></div>
    <div><label class="form-label small mb-1" for="to">Au</label><input class="form-control" type="date" id="to" name="to" value="<?= e($filters['to']) ?>"></div>
    <div><button class="btn btn-outline-dark"><i class="bi bi-funnel"></i> Filtrer</button></div>
  </form>
  <div class="table-responsive">
    <table class="table table-sm table-hover align-middle mb-0">
      <thead><tr><th>Date</th><th>Utilisateur</th><th>Module</th><th>Action</th><th>Description</th><th>IP</th><th></th></tr></thead>
      <tbody>
      <?php if (!$logs): ?><tr><td colspan="7" class="hoc-empty"><i class="bi bi-journal"></i>Aucune entrée.</td></tr><?php endif; ?>
      <?php foreach ($logs as $l): $hasDiff = $l['old_values'] !== null || $l['new_values'] !== null; ?>
        <tr>
          <td class="text-nowrap small"><?= e(format_date($l['created_at'], true)) ?></td>
          <td class="small"><?= e($l['full_name'] ?? 'Système') ?></td>
          <td><span class="badge text-bg-light border"><?= e($l['module']) ?></span></td>
          <td class="small"><code><?= e($l['action']) ?></code></td>
          <td class="small"><?= e($l['description']) ?><?= $l['entity_type'] ? ' <span class="text-body-secondary">(' . e($l['entity_type']) . ($l['entity_id'] ? ' #' . (int) $l['entity_id'] : '') . ')</span>' : '' ?></td>
          <td class="small"><code><?= e($l['ip_address']) ?></code></td>
          <td><?php if ($hasDiff): ?><button class="btn btn-sm btn-link p-0" type="button" data-bs-toggle="collapse" data-bs-target="#log<?= (int) $l['id'] ?>">Détail</button><?php endif; ?></td>
        </tr>
        <?php if ($hasDiff): ?>
          <tr class="collapse" id="log<?= (int) $l['id'] ?>"><td colspan="7" class="bg-body-tertiary">
            <div class="row g-2 small">
              <div class="col-md-6"><strong>Avant</strong><pre class="mb-0 text-wrap"><?= e(json_encode(json_decode((string) $l['old_values'], true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre></div>
              <div class="col-md-6"><strong>Après</strong><pre class="mb-0 text-wrap"><?= e(json_encode(json_decode((string) $l['new_values'], true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre></div>
            </div>
          </td></tr>
        <?php endif; ?>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php \App\Core\View::partial('pagination', ['p' => $p]); ?>
</div>
