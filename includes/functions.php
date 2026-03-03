<?php
/**
 * BERRADI PRINT - Fonctions Utilitaires
 */

// Nettoyage des entrées
function clean($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

// Formater le prix en DH
function formatPrix($montant) {
    return number_format($montant, 2, ',', ' ') . ' ' . APP_CURRENCY_SYMBOL;
}

// Générer un numéro de commande unique
function genererNumeroCommande() {
    $db = getDB();
    $prefix = 'BP-' . date('Ym');
    $stmt = $db->prepare("SELECT COUNT(*) FROM commandes WHERE numero_commande LIKE ?");
    $stmt->execute([$prefix . '%']);
    $count = $stmt->fetchColumn() + 1;
    return $prefix . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
}

// Générer un numéro de devis unique
function genererNumeroDevis() {
    $db = getDB();
    $prefix = 'DV-' . date('Ym');
    $stmt = $db->prepare("SELECT COUNT(*) FROM devis WHERE numero_devis LIKE ?");
    $stmt->execute([$prefix . '%']);
    $count = $stmt->fetchColumn() + 1;
    return $prefix . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
}

// Obtenir le libellé du statut de commande
function statutCommande($statut) {
    $statuts = [
        'nouvelle' => ['label' => 'Nouvelle', 'class' => 'primary'],
        'confirmee' => ['label' => 'Confirmée', 'class' => 'info'],
        'en_production' => ['label' => 'En production', 'class' => 'warning'],
        'prete' => ['label' => 'Prête', 'class' => 'success'],
        'en_livraison' => ['label' => 'En livraison', 'class' => 'info'],
        'livree' => ['label' => 'Livrée', 'class' => 'success'],
        'annulee' => ['label' => 'Annulée', 'class' => 'danger'],
    ];
    return $statuts[$statut] ?? ['label' => $statut, 'class' => 'secondary'];
}

// Obtenir le libellé du statut de paiement
function statutPaiement($statut) {
    $statuts = [
        'en_attente' => ['label' => 'En attente', 'class' => 'warning'],
        'paye' => ['label' => 'Payé', 'class' => 'success'],
        'partiel' => ['label' => 'Partiel', 'class' => 'info'],
        'rembourse' => ['label' => 'Remboursé', 'class' => 'danger'],
    ];
    return $statuts[$statut] ?? ['label' => $statut, 'class' => 'secondary'];
}

// Obtenir le libellé de priorité
function prioriteLabel($priorite) {
    $priorites = [
        'normale' => ['label' => 'Normale', 'class' => 'secondary'],
        'urgente' => ['label' => 'Urgente', 'class' => 'warning'],
        'express' => ['label' => 'Express', 'class' => 'danger'],
    ];
    return $priorites[$priorite] ?? ['label' => $priorite, 'class' => 'secondary'];
}

// Vérifier si l'admin est connecté
function estConnecte() {
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

// Obtenir l'admin connecté
function adminConnecte() {
    if (!estConnecte()) return null;
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM admins WHERE id = ? AND actif = 1");
    $stmt->execute([$_SESSION['admin_id']]);
    return $stmt->fetch();
}

// ====== Customer Authentication ======
function clientEstConnecte() {
    return isset($_SESSION['client_id']) && !empty($_SESSION['client_id']);
}

function clientConnecte() {
    if (!clientEstConnecte()) return null;
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM clients WHERE id = ? AND actif = 1");
    $stmt->execute([$_SESSION['client_id']]);
    return $stmt->fetch();
}

function clientLogin($email, $mot_de_passe) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM clients WHERE email = ? AND actif = 1 AND mot_de_passe IS NOT NULL");
    $stmt->execute([$email]);
    $client = $stmt->fetch();
    if ($client && password_verify($mot_de_passe, $client['mot_de_passe'])) {
        $_SESSION['client_id'] = $client['id'];
        $db->prepare("UPDATE clients SET derniere_connexion = NOW() WHERE id = ?")->execute([$client['id']]);
        return $client;
    }
    return false;
}

function clientRegister($data) {
    $db = getDB();
    // Check if email already exists
    $stmt = $db->prepare("SELECT id FROM clients WHERE email = ?");
    $stmt->execute([$data['email']]);
    if ($stmt->fetch()) return ['error' => 'Cette adresse email est déjà utilisée.'];

    // Check if phone already exists with a password
    $stmt = $db->prepare("SELECT id FROM clients WHERE telephone = ? AND mot_de_passe IS NOT NULL");
    $stmt->execute([$data['telephone']]);
    if ($stmt->fetch()) return ['error' => 'Ce numéro de téléphone est déjà associé à un compte.'];

    $hash = password_hash($data['mot_de_passe'], PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT INTO clients (nom, prenom, email, telephone, mot_de_passe, adresse, ville, type_client) VALUES (?,?,?,?,?,?,?,?)");
    $stmt->execute([
        $data['nom'], $data['prenom'], $data['email'], $data['telephone'],
        $hash, $data['adresse'] ?? '', $data['ville'] ?? '', $data['type_client'] ?? 'particulier'
    ]);
    $client_id = $db->lastInsertId();
    $_SESSION['client_id'] = $client_id;
    return ['success' => true, 'client_id' => $client_id];
}

function clientLogout() {
    unset($_SESSION['client_id']);
}

// Redirection
function redirect($url) {
    header("Location: $url");
    exit;
}

// Message flash
function setFlash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// Gestion du panier
function getPanier() {
    return $_SESSION['panier'] ?? [];
}

function ajouterAuPanier($produit_id, $quantite, $options = []) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM produits WHERE id = ? AND actif = 1");
    $stmt->execute([$produit_id]);
    $produit = $stmt->fetch();
    if (!$produit) return false;

    $prix = $produit['prix_base'];
    $supplement = 0;
    foreach ($options as $opt) {
        if (isset($opt['prix_supplement'])) {
            $supplement += floatval($opt['prix_supplement']);
        }
    }

    $item_key = $produit_id . '_' . md5(json_encode($options));

    if (isset($_SESSION['panier'][$item_key])) {
        $_SESSION['panier'][$item_key]['quantite'] += $quantite;
        $_SESSION['panier'][$item_key]['prix_total'] =
            $_SESSION['panier'][$item_key]['quantite'] *
            ($_SESSION['panier'][$item_key]['prix_unitaire']);
    } else {
        $_SESSION['panier'][$item_key] = [
            'produit_id' => $produit_id,
            'nom' => $produit['nom'],
            'quantite' => $quantite,
            'prix_unitaire' => $prix + $supplement,
            'prix_total' => ($prix + $supplement) * $quantite,
            'options' => $options,
            'image' => $produit['image'],
            'unite' => $produit['unite'],
        ];
    }
    return true;
}

function supprimerDuPanier($item_key) {
    unset($_SESSION['panier'][$item_key]);
}

function viderPanier() {
    $_SESSION['panier'] = [];
}

function totalPanier() {
    $total = 0;
    foreach (getPanier() as $item) {
        $total += $item['prix_total'];
    }
    return $total;
}

function nombreArticlesPanier() {
    $count = 0;
    foreach (getPanier() as $item) {
        $count += $item['quantite'];
    }
    return $count;
}

// Upload de fichier
function uploadFichier($file, $dossier = 'commandes') {
    $upload_dir = UPLOAD_DIR . $dossier . '/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ALLOWED_EXTENSIONS)) {
        return ['error' => 'Type de fichier non autorisé'];
    }

    if ($file['size'] > MAX_FILE_SIZE) {
        return ['error' => 'Fichier trop volumineux (max 50 MB)'];
    }

    $nouveau_nom = uniqid('file_') . '_' . time() . '.' . $extension;
    $chemin = $upload_dir . $nouveau_nom;

    if (move_uploaded_file($file['tmp_name'], $chemin)) {
        return ['success' => true, 'filename' => $dossier . '/' . $nouveau_nom];
    }

    return ['error' => 'Erreur lors de l\'upload'];
}

