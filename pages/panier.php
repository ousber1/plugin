<?php
/**
 * BERRADI PRINT - Page Panier
 */

// Actions sur le panier
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['supprimer'])) {
        verifyCsrf();
        supprimerDuPanier($_POST['item_key']);
        setFlash('info', 'Article supprimé du panier.');
        redirect('index.php?page=panier');
    }
    if (isset($_POST['maj_quantite'])) {
        verifyCsrf();
        $key = $_POST['item_key'];
        $qte = max(1, (int)$_POST['quantite']);
        if (isset($_SESSION['panier'][$key])) {
            $_SESSION['panier'][$key]['quantite'] = $qte;
            $_SESSION['panier'][$key]['prix_total'] = $_SESSION['panier'][$key]['prix_unitaire'] * $qte;
        }
        setFlash('success', 'Quantité mise à jour.');
        redirect('index.php?page=panier');
    }
    if (isset($_POST['vider'])) {
        verifyCsrf();
        viderPanier();
        setFlash('info', 'Panier vidé.');
        redirect('index.php?page=panier');
    }
}

$panier = getPanier();
$total = totalPanier();
?>

<section class="bg-light py-3">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="index.php">Accueil</a></li>
                <li class="breadcrumb-item active">Panier</li>
            </ol>
        </nav>
    </div>
</section>

<section class="py-5">
    <div class="container">
        <h2 class="fw-bold mb-4"><i class="bi bi-cart3 me-2"></i>Mon Panier</h2>

        <?php if (empty($panier)): ?>
        <div class="text-center py-5">
            <i class="bi bi-cart-x display-1 text-muted"></i>
            <h4 class="mt-3">Votre panier est vide</h4>
            <p class="text-muted">Parcourez notre catalogue et ajoutez des services à votre panier</p>
            <a href="index.php?page=catalogue" class="btn btn-primary">
                <i class="bi bi-grid me-2"></i>Voir nos services
            </a>
        </div>
        <?php else: ?>
        <div class="row g-4">
            <!-- Articles -->
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Produit</th>
                                        <th class="text-center" style="width:120px">Quantité</th>
                                        <th class="text-end" style="width:120px">Prix unit.</th>
                                        <th class="text-end" style="width:120px">Total</th>
                                        <th style="width:50px"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($panier as $key => $item): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="bg-primary-soft rounded d-flex align-items-center justify-content-center me-3" style="width:50px;height:50px;">
                                                    <i class="bi bi-printer text-primary"></i>
                                                </div>
                                                <div>
                                                    <div class="fw-bold"><?= $item['nom'] ?></div>
                                                    <?php if (!empty($item['options'])): ?>
                                                    <small class="text-muted">
                                                        <?php foreach ($item['options'] as $opt): ?>
                                                            <?= $opt['nom'] ?>: <?= $opt['valeur'] ?><br>
                                                        <?php endforeach; ?>
                                                    </small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <form method="POST" class="d-inline">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="item_key" value="<?= $key ?>">
                                                <div class="input-group input-group-sm">
                                                    <input type="number" name="quantite" value="<?= $item['quantite'] ?>" min="1" class="form-control text-center" style="max-width:70px">
                                                    <button type="submit" name="maj_quantite" class="btn btn-outline-primary btn-sm"><i class="bi bi-check"></i></button>
                                                </div>
                                            </form>
                                        </td>
                                        <td class="text-end"><?= formatPrix($item['prix_unitaire']) ?></td>
                                        <td class="text-end fw-bold"><?= formatPrix($item['prix_total']) ?></td>
                                        <td>
                                            <form method="POST" class="d-inline">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="item_key" value="<?= $key ?>">
                                                <button type="submit" name="supprimer" class="btn btn-sm btn-outline-danger" title="Supprimer">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="d-flex justify-content-between mt-3">
                    <a href="index.php?page=catalogue" class="btn btn-outline-primary">
                        <i class="bi bi-arrow-left me-2"></i>Continuer les achats
                    </a>
                    <form method="POST" class="d-inline">
                        <?= csrfField() ?>
                        <button type="submit" name="vider" class="btn btn-outline-danger">
                            <i class="bi bi-trash me-2"></i>Vider le panier
                        </button>
                    </form>
                </div>
            </div>

            <!-- Récapitulatif -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-primary text-white fw-bold">
                        <i class="bi bi-receipt me-2"></i>Récapitulatif
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Sous-total</span>
                            <span class="fw-bold"><?= formatPrix($total) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Frais de livraison</span>
                            <span class="text-muted">
                                <?= $total >= FREE_DELIVERY_MIN ? '<span class="text-success">Gratuit</span>' : 'Calculés à l\'étape suivante' ?>
                            </span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between mb-3">
                            <span class="fw-bold fs-5">Total</span>
                            <span class="fw-bold fs-5 text-primary"><?= formatPrix($total) ?></span>
                        </div>

                        <div class="d-grid">
                            <a href="index.php?page=commander" class="btn btn-primary btn-lg">
                                <i class="bi bi-credit-card me-2"></i>Passer la commande
                            </a>
                        </div>

                        <div class="mt-3 text-center">
                            <small class="text-muted">
                                <i class="bi bi-cash me-1"></i>Paiement à la livraison<br>
                                <i class="bi bi-shield-check me-1"></i>Satisfaction garantie
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>
