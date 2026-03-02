<?php
/**
 * BERRADI PRINT - Confirmation de Commande
 */
$numero = $_SESSION['derniere_commande'] ?? '';
if (!$numero) {
    redirect('index.php');
}
unset($_SESSION['derniere_commande']);
?>

<section class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-6 text-center">
                <div class="card border-0 shadow-sm p-5">
                    <div class="mb-4">
                        <div class="bg-success text-white rounded-circle d-inline-flex align-items-center justify-content-center" style="width:80px;height:80px;">
                            <i class="bi bi-check-lg display-4"></i>
                        </div>
                    </div>
                    <h2 class="fw-bold text-success mb-3">Commande Confirmée !</h2>
                    <p class="text-muted mb-4">
                        Merci pour votre commande. Votre numéro de commande est :
                    </p>
                    <div class="bg-light rounded p-3 mb-4">
                        <h3 class="fw-bold text-primary mb-0"><?= htmlspecialchars($numero) ?></h3>
                    </div>
                    <p class="text-muted mb-4">
                        <i class="bi bi-telephone me-2"></i>Nous vous contacterons par téléphone pour confirmer votre commande.<br>
                        <i class="bi bi-cash me-2"></i>Le paiement se fait à la livraison (Cash on Delivery).<br>
                        <i class="bi bi-clock me-2"></i>Conservez votre numéro de commande pour le suivi.
                    </p>
                    <div class="d-flex gap-3 justify-content-center flex-wrap">
                        <a href="index.php?page=suivi-commande" class="btn btn-primary">
                            <i class="bi bi-search me-2"></i>Suivre ma commande
                        </a>
                        <a href="index.php" class="btn btn-outline-primary">
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
</section>