// Créer une notification
function creerNotification($type, $titre, $message = '', $lien = '', $admin_id = null) {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO notifications (type, titre, message, lien, admin_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$type, $titre, $message, $lien, $admin_id]);
}

// Obtenir les notifications non lues
function getNotificationsNonLues($admin_id = null) {
    $db = getDB();
    $sql = "SELECT * FROM notifications WHERE lue = 0";
    $params = [];
    if ($admin_id) {
        $sql .= " AND (admin_id IS NULL OR admin_id = ?)";
        $params[] = $admin_id;
    }
    $sql .= " ORDER BY created_at DESC LIMIT 20";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// Obtenir un paramètre
function getParametre($cle, $defaut = '') {
    $db = getDB();
    $stmt = $db->prepare("SELECT valeur FROM parametres WHERE cle = ?");
    $stmt->execute([$cle]);
    $result = $stmt->fetch();
    return $result ? $result['valeur'] : $defaut;
}

// Mettre à jour un paramètre
function setParametre($cle, $valeur) {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO parametres (cle, valeur) VALUES (?, ?) ON DUPLICATE KEY UPDATE valeur = ?");
    $stmt->execute([$cle, $valeur, $valeur]);
}

// Formater la date en français
function dateFormatFr($date, $format = 'long') {
    if (!$date) return '-';
    $timestamp = strtotime($date);
    $mois = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
             'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    if ($format === 'long') {
        return date('d', $timestamp) . ' ' . $mois[date('n', $timestamp)] . ' ' . date('Y', $timestamp);
    } elseif ($format === 'court') {
        return date('d/m/Y', $timestamp);
    } elseif ($format === 'complet') {
        return date('d', $timestamp) . ' ' . $mois[date('n', $timestamp)] . ' ' . date('Y', $timestamp) . ' à ' . date('H:i', $timestamp);
    }
    return date('d/m/Y', $timestamp);
}

