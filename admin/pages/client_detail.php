<?php
/**
 * BERRADI PRINT - Détail Client
 */
$db = getDB();
$client_id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->execute([$client_id]);
$client = $stmt->fetch();
if (!$client) { echo '<div class="alert alert-danger">Client non trouvé.</div>'; return; }

$stmt = $db->prepare("SELECT * FROM commandes WHERE client_id = ? ORDER BY created_at DESC");
$stmt->execute([$client_id]);
$commandes = $stmt->fetchAll();

// Mise à jour client
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['maj_client'])) {
    verifyCsrf();
    $db->prepare("UPDATE clients SET nom=?, prenom=?, telephone=?, email=?, adresse=?, ville=?, type_client=?, nom_entreprise=?, ice=?, notes=? WHERE id=?")->execute([
        clean($_POST['nom']), clean($_POST['prenom']), clean($_POST['telephone']),
        clean($_POST['email']), clean($_POST['adresse']), clean($_POST['ville']),
        clean($_POST['type_client']), clean($_POST['nom_entreprise']), clean($_POST['ice']),
        clean($_POST['notes']), $client_id
    ]);
    setFlash('success', 'Client mis à jour.');
    redirect('index.php?page=client_detail&id=' . $client_id);
}
?>

<div class="mb-4">
    <a href="index.php?page=clients" class="text-decoration-none small"><i class="bi bi-arrow-left me-1"></i>Retour</a>
    <h4 class="fw-bold"><?= $client['prenom'] ?> <?= $client['nom'] ?></h4>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-bold"><i class="bi bi-person me-2"></i>Informations</div>
            <div class="card-body">
                <form method="POST">
                    <?= csrfField() ?>
                    <div class="mb-2"><label class="form-label small">Prénom</label><input type="text" name="prenom" class="form-control form-control-sm" value="<?= $client['prenom'] ?>"></div>
                    <div class="mb-2"><label class="form-label small">Nom</label><input type="text" name="nom" class="form-control form-control-sm" value="<?= $client['nom'] ?>"></div>
                    <div class="mb-2"><label class="form-label small">Téléphone</label><input type="tel" name="telephone" class="form-control form-control-sm" value="<?= $client['telephone'] ?>"></div>
                    <div class="mb-2"><label class="form-label small">Email</label><input type="email" name="email" class="form-control form-control-sm" value="<?= $client['email'] ?>"></div>
                    <div class="mb-2"><label class="form-label small">Ville</label><input type="text" name="ville" class="form-control form-control-sm" value="<?= $client['ville'] ?>"></div>
                    <div class="mb-2"><label class="form-label small">Adresse</label><input type="text" name="adresse" class="form-control form-control-sm" value="<?= $client['adresse'] ?>"></div>
                    <div class="mb-2"><label class="form-label small">Type</label>
                        <select name="type_client" class="form-select form-select-sm">
                            <option value="particulier" <?= $client['type_client'] === 'particulier' ? 'selected' : '' ?>>Particulier</option>
                            <option value="entreprise" <?= $client['type_client'] === 'entreprise' ? 'selected' : '' ?>>Entreprise</option>
                        </select>
                    </div>
                    <div class="mb-2"><label class="form-label small">Entreprise</label><input type="text" name="nom_entreprise" class="form-control form-control-sm" value="<?= $client['nom_entreprise'] ?>"></div>
                    <div class="mb-2"><label class="form-label small">ICE</label><input type="text" name="ice" class="form-control form-control-sm" value="<?= $client['ice'] ?>"></div>
                    <div class="mb-2"><label class="form-label small">Notes</label><textarea name="notes" class="form-control form-control-sm" rows="2"><?= $client['notes'] ?></textarea></div>
                    <button type="submit" name="maj_client" class="btn btn-primary btn-sm w-100">Sauvegarder</button>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-bold"><i class="bi bi-graph-up me-2"></i>Statistiques</div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2"><span>Total commandes</span><strong><?= $client['total_commandes'] ?></strong></div>
                <div class="d-flex justify-content-between mb-2"><span>Total dépensé</span><strong class="text-primary"><?= formatPrix($client['total_depense']) ?></strong></div>
                <div class="d-flex justify-content-between"><span>Client depuis</span><strong><?= dateFormatFr($client['created_at'], 'court') ?></strong></div>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-bold"><i class="bi bi-cart-check me-2"></i>Commandes (<?= count($commandes) ?>)</div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead class="bg-light"><tr><th>N°</th><th class="text-end">Total</th><th>Statut</th><th>Paiement</th><th>Date</th><th></th></tr></thead>
                    <tbody>
                        <?php foreach ($commandes as $cmd): $st = statutCommande($cmd['statut']); $sp = statutPaiement($cmd['statut_paiement']); ?>
                        <tr>
                            <td class="fw-bold"><?= $cmd['numero_commande'] ?></td>
                            <td class="text-end fw-bold"><?= formatPrix($cmd['total']) ?></td>
                            <td><span class="badge bg-<?= $st['class'] ?>"><?= $st['label'] ?></span></td>
                            <td><span class="badge bg-<?= $sp['class'] ?>"><?= $sp['label'] ?></span></td>
                            <td><small><?= dateFormatFr($cmd['created_at'], 'court') ?></small></td>
                            <td><a href="index.php?page=commande_detail&id=<?= $cmd['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($commandes)): ?><tr><td colspan="6" class="text-center py-4 text-muted">Aucune commande</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
