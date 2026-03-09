<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( 'Accès non autorisé.' );
}
?>
<div class="wrap pmp-admin-wrap">
    <h1>Statistiques</h1>

    <div class="pmp-chart-controls">
        <select id="pmp-stats-year">
            <?php for ( $y = intval( date( 'Y' ) ); $y >= intval( date( 'Y' ) ) - 5; $y-- ) : ?>
                <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
            <?php endfor; ?>
        </select>
        <button class="button button-primary" id="pmp-stats-refresh">Actualiser</button>
    </div>

    <div class="pmp-stats-grid">
        <div class="pmp-chart-container">
            <h3>Revenus vs Dépenses</h3>
            <canvas id="pmp-stats-revenue-chart" height="300"></canvas>
        </div>

        <div class="pmp-chart-container">
            <h3>Bénéfice mensuel</h3>
            <canvas id="pmp-stats-profit-chart" height="300"></canvas>
        </div>

        <div class="pmp-chart-container">
            <h3>Répartition des dépenses</h3>
            <canvas id="pmp-stats-expenses-pie" height="300"></canvas>
        </div>

        <div class="pmp-chart-container">
            <h3>Commandes par mois</h3>
            <canvas id="pmp-stats-orders-chart" height="300"></canvas>
        </div>
    </div>
</div>
