<?php
/**
 * BERRADI PRINT - Gestion des Devis
 */
$db = getDB();

// Changement statut
if (isset($_GET['action']) && $id) {
    $action_devis = clean($_GET['action']);
    if (in_array($action_devis, ['envoye', 'accepte', 'refuse'])) {
        $db->prepare("UPDATE devis SET statut = ? WHERE id = ?")->execute([$action_devis, $id]);
        setFlash('success', 'Statut du devis mis à jour.');
        redirect('index.php?page=devis');
    }
}

$filtre = clean($_GET['statut'] ?? '');
$sql = "SELECT * FROM devis WHERE 1=1";
$params = [];
if ($filtre) { $sql .= " AND statut = ?"; $params[] = $filtre; }
$sql .= " ORDER BY created_at DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$devis_list = $stmt->fetchAll();

$statuts_devis = ['brouillon' => 'secondary', 'envoye' => 'info', 'accepte' => 'success', 'refuse' => 'danger', 'expire' => 'warning'];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-file-earmark-text me-2"></i>Devis (<?= count($devis_list) ?>)</h4>
    <a href="index.php?page=devis_nouveau" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>Nouveau devis</a>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-2">
        <div class="d-flex gap-2">
            <a href="index.php?page=devis" class="btn btn-sm <?= !$filtre ? 'btn-primary' : 'btn-outline-primary' ?>">Tous</a>
            <?php foreach ($statuts_devis as $s => $c): ?>
            <a href="index.php?page=devis&statut=<?= $s ?>" class="btn btn-sm <?= $filtre === $s ? "btn-$c" : "btn-outline-$c" ?>"><?= ucfirst($s) ?></a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="bg-light"><tr><th>N° Devis</th><th>Client</th><th class="text-end">Total</th><th>Statut</th><th>Date</th><th>Actions</th></tr></thead>
            <tbody>
                <?php if (empty($devis_list)): ?>
                <tr><td colspan="6" class="text-center py-4 text-muted">Aucun devis</td></tr>
                <?php else: foreach ($devis_list as $d): ?>
                <tr>
                    <td class="fw-bold"><?= $d['numero_devis'] ?></td>
                    <td><?= $d['client_nom'] ?><br><small class="text-muted"><?= $d['client_telephone'] ?></small></td>
                    <td class="text-end fw-bold"><?= $d['total'] > 0 ? formatPrix($d['total']) : '-' ?></td>
                    <td><span class="badge bg-<?= $statuts_devis[$d['statut']] ?? 'secondary' ?>"><?= ucfirst($d['statut']) ?></span></td>
                    <td><small><?= dateFormatFr($d['created_at'], 'court') ?></small></td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <?php if ($d['statut'] === 'brouillon'): ?>
                            <a href="index.php?page=devis&action=envoye&id=<?= $d['id'] ?>" class="btn btn-outline-info" title="Marquer envoyé"><i class="bi bi-send"></i></a>
                            <?php endif; ?>
                            <?php if ($d['statut'] === 'envoye'): ?>
                            <a href="index.php?page=devis&action=accepte&id=<?= $d['id'] ?>" class="btn btn-outline-success" title="Accepté"><i class="bi bi-check"></i></a>
                            <a href="index.php?page=devis&action=refuse&id=<?= $d['id'] ?>" class="btn btn-outline-danger" title="Refusé"><i class="bi bi-x"></i></a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
