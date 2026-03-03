<?php
/**
 * BERRADI PRINT - Espace Client (Mon Compte)
 */
$db = getDB();

// Auto-add columns
try { $db->exec("ALTER TABLE clients ADD COLUMN IF NOT EXISTS mot_de_passe VARCHAR(255) DEFAULT NULL"); } catch (Exception $e) {}
try { $db->exec("ALTER TABLE clients ADD COLUMN IF NOT EXISTS derniere_connexion DATETIME DEFAULT NULL"); } catch (Exception $e) {}

// Logout action
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    clientLogout();
    setFlash('success', 'Vous avez été déconnecté.');
    redirect('index.php');
}

// Must be logged in
if (!clientEstConnecte()) {
    redirect('index.php?page=compte-login');
}

$client = clientConnecte();
if (!$client) {
    clientLogout();
    redirect('index.php?page=compte-login');
}

$tab = $_GET['tab'] ?? 'dashboard';

// Update profile
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profil'])) {
    verifyCsrf();
    $nom = clean($_POST['nom'] ?? '');
    $prenom = clean($_POST['prenom'] ?? '');
    $telephone = clean($_POST['telephone'] ?? '');
    $adresse = clean($_POST['adresse'] ?? '');
    $ville = clean($_POST['ville'] ?? '');

    if ($nom && $prenom && $telephone) {
        $db->prepare("UPDATE clients SET nom=?, prenom=?, telephone=?, adresse=?, ville=? WHERE id=?")
           ->execute([$nom, $prenom, $telephone, $adresse, $ville, $client['id']]);
        setFlash('success', 'Profil mis à jour avec succès.');
        redirect('index.php?page=compte&tab=profil');
    }
}

// Change password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_mdp'])) {
    verifyCsrf();
    $ancien = $_POST['ancien_mdp'] ?? '';
    $nouveau = $_POST['nouveau_mdp'] ?? '';
    $confirm = $_POST['confirm_mdp'] ?? '';

    if (!password_verify($ancien, $client['mot_de_passe'])) {
        setFlash('danger', 'Ancien mot de passe incorrect.');
    } elseif (strlen($nouveau) < 6) {
        setFlash('danger', 'Le nouveau mot de passe doit contenir au moins 6 caractères.');
    } elseif ($nouveau !== $confirm) {
        setFlash('danger', 'Les mots de passe ne correspondent pas.');
    } else {
        $hash = password_hash($nouveau, PASSWORD_DEFAULT);
        $db->prepare("UPDATE clients SET mot_de_passe = ? WHERE id = ?")->execute([$hash, $client['id']]);
        setFlash('success', 'Mot de passe modifié avec succès.');
    }
    redirect('index.php?page=compte&tab=profil');
}

// Fetch orders
$commandes = $db->prepare("SELECT * FROM commandes WHERE client_id = ? ORDER BY created_at DESC LIMIT 20");
$commandes->execute([$client['id']]);
$commandes = $commandes->fetchAll();

// Fetch devis
$devis_list = $db->prepare("SELECT * FROM devis WHERE client_id = ? ORDER BY created_at DESC LIMIT 20");
$devis_list->execute([$client['id']]);
$devis_list = $devis_list->fetchAll();

// Stats
$total_cmd = count($commandes);
$total_depense = 0;
foreach ($commandes as $c) $total_depense += $c['total'];
$villes = getVillesLivraison();
?>

