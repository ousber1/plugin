<?php
/**
 * BERRADI PRINT - Suivi de Commande
 */
$commande = null;
$lignes = [];
$historique = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['num'])) {
    $numero = clean($_POST['numero'] ?? $_GET['num'] ?? '');
    $telephone = clean($_POST['telephone'] ?? $_GET['tel'] ?? '');

    if ($numero) {
        $db = getDB();
        $sql = "SELECT * FROM commandes WHERE numero_commande = ?";
        $params = [$numero];
        if ($telephone) {
            $sql .= " AND client_telephone = ?";
            $params[] = $telephone;
        }
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $commande = $stmt->fetch();

        if ($commande) {
            $stmt = $db->prepare("SELECT * FROM commande_lignes WHERE commande_id = ?");
            $stmt->execute([$commande['id']]);
            $lignes = $stmt->fetchAll();

            $stmt = $db->prepare("SELECT h.*, a.prenom as admin_prenom FROM commande_historique h LEFT JOIN admins a ON h.admin_id = a.id WHERE h.commande_id = ? ORDER BY h.created_at DESC");
            $stmt->execute([$commande['id']]);
            $historique = $stmt->fetchAll();
        }
    }
}
?>

<section class="bg-primary py-4">
    <div class="container text-center">
        <h1 class="text-white fw-bold"><i class="bi bi-search me-2"></i>Suivi de Commande</h1>
        <p class="text-white-50">Entrez votre numéro de commande pour voir son statut</p>
    </div>
</section>

<section class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <!-- Formulaire de recherche -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4">
                        <form method="POST">
                            <div class="row g-3 align-items-end">
                                <div class="col-md-5">
                                    <label class="form-label fw-semibold">Numéro de commande</label>
                                    <input type="text" name="numero" class="form-control form-control-lg" placeholder="BP-XXXXXX-XXXX" value="<?= $numero ?? '' ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Téléphone (optionnel)</label>
                                    <input type="tel" name="telephone" class="form-control form-control-lg" placeholder="06XXXXXXXX" value="<?= $telephone ?? '' ?>">
                                </div>
                                <div class="col-md-3">
                                    <button type="submit" class="btn btn-primary btn-lg w-100">
                                        <i class="bi bi-search me-2"></i>Rechercher
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if (isset($numero) && !$commande && !empty($numero)): ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle me-2"></i>Aucune commande trouvée avec ce numéro.
                </div>
                <?php endif; ?>

                <?php if ($commande): ?>
                <!-- Résultat -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0 fw-bold">Commande #<?= $commande['numero_commande'] ?></h5>
                            <?php $st = statutCommande($commande['statut']); ?>
                            <span class="badge bg-<?= $st['class'] ?> fs-6"><?= $st['label'] ?></span>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Barre de progression -->
                        <div class="progress-tracker mb-4">
                            <?php
                            $etapes = ['nouvelle' => 'Reçue', 'confirmee' => 'Confirmée', 'en_production' => 'Production', 'prete' => 'Prête', 'en_livraison' => 'Livraison', 'livree' => 'Livrée'];
                            $statuts_ordre = array_keys($etapes);
                            $current_index = array_search($commande['statut'], $statuts_ordre);
                            if ($commande['statut'] === 'annulee') $current_index = -1;
                            ?>
                            <div class="d-flex justify-content-between position-relative mb-4">
                                <?php foreach ($etapes as $key => $label):
                                    $index = array_search($key, $statuts_ordre);
                                    $active = $index <= $current_index;
                                ?>
                                <div class="text-center" style="flex:1">
                                    <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-1 <?= $active ? 'bg-success text-white' : 'bg-light text-muted' ?>" style="width:35px;height:35px;">
                                        <?php if ($active): ?><i class="bi bi-check"></i><?php else: ?><?= $index + 1 ?><?php endif; ?>
                                    </div>
                                    <div class="small <?= $active ? 'fw-bold' : 'text-muted' ?>"><?= $label ?></div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <strong>Date de commande:</strong><br>
                                <?= dateFormatFr($commande['created_at'], 'complet') ?>
                            </div>
                            <div class="col-md-6">
                                <strong>Type:</strong><br>
                                <?= $commande['type_livraison'] === 'livraison' ? 'Livraison à ' . $commande['client_ville'] : 'Retrait en magasin' ?>
                            </div>
                            <div class="col-md-6">
                                <strong>Total:</strong><br>
                                <span class="fs-5 fw-bold text-primary"><?= formatPrix($commande['total']) ?></span>
                            </div>
                            <div class="col-md-6">
                                <?php $sp = statutPaiement($commande['statut_paiement']); ?>
                                <strong>Paiement:</strong><br>
                                <span class="badge bg-<?= $sp['class'] ?>"><?= $sp['label'] ?></span>
                            </div>
                        </div>

                        <?php if (!empty($lignes)): ?>
                        <h6 class="fw-bold mt-4 mb-3">Articles commandés</h6>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead class="bg-light">
                                    <tr><th>Produit</th><th class="text-center">Qté</th><th class="text-end">Total</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($lignes as $l): ?>
                                    <tr>
                                        <td><?= $l['designation'] ?></td>
                                        <td class="text-center"><?= $l['quantite'] ?></td>
                                        <td class="text-end"><?= formatPrix($l['prix_total']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($historique)): ?>
                        <h6 class="fw-bold mt-4 mb-3">Historique</h6>
                        <div class="timeline-simple">
                            <?php foreach ($historique as $h):
                                $hs = statutCommande($h['statut_nouveau']);
                            ?>
                            <div class="d-flex mb-3">
                                <div class="me-3">
                                    <div class="rounded-circle bg-<?= $hs['class'] ?> d-flex align-items-center justify-content-center" style="width:30px;height:30px;">
                                        <i class="bi bi-check text-white small"></i>
                                    </div>
                                </div>
                                <div>
                                    <div class="fw-bold small"><?= $hs['label'] ?></div>
                                    <small class="text-muted"><?= dateFormatFr($h['created_at'], 'complet') ?></small>
                                    <?php if ($h['commentaire']): ?>
                                    <div class="small text-muted"><?= $h['commentaire'] ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
