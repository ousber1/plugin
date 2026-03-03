<?php
/**
 * BERRADI PRINT - Dashboard Admin
 */
$db = getDB();
$stats = getStats();

// Dernières commandes
$dernieres_commandes = $db->query("SELECT * FROM commandes ORDER BY created_at DESC LIMIT 10")->fetchAll();

// Chiffre d'affaires des 7 derniers jours
$ca_7jours = $db->query("SELECT DATE(created_at) as jour, COALESCE(SUM(total), 0) as total FROM commandes WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND statut != 'annulee' GROUP BY DATE(created_at) ORDER BY jour")->fetchAll();

// Commandes par statut
$par_statut = $db->query("SELECT statut, COUNT(*) as nb FROM commandes GROUP BY statut")->fetchAll();

// Top produits du mois
$top_produits = $db->query("SELECT cl.designation, SUM(cl.quantite) as total_qte, SUM(cl.prix_total) as total_ca FROM commande_lignes cl JOIN commandes c ON cl.commande_id = c.id WHERE MONTH(c.created_at) = MONTH(CURDATE()) AND c.statut != 'annulee' GROUP BY cl.designation ORDER BY total_ca DESC LIMIT 5")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0">Tableau de Bord</h4>
        <small class="text-muted">Bienvenue, <?= $admin['prenom'] ?> ! Voici un aperçu de votre activité.</small>
    </div>
    <div>
        <a href="index.php?page=commande_nouvelle" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i>Nouvelle commande
        </a>
    </div>
</div>

<!-- Quick customizer tabs -->
<?php $last_tab = $_SESSION['last_customizer_tab'] ?? null; ?>
<ul class="nav nav-tabs mb-2">
    <li class="nav-item"><a class="nav-link <?= $last_tab === 'header' ? 'active' : '' ?>" href="index.php?page=header_footer&tab=header">Header <?= $last_tab === 'header' ? '<span class="badge-last-mod">Dernière modif</span>' : '' ?></a></li>
    <li class="nav-item"><a class="nav-link <?= $last_tab === 'footer' ? 'active' : '' ?>" href="index.php?page=header_footer&tab=footer">Footer <?= $last_tab === 'footer' ? '<span class="badge-last-mod">Dernière modif</span>' : '' ?></a></li>
    <li class="nav-item"><a class="nav-link <?= $last_tab === 'social' ? 'active' : '' ?>" href="index.php?page=header_footer&tab=social">Réseaux sociaux <?= $last_tab === 'social' ? '<span class="badge-last-mod">Dernière modif</span>' : '' ?></a></li>
    <li class="nav-item"><a class="nav-link <?= $last_tab === 'custom' ? 'active' : '' ?>" href="index.php?page=header_footer&tab=custom">Code personnalisé <?= $last_tab === 'custom' ? '<span class="badge-last-mod">Dernière modif</span>' : '' ?></a></li>
    <li class="nav-item"><a class="nav-link <?= $last_tab === 'invoices' ? 'active' : '' ?>" href="index.php?page=header_footer&tab=invoices">Factures & Devis <?= $last_tab === 'invoices' ? '<span class="badge-last-mod">Dernière modif</span>' : '' ?></a></li>
    <li class="nav-item"><a class="nav-link <?= $last_tab === 'orders' ? 'active' : '' ?>" href="index.php?page=header_footer&tab=orders">Commandes Imprimées <?= $last_tab === 'orders' ? '<span class="badge-last-mod">Dernière modif</span>' : '' ?></a></li>
</ul>
<?php if ($last_tab): unset($_SESSION['last_customizer_tab']); endif; ?>