// Statistiques rapides pour le dashboard
function getStats() {
    $db = getDB();
    $stats = [];

    // Commandes aujourd'hui
    $stmt = $db->query("SELECT COUNT(*) FROM commandes WHERE DATE(created_at) = CURDATE()");
    $stats['commandes_aujourdhui'] = $stmt->fetchColumn();

    // Commandes du mois
    $stmt = $db->query("SELECT COUNT(*) FROM commandes WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
    $stats['commandes_mois'] = $stmt->fetchColumn();

    // Chiffre d'affaires du mois
    $stmt = $db->query("SELECT COALESCE(SUM(total), 0) FROM commandes WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE()) AND statut != 'annulee'");
    $stats['ca_mois'] = $stmt->fetchColumn();

    // Chiffre d'affaires aujourd'hui
    $stmt = $db->query("SELECT COALESCE(SUM(total), 0) FROM commandes WHERE DATE(created_at) = CURDATE() AND statut != 'annulee'");
    $stats['ca_aujourdhui'] = $stmt->fetchColumn();

    // Commandes en attente
    $stmt = $db->query("SELECT COUNT(*) FROM commandes WHERE statut IN ('nouvelle', 'confirmee')");
    $stats['commandes_en_attente'] = $stmt->fetchColumn();

    // Commandes en production
    $stmt = $db->query("SELECT COUNT(*) FROM commandes WHERE statut = 'en_production'");
    $stats['commandes_en_production'] = $stmt->fetchColumn();

    // Total clients
    $stmt = $db->query("SELECT COUNT(*) FROM clients WHERE actif = 1");
    $stats['total_clients'] = $stmt->fetchColumn();

    // Paiements en attente
    $stmt = $db->query("SELECT COALESCE(SUM(total - montant_paye), 0) FROM commandes WHERE statut_paiement IN ('en_attente', 'partiel') AND statut != 'annulee'");
    $stats['paiements_en_attente'] = $stmt->fetchColumn();

    return $stats;
}

// Obtenir les villes de livraison
function getVillesLivraison() {
    $db = getDB();
    $stmt = $db->query("SELECT * FROM villes_livraison WHERE actif = 1 ORDER BY nom");
    return $stmt->fetchAll();
}

// Obtenir les frais de livraison pour une ville
function getFraisLivraison($ville_id) {
    $db = getDB();
    $stmt = $db->prepare("SELECT frais_livraison FROM villes_livraison WHERE id = ?");
    $stmt->execute([$ville_id]);
    $result = $stmt->fetch();
    return $result ? $result['frais_livraison'] : DELIVERY_FEE;
}

// Token CSRF
function csrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . csrfToken() . '">';
}

function verifyCsrf() {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        die('Token CSRF invalide');
    }
}
