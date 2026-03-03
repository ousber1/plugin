<?php
/**
 * BERRADI PRINT - Gestion des Commandes
 */
$db = getDB();

// Filtres
$filtre_statut = clean($_GET['statut'] ?? '');
$filtre_paiement = clean($_GET['paiement'] ?? '');
$filtre_recherche = clean($_GET['q'] ?? '');
$filtre_date_du = clean($_GET['date_du'] ?? '');
$filtre_date_au = clean($_GET['date_au'] ?? '');

$sql = "SELECT c.*, (SELECT COUNT(*) FROM commande_lignes WHERE commande_id = c.id) as nb_articles FROM commandes c WHERE 1=1";
$params = [];

if ($filtre_statut) { $sql .= " AND c.statut = ?"; $params[] = $filtre_statut; }
if ($filtre_paiement) { $sql .= " AND c.statut_paiement = ?"; $params[] = $filtre_paiement; }
if ($filtre_recherche) {
    $sql .= " AND (c.numero_commande LIKE ? OR c.client_nom LIKE ? OR c.client_telephone LIKE ?)";
    $params[] = "%$filtre_recherche%"; $params[] = "%$filtre_recherche%"; $params[] = "%$filtre_recherche%";
}
if ($filtre_date_du) { $sql .= " AND DATE(c.created_at) >= ?"; $params[] = $filtre_date_du; }
if ($filtre_date_au) { $sql .= " AND DATE(c.created_at) <= ?"; $params[] = $filtre_date_au; }

$sql .= " ORDER BY c.created_at DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$commandes = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-cart-check me-2"></i>Commandes (<?= count($commandes) ?>)</h4>
    <a href="index.php?page=commande_nouvelle" class="btn btn-primary">
        <i class="bi bi-plus-circle me-1"></i>Nouvelle commande
    </a>
</div>

<!-- Filtres -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <input type="hidden" name="page" value="commandes">
            <div class="col-md-3">
                <label class="form-label small">Recherche</label>
                <input type="text" name="q" class="form-control form-control-sm" placeholder="N°, nom, tél..." value="<?= $filtre_recherche ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small">Statut</label>
                <select name="statut" class="form-select form-select-sm">
                    <option value="">Tous</option>
                    <option value="nouvelle" <?= $filtre_statut === 'nouvelle' ? 'selected' : '' ?>>Nouvelle</option>
                    <option value="confirmee" <?= $filtre_statut === 'confirmee' ? 'selected' : '' ?>>Confirmée</option>
                    <option value="en_production" <?= $filtre_statut === 'en_production' ? 'selected' : '' ?>>En production</option>
                    <option value="prete" <?= $filtre_statut === 'prete' ? 'selected' : '' ?>>Prête</option>
                    <option value="en_livraison" <?= $filtre_statut === 'en_livraison' ? 'selected' : '' ?>>En livraison</option>
                    <option value="livree" <?= $filtre_statut === 'livree' ? 'selected' : '' ?>>Livrée</option>
                    <option value="annulee" <?= $filtre_statut === 'annulee' ? 'selected' : '' ?>>Annulée</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small">Paiement</label>
                <select name="paiement" class="form-select form-select-sm">
                    <option value="">Tous</option>
                    <option value="en_attente" <?= $filtre_paiement === 'en_attente' ? 'selected' : '' ?>>En attente</option>
                    <option value="paye" <?= $filtre_paiement === 'paye' ? 'selected' : '' ?>>Payé</option>
                    <option value="partiel" <?= $filtre_paiement === 'partiel' ? 'selected' : '' ?>>Partiel</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small">Du</label>
                <input type="date" name="date_du" class="form-control form-control-sm" value="<?= $filtre_date_du ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small">Au</label>
                <input type="date" name="date_au" class="form-control form-control-sm" value="<?= $filtre_date_au ?>">
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-search"></i></button>
            </div>
        </form>
    </div>
</div>

<!-- Liste des commandes -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="bg-light">
                    <tr>
                        <th>N° Commande</th>
                        <th>Client</th>
                        <th>Articles</th>
                        <th class="text-end">Total</th>
                        <th>Statut</th>
                        <th>Paiement</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($commandes)): ?>
                    <tr><td colspan="8" class="text-center py-4 text-muted">Aucune commande trouvée</td></tr>
                    <?php else: foreach ($commandes as $cmd):
                        $st = statutCommande($cmd['statut']);
                        $sp = statutPaiement($cmd['statut_paiement']);
                        $pr = prioriteLabel($cmd['priorite']);
                    ?>
                    <tr>
                        <td>
                            <a href="index.php?page=commande_detail&id=<?= $cmd['id'] ?>" class="fw-bold text-decoration-none">
                                <?= $cmd['numero_commande'] ?>
                            </a>
                            <?php if ($cmd['priorite'] !== 'normale'): ?>
                            <span class="badge bg-<?= $pr['class'] ?> ms-1"><?= $pr['label'] ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="fw-semibold"><?= $cmd['client_nom'] ?></div>
                            <small class="text-muted"><i class="bi bi-telephone me-1"></i><?= $cmd['client_telephone'] ?></small>
                        </td>
                        <td><span class="badge bg-light text-dark"><?= $cmd['nb_articles'] ?></span></td>
                        <td class="text-end fw-bold"><?= formatPrix($cmd['total']) ?></td>
                        <td><span class="badge bg-<?= $st['class'] ?>"><?= $st['label'] ?></span></td>
                        <td><span class="badge bg-<?= $sp['class'] ?>"><?= $sp['label'] ?></span></td>
                        <td><small><?= dateFormatFr($cmd['created_at'], 'court') ?></small></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="index.php?page=commande_detail&id=<?= $cmd['id'] ?>" class="btn btn-outline-primary" title="Détails">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
