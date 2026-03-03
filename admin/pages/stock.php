<?php
/**
 * BERRADI PRINT - Gestion de Stock
 */
$db = getDB();

// Auto-create stock columns + table
try {
    $db->exec("ALTER TABLE produits ADD COLUMN IF NOT EXISTS stock_quantite INT DEFAULT 0");
    $db->exec("ALTER TABLE produits ADD COLUMN IF NOT EXISTS stock_min INT DEFAULT 0");
    $db->exec("ALTER TABLE produits ADD COLUMN IF NOT EXISTS stock_actif TINYINT(1) DEFAULT 0");
    $db->exec("CREATE TABLE IF NOT EXISTS stock_mouvements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        produit_id INT NOT NULL,
        type_mouvement ENUM('entree','sortie','ajustement','commande','retour') NOT NULL,
        quantite INT NOT NULL,
        quantite_avant INT DEFAULT 0,
        quantite_apres INT DEFAULT 0,
        reference VARCHAR(100) DEFAULT NULL,
        motif VARCHAR(255) DEFAULT NULL,
        fournisseur VARCHAR(200) DEFAULT NULL,
        cout_unitaire DECIMAL(10,2) DEFAULT NULL,
        notes TEXT,
        admin_id INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (produit_id) REFERENCES produits(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");
} catch (Exception $e) {}

$tab = $_GET['tab'] ?? 'stock';
$filtre = $_GET['filtre'] ?? '';

// Quick stock adjustment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajustement_rapide'])) {
    verifyCsrf();
    $pid = (int)$_POST['produit_id'];
    $new_qty = (int)$_POST['nouvelle_quantite'];
    $motif = clean($_POST['motif'] ?? 'Ajustement manuel');

    $stmt = $db->prepare("SELECT stock_quantite, nom FROM produits WHERE id = ?");
    $stmt->execute([$pid]);
    $p = $stmt->fetch();
    if ($p) {
        $old_qty = (int)$p['stock_quantite'];
        $diff = $new_qty - $old_qty;
        $type = $diff >= 0 ? 'entree' : 'sortie';
        if ($diff === 0) $type = 'ajustement';

        $db->prepare("UPDATE produits SET stock_quantite = ? WHERE id = ?")->execute([$new_qty, $pid]);
        $db->prepare("INSERT INTO stock_mouvements (produit_id, type_mouvement, quantite, quantite_avant, quantite_apres, motif, admin_id) VALUES (?,?,?,?,?,?,?)")
           ->execute([$pid, $type, abs($diff), $old_qty, $new_qty, $motif, $admin['id']]);

        setFlash('success', "Stock de <strong>" . htmlspecialchars($p['nom']) . "</strong> ajusté: $old_qty → $new_qty");
    }
    redirect('index.php?page=stock&tab=' . $tab);
}

// Toggle stock tracking for a product
if (isset($_GET['action']) && $_GET['action'] === 'toggle_stock' && $id) {
    $db->prepare("UPDATE produits SET stock_actif = NOT stock_actif WHERE id = ?")->execute([$id]);
    setFlash('success', 'Suivi de stock mis à jour.');
    redirect('index.php?page=stock');
}

// Stock overview
if ($filtre === 'alerte') {
    $produits = $db->query("SELECT p.*, c.nom as cat_nom FROM produits p LEFT JOIN categories c ON p.categorie_id = c.id WHERE p.stock_actif = 1 AND p.stock_quantite <= p.stock_min AND p.actif = 1 ORDER BY p.stock_quantite ASC")->fetchAll();
} elseif ($filtre === 'rupture') {
    $produits = $db->query("SELECT p.*, c.nom as cat_nom FROM produits p LEFT JOIN categories c ON p.categorie_id = c.id WHERE p.stock_actif = 1 AND p.stock_quantite = 0 AND p.actif = 1 ORDER BY p.nom")->fetchAll();
} else {
    $produits = $db->query("SELECT p.*, c.nom as cat_nom FROM produits p LEFT JOIN categories c ON p.categorie_id = c.id WHERE p.actif = 1 ORDER BY p.stock_actif DESC, p.nom")->fetchAll();
}

// Stats
$total_tracked = $db->query("SELECT COUNT(*) FROM produits WHERE stock_actif = 1 AND actif = 1")->fetchColumn();
$total_alerte = $db->query("SELECT COUNT(*) FROM produits WHERE stock_actif = 1 AND stock_quantite <= stock_min AND actif = 1")->fetchColumn();
$total_rupture = $db->query("SELECT COUNT(*) FROM produits WHERE stock_actif = 1 AND stock_quantite = 0 AND actif = 1")->fetchColumn();
$total_value = $db->query("SELECT COALESCE(SUM(stock_quantite * prix_base), 0) FROM produits WHERE stock_actif = 1 AND actif = 1")->fetchColumn();

// Recent movements
$mouvements = [];
if ($tab === 'historique') {
    $mouvements = $db->query("SELECT sm.*, p.nom as produit_nom, a.prenom as admin_prenom
        FROM stock_mouvements sm
        LEFT JOIN produits p ON sm.produit_id = p.id
        LEFT JOIN admins a ON sm.admin_id = a.id
        ORDER BY sm.created_at DESC LIMIT 100")->fetchAll();
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-box-seam me-2"></i>Gestion de Stock</h4>
    <a href="index.php?page=stock_mouvement" class="btn btn-primary">
        <i class="bi bi-plus-circle me-1"></i>Nouveau mouvement
    </a>
</div>

<!-- Stats Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle bg-primary bg-opacity-10 p-3 me-3"><i class="bi bi-box-seam text-primary fs-4"></i></div>
                    <div>
                        <div class="text-muted small">Produits suivis</div>
                        <div class="fw-bold fs-5"><?= $total_tracked ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle bg-warning bg-opacity-10 p-3 me-3"><i class="bi bi-exclamation-triangle text-warning fs-4"></i></div>
                    <div>
                        <div class="text-muted small">Alertes stock</div>
                        <div class="fw-bold fs-5 <?= $total_alerte > 0 ? 'text-warning' : '' ?>"><?= $total_alerte ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle bg-danger bg-opacity-10 p-3 me-3"><i class="bi bi-x-circle text-danger fs-4"></i></div>
                    <div>
                        <div class="text-muted small">Ruptures</div>
                        <div class="fw-bold fs-5 <?= $total_rupture > 0 ? 'text-danger' : '' ?>"><?= $total_rupture ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle bg-success bg-opacity-10 p-3 me-3"><i class="bi bi-currency-dollar text-success fs-4"></i></div>
                    <div>
                        <div class="text-muted small">Valeur du stock</div>
                        <div class="fw-bold fs-5"><?= formatPrix($total_value) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tabs -->
<ul class="nav nav-tabs mb-4">
    <li class="nav-item"><a class="nav-link <?= $tab === 'stock' ? 'active' : '' ?>" href="?page=stock&tab=stock">Niveaux de stock</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab === 'historique' ? 'active' : '' ?>" href="?page=stock&tab=historique">Historique mouvements</a></li>
</ul>

<?php if ($tab === 'stock'): ?>
<!-- Filters -->
<div class="d-flex gap-2 mb-3">
    <a href="?page=stock&tab=stock" class="btn btn-sm <?= !$filtre ? 'btn-primary' : 'btn-outline-primary' ?>">Tous</a>
    <a href="?page=stock&tab=stock&filtre=alerte" class="btn btn-sm <?= $filtre === 'alerte' ? 'btn-warning' : 'btn-outline-warning' ?>">
        <i class="bi bi-exclamation-triangle me-1"></i>Alertes (<?= $total_alerte ?>)
    </a>
    <a href="?page=stock&tab=stock&filtre=rupture" class="btn btn-sm <?= $filtre === 'rupture' ? 'btn-danger' : 'btn-outline-danger' ?>">
        <i class="bi bi-x-circle me-1"></i>Ruptures (<?= $total_rupture ?>)
    </a>
</div>

<!-- Stock Table -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="bg-light">
                    <tr>
                        <th>Produit</th>
                        <th>Catégorie</th>
                        <th class="text-center">Stock actuel</th>
                        <th class="text-center">Stock min</th>
                        <th class="text-center">Statut</th>
                        <th class="text-center">Suivi</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($produits)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">Aucun produit trouvé.</td></tr>
                    <?php else: ?>
                    <?php foreach ($produits as $p): ?>
                    <tr class="<?= ($p['stock_actif'] && $p['stock_quantite'] <= $p['stock_min']) ? 'table-warning' : '' ?> <?= ($p['stock_actif'] && $p['stock_quantite'] == 0) ? 'table-danger' : '' ?>">
                        <td>
                            <div class="fw-bold"><?= htmlspecialchars($p['nom']) ?></div>
                            <small class="text-muted"><?= formatPrix($p['prix_base']) ?> / <?= $p['unite'] ?></small>
                        </td>
                        <td><small class="text-muted"><?= htmlspecialchars($p['cat_nom'] ?? '-') ?></small></td>
                        <td class="text-center">
                            <?php if ($p['stock_actif']): ?>
                            <span class="fw-bold fs-5 <?= $p['stock_quantite'] == 0 ? 'text-danger' : ($p['stock_quantite'] <= $p['stock_min'] ? 'text-warning' : 'text-success') ?>">
                                <?= $p['stock_quantite'] ?>
                            </span>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?= $p['stock_actif'] ? $p['stock_min'] : '-' ?>
                        </td>
                        <td class="text-center">
                            <?php if (!$p['stock_actif']): ?>
                            <span class="badge bg-secondary">Non suivi</span>
                            <?php elseif ($p['stock_quantite'] == 0): ?>
                            <span class="badge bg-danger">Rupture</span>
                            <?php elseif ($p['stock_quantite'] <= $p['stock_min']): ?>
                            <span class="badge bg-warning text-dark">Stock bas</span>
                            <?php else: ?>
                            <span class="badge bg-success">OK</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <a href="?page=stock&action=toggle_stock&id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-<?= $p['stock_actif'] ? 'success' : 'secondary' ?>" title="<?= $p['stock_actif'] ? 'Désactiver suivi' : 'Activer suivi' ?>">
                                <i class="bi bi-toggle-<?= $p['stock_actif'] ? 'on' : 'off' ?>"></i>
                            </a>
                        </td>
                        <td>
                            <?php if ($p['stock_actif']): ?>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-primary" onclick="ajustementRapide(<?= $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['nom'])) ?>', <?= $p['stock_quantite'] ?>)" title="Ajustement rapide">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <a href="index.php?page=stock_mouvement&produit_id=<?= $p['id'] ?>" class="btn btn-outline-success" title="Entrée de stock">
                                    <i class="bi bi-plus-lg"></i>
                                </a>
                            </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Ajustement Rapide -->
<div class="modal fade" id="modalAjustement" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="produit_id" id="adj_produit_id">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Ajustement rapide</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="fw-bold" id="adj_produit_nom"></p>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label">Stock actuel</label>
                            <input type="text" class="form-control" id="adj_stock_actuel" readonly>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Nouvelle quantité *</label>
                            <input type="number" name="nouvelle_quantite" id="adj_nouvelle_qte" class="form-control" required min="0">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Motif</label>
                            <input type="text" name="motif" class="form-control" value="Ajustement manuel" placeholder="Raison de l'ajustement...">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="ajustement_rapide" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>Ajuster</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function ajustementRapide(id, nom, qte) {
    document.getElementById('adj_produit_id').value = id;
    document.getElementById('adj_produit_nom').textContent = nom;
    document.getElementById('adj_stock_actuel').value = qte;
    document.getElementById('adj_nouvelle_qte').value = qte;
    new bootstrap.Modal(document.getElementById('modalAjustement')).show();
}
</script>

<?php elseif ($tab === 'historique'): ?>
<!-- Historique des mouvements -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="bg-light">
                    <tr>
                        <th>Date</th>
                        <th>Produit</th>
                        <th class="text-center">Type</th>
                        <th class="text-center">Quantité</th>
                        <th class="text-center">Avant</th>
                        <th class="text-center">Après</th>
                        <th>Motif / Référence</th>
                        <th>Par</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($mouvements)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">Aucun mouvement enregistré.</td></tr>
                    <?php else: ?>
                    <?php foreach ($mouvements as $m):
                        $type_colors = ['entree' => 'success', 'sortie' => 'danger', 'ajustement' => 'info', 'commande' => 'primary', 'retour' => 'warning'];
                        $type_icons = ['entree' => 'arrow-down-circle', 'sortie' => 'arrow-up-circle', 'ajustement' => 'arrow-repeat', 'commande' => 'cart-check', 'retour' => 'arrow-return-left'];
                        $tc = $type_colors[$m['type_mouvement']] ?? 'secondary';
                        $ti = $type_icons[$m['type_mouvement']] ?? 'circle';
                    ?>
                    <tr>
                        <td><small><?= dateFormatFr($m['created_at'], 'complet') ?></small></td>
                        <td class="fw-bold"><?= htmlspecialchars($m['produit_nom'] ?? 'Supprimé') ?></td>
                        <td class="text-center">
                            <span class="badge bg-<?= $tc ?>">
                                <i class="bi bi-<?= $ti ?> me-1"></i><?= ucfirst($m['type_mouvement']) ?>
                            </span>
                        </td>
                        <td class="text-center fw-bold <?= in_array($m['type_mouvement'], ['entree', 'retour']) ? 'text-success' : 'text-danger' ?>">
                            <?= in_array($m['type_mouvement'], ['entree', 'retour']) ? '+' : '-' ?><?= $m['quantite'] ?>
                        </td>
                        <td class="text-center text-muted"><?= $m['quantite_avant'] ?></td>
                        <td class="text-center fw-bold"><?= $m['quantite_apres'] ?></td>
                        <td>
                            <?php if ($m['motif']): ?><small><?= htmlspecialchars($m['motif']) ?></small><?php endif; ?>
                            <?php if ($m['reference']): ?><br><small class="text-muted">Réf: <?= htmlspecialchars($m['reference']) ?></small><?php endif; ?>
                            <?php if ($m['fournisseur']): ?><br><small class="text-muted">Fournisseur: <?= htmlspecialchars($m['fournisseur']) ?></small><?php endif; ?>
                        </td>
                        <td><small class="text-muted"><?= htmlspecialchars($m['admin_prenom'] ?? '-') ?></small></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>
