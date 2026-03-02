<?php
/**
 * BERRADI PRINT - Panneau d'Administration
 */
session_start();

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$page = isset($_GET['page']) ? clean($_GET['page']) : 'dashboard';
$action = isset($_GET['action']) ? clean($_GET['action']) : '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Pages qui ne nécessitent pas d'authentification
if ($page === 'login') {
    include __DIR__ . '/pages/login.php';
    exit;
}

// Vérifier l'authentification
if (!estConnecte()) {
    redirect('index.php?page=login');
}

$admin = adminConnecte();
if (!$admin) {
    unset($_SESSION['admin_id']);
    redirect('index.php?page=login');
}

// Déconnexion
if ($page === 'logout') {
    session_destroy();
    redirect('index.php?page=login');
}

// Pages admin
$pages_admin = [
    'dashboard', 'commandes', 'commande_detail', 'commande_nouvelle',
    'produits', 'produit_edit', 'categories',
    'clients', 'client_detail',
    'devis', 'devis_detail', 'devis_nouveau',
    'depenses', 'rapports',
    'parametres', 'villes', 'admins', 'notifications'
];

include __DIR__ . '/includes/header.php';

if (in_array($page, $pages_admin)) {
    $page_file = __DIR__ . '/pages/' . $page . '.php';
    if (file_exists($page_file)) {
        include $page_file;
    } else {
        echo '<div class="p-4"><div class="alert alert-warning">Page en cours de développement.</div></div>';
    }
} else {
    echo '<div class="p-4"><div class="alert alert-danger">Page non trouvée.</div></div>';
}

include __DIR__ . '/includes/footer.php';
