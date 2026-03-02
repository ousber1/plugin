<?php
/**
 * BERRADI PRINT - Gestion Clients
 */
$db = getDB();
$q = clean($_GET['q'] ?? '');
$type = clean($_GET['type'] ?? '');

$sql = "SELECT c.*, (SELECT COUNT(*) FROM commandes WHERE client_id = c.id) as nb_cmd FROM clients c WHERE 1=1";
$params = [];
if ($q) { $sql .= " AND (c.nom LIKE ? OR c.prenom LIKE ? OR c.telephone LIKE ? OR c.email LIKE ? OR c.nom_entreprise LIKE ?)"; $params = array_fill(0, 5, "%$q%"); }
if ($type) { $sql .= " AND c.type_client = ?"; $params[] = $type; }
$sql .= " ORDER BY c.created_at DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$clients = $stmt->fetchAll();

// Ajout rapide client
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajouter_client'])) {
    verifyCsrf();
    $db->prepare("INSERT INTO clients (nom, prenom, telephone, email, adresse, ville, type_client, nom_entreprise, notes) VALUES (?,?,?,?,?,?,?,?,?)")->execute([
        clean($_POST['nom']), clean($_POST['prenom']), clean($_POST['telephone']),
        clean($_POST['email']), clean($_POST['adresse']), clean($_POST['ville']),
        clean($_POST['type_client']), clean($_POST['nom_entreprise']), clean($_POST['notes'])
    ]);
    setFlash('success', 'Client ajouté.');
    redirect('index.php?page=clients');
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-people me-2"></i>Clients (<?= count($clients) ?>)</h4>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalClient">
        <i class="bi bi-plus-circle me-1"></i>Nouveau client
    </button>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <input type="hidden" name="page" value="clients">
            <div class="col-md-4">
                <input type="text" name="q" class="form-control form-control-sm" placeholder="Rechercher..." value="<?= $q ?>">
            </div>
            <div class="col-md-3">
                <select name="type" class="form-select form-select-sm">
                    <option value="">Tous les types</option>
                    <option value="particulier" <?= $type === 'particulier' ? 'selected' : '' ?>>Particulier</option>
                    <option value="entreprise" <?= $type === 'entreprise' ? 'selected' : '' ?>>Entreprise</option>
                </select>
            </div>
            <div class="col-md-1"><button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search"></i></button></div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="bg-light">
                    <tr><th>Client</th><th>Contact</th><th>Type</th><th class="text-center">Commandes</th><th class="text-end">Total dépensé</th><th>Date</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($clients)): ?>
                    <tr><td colspan="7" class="text-center py-4 text-muted">Aucun client trouvé</td></tr>
                    <?php else: foreach ($clients as $c): ?>
                    <tr>
                        <td>
                            <div class="fw-bold"><?= $c['prenom'] ?> <?= $c['nom'] ?></div>
                            <?php if ($c['nom_entreprise']): ?><small class="text-muted"><?= $c['nom_entreprise'] ?></small><?php endif; ?>
                        </td>
                        <td>
                            <small><i class="bi bi-telephone me-1"></i><?= $c['telephone'] ?></small><br>
                            <small class="text-muted"><?= $c['email'] ?: '-' ?></small>
                        </td>
                        <td><span class="badge bg-<?= $c['type_client'] === 'entreprise' ? 'info' : 'secondary' ?>"><?= ucfirst($c['type_client']) ?></span></td>
                        <td class="text-center"><span class="badge bg-primary"><?= $c['nb_cmd'] ?></span></td>
                        <td class="text-end fw-bold"><?= formatPrix($c['total_depense']) ?></td>
                        <td><small><?= dateFormatFr($c['created_at'], 'court') ?></small></td>
                        <td>
                            <a href="index.php?page=client_detail&id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Nouveau Client -->
<div class="modal fade" id="modalClient" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <?= csrfField() ?>
                <div class="modal-header"><h5 class="modal-title">Nouveau Client</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-4"><label class="form-label">Prénom *</label><input type="text" name="prenom" class="form-control" required></div>
                        <div class="col-md-4"><label class="form-label">Nom *</label><input type="text" name="nom" class="form-control" required></div>
                        <div class="col-md-4"><label class="form-label">Téléphone *</label><input type="tel" name="telephone" class="form-control" required></div>
                        <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label">Type</label>
                            <select name="type_client" class="form-select"><option value="particulier">Particulier</option><option value="entreprise">Entreprise</option></select>
                        </div>
                        <div class="col-md-6"><label class="form-label">Entreprise</label><input type="text" name="nom_entreprise" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label">Ville</label><input type="text" name="ville" class="form-control"></div>
                        <div class="col-12"><label class="form-label">Adresse</label><input type="text" name="adresse" class="form-control"></div>
                        <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2"></textarea></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="submit" name="ajouter_client" class="btn btn-primary">Ajouter le client</button></div>
            </form>
        </div>
    </div>
</div>
