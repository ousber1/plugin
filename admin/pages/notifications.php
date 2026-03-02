<?php
/**
 * BERRADI PRINT - Notifications
 */
$db = getDB();

// Marquer comme lue
if (isset($_GET['action']) && $_GET['action'] === 'lire' && $id) {
    $db->prepare("UPDATE notifications SET lue = 1 WHERE id = ?")->execute([$id]);
    $stmt = $db->prepare("SELECT lien FROM notifications WHERE id = ?");
    $stmt->execute([$id]);
    $n = $stmt->fetch();
    redirect($n && $n['lien'] ? $n['lien'] : 'index.php?page=notifications');
}

// Tout marquer comme lu
if (isset($_GET['action']) && $_GET['action'] === 'tout_lire') {
    $db->query("UPDATE notifications SET lue = 1 WHERE lue = 0");
    setFlash('success', 'Toutes les notifications marquées comme lues.');
    redirect('index.php?page=notifications');
}

$notifs = $db->query("SELECT * FROM notifications ORDER BY created_at DESC LIMIT 100")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-bell me-2"></i>Notifications</h4>
    <a href="index.php?page=notifications&action=tout_lire" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-check-all me-1"></i>Tout marquer comme lu
    </a>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <?php if (empty($notifs)): ?>
        <div class="text-center py-5 text-muted"><i class="bi bi-bell-slash fs-1"></i><p class="mt-2">Aucune notification</p></div>
        <?php else: ?>
        <div class="list-group list-group-flush">
            <?php foreach ($notifs as $n): ?>
            <a href="index.php?page=notifications&action=lire&id=<?= $n['id'] ?>" class="list-group-item list-group-item-action <?= !$n['lue'] ? 'bg-light' : '' ?>">
                <div class="d-flex justify-content-between">
                    <div>
                        <?php if (!$n['lue']): ?><span class="badge bg-primary me-2">Nouveau</span><?php endif; ?>
                        <strong><?= $n['titre'] ?></strong>
                        <?php if ($n['message']): ?><div class="small text-muted"><?= $n['message'] ?></div><?php endif; ?>
                    </div>
                    <small class="text-muted"><?= dateFormatFr($n['created_at'], 'complet') ?></small>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
