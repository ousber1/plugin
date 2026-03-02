<?php
/**
 * BERRADI PRINT - Gestion des Villes de Livraison
 */
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    if (isset($_POST['ajouter_ville'])) {
        $db->prepare("INSERT INTO villes_livraison (nom, frais_livraison, delai_livraison) VALUES (?,?,?)")->execute([
            clean($_POST['nom']), floatval($_POST['frais_livraison']), clean($_POST['delai_livraison'])
        ]);
        setFlash('success', 'Ville ajoutée.');
        redirect('index.php?page=villes');
    }
    if (isset($_POST['modifier_ville'])) {
        $db->prepare("UPDATE villes_livraison SET nom=?, frais_livraison=?, delai_livraison=?, actif=? WHERE id=?")->execute([
            clean($_POST['nom']), floatval($_POST['frais_livraison']),
            clean($_POST['delai_livraison']), isset($_POST['actif']) ? 1 : 0,
            (int)$_POST['ville_id']
        ]);
        setFlash('success', 'Ville mise à jour.');
        redirect('index.php?page=villes');
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'supprimer' && $id) {
    $db->prepare("DELETE FROM villes_livraison WHERE id = ?")->execute([$id]);
    setFlash('success', 'Ville supprimée.');
    redirect('index.php?page=villes');
}

$villes = $db->query("SELECT * FROM villes_livraison ORDER BY nom")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-geo-alt me-2"></i>Villes & Frais de Livraison (<?= count($villes) ?>)</h4>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalVille"><i class="bi bi-plus-circle me-1"></i>Ajouter une ville</button>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="bg-light"><tr><th>Ville</th><th class="text-end">Frais livraison</th><th>Délai</th><th>Statut</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($villes as $v): ?>
                <tr class="<?= !$v['actif'] ? 'opacity-50' : '' ?>">
                    <td class="fw-bold"><?= $v['nom'] ?></td>
                    <td class="text-end"><?= $v['frais_livraison'] > 0 ? formatPrix($v['frais_livraison']) : '<span class="text-success">Gratuit</span>' ?></td>
                    <td><?= $v['delai_livraison'] ?></td>
                    <td><?= $v['actif'] ? '<span class="badge bg-success">Actif</span>' : '<span class="badge bg-secondary">Inactif</span>' ?></td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary" onclick="modVille(<?= htmlspecialchars(json_encode($v)) ?>)"><i class="bi bi-pencil"></i></button>
                        <a href="index.php?page=villes&action=supprimer&id=<?= $v['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Supprimer ?')"><i class="bi bi-trash"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Ajouter -->
<div class="modal fade" id="modalVille" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrfField() ?>
                <div class="modal-header"><h5 class="modal-title">Ajouter une ville</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Nom *</label><input type="text" name="nom" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Frais de livraison (DH)</label><input type="number" name="frais_livraison" class="form-control" step="0.01" value="30"></div>
                    <div class="mb-3"><label class="form-label">Délai</label><input type="text" name="delai_livraison" class="form-control" value="24-48h"></div>
                </div>
                <div class="modal-footer"><button type="submit" name="ajouter_ville" class="btn btn-primary">Ajouter</button></div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Modifier -->
<div class="modal fade" id="modalModVille" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="ville_id" id="mv_id">
                <div class="modal-header"><h5 class="modal-title">Modifier la ville</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Nom</label><input type="text" name="nom" id="mv_nom" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Frais (DH)</label><input type="number" name="frais_livraison" id="mv_frais" class="form-control" step="0.01"></div>
                    <div class="mb-3"><label class="form-label">Délai</label><input type="text" name="delai_livraison" id="mv_delai" class="form-control"></div>
                    <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="actif" id="mv_actif"><label class="form-check-label" for="mv_actif">Actif</label></div>
                </div>
                <div class="modal-footer"><button type="submit" name="modifier_ville" class="btn btn-primary">Sauvegarder</button></div>
            </form>
        </div>
    </div>
</div>

<script>
function modVille(v) {
    document.getElementById('mv_id').value = v.id;
    document.getElementById('mv_nom').value = v.nom;
    document.getElementById('mv_frais').value = v.frais_livraison;
    document.getElementById('mv_delai').value = v.delai_livraison;
    document.getElementById('mv_actif').checked = v.actif == 1;
    new bootstrap.Modal(document.getElementById('modalModVille')).show();
}
</script>
