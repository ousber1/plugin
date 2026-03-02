<?php
/**
 * BERRADI PRINT - Rapports & Statistiques
 */
$db = getDB();

$periode = clean($_GET['periode'] ?? 'mois');
$mois_selec = clean($_GET['mois'] ?? date('Y-m'));

// CA par mois (12 derniers mois)
$ca_mensuel = $db->query("SELECT DATE_FORMAT(created_at, '%Y-%m') as mois, COUNT(*) as nb_commandes, COALESCE(SUM(total), 0) as ca, COALESCE(SUM(montant_paye), 0) as paye FROM commandes WHERE statut != 'annulee' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) GROUP BY DATE_FORMAT(created_at, '%Y-%m') ORDER BY mois")->fetchAll();

// Dépenses par mois
$dep_mensuel = $db->query("SELECT DATE_FORMAT(date_depense, '%Y-%m') as mois, COALESCE(SUM(montant), 0) as total FROM depenses WHERE date_depense >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) GROUP BY DATE_FORMAT(date_depense, '%Y-%m') ORDER BY mois")->fetchAll();
$dep_map = [];
foreach ($dep_mensuel as $d) $dep_map[$d['mois']] = $d['total'];

// Stats du mois sélectionné
$stmt = $db->prepare("SELECT COUNT(*) as nb, COALESCE(SUM(total), 0) as ca, COALESCE(SUM(montant_paye), 0) as paye FROM commandes WHERE DATE_FORMAT(created_at, '%Y-%m') = ? AND statut != 'annulee'");
$stmt->execute([$mois_selec]);
$stats_mois = $stmt->fetch();

$stmt = $db->prepare("SELECT COALESCE(SUM(montant), 0) as total FROM depenses WHERE DATE_FORMAT(date_depense, '%Y-%m') = ?");
$stmt->execute([$mois_selec]);
$dep_mois = $stmt->fetchColumn();

$benefice = $stats_mois['ca'] - $dep_mois;

// Top catégories
$top_cats = $db->query("SELECT cat.nom, COUNT(cl.id) as nb, SUM(cl.prix_total) as ca FROM commande_lignes cl JOIN commandes c ON cl.commande_id = c.id LEFT JOIN produits p ON cl.produit_id = p.id LEFT JOIN categories cat ON p.categorie_id = cat.id WHERE c.statut != 'annulee' AND MONTH(c.created_at) = MONTH(CURDATE()) GROUP BY cat.nom ORDER BY ca DESC LIMIT 5")->fetchAll();

// Top villes
$top_villes = $db->query("SELECT client_ville, COUNT(*) as nb, SUM(total) as ca FROM commandes WHERE statut != 'annulee' AND client_ville != '' GROUP BY client_ville ORDER BY ca DESC LIMIT 10")->fetchAll();

// Commandes par source
$par_source = $db->query("SELECT source, COUNT(*) as nb FROM commandes WHERE MONTH(created_at) = MONTH(CURDATE()) GROUP BY source")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-graph-up me-2"></i>Rapports & Statistiques</h4>
    <form method="GET" class="d-flex gap-2">
        <input type="hidden" name="page" value="rapports">
        <input type="month" name="mois" class="form-control form-control-sm" value="<?= $mois_selec ?>">
        <button class="btn btn-primary btn-sm">Afficher</button>
    </form>
</div>

<!-- Stats clés du mois -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm text-center p-3">
            <h6 class="text-muted small">CA du mois</h6>
            <h4 class="fw-bold text-primary"><?= formatPrix($stats_mois['ca']) ?></h4>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm text-center p-3">
            <h6 class="text-muted small">Encaissé</h6>
            <h4 class="fw-bold text-success"><?= formatPrix($stats_mois['paye']) ?></h4>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm text-center p-3">
            <h6 class="text-muted small">Dépenses</h6>
            <h4 class="fw-bold text-danger"><?= formatPrix($dep_mois) ?></h4>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm text-center p-3">
            <h6 class="text-muted small">Bénéfice</h6>
            <h4 class="fw-bold <?= $benefice >= 0 ? 'text-success' : 'text-danger' ?>"><?= formatPrix($benefice) ?></h4>
        </div>
    </div>
</div>

<!-- Graphique CA -->
<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-bold">CA vs Dépenses (12 derniers mois)</div>
            <div class="card-body"><canvas id="chartRevenu" height="250"></canvas></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-bold">Sources de commandes</div>
            <div class="card-body"><canvas id="chartSource" height="250"></canvas></div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-bold">Top Catégories du mois</div>
            <div class="card-body p-0">
                <table class="table mb-0"><thead class="bg-light"><tr><th>Catégorie</th><th class="text-center">Cmd</th><th class="text-end">CA</th></tr></thead>
                <tbody>
                    <?php foreach ($top_cats as $tc): ?>
                    <tr><td><?= $tc['nom'] ?: 'Non catégorisé' ?></td><td class="text-center"><?= $tc['nb'] ?></td><td class="text-end fw-bold"><?= formatPrix($tc['ca']) ?></td></tr>
                    <?php endforeach; ?>
                    <?php if (empty($top_cats)): ?><tr><td colspan="3" class="text-center text-muted py-3">Aucune donnée</td></tr><?php endif; ?>
                </tbody></table>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-bold">Top Villes</div>
            <div class="card-body p-0">
                <table class="table mb-0"><thead class="bg-light"><tr><th>Ville</th><th class="text-center">Cmd</th><th class="text-end">CA</th></tr></thead>
                <tbody>
                    <?php foreach ($top_villes as $tv): ?>
                    <tr><td><?= $tv['client_ville'] ?></td><td class="text-center"><?= $tv['nb'] ?></td><td class="text-end fw-bold"><?= formatPrix($tv['ca']) ?></td></tr>
                    <?php endforeach; ?>
                    <?php if (empty($top_villes)): ?><tr><td colspan="3" class="text-center text-muted py-3">Aucune donnée</td></tr><?php endif; ?>
                </tbody></table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const caData = <?= json_encode($ca_mensuel) ?>;
    const depMap = <?= json_encode($dep_map) ?>;

    new Chart(document.getElementById('chartRevenu'), {
        type: 'bar',
        data: {
            labels: caData.map(d => d.mois),
            datasets: [
                { label: 'CA (DH)', data: caData.map(d => d.ca), backgroundColor: 'rgba(37,99,235,0.7)', borderRadius: 6 },
                { label: 'Dépenses (DH)', data: caData.map(d => depMap[d.mois] || 0), backgroundColor: 'rgba(220,53,69,0.5)', borderRadius: 6 }
            ]
        },
        options: { responsive: true, scales: { y: { beginAtZero: true } } }
    });

    const srcData = <?= json_encode($par_source) ?>;
    const srcColors = { site: '#0d6efd', telephone: '#198754', whatsapp: '#25d366', direct: '#ffc107', autre: '#6c757d' };
    new Chart(document.getElementById('chartSource'), {
        type: 'doughnut',
        data: {
            labels: srcData.map(s => s.source),
            datasets: [{ data: srcData.map(s => s.nb), backgroundColor: srcData.map(s => srcColors[s.source] || '#6c757d') }]
        },
        options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
    });
});
</script>
