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

// ========================================
// Dynamic SEO - Prepare per-page meta data
// ========================================
$_dynamic_seo = ['title' => '', 'description' => '', 'og_image' => ''];
try {
    $db = getDB();
    // Auto-add meta columns if missing
    try { $db->exec("ALTER TABLE produits ADD COLUMN IF NOT EXISTS meta_title VARCHAR(255) DEFAULT NULL"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE produits ADD COLUMN IF NOT EXISTS meta_description TEXT DEFAULT NULL"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE categories ADD COLUMN IF NOT EXISTS meta_title VARCHAR(255) DEFAULT NULL"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE categories ADD COLUMN IF NOT EXISTS meta_description TEXT DEFAULT NULL"); } catch (Exception $e) {}

    if ($page === 'produit' && $id) {
        $stmt = $db->prepare("SELECT p.nom, p.description_courte, p.meta_title, p.meta_description, p.image, p.prix_base, p.unite, c.nom as cat_nom FROM produits p JOIN categories c ON p.categorie_id = c.id WHERE p.id = ? AND p.actif = 1");
        $stmt->execute([$id]);
        $_seo_item = $stmt->fetch();
        if ($_seo_item) {
            $_dynamic_seo['title'] = $_seo_item['meta_title'] ?: $_seo_item['nom'] . ' - ' . APP_NAME;
            $_dynamic_seo['description'] = $_seo_item['meta_description'] ?: $_seo_item['description_courte'] ?: $_seo_item['nom'] . ' - ' . $_seo_item['cat_nom'] . ' | ' . APP_NAME;
            if ($_seo_item['image']) $_dynamic_seo['og_image'] = APP_URL . '/uploads/produits/' . $_seo_item['image'];
        }
    } elseif ($page === 'catalogue') {
        $cat_slug = clean($_GET['cat'] ?? '');
        if ($cat_slug) {
            $stmt = $db->prepare("SELECT nom, description, meta_title, meta_description FROM categories WHERE slug = ? AND actif = 1");
            $stmt->execute([$cat_slug]);
            $_seo_cat = $stmt->fetch();
            if ($_seo_cat) {
                $_dynamic_seo['title'] = $_seo_cat['meta_title'] ?: $_seo_cat['nom'] . ' - ' . APP_NAME;
                $_dynamic_seo['description'] = $_seo_cat['meta_description'] ?: $_seo_cat['description'] ?: $_seo_cat['nom'] . ' - Services d\'impression | ' . APP_NAME;
            }
        } else {
            $_dynamic_seo['title'] = 'Catalogue - ' . APP_NAME;
            $_dynamic_seo['description'] = 'Découvrez notre catalogue complet de services d\'impression professionnels. ' . APP_NAME;
        }
    } elseif ($page === 'devis') {
        $_dynamic_seo['title'] = 'Demande de Devis Gratuit - ' . APP_NAME;
        $_dynamic_seo['description'] = 'Demandez un devis gratuit et sans engagement pour vos projets d\'impression. Réponse rapide garantie. ' . APP_NAME;
    } elseif ($page === 'contact') {
        $_dynamic_seo['title'] = 'Contactez-nous - ' . APP_NAME;
        $_dynamic_seo['description'] = 'Contactez ' . APP_NAME . ' pour tous vos besoins d\'impression. Téléphone, email, WhatsApp.';
    } elseif ($page === 'panier') {
        $_dynamic_seo['title'] = 'Mon Panier - ' . APP_NAME;
    } elseif ($page === 'commander') {
        $_dynamic_seo['title'] = 'Finaliser la commande - ' . APP_NAME;
    } elseif ($page === 'compte-login') {
        $_dynamic_seo['title'] = 'Connexion - ' . APP_NAME;
    } elseif ($page === 'compte-register') {
        $_dynamic_seo['title'] = 'Créer un compte - ' . APP_NAME;
    } elseif ($page === 'compte') {
        $_dynamic_seo['title'] = 'Mon Compte - ' . APP_NAME;
    }
} catch (Exception $e) {}

// Pages publiques
$pages_publiques = [
    'accueil', 'catalogue', 'produit', 'panier', 'commander',
    'confirmation', 'contact', 'devis', 'a-propos', 'suivi-commande',
    'compte', 'compte-login', 'compte-register'
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
        $_dynamic_seo['title'] = $dynamic_page['meta_title'] ?: $dynamic_page['titre'] . ' - ' . APP_NAME;
        $_dynamic_seo['description'] = $dynamic_page['meta_description'] ?: '';
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
        $_dynamic_seo['title'] = 'Page non trouvée - ' . APP_NAME;
        include __DIR__ . '/includes/header.php';
        echo '<div class="container py-5 text-center">';
        echo '<h1 class="display-1">404</h1>';
        echo '<p class="lead">Page non trouvée</p>';
        echo '<a href="index.php" class="btn btn-primary">Retour à l\'accueil</a>';
        echo '</div>';
        include __DIR__ . '/includes/footer.php';
    }
}
