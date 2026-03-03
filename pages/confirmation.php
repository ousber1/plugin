<?php
/**
 * BERRADI PRINT - Confirmation de Commande (Thank You Page)
 */
$numero = $_SESSION['derniere_commande'] ?? '';
if (!$numero) {
    redirect('index.php');
}

$db = getDB();
$stmt = $db->prepare("SELECT * FROM commandes WHERE numero_commande = ?");
$stmt->execute([$numero]);
$commande = $stmt->fetch();

// Ne pas supprimer immédiatement pour permettre le refresh
if (isset($_SESSION['confirmation_shown'])) {
    unset($_SESSION['derniere_commande']);
    unset($_SESSION['confirmation_shown']);
}
$_SESSION['confirmation_shown'] = true;
?>

<section class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-5 text-center">
                        <div class="mb-4">
                            <div class="bg-success text-white rounded-circle d-inline-flex align-items-center justify-content-center" style="width:90px;height:90px;">
                                <i class="bi bi-check-lg display-4"></i>
                            </div>
                        </div>
                        <h2 class="fw-bold text-success mb-3">Merci pour votre commande !</h2>
                        <p class="text-muted mb-4">
                            Votre commande a été reçue avec succès. Voici votre numéro de commande :
                        </p>
                        <div class="bg-light rounded p-4 mb-4">
                            <h2 class="fw-bold text-primary mb-0"><?= htmlspecialchars($numero) ?></h2>
                        </div>

                        <?php if ($commande): ?>
                        <div class="text-start mb-4">
                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <div class="border rounded p-3">
                                        <small class="text-muted d-block">Client</small>
                                        <strong><?= htmlspecialchars($commande['client_nom']) ?></strong><br>
                                        <small><?= htmlspecialchars($commande['client_telephone']) ?></small>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="border rounded p-3">
                                        <small class="text-muted d-block">Total à payer</small>
                                        <strong class="text-primary fs-5"><?= formatPrix($commande['total']) ?></strong><br>
                                        <small class="text-muted">Paiement à la livraison</small>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="border rounded p-3">
                                        <small class="text-muted d-block">Mode de réception</small>
                                        <strong><?= $commande['type_livraison'] === 'livraison' ? 'Livraison à domicile' : 'Retrait en magasin' ?></strong>
                                        <?php if ($commande['client_ville']): ?>
                                        <br><small><?= htmlspecialchars($commande['client_ville']) ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="border rounded p-3">
                                        <small class="text-muted d-block">Statut</small>
                                        <span class="badge bg-primary">Nouvelle commande</span><br>
                                        <small class="text-muted">Nous vous appellerons bientôt</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="alert alert-info text-start mb-4">
                            <h6 class="fw-bold mb-2"><i class="bi bi-info-circle me-2"></i>Prochaines étapes :</h6>
                            <ol class="mb-0 small">
                                <li class="mb-1">Nous vous contacterons par téléphone pour confirmer votre commande</li>
                                <li class="mb-1">Votre commande sera préparée dans les délais indiqués</li>
                                <li class="mb-1">Le paiement se fait à la livraison (Cash on Delivery)</li>
                                <li>Conservez votre numéro de commande pour le suivi</li>
                            </ol>
                        </div>

                        <div class="d-flex gap-3 justify-content-center flex-wrap">
                            <a href="index.php?page=suivi-commande" class="btn btn-primary btn-lg">
                                <i class="bi bi-search me-2"></i>Suivre ma commande
                            </a>
                            <a href="index.php" class="btn btn-outline-primary btn-lg">
                                <i class="bi bi-house me-2"></i>Retour à l'accueil
                            </a>
                        </div>
                        <div class="mt-4">
                            <a href="https://wa.me/<?= str_replace(['+', ' ', '-'], '', APP_PHONE) ?>?text=Bonjour, j'ai passé la commande <?= urlencode($numero) ?>"
                               class="btn btn-success" target="_blank">
                                <i class="bi bi-whatsapp me-2"></i>Nous contacter sur WhatsApp
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
