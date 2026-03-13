<?php
defined( 'ABSPATH' ) || exit;
?>
<div class="wrap pfp-dashboard">
    <h1><?php esc_html_e( 'Tableau de Bord', 'printflow-pro' ); ?></h1>

    <!-- KPI Widgets -->
    <div class="pfp-kpi-row" id="pfp-kpi-widgets">
        <div class="pfp-kpi-card card">
            <h3><?php esc_html_e( 'Commandes Aujourd\'hui', 'printflow-pro' ); ?></h3>
            <div class="pfp-kpi-value" id="pfp-kpi-orders-today">
                <span class="spinner is-active"></span>
            </div>
        </div>

        <div class="pfp-kpi-card card">
            <h3><?php esc_html_e( 'Chiffre d\'Affaires', 'printflow-pro' ); ?></h3>
            <div class="pfp-kpi-value" id="pfp-kpi-revenue">
                <span class="spinner is-active"></span>
            </div>
            <span class="pfp-kpi-period"><?php esc_html_e( 'Ce mois', 'printflow-pro' ); ?></span>
        </div>

        <div class="pfp-kpi-card card">
            <h3><?php esc_html_e( 'Bénéfice Net', 'printflow-pro' ); ?></h3>
            <div class="pfp-kpi-value" id="pfp-kpi-profit">
                <span class="spinner is-active"></span>
            </div>
            <span class="pfp-kpi-period"><?php esc_html_e( 'Ce mois', 'printflow-pro' ); ?></span>
        </div>

        <div class="pfp-kpi-card card">
            <h3><?php esc_html_e( 'Statut Production', 'printflow-pro' ); ?></h3>
            <div class="pfp-kpi-value" id="pfp-kpi-production-status">
                <span class="spinner is-active"></span>
            </div>
        </div>
    </div>

    <!-- Low Stock Alerts -->
    <div class="card pfp-alerts-card" id="pfp-low-stock-alerts">
        <h2><?php esc_html_e( 'Alertes Stock Bas', 'printflow-pro' ); ?></h2>
        <div id="pfp-alerts-container">
            <span class="spinner is-active"></span>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="pfp-charts-row">
        <div class="card pfp-chart-card">
            <h2><?php esc_html_e( 'Évolution du Chiffre d\'Affaires', 'printflow-pro' ); ?></h2>
            <div class="pfp-chart-container">
                <canvas id="pfp-chart-revenue"></canvas>
            </div>
        </div>

        <div class="card pfp-chart-card">
            <h2><?php esc_html_e( 'Répartition des Commandes', 'printflow-pro' ); ?></h2>
            <div class="pfp-chart-container">
                <canvas id="pfp-chart-orders"></canvas>
            </div>
        </div>
    </div>

    <div class="pfp-charts-row">
        <div class="card pfp-chart-card">
            <h2><?php esc_html_e( 'Production par Catégorie', 'printflow-pro' ); ?></h2>
            <div class="pfp-chart-container">
                <canvas id="pfp-chart-production"></canvas>
            </div>
        </div>

        <div class="card pfp-chart-card">
            <h2><?php esc_html_e( 'Top Produits', 'printflow-pro' ); ?></h2>
            <div class="pfp-chart-container">
                <canvas id="pfp-chart-top-products"></canvas>
            </div>
        </div>
    </div>

    <!-- Recent Orders -->
    <div class="card">
        <h2><?php esc_html_e( 'Commandes Récentes', 'printflow-pro' ); ?></h2>
        <table class="widefat striped" id="pfp-recent-orders-table">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'N° Commande', 'printflow-pro' ); ?></th>
                    <th><?php esc_html_e( 'Client', 'printflow-pro' ); ?></th>
                    <th><?php esc_html_e( 'Produit', 'printflow-pro' ); ?></th>
                    <th><?php esc_html_e( 'Montant', 'printflow-pro' ); ?></th>
                    <th><?php esc_html_e( 'Statut', 'printflow-pro' ); ?></th>
                    <th><?php esc_html_e( 'Date', 'printflow-pro' ); ?></th>
                </tr>
            </thead>
            <tbody id="pfp-recent-orders-body">
                <tr>
                    <td colspan="6" class="pfp-loading">
                        <span class="spinner is-active"></span>
                        <?php esc_html_e( 'Chargement...', 'printflow-pro' ); ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<script type="text/javascript">
jQuery( document ).ready( function( $ ) {
    if ( typeof pfp_admin === 'undefined' ) {
        return;
    }

    // Load KPI data
    $.post( pfp_admin.ajax_url, {
        action: 'pfp_get_dashboard_kpis',
        nonce: pfp_admin.nonce
    }, function( response ) {
        if ( response.success ) {
            $( '#pfp-kpi-orders-today' ).html( response.data.orders_today );
            $( '#pfp-kpi-revenue' ).html( response.data.revenue );
            $( '#pfp-kpi-profit' ).html( response.data.profit );
            $( '#pfp-kpi-production-status' ).html( response.data.production_status );
        }
    });

    // Load low stock alerts
    $.post( pfp_admin.ajax_url, {
        action: 'pfp_get_low_stock_alerts',
        nonce: pfp_admin.nonce
    }, function( response ) {
        if ( response.success ) {
            $( '#pfp-alerts-container' ).html( response.data.html );
        }
    });

    // Load recent orders
    $.post( pfp_admin.ajax_url, {
        action: 'pfp_get_recent_orders',
        nonce: pfp_admin.nonce
    }, function( response ) {
        if ( response.success ) {
            $( '#pfp-recent-orders-body' ).html( response.data.html );
        }
    });

    // Load charts
    $.post( pfp_admin.ajax_url, {
        action: 'pfp_get_dashboard_charts',
        nonce: pfp_admin.nonce
    }, function( response ) {
        if ( response.success && typeof Chart !== 'undefined' ) {
            // Revenue chart
            new Chart( document.getElementById( 'pfp-chart-revenue' ), {
                type: 'line',
                data: response.data.revenue_chart,
                options: { responsive: true, maintainAspectRatio: false }
            });

            // Orders chart
            new Chart( document.getElementById( 'pfp-chart-orders' ), {
                type: 'doughnut',
                data: response.data.orders_chart,
                options: { responsive: true, maintainAspectRatio: false }
            });

            // Production chart
            new Chart( document.getElementById( 'pfp-chart-production' ), {
                type: 'bar',
                data: response.data.production_chart,
                options: { responsive: true, maintainAspectRatio: false }
            });

            // Top products chart
            new Chart( document.getElementById( 'pfp-chart-top-products' ), {
                type: 'horizontalBar',
                data: response.data.top_products_chart,
                options: { responsive: true, maintainAspectRatio: false }
            });
        }
    });
});
</script>
