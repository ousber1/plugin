<?php
$notifications = getNotificationsNonLues($admin['id'] ?? null);
$nb_notifs = count($notifications);
$stats_rapide = getStats();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <div class="admin-wrapper">
        <!-- Sidebar -->
        <aside class="admin-sidebar" id="adminSidebar">
            <div class="sidebar-header">
                <a href="index.php" class="text-decoration-none">
                    <i class="bi bi-printer-fill text-primary me-2"></i>
                    <span class="fw-bold text-white"><?= APP_NAME ?></span>
                </a>
                <button class="btn btn-sm text-white d-lg-none" onclick="toggleSidebar()">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <nav class="sidebar-nav">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link <?= $page === 'dashboard' ? 'active' : '' ?>" href="index.php?page=dashboard">
                            <i class="bi bi-speedometer2"></i>
                            <span>Tableau de bord</span>
                        </a>
                    </li>

                    <li class="nav-section">GESTION</li>

                    <li class="nav-item">
                        <a class="nav-link <?= in_array($page, ['commandes', 'commande_detail', 'commande_nouvelle']) ? 'active' : '' ?>" href="index.php?page=commandes">
                            <i class="bi bi-cart-check"></i>
                            <span>Commandes</span>
                            <?php if ($stats_rapide['commandes_en_attente'] > 0): ?>
                            <span class="badge bg-danger ms-auto"><?= $stats_rapide['commandes_en_attente'] ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= in_array($page, ['devis', 'devis_detail', 'devis_nouveau']) ? 'active' : '' ?>" href="index.php?page=devis">
                            <i class="bi bi-file-earmark-text"></i>
                            <span>Devis</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= in_array($page, ['clients', 'client_detail']) ? 'active' : '' ?>" href="index.php?page=clients">
                            <i class="bi bi-people"></i>
                            <span>Clients</span>
                        </a>
                    </li>

                    <li class="nav-section">CATALOGUE</li>

                    <li class="nav-item">
                        <a class="nav-link <?= in_array($page, ['produits', 'produit_edit']) ? 'active' : '' ?>" href="index.php?page=produits">
                            <i class="bi bi-box"></i>
                            <span>Produits / Services</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $page === 'categories' ? 'active' : '' ?>" href="index.php?page=categories">
                            <i class="bi bi-tags"></i>
                            <span>Catégories</span>
                        </a>
                    </li>

                    <li class="nav-section">FINANCES</li>

                    <li class="nav-item">
                        <a class="nav-link <?= $page === 'depenses' ? 'active' : '' ?>" href="index.php?page=depenses">
                            <i class="bi bi-cash-stack"></i>
                            <span>Dépenses</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $page === 'rapports' ? 'active' : '' ?>" href="index.php?page=rapports">
                            <i class="bi bi-graph-up"></i>
                            <span>Rapports</span>
                        </a>
                    </li>

                    <li class="nav-section">MARKETING & SEO</li>

                    <li class="nav-item">
                        <a class="nav-link <?= $page === 'seo' ? 'active' : '' ?>" href="index.php?page=seo">
                            <i class="bi bi-search"></i>
                            <span>Outils SEO</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $page === 'seo_kit' ? 'active' : '' ?>" href="index.php?page=seo_kit">
                            <i class="bi bi-tools"></i>
                            <span>SEO Kit</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $page === 'pixels' ? 'active' : '' ?>" href="index.php?page=pixels">
                            <i class="bi bi-broadcast"></i>
                            <span>Pixels & Tracking</span>
                        </a>
                    </li>

                    <li class="nav-section">PARAMÈTRES</li>

                    <li class="nav-item">
                        <a class="nav-link <?= $page === 'import_export' ? 'active' : '' ?>" href="index.php?page=import_export">
                            <i class="bi bi-arrow-left-right"></i>
                            <span>Import / Export</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $page === 'villes' ? 'active' : '' ?>" href="index.php?page=villes">
                            <i class="bi bi-geo-alt"></i>
                            <span>Villes & Livraison</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $page === 'admins' ? 'active' : '' ?>" href="index.php?page=admins">
                            <i class="bi bi-person-gear"></i>
                            <span>Utilisateurs</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $page === 'parametres' ? 'active' : '' ?>" href="index.php?page=parametres">
                            <i class="bi bi-gear"></i>
                            <span>Paramètres</span>
                        </a>
                    </li>
                </ul>
            </nav>
            <div class="sidebar-footer">
                <a href="../index.php" class="btn btn-sm btn-outline-light w-100" target="_blank">
                    <i class="bi bi-box-arrow-up-right me-1"></i>Voir le site
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="admin-main">
            <!-- Top Navbar -->
            <header class="admin-topbar">
                <div class="d-flex align-items-center">
                    <button class="btn btn-link text-dark d-lg-none me-2" onclick="toggleSidebar()">
                        <i class="bi bi-list fs-4"></i>
                    </button>
                    <h5 class="mb-0 fw-bold d-none d-md-block"><?= APP_NAME ?> - Administration</h5>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <!-- Notifications -->
                    <div class="dropdown">
                        <button class="btn btn-light position-relative" data-bs-toggle="dropdown">
                            <i class="bi bi-bell"></i>
                            <?php if ($nb_notifs > 0): ?>
                            <span class="badge bg-danger position-absolute top-0 start-100 translate-middle"><?= $nb_notifs ?></span>
                            <?php endif; ?>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end" style="width:300px;max-height:400px;overflow-y:auto;">
                            <h6 class="dropdown-header">Notifications</h6>
                            <?php if (empty($notifications)): ?>
                            <div class="text-center p-3 text-muted small">Aucune notification</div>
                            <?php else: ?>
                            <?php foreach ($notifications as $n): ?>
                            <a class="dropdown-item py-2" href="<?= $n['lien'] ?: '#' ?>">
                                <div class="fw-bold small"><?= $n['titre'] ?></div>
                                <small class="text-muted"><?= dateFormatFr($n['created_at'], 'complet') ?></small>
                            </a>
                            <?php endforeach; ?>
                            <?php endif; ?>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-center small" href="index.php?page=notifications">Voir toutes</a>
                        </div>
                    </div>

                    <!-- Profil -->
                    <div class="dropdown">
                        <button class="btn btn-light d-flex align-items-center gap-2" data-bs-toggle="dropdown">
                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width:32px;height:32px;">
                                <?= strtoupper(substr($admin['prenom'], 0, 1)) ?>
                            </div>
                            <span class="d-none d-md-inline"><?= $admin['prenom'] ?></span>
                            <i class="bi bi-chevron-down small"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><span class="dropdown-item-text small text-muted"><?= $admin['email'] ?></span></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="index.php?page=parametres"><i class="bi bi-gear me-2"></i>Paramètres</a></li>
                            <li><a class="dropdown-item text-danger" href="index.php?page=logout"><i class="bi bi-box-arrow-right me-2"></i>Déconnexion</a></li>
                        </ul>
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <div class="admin-content">
                <?php $flash = getFlash(); if ($flash): ?>
                <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show">
                    <?= $flash['message'] ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
