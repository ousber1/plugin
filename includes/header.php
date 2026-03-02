<?php if (!defined('APP_NAME')) { require_once __DIR__ . '/../config/app.php'; require_once __DIR__ . '/../config/database.php'; require_once __DIR__ . '/../includes/functions.php'; } ?>
<!DOCTYPE html>
<html lang="fr" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> - <?= APP_TAGLINE ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Barre supérieure -->
    <div class="top-bar bg-dark text-white py-2 d-none d-md-block">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <small>
                        <i class="bi bi-telephone-fill me-1"></i> <?= APP_PHONE ?>
                        <span class="mx-2">|</span>
                        <i class="bi bi-envelope-fill me-1"></i> <?= APP_EMAIL ?>
                    </small>
                </div>
                <div class="col-md-6 text-end">
                    <small>
                        <i class="bi bi-clock me-1"></i> Lundi - Samedi: 9h00 - 19h00
                        <span class="mx-2">|</span>
                        <i class="bi bi-geo-alt-fill me-1"></i> Maroc
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation principale -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="bi bi-printer-fill text-primary me-2"></i>
                <span class="brand-text"><?= APP_NAME ?></span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarMain">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link <?= ($page ?? '') === 'accueil' ? 'active' : '' ?>" href="index.php">
                            <i class="bi bi-house me-1"></i> Accueil
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?= ($page ?? '') === 'catalogue' ? 'active' : '' ?>" href="#" data-bs-toggle="dropdown">
                            <i class="bi bi-grid me-1"></i> Nos Services
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="index.php?page=catalogue">Tous les services</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <?php
                            try {
                                $db = getDB();
                                $cats = $db->query("SELECT * FROM categories WHERE actif = 1 ORDER BY ordre")->fetchAll();
                                foreach ($cats as $cat): ?>
                                    <li><a class="dropdown-item" href="index.php?page=catalogue&cat=<?= $cat['slug'] ?>">
                                        <i class="<?= $cat['icone'] ?> me-2"></i><?= $cat['nom'] ?>
                                    </a></li>
                                <?php endforeach;
                            } catch(Exception $e) {} ?>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= ($page ?? '') === 'devis' ? 'active' : '' ?>" href="index.php?page=devis">
                            <i class="bi bi-file-text me-1"></i> Demande de Devis
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= ($page ?? '') === 'suivi-commande' ? 'active' : '' ?>" href="index.php?page=suivi-commande">
                            <i class="bi bi-search me-1"></i> Suivi Commande
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= ($page ?? '') === 'contact' ? 'active' : '' ?>" href="index.php?page=contact">
                            <i class="bi bi-chat-dots me-1"></i> Contact
                        </a>
                    </li>
                </ul>
                <div class="d-flex align-items-center">
                    <a href="index.php?page=panier" class="btn btn-outline-primary position-relative me-2">
                        <i class="bi bi-cart3"></i>
                        <span class="badge bg-danger position-absolute top-0 start-100 translate-middle badge-panier" style="<?= nombreArticlesPanier() > 0 ? '' : 'display:none' ?>">
                            <?= nombreArticlesPanier() ?>
                        </span>
                    </a>
                    <a href="https://wa.me/<?= str_replace(['+', ' ', '-'], '', APP_PHONE) ?>" class="btn btn-success btn-sm" target="_blank">
                        <i class="bi bi-whatsapp me-1"></i> WhatsApp
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <?php $flash = getFlash(); if ($flash): ?>
    <div class="container mt-3">
        <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show">
            <?= $flash['message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    </div>
    <?php endif; ?>

    <main>
