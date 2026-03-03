<?php
/**
 * BERRADI PRINT - Détail Commande
 */
$db = getDB();
$commande_id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT * FROM commandes WHERE id = ?");
$stmt->execute([$commande_id]);
$cmd = $stmt->fetch();
if (!$cmd) { echo '<div class="alert alert-danger">Commande non trouvée.</div>'; return; }

// Lignes de commande
$stmt = $db->prepare("SELECT cl.*, p.nom as produit_nom FROM commande_lignes cl LEFT JOIN produits p ON cl.produit_id = p.id WHERE cl.commande_id = ?");
$stmt->execute([$commande_id]);
$lignes = $stmt->fetchAll();

// Historique
$stmt = $db->prepare("SELECT h.*, a.prenom as admin_prenom, a.nom as admin_nom FROM commande_historique h LEFT JOIN admins a ON h.admin_id = a.id WHERE h.commande_id = ? ORDER BY h.created_at DESC");
$stmt->execute([$commande_id]);
$historique = $stmt->fetchAll();

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    // Changer le statut
    if (isset($_POST['changer_statut'])) {
        $nouveau_statut = clean($_POST['nouveau_statut']);
        $commentaire = clean($_POST['commentaire'] ?? '');
        $ancien_statut = $cmd['statut'];

        $db->prepare("UPDATE commandes SET statut = ? WHERE id = ?")->execute([$nouveau_statut, $commande_id]);
        $db->prepare("INSERT INTO commande_historique (commande_id, statut_ancien, statut_nouveau, commentaire, admin_id) VALUES (?,?,?,?,?)")->execute([$commande_id, $ancien_statut, $nouveau_statut, $commentaire, $admin['id']]);

        if ($nouveau_statut === 'livree') {
            $db->prepare("UPDATE commandes SET date_livraison_reelle = NOW() WHERE id = ?")->execute([$commande_id]);
        }

        setFlash('success', 'Statut mis à jour avec succès.');
        redirect('index.php?page=commande_detail&id=' . $commande_id);
    }

    // Enregistrer un paiement
    if (isset($_POST['enregistrer_paiement'])) {
        $montant = floatval($_POST['montant_paiement']);
        $nouveau_paye = $cmd['montant_paye'] + $montant;
        $statut_paiement = $nouveau_paye >= $cmd['total'] ? 'paye' : 'partiel';

        $db->prepare("UPDATE commandes SET montant_paye = ?, statut_paiement = ? WHERE id = ?")->execute([$nouveau_paye, $statut_paiement, $commande_id]);
        $db->prepare("INSERT INTO commande_historique (commande_id, statut_ancien, statut_nouveau, commentaire, admin_id) VALUES (?,?,?,?,?)")->execute([$commande_id, $cmd['statut_paiement'], $statut_paiement, "Paiement de " . formatPrix($montant) . " enregistré", $admin['id']]);

        setFlash('success', 'Paiement enregistré.');
        redirect('index.php?page=commande_detail&id=' . $commande_id);
    }

    // Mettre à jour les notes internes
    if (isset($_POST['maj_notes'])) {
        $db->prepare("UPDATE commandes SET notes_internes = ? WHERE id = ?")->execute([clean($_POST['notes_internes']), $commande_id]);
        setFlash('success', 'Notes mises à jour.');
        redirect('index.php?page=commande_detail&id=' . $commande_id);
    }

    // Changer la priorité
    if (isset($_POST['changer_priorite'])) {
        $db->prepare("UPDATE commandes SET priorite = ? WHERE id = ?")->execute([clean($_POST['priorite']), $commande_id]);
        setFlash('success', 'Priorité mise à jour.');
        redirect('index.php?page=commande_detail&id=' . $commande_id);
    }
}

// Recharger la commande après modification
$stmt = $db->prepare("SELECT * FROM commandes WHERE id = ?");
$stmt->execute([$commande_id]);
$cmd = $stmt->fetch();

$st = statutCommande($cmd['statut']);
$sp = statutPaiement($cmd['statut_paiement']);
$pr = prioriteLabel($cmd['priorite']);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <a href="index.php?page=commandes" class="text-decoration-none small"><i class="bi bi-arrow-left me-1"></i>Retour aux commandes</a>
        <h4 class="fw-bold mb-0">
            Commande #<?= $cmd['numero_commande'] ?>
            <span class="badge bg-<?= $st['class'] ?> ms-2"><?= $st['label'] ?></span>
            <span class="badge bg-<?= $sp['class'] ?> ms-1"><?= $sp['label'] ?></span>
            <?php if ($cmd['priorite'] !== 'normale'): ?>
            <span class="badge bg-<?= $pr['class'] ?> ms-1"><?= $pr['label'] ?></span>
            <?php endif; ?>
        </h4>
    </div>
    <div class="d-flex gap-2">
        <!-- Impression -->
        <button onclick="window.print()" class="btn btn-outline-secondary">
            <i class="bi bi-printer me-1"></i>Imprimer
        </button>
    </div>
