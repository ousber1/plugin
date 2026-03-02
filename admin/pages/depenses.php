<?php
/**
 * BERRADI PRINT - Gestion des Dépenses
 */
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajouter_depense'])) {
    verifyCsrf();
    $db->prepare("INSERT INTO depenses (categorie_depense, description, montant, date_depense, fournisseur, reference, admin_id) VALUES (?,?,?,?,?,?,?)")->execute([
        clean($_POST['categorie']), clean($_POST['description']),
        floatval($_POST['montant']), $_POST['date_depense'],
        clean($_POST['fournisseur']), clean($_POST['reference']), $admin['id']
    ]);
    setFlash('success', 'Dépense enregistrée.');
    redirect('index.php?page=depenses');
}

if (isset($_GET['action']) && $_GET['action'] === 'supprimer' && $id) {
    $db->prepare("DELETE FROM depenses WHERE id = ?")->execute([$id]);
    setFlash('success', 'Dépense supprimée.');
    redirect('index.php?page=depenses');
}

$mois = clean($_GET['mois'] ?? date('Y-m'));
$stmt = $db->prepare("SELECT * FROM depenses WHERE DATE_FORMAT(date_depense, '%Y-%m') = ? ORDER BY date_depense DESC");
$stmt->execute([$mois]);
$depenses = $stmt->fetchAll();

$total_depenses = array_sum(array_column($depenses, 'montant'));

$cats_depenses = ['Matière première', 'Encres & Consommables', 'Maintenance', 'Loyer', 'Électricité', 'Internet & Téléphone', 'Transport', 'Salaires', 'Marketing', 'Fournitures bureau', 'Autre'];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-cash-stack me-2"></i>Dépenses</h4>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalDepense"><i class="bi bi-plus-circle me-1"></i>Nouvelle dépense</button>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <h6 class="text-muted">Total du mois</h6>
                <h3 class="fw-bold text-danger"><?= formatPrix($total_depenses) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card border-0 shadow-sm">
            <div class="card-body py-2">
                <form method="GET" class="d-flex align-items-center gap-2">
                    <input type="hidden" name="page" value="depenses">
                    <label class="form-label mb-0 small">Mois:</label>
                    <input type="month" name="mois" class="form-control form-control-sm" value="<?= $mois ?>" style="max-width:200px">
                    <button type="submit" class="btn btn-primary btn-sm">Filtrer</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="bg-light"><tr><th>Date</th><th>Catégorie</th><th>Description</th><th>Fournisseur</th><th class="text-end">Montant</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($depenses as $d): ?>
                <tr>
                    <td><?= dateFormatFr($d['date_depense'], 'court') ?></td>
                    <td><span class="badge bg-secondary"><?= $d['categorie_depense'] ?></span></td>
                    <td><?= $d['description'] ?></td>
                    <td><small class="text-muted"><?= $d['fournisseur'] ?: '-' ?></small></td>
                    <td class="text-end fw-bold text-danger"><?= formatPrix($d['montant']) ?></td>
                    <td><a href="index.php?page=depenses&action=supprimer&id=<?= $d['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Supprimer ?')"><i class="bi bi-trash"></i></a></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($depenses)): ?><tr><td colspan="6" class="text-center py-4 text-muted">Aucune dépense ce mois</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="modalDepense" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrfField() ?>
                <div class="modal-header"><h5 class="modal-title">Nouvelle dépense</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Catégorie *</label>
                        <select name="categorie" class="form-select" required>
                            <?php foreach ($cats_depenses as $c): ?><option value="<?= $c ?>"><?= $c ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3"><label class="form-label">Description *</label><input type="text" name="description" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Montant (DH) *</label><input type="number" name="montant" class="form-control" step="0.01" required></div>
                    <div class="mb-3"><label class="form-label">Date *</label><input type="date" name="date_depense" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
                    <div class="mb-3"><label class="form-label">Fournisseur</label><input type="text" name="fournisseur" class="form-control"></div>
                    <div class="mb-3"><label class="form-label">Référence</label><input type="text" name="reference" class="form-control"></div>
                </div>
                <div class="modal-footer"><button type="submit" name="ajouter_depense" class="btn btn-primary">Enregistrer</button></div>
            </form>
        </div>
    </div>
</div>
