<?php
/**
 * BERRADI PRINT - Point d'Entrée Principal
 * Système de Gestion de Services d'Impression
 */

session_start();
ob_start();

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

// Routage simple
$page = isset($_GET['page']) ? clean($_GET['page']) : 'accueil';
$action = isset($_GET['action']) ? clean($_GET['action']) : '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Pages publiques
$pages_publiques = [
    'accueil', 'catalogue', 'produit', 'panier', 'commander',
    'confirmation', 'contact', 'devis', 'a-propos', 'suivi-commande'
];

if (in_array($page, $pages_publiques)) {
    $page_file = __DIR__ . '/pages/' . str_replace('-', '_', $page) . '.php';
    if (file_exists($page_file)) {
        include __DIR__ . '/includes/header.php';
        include $page_file;
        include __DIR__ . '/includes/footer.php';
    } else {
        include __DIR__ . '/includes/header.php';
        include __DIR__ . '/pages/accueil.php';
        include __DIR__ . '/includes/footer.php';
    }
} else {
    // Check for dynamic pages from database
    $dynamic_page = null;
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM pages WHERE slug = ? AND actif = 1");
        $stmt->execute([$page]);
        $dynamic_page = $stmt->fetch();
    } catch (Exception $e) {}

    if ($dynamic_page) {
        include __DIR__ . '/includes/header.php';
        echo '<section class="py-5">';
        echo '<div class="container">';
        echo '<nav aria-label="breadcrumb" class="mb-4"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="index.php">Accueil</a></li><li class="breadcrumb-item active">' . htmlspecialchars($dynamic_page['titre']) . '</li></ol></nav>';
        echo '<h1 class="fw-bold mb-4">' . htmlspecialchars($dynamic_page['titre']) . '</h1>';
        echo '<div class="page-content">' . $dynamic_page['contenu'] . '</div>';
        echo '</div>';
        echo '</section>';
        include __DIR__ . '/includes/footer.php';
    } else {
        // Page 404
        include __DIR__ . '/includes/header.php';
        echo '<div class="container py-5 text-center">';
        echo '<h1 class="display-1">404</h1>';
        echo '<p class="lead">Page non trouvée</p>';
        echo '<a href="index.php" class="btn btn-primary">Retour à l\'accueil</a>';
        echo '</div>';
        include __DIR__ . '/includes/footer.php';
    }
}
