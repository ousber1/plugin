<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( 'Accès non autorisé.' );
}

global $wpdb;

// Quick stats
$total_orders = 0;
$total_revenue = 0;
if ( function_exists( 'wc_get_orders' ) ) {
    $orders = wc_get_orders( array(
        'limit'  => -1,
        'status' => array( 'completed', 'processing' ),
        'date_created' => date( 'Y' ) . '-01-01...' . date( 'Y' ) . '-12-31',
    ) );
    $total_orders = count( $orders );
    foreach ( $orders as $order ) {
        $total_revenue += floatval( $order->get_total() );
    }
}

$total_expenses = $wpdb->get_var(
    $wpdb->prepare(
        "SELECT COALESCE(SUM(amount), 0) FROM {$wpdb->prefix}print_expenses WHERE YEAR(expense_date) = %d",
        intval( date( 'Y' ) )
    )
);

$machines_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}print_machines" );
$active_machines = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}print_machines WHERE status = 'active'" );
$net_profit = $total_revenue - floatval( $total_expenses );

// Recent expenses
$recent_expenses = $wpdb->get_results(
    "SELECT * FROM {$wpdb->prefix}print_expenses ORDER BY expense_date DESC LIMIT 5"
);
?>
<div class="wrap pmp-admin-wrap">
    <h1>Imprimerie Manager — Tableau de bord</h1>

    <div class="pmp-dashboard-cards">
        <div class="pmp-card pmp-card-revenue">
            <div class="pmp-card-icon">&#128176;</div>
            <div class="pmp-card-content">
                <h3><?php echo number_format( $total_revenue, 2, ',', ' ' ); ?> €</h3>
                <p>Revenus <?php echo date( 'Y' ); ?></p>
            </div>
        </div>

        <div class="pmp-card pmp-card-expenses">
            <div class="pmp-card-icon">&#128200;</div>
            <div class="pmp-card-content">
                <h3><?php echo number_format( floatval( $total_expenses ), 2, ',', ' ' ); ?> €</h3>
                <p>Dépenses <?php echo date( 'Y' ); ?></p>
            </div>
        </div>

        <div class="pmp-card pmp-card-profit">
            <div class="pmp-card-icon">&#128178;</div>
            <div class="pmp-card-content">
                <h3 class="<?php echo $net_profit >= 0 ? 'positive' : 'negative'; ?>">
                    <?php echo number_format( $net_profit, 2, ',', ' ' ); ?> €
                </h3>
                <p>Bénéfice net</p>
            </div>
        </div>

        <div class="pmp-card pmp-card-orders">
            <div class="pmp-card-icon">&#128230;</div>
            <div class="pmp-card-content">
                <h3><?php echo intval( $total_orders ); ?></h3>
                <p>Commandes <?php echo date( 'Y' ); ?></p>
            </div>
        </div>

        <div class="pmp-card pmp-card-machines">
            <div class="pmp-card-icon">&#9881;</div>
            <div class="pmp-card-content">
                <h3><?php echo intval( $active_machines ); ?> / <?php echo intval( $machines_count ); ?></h3>
                <p>Machines actives</p>
            </div>
        </div>
    </div>

    <div class="pmp-dashboard-charts">
        <div class="pmp-chart-container">
            <h3>Revenus vs Dépenses — <?php echo date( 'Y' ); ?></h3>
            <div class="pmp-chart-controls">
                <select id="pmp-chart-year">
                    <?php for ( $y = intval( date( 'Y' ) ); $y >= intval( date( 'Y' ) ) - 5; $y-- ) : ?>
                        <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <canvas id="pmp-revenue-chart" height="300"></canvas>
        </div>

        <div class="pmp-chart-container">
            <h3>Bénéfice mensuel</h3>
            <canvas id="pmp-profit-chart" height="300"></canvas>
        </div>
    </div>

    <div class="pmp-dashboard-tables">
        <div class="pmp-table-container">
            <h3>Dernières dépenses</h3>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Catégorie</th>
                        <th>Description</th>
                        <th>Montant</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $recent_expenses ) ) : ?>
                        <tr><td colspan="4">Aucune dépense enregistrée.</td></tr>
                    <?php else : ?>
                        <?php foreach ( $recent_expenses as $exp ) : ?>
                            <tr>
                                <td><?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $exp->expense_date ) ) ); ?></td>
                                <td><?php echo esc_html( $exp->category ); ?></td>
                                <td><?php echo esc_html( $exp->description ); ?></td>
                                <td><?php echo number_format( floatval( $exp->amount ), 2, ',', ' ' ); ?> €</td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