</div>

<div class="row g-4">
    <!-- Colonne principale -->
    <div class="col-lg-8">
        <!-- Info Client -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white fw-bold"><i class="bi bi-person me-2"></i>Informations Client</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <p class="mb-1"><strong>Nom:</strong> <?= $cmd['client_nom'] ?></p>
                        <p class="mb-1"><strong>Téléphone:</strong> <a href="tel:<?= $cmd['client_telephone'] ?>"><?= $cmd['client_telephone'] ?></a></p>
                        <p class="mb-0"><strong>Email:</strong> <?= $cmd['client_email'] ?: '-' ?></p>
                    </div>
                    <div class="col-md-6">
                        <p class="mb-1"><strong>Adresse:</strong> <?= $cmd['client_adresse'] ?></p>
                        <p class="mb-1"><strong>Ville:</strong> <?= $cmd['client_ville'] ?></p>
                        <p class="mb-0"><strong>Type:</strong> <?= $cmd['type_livraison'] === 'livraison' ? 'Livraison' : 'Retrait en magasin' ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Articles commandés -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white fw-bold"><i class="bi bi-box me-2"></i>Articles commandés</div>
            <div class="card-body p-0">
                <table class="table mb-0">
                    <thead class="bg-light">
                        <tr><th>Produit</th><th class="text-center">Qté</th><th class="text-end">Prix unit.</th><th class="text-end">Total</th><th>Production</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lignes as $l):
                            $lp = statutCommande($l['statut_production'] === 'en_attente' ? 'nouvelle' : ($l['statut_production'] === 'en_cours' ? 'en_production' : 'livree'));
                        ?>
                        <tr>
                            <td>
                                <div class="fw-bold"><?= $l['designation'] ?></div>
                                <?php if ($l['notes']): ?><small class="text-muted"><?= $l['notes'] ?></small><?php endif; ?>
                                <?php if ($l['options_selectionnees']): ?>
                                    <?php $opts = json_decode($l['options_selectionnees'], true); if (is_array($opts)): ?>
                                    <small class="text-muted d-block">
                                        <?php foreach ($opts as $o): ?>
                                            <?= $o['nom'] ?? '' ?>: <?= $o['valeur'] ?? '' ?><br>
                                        <?php endforeach; ?>
                                    </small>
                                    <?php endif; endif; ?>
                            </td>
                            <td class="text-center"><?= $l['quantite'] ?></td>
                            <td class="text-end"><?= formatPrix($l['prix_unitaire']) ?></td>
                            <td class="text-end fw-bold"><?= formatPrix($l['prix_total']) ?></td>
                            <td><span class="badge bg-<?= $lp['class'] ?>"><?= ucfirst(str_replace('_', ' ', $l['statut_production'])) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="bg-light">
                        <tr><td colspan="3" class="text-end">Sous-total</td><td class="text-end fw-bold"><?= formatPrix($cmd['sous_total']) ?></td><td></td></tr>
                        <?php if ($cmd['remise_montant'] > 0): ?>
                        <tr><td colspan="3" class="text-end text-danger">Remise</td><td class="text-end text-danger">-<?= formatPrix($cmd['remise_montant']) ?></td><td></td></tr>
                        <?php endif; ?>
                        <tr><td colspan="3" class="text-end">Frais de livraison</td><td class="text-end"><?= $cmd['frais_livraison'] > 0 ? formatPrix($cmd['frais_livraison']) : '<span class="text-success">Gratuit</span>' ?></td><td></td></tr>
                        <tr><td colspan="3" class="text-end fw-bold fs-5">Total</td><td class="text-end fw-bold fs-5 text-primary"><?= formatPrix($cmd['total']) ?></td><td></td></tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Historique -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-bold"><i class="bi bi-clock-history me-2"></i>Historique</div>
            <div class="card-body">
                <?php foreach ($historique as $h):
                    $hs = statutCommande($h['statut_nouveau']);
                ?>
                <div class="d-flex mb-3 pb-3 border-bottom">
                    <div class="me-3">
                        <div class="rounded-circle bg-<?= $hs['class'] ?> d-flex align-items-center justify-content-center" style="width:30px;height:30px;">
                            <i class="bi bi-check text-white small"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1">
                        <div class="fw-bold small">
                            <?= $h['statut_ancien'] ? ucfirst($h['statut_ancien']) . ' → ' : '' ?><?= ucfirst($h['statut_nouveau']) ?>
                        </div>
                        <?php if ($h['commentaire']): ?><div class="small text-muted"><?= $h['commentaire'] ?></div><?php endif; ?>
                        <small class="text-muted"><?= dateFormatFr($h['created_at'], 'complet') ?> <?= $h['admin_prenom'] ? '- par ' . $h['admin_prenom'] : '' ?></small>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Colonne actions -->
    <div class="col-lg-4">
        <!-- Changer statut -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-bold"><i class="bi bi-arrow-repeat me-2"></i>Changer le statut</div>
            <div class="card-body">
                <form method="POST">
                    <?= csrfField() ?>
                    <div class="mb-2">
                        <select name="nouveau_statut" class="form-select">
                            <option value="nouvelle" <?= $cmd['statut'] === 'nouvelle' ? 'selected' : '' ?>>Nouvelle</option>
                            <option value="confirmee" <?= $cmd['statut'] === 'confirmee' ? 'selected' : '' ?>>Confirmée</option>
                            <option value="en_production" <?= $cmd['statut'] === 'en_production' ? 'selected' : '' ?>>En production</option>
                            <option value="prete" <?= $cmd['statut'] === 'prete' ? 'selected' : '' ?>>Prête</option>
                            <option value="en_livraison" <?= $cmd['statut'] === 'en_livraison' ? 'selected' : '' ?>>En livraison</option>
                            <option value="livree" <?= $cmd['statut'] === 'livree' ? 'selected' : '' ?>>Livrée</option>
                            <option value="annulee" <?= $cmd['statut'] === 'annulee' ? 'selected' : '' ?>>Annulée</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <input type="text" name="commentaire" class="form-control form-control-sm" placeholder="Commentaire (optionnel)">
                    </div>
                    <button type="submit" name="changer_statut" class="btn btn-primary btn-sm w-100">Mettre à jour</button>
                </form>
            </div>
        </div>

        <!-- Paiement -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-bold"><i class="bi bi-cash me-2"></i>Paiement</div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span>Total:</span>
                    <strong><?= formatPrix($cmd['total']) ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Payé:</span>
                    <strong class="text-success"><?= formatPrix($cmd['montant_paye']) ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-3">
                    <span>Reste:</span>
                    <strong class="text-danger"><?= formatPrix($cmd['total'] - $cmd['montant_paye']) ?></strong>
                </div>
                <?php if ($cmd['statut_paiement'] !== 'paye'): ?>
                <form method="POST">
                    <?= csrfField() ?>
                    <div class="input-group input-group-sm mb-2">
                        <input type="number" name="montant_paiement" class="form-control" step="0.01" placeholder="Montant" value="<?= $cmd['total'] - $cmd['montant_paye'] ?>">
                        <span class="input-group-text">DH</span>
                    </div>
                    <button type="submit" name="enregistrer_paiement" class="btn btn-success btn-sm w-100">
                        <i class="bi bi-check-circle me-1"></i>Enregistrer paiement
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Priorité -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-bold"><i class="bi bi-flag me-2"></i>Priorité</div>
            <div class="card-body">
                <form method="POST">
                    <?= csrfField() ?>
                    <div class="d-flex gap-2">
                        <select name="priorite" class="form-select form-select-sm">
                            <option value="normale" <?= $cmd['priorite'] === 'normale' ? 'selected' : '' ?>>Normale</option>
                            <option value="urgente" <?= $cmd['priorite'] === 'urgente' ? 'selected' : '' ?>>Urgente</option>
                            <option value="express" <?= $cmd['priorite'] === 'express' ? 'selected' : '' ?>>Express</option>
                        </select>
                        <button type="submit" name="changer_priorite" class="btn btn-sm btn-outline-primary">OK</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Notes internes -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-bold"><i class="bi bi-sticky me-2"></i>Notes internes</div>
            <div class="card-body">
                <form method="POST">
                    <?= csrfField() ?>
                    <textarea name="notes_internes" class="form-control form-control-sm mb-2" rows="3"><?= $cmd['notes_internes'] ?></textarea>
                    <button type="submit" name="maj_notes" class="btn btn-sm btn-outline-primary w-100">Sauvegarder</button>
                </form>
                <?php if ($cmd['notes_client']): ?>
                <div class="mt-3 p-2 bg-light rounded">
                    <small class="fw-bold">Note du client:</small><br>
                    <small class="text-muted"><?= $cmd['notes_client'] ?></small>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Infos supplémentaires -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-bold"><i class="bi bi-info-circle me-2"></i>Informations</div>
            <div class="card-body small">
                <p class="mb-1"><strong>Source:</strong> <?= ucfirst($cmd['source']) ?></p>
                <p class="mb-1"><strong>Créée le:</strong> <?= dateFormatFr($cmd['created_at'], 'complet') ?></p>
                <p class="mb-1"><strong>Modifiée:</strong> <?= dateFormatFr($cmd['updated_at'], 'complet') ?></p>
                <?php if ($cmd['date_livraison_reelle']): ?>
                <p class="mb-0"><strong>Livrée le:</strong> <?= dateFormatFr($cmd['date_livraison_reelle'], 'complet') ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