<section class="py-5">
    <div class="container">
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Accueil</a></li>
                <li class="breadcrumb-item active">Mon Compte</li>
            </ol>
        </nav>

        <div class="row g-4">
            <!-- Sidebar -->
            <div class="col-lg-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center p-4">
                        <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center mb-3" style="width:64px;height:64px;font-size:1.5rem;">
                            <?= strtoupper(substr($client['prenom'], 0, 1) . substr($client['nom'], 0, 1)) ?>
                        </div>
                        <h6 class="fw-bold mb-0"><?= htmlspecialchars($client['prenom'] . ' ' . $client['nom']) ?></h6>
                        <small class="text-muted"><?= htmlspecialchars($client['email']) ?></small>
                    </div>
                    <div class="list-group list-group-flush">
                        <a href="?page=compte&tab=dashboard" class="list-group-item list-group-item-action <?= $tab === 'dashboard' ? 'active' : '' ?>">
                            <i class="bi bi-speedometer2 me-2"></i>Tableau de bord
                        </a>
                        <a href="?page=compte&tab=commandes" class="list-group-item list-group-item-action <?= $tab === 'commandes' ? 'active' : '' ?>">
                            <i class="bi bi-cart-check me-2"></i>Mes Commandes
                            <?php if ($total_cmd > 0): ?><span class="badge bg-primary float-end"><?= $total_cmd ?></span><?php endif; ?>
                        </a>
                        <a href="?page=compte&tab=devis" class="list-group-item list-group-item-action <?= $tab === 'devis' ? 'active' : '' ?>">
                            <i class="bi bi-file-earmark-text me-2"></i>Mes Devis
                        </a>
                        <a href="?page=compte&tab=profil" class="list-group-item list-group-item-action <?= $tab === 'profil' ? 'active' : '' ?>">
                            <i class="bi bi-person me-2"></i>Mon Profil
                        </a>
                        <a href="?page=compte&action=logout" class="list-group-item list-group-item-action text-danger">
                            <i class="bi bi-box-arrow-right me-2"></i>Déconnexion
                        </a>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-lg-9">

                <?php if ($tab === 'dashboard'): ?>
                <!-- Dashboard -->
                <h5 class="fw-bold mb-3">Bonjour, <?= htmlspecialchars($client['prenom']) ?> !</h5>
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body d-flex align-items-center">
                                <div class="rounded-circle bg-primary bg-opacity-10 p-3 me-3"><i class="bi bi-cart-check text-primary fs-4"></i></div>
                                <div>
                                    <div class="text-muted small">Commandes</div>
                                    <div class="fw-bold fs-5"><?= $total_cmd ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body d-flex align-items-center">
                                <div class="rounded-circle bg-success bg-opacity-10 p-3 me-3"><i class="bi bi-currency-dollar text-success fs-4"></i></div>
                                <div>
                                    <div class="text-muted small">Total dépensé</div>
                                    <div class="fw-bold fs-5"><?= formatPrix($total_depense) ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body d-flex align-items-center">
                                <div class="rounded-circle bg-info bg-opacity-10 p-3 me-3"><i class="bi bi-file-earmark-text text-info fs-4"></i></div>
                                <div>
                                    <div class="text-muted small">Devis</div>
                                    <div class="fw-bold fs-5"><?= count($devis_list) ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent orders -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-clock-history me-2"></i>Dernières commandes</span>
                        <a href="?page=compte&tab=commandes" class="btn btn-sm btn-outline-primary">Voir tout</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="bg-light">
                                    <tr><th>N° Commande</th><th>Date</th><th>Total</th><th>Statut</th></tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($commandes)): ?>
                                    <tr><td colspan="4" class="text-center text-muted py-4">Aucune commande pour le moment.</td></tr>
                                    <?php else: ?>
                                    <?php foreach (array_slice($commandes, 0, 5) as $c):
                                        $sc = statutCommande($c['statut']);
                                    ?>
                                    <tr>
                                        <td class="fw-bold"><?= $c['numero_commande'] ?></td>
                                        <td><?= dateFormatFr($c['created_at'], 'court') ?></td>
                                        <td class="fw-bold"><?= formatPrix($c['total']) ?></td>
                                        <td><span class="badge bg-<?= $sc['class'] ?>"><?= $sc['label'] ?></span></td>
                                    </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <?php elseif ($tab === 'commandes'): ?>
                <!-- All Orders -->
                <h5 class="fw-bold mb-3"><i class="bi bi-cart-check me-2"></i>Mes Commandes</h5>
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="bg-light">
                                    <tr><th>N° Commande</th><th>Date</th><th>Total</th><th>Paiement</th><th>Statut</th></tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($commandes)): ?>
                                    <tr><td colspan="5" class="text-center text-muted py-4">
                                        <i class="bi bi-cart-x fs-1 d-block mb-2"></i>
                                        Aucune commande pour le moment.<br>
                                        <a href="index.php?page=catalogue" class="btn btn-primary btn-sm mt-2">Découvrir nos services</a>
                                    </td></tr>
                                    <?php else: ?>
                                    <?php foreach ($commandes as $c):
                                        $sc = statutCommande($c['statut']);
                                        $sp = statutPaiement($c['statut_paiement']);
                                    ?>
                                    <tr>
                                        <td class="fw-bold"><?= $c['numero_commande'] ?></td>
                                        <td><?= dateFormatFr($c['created_at'], 'long') ?></td>
                                        <td class="fw-bold"><?= formatPrix($c['total']) ?></td>
                                        <td><span class="badge bg-<?= $sp['class'] ?>"><?= $sp['label'] ?></span></td>
                                        <td><span class="badge bg-<?= $sc['class'] ?>"><?= $sc['label'] ?></span></td>
                                    </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <?php elseif ($tab === 'devis'): ?>
                <!-- Devis -->
                <h5 class="fw-bold mb-3"><i class="bi bi-file-earmark-text me-2"></i>Mes Devis</h5>
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="bg-light">
                                    <tr><th>N° Devis</th><th>Date</th><th>Total</th><th>Statut</th><th>Validité</th></tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($devis_list)): ?>
                                    <tr><td colspan="5" class="text-center text-muted py-4">
                                        <i class="bi bi-file-x fs-1 d-block mb-2"></i>
                                        Aucun devis pour le moment.<br>
                                        <a href="index.php?page=devis" class="btn btn-primary btn-sm mt-2">Demander un devis</a>
                                    </td></tr>
                                    <?php else: ?>
                                    <?php
                                    $devis_statuts = [
                                        'brouillon' => ['label' => 'Brouillon', 'class' => 'secondary'],
                                        'envoye' => ['label' => 'Envoyé', 'class' => 'info'],
                                        'accepte' => ['label' => 'Accepté', 'class' => 'success'],
                                        'refuse' => ['label' => 'Refusé', 'class' => 'danger'],
                                        'expire' => ['label' => 'Expiré', 'class' => 'warning'],
                                    ];
                                    foreach ($devis_list as $d):
                                        $ds = $devis_statuts[$d['statut']] ?? ['label' => $d['statut'], 'class' => 'secondary'];
                                    ?>
                                    <tr>
                                        <td class="fw-bold"><?= $d['numero_devis'] ?></td>
                                        <td><?= dateFormatFr($d['created_at'], 'long') ?></td>
                                        <td class="fw-bold"><?= formatPrix($d['total']) ?></td>
                                        <td><span class="badge bg-<?= $ds['class'] ?>"><?= $ds['label'] ?></span></td>
                                        <td><?= $d['date_validite'] ? dateFormatFr($d['date_validite'], 'court') : '-' ?></td>
                                    </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <?php elseif ($tab === 'profil'): ?>
                <!-- Profile -->
                <h5 class="fw-bold mb-3"><i class="bi bi-person me-2"></i>Mon Profil</h5>
                <div class="row g-4">
                    <div class="col-md-7">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white fw-bold">Informations personnelles</div>
                            <div class="card-body">
                                <form method="POST">
                                    <?= csrfField() ?>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Prénom</label>
                                            <input type="text" name="prenom" class="form-control" required value="<?= htmlspecialchars($client['prenom']) ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Nom</label>
                                            <input type="text" name="nom" class="form-control" required value="<?= htmlspecialchars($client['nom']) ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Email</label>
                                            <input type="email" class="form-control" value="<?= htmlspecialchars($client['email']) ?>" disabled>
                                            <small class="text-muted">L'email ne peut pas être modifié</small>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Téléphone</label>
                                            <input type="tel" name="telephone" class="form-control" required value="<?= htmlspecialchars($client['telephone']) ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Ville</label>
                                            <select name="ville" class="form-select">
                                                <option value="">-- Sélectionner --</option>
                                                <?php foreach ($villes as $v): ?>
                                                <option value="<?= htmlspecialchars($v['nom']) ?>" <?= $client['ville'] === $v['nom'] ? 'selected' : '' ?>><?= htmlspecialchars($v['nom']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Type</label>
                                            <input type="text" class="form-control" value="<?= $client['type_client'] === 'entreprise' ? 'Entreprise' : 'Particulier' ?>" disabled>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">Adresse</label>
                                            <input type="text" name="adresse" class="form-control" value="<?= htmlspecialchars($client['adresse'] ?? '') ?>">
                                        </div>
                                    </div>
                                    <button type="submit" name="update_profil" class="btn btn-primary mt-3">
                                        <i class="bi bi-check-circle me-1"></i>Enregistrer
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-5">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white fw-bold">Changer le mot de passe</div>
                            <div class="card-body">
                                <form method="POST">
                                    <?= csrfField() ?>
                                    <div class="mb-3">
                                        <label class="form-label">Ancien mot de passe</label>
                                        <input type="password" name="ancien_mdp" class="form-control" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Nouveau mot de passe</label>
                                        <input type="password" name="nouveau_mdp" class="form-control" required placeholder="Min. 6 caractères">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Confirmer</label>
                                        <input type="password" name="confirm_mdp" class="form-control" required>
                                    </div>
                                    <button type="submit" name="change_mdp" class="btn btn-warning w-100">
                                        <i class="bi bi-key me-1"></i>Modifier le mot de passe
                                    </button>
                                </form>
                            </div>
                        </div>

                        <div class="card border-0 shadow-sm mt-3">
                            <div class="card-body small">
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Membre depuis</span>
                                    <span class="fw-bold"><?= dateFormatFr($client['created_at'], 'long') ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Dernière connexion</span>
                                    <span class="fw-bold"><?= $client['derniere_connexion'] ? dateFormatFr($client['derniere_connexion'], 'complet') : '-' ?></span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted">Total commandes</span>
                                    <span class="fw-bold"><?= $total_cmd ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</section>
