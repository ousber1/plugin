<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( 'Accès non autorisé.' );
}

global $wpdb;

$year = isset( $_GET['year'] ) ? absint( $_GET['year'] ) : intval( date( 'Y' ) );

// Monthly revenue
$monthly_revenue = array_fill( 1, 12, 0 );
$monthly_orders = array_fill( 1, 12, 0 );

if ( function_exists( 'wc_get_orders' ) ) {
    $orders = wc_get_orders( array(
        'limit'  => -1,
        'status' => array( 'completed', 'processing' ),
        'date_created' => $year . '-01-01...' . $year . '-12-31',
    ) );

    foreach ( $orders as $order ) {
        $month = intval( $order->get_date_created()->format( 'n' ) );
        $monthly_revenue[ $month ] += floatval( $order->get_total() );
        $monthly_orders[ $month ]++;
    }
}

// Monthly expenses
$monthly_expenses = array_fill( 1, 12, 0 );
$expenses_data = $wpdb->get_results( $wpdb->prepare(
    "SELECT MONTH(expense_date) as month, SUM(amount) as total FROM {$wpdb->prefix}print_expenses WHERE YEAR(expense_date) = %d GROUP BY MONTH(expense_date)",
    $year
) );
foreach ( $expenses_data as $exp ) {
    $monthly_expenses[ intval( $exp->month ) ] = floatval( $exp->total );
}

$total_revenue = array_sum( $monthly_revenue );
$total_expenses = array_sum( $monthly_expenses );
$net_profit = $total_revenue - $total_expenses;
$total_orders = array_sum( $monthly_orders );

$months = array( '', 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre' );
?>
<div class="wrap pmp-admin-wrap">
    <h1>Revenus — <?php echo $year; ?></h1>

    <div class="pmp-filters">
        <form method="get">
            <input type="hidden" name="page" value="pmp-revenue">
            <select name="year" onchange="this.form.submit();">
                <?php for ( $y = intval( date( 'Y' ) ); $y >= intval( date( 'Y' ) ) - 5; $y-- ) : ?>
                    <option value="<?php echo $y; ?>" <?php selected( $year, $y ); ?>><?php echo $y; ?></option>
                <?php endfor; ?>
            </select>
        </form>
    </div>

    <div class="pmp-dashboard-cards">
        <div class="pmp-card pmp-card-revenue">
            <div class="pmp-card-icon">&#128176;</div>
            <div class="pmp-card-content">
                <h3><?php echo number_format( $total_revenue, 2, ',', ' ' ); ?> €</h3>
                <p>Revenus totaux</p>
            </div>
        </div>
        <div class="pmp-card pmp-card-expenses">
            <div class="pmp-card-icon">&#128200;</div>
            <div class="pmp-card-content">
                <h3><?php echo number_format( $total_expenses, 2, ',', ' ' ); ?> €</h3>
                <p>Dépenses totales</p>
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
                <h3><?php echo $total_orders; ?></h3>
                <p>Commandes totales</p>
            </div>
        </div>
    </div>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Mois</th>
                <th>Commandes</th>
                <th>Revenus</th>
                <th>Dépenses</th>
                <th>Bénéfice</th>
                <th>Marge</th>
            </tr>
        </thead>
        <tbody>
            <?php for ( $m = 1; $m <= 12; $m++ ) :
                $profit = $monthly_revenue[ $m ] - $monthly_expenses[ $m ];
                $margin = $monthly_revenue[ $m ] > 0 ? ( $profit / $monthly_revenue[ $m ] ) * 100 : 0;
            ?>
                <tr>
                    <td><strong><?php echo esc_html( $months[ $m ] ); ?></strong></td>
                    <td><?php echo intval( $monthly_orders[ $m ] ); ?></td>
                    <td><?php echo number_format( $monthly_revenue[ $m ], 2, ',', ' ' ); ?> €</td>
                    <td><?php echo number_format( $monthly_expenses[ $m ], 2, ',', ' ' ); ?> €</td>
                    <td class="<?php echo $profit >= 0 ? 'positive' : 'negative'; ?>">
                        <?php echo number_format( $profit, 2, ',', ' ' ); ?> €
                    </td>
                    <td><?php echo number_format( $margin, 1 ); ?>%</td>
                </tr>
            <?php endfor; ?>
        </tbody>
        <tfoot>
            <tr>
                <th><strong>TOTAL</strong></th>
                <th><strong><?php echo $total_orders; ?></strong></th>
                <th><strong><?php echo number_format( $total_revenue, 2, ',', ' ' ); ?> €</strong></th>
                <th><strong><?php echo number_format( $total_expenses, 2, ',', ' ' ); ?> €</strong></th>
                <th class="<?php echo $net_profit >= 0 ? 'positive' : 'negative'; ?>">
                    <strong><?php echo number_format( $net_profit, 2, ',', ' ' ); ?> €</strong>
                </th>
                <th><strong><?php echo $total_revenue > 0 ? number_format( ( $net_profit / $total_revenue ) * 100, 1 ) : '0.0'; ?>%</strong></th>
            </tr>
        </tfoot>
    </table>
</div>