<!-- Stats Cards -->
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="card border-0 shadow-sm stat-card">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <p class="text-muted small mb-1">CA Aujourd'hui</p>
                        <h4 class="fw-bold mb-0"><?= formatPrix($stats['ca_aujourdhui']) ?></h4>
                        <small class="text-muted"><?= $stats['commandes_aujourdhui'] ?> commande(s)</small>
                    </div>
                    <div class="stat-icon bg-primary-soft">
                        <i class="bi bi-cash-coin text-primary"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card border-0 shadow-sm stat-card">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <p class="text-muted small mb-1">CA du Mois</p>
                        <h4 class="fw-bold mb-0"><?= formatPrix($stats['ca_mois']) ?></h4>
                        <small class="text-muted"><?= $stats['commandes_mois'] ?> commande(s)</small>
                    </div>
                    <div class="stat-icon bg-success-soft">
                        <i class="bi bi-graph-up-arrow text-success"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card border-0 shadow-sm stat-card">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <p class="text-muted small mb-1">En attente</p>
                        <h4 class="fw-bold mb-0"><?= $stats['commandes_en_attente'] ?></h4>
                        <small class="text-muted"><?= $stats['commandes_en_production'] ?> en production</small>
                    </div>
                    <div class="stat-icon bg-warning-soft">
                        <i class="bi bi-clock-history text-warning"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card border-0 shadow-sm stat-card">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <p class="text-muted small mb-1">Impayés</p>
                        <h4 class="fw-bold mb-0"><?= formatPrix($stats['paiements_en_attente']) ?></h4>
                        <small class="text-muted"><?= $stats['total_clients'] ?> client(s) total</small>
                    </div>
                    <div class="stat-icon bg-danger-soft">
                        <i class="bi bi-exclamation-triangle text-danger"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Graphiques -->
<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-bold">
                <i class="bi bi-bar-chart me-2"></i>Chiffre d'affaires (7 derniers jours)
            </div>
            <div class="card-body">
                <canvas id="chartCA" height="250"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-bold">
                <i class="bi bi-pie-chart me-2"></i>Commandes par statut
            </div>
            <div class="card-body">
                <canvas id="chartStatut" height="250"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Dernières commandes -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-bold"><i class="bi bi-cart-check me-2"></i>Dernières commandes</span>
                <a href="index.php?page=commandes" class="btn btn-sm btn-outline-primary">Voir tout</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th>N° Commande</th>
                                <th>Client</th>
                                <th>Total</th>
                                <th>Statut</th>
                                <th>Date</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($dernieres_commandes as $cmd):
                                $st = statutCommande($cmd['statut']);
                                $sp = statutPaiement($cmd['statut_paiement']);
                            ?>
                            <tr>
                                <td class="fw-bold"><?= $cmd['numero_commande'] ?></td>
                                <td>
                                    <?= $cmd['client_nom'] ?><br>
                                    <small class="text-muted"><?= $cmd['client_telephone'] ?></small>
                                </td>
                                <td class="fw-bold"><?= formatPrix($cmd['total']) ?></td>
                                <td>
                                    <span class="badge bg-<?= $st['class'] ?>"><?= $st['label'] ?></span>
                                    <span class="badge bg-<?= $sp['class'] ?> ms-1"><?= $sp['label'] ?></span>
                                </td>
                                <td><small><?= dateFormatFr($cmd['created_at'], 'court') ?></small></td>
                                <td>
                                    <a href="index.php?page=commande_detail&id=<?= $cmd['id'] ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Produits -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-bold">
                <i class="bi bi-trophy me-2"></i>Top Produits du Mois
            </div>
            <div class="card-body p-0">
                <?php if (empty($top_produits)): ?>
                <div class="text-center p-4 text-muted">
                    <i class="bi bi-inbox fs-1"></i>
                    <p class="mt-2">Aucune donnée ce mois</p>
                </div>
                <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($top_produits as $i => $tp): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <span class="badge bg-primary me-2">#<?= $i + 1 ?></span>
                            <span class="small"><?= mb_strimwidth($tp['designation'], 0, 30, '...') ?></span>
                        </div>
                        <span class="fw-bold small"><?= formatPrix($tp['total_ca']) ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Graphique CA
    const caData = <?= json_encode($ca_7jours) ?>;
    new Chart(document.getElementById('chartCA'), {
        type: 'bar',
        data: {
            labels: caData.map(d => d.jour),
            datasets: [{
                label: 'Chiffre d\'affaires (DH)',
                data: caData.map(d => d.total),
                backgroundColor: 'rgba(37, 99, 235, 0.7)',
                borderRadius: 8
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true } }
        }
    });

    // Graphique Statuts
    const statutData = <?= json_encode($par_statut) ?>;
    const colors = { nouvelle: '#0d6efd', confirmee: '#0dcaf0', en_production: '#ffc107', prete: '#198754', en_livraison: '#6610f2', livree: '#20c997', annulee: '#dc3545' };
    new Chart(document.getElementById('chartStatut'), {
        type: 'doughnut',
        data: {
            labels: statutData.map(s => s.statut),
            datasets: [{
                data: statutData.map(s => s.nb),
                backgroundColor: statutData.map(s => colors[s.statut] || '#6c757d')
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } }
        }
    });
});
</script>
