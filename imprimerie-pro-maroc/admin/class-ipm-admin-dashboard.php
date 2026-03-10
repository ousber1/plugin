<?php
/**
 * Tableau de bord admin
 *
 * @package ImprimerieProMaroc
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class IPM_Admin_Dashboard {

    /**
     * Afficher le tableau de bord
     */
    public static function render() {
        $stats = self::get_stats();
        ?>
        <div class="wrap ipm-admin-wrap">
            <h1>
                <span class="dashicons dashicons-printer"></span>
                Imprimerie Pro Maroc — Tableau de bord
            </h1>

            <div class="ipm-dashboard">
                <!-- Statistiques rapides -->
                <div class="ipm-stats-grid">
                    <div class="ipm-stat-card ipm-stat-orders">
                        <div class="ipm-stat-icon">
                            <span class="dashicons dashicons-cart"></span>
                        </div>
                        <div class="ipm-stat-content">
                            <h3><?php echo esc_html( $stats['orders_month'] ); ?></h3>
                            <p>Commandes ce mois</p>
                        </div>
                    </div>

                    <div class="ipm-stat-card ipm-stat-revenue">
                        <div class="ipm-stat-icon">
                            <span class="dashicons dashicons-money-alt"></span>
                        </div>
                        <div class="ipm-stat-content">
                            <h3><?php echo esc_html( number_format( $stats['revenue_month'], 2, ',', ' ' ) ); ?> MAD</h3>
                            <p>Chiffre d'affaires du mois</p>
                        </div>
                    </div>

                    <div class="ipm-stat-card ipm-stat-quotes">
                        <div class="ipm-stat-icon">
                            <span class="dashicons dashicons-media-document"></span>
                        </div>
                        <div class="ipm-stat-content">
                            <h3><?php echo esc_html( $stats['new_quotes'] ); ?></h3>
                            <p>Nouveaux devis</p>
                        </div>
                    </div>

                    <div class="ipm-stat-card ipm-stat-products">
                        <div class="ipm-stat-icon">
                            <span class="dashicons dashicons-format-gallery"></span>
                        </div>
                        <div class="ipm-stat-content">
                            <h3><?php echo esc_html( $stats['total_products'] ); ?></h3>
                            <p>Produits actifs</p>
                        </div>
                    </div>
                </div>

                <div class="ipm-dashboard-grid">
                    <!-- Commandes récentes -->
                    <div class="ipm-dashboard-card">
                        <h2>Commandes récentes</h2>
                        <?php if ( ! empty( $stats['recent_orders'] ) ) : ?>
                            <table class="widefat striped">
                                <thead>
                                    <tr>
                                        <th>Commande</th>
                                        <th>Client</th>
                                        <th>Total</th>
                                        <th>Statut</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ( $stats['recent_orders'] as $order ) : ?>
                                        <tr>
                                            <td>
                                                <a href="<?php echo esc_url( $order['edit_url'] ); ?>">
                                                    #<?php echo esc_html( $order['number'] ); ?>
                                                </a>
                                            </td>
                                            <td><?php echo esc_html( $order['customer'] ); ?></td>
                                            <td><?php echo esc_html( $order['total'] ); ?> MAD</td>
                                            <td><span class="ipm-status-badge"><?php echo esc_html( $order['status'] ); ?></span></td>
                                            <td><?php echo esc_html( $order['date'] ); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else : ?>
                            <p>Aucune commande récente.</p>
                        <?php endif; ?>
                    </div>

                    <!-- Devis récents -->
                    <div class="ipm-dashboard-card">
                        <h2>Devis récents</h2>
                        <?php if ( ! empty( $stats['recent_quotes'] ) ) : ?>
                            <table class="widefat striped">
                                <thead>
                                    <tr>
                                        <th>Référence</th>
                                        <th>Client</th>
                                        <th>Type</th>
                                        <th>Statut</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ( $stats['recent_quotes'] as $quote ) : ?>
                                        <tr>
                                            <td>
                                                <a href="<?php echo esc_url( $quote['edit_url'] ); ?>">
                                                    <?php echo esc_html( $quote['ref'] ); ?>
                                                </a>
                                            </td>
                                            <td><?php echo esc_html( $quote['client'] ); ?></td>
                                            <td><?php echo esc_html( $quote['type'] ); ?></td>
                                            <td><span class="ipm-status-badge ipm-status-<?php echo esc_attr( $quote['status'] ); ?>"><?php echo esc_html( $quote['status_label'] ); ?></span></td>
                                            <td><?php echo esc_html( $quote['date'] ); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else : ?>
                            <p>Aucun devis récent.</p>
                        <?php endif; ?>

                        <p><a href="<?php echo esc_url( admin_url( 'admin.php?page=ipm-quotes' ) ); ?>" class="button">Voir tous les devis</a></p>
                    </div>
                </div>

                <!-- Liens rapides -->
                <div class="ipm-quick-links">
                    <h2>Actions rapides</h2>
                    <div class="ipm-links-grid">
                        <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=ipm_product' ) ); ?>" class="ipm-quick-link">
                            <span class="dashicons dashicons-plus-alt"></span>
                            <span>Nouveau produit</span>
                        </a>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=ipm-quotes' ) ); ?>" class="ipm-quick-link">
                            <span class="dashicons dashicons-media-document"></span>
                            <span>Gérer les devis</span>
                        </a>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=ipm-files' ) ); ?>" class="ipm-quick-link">
                            <span class="dashicons dashicons-media-default"></span>
                            <span>Fichiers clients</span>
                        </a>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=ipm-shipping' ) ); ?>" class="ipm-quick-link">
                            <span class="dashicons dashicons-car"></span>
                            <span>Livraison</span>
                        </a>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=ipm-settings' ) ); ?>" class="ipm-quick-link">
                            <span class="dashicons dashicons-admin-settings"></span>
                            <span>Réglages</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Récupérer les statistiques
     *
     * @return array
     */
    public static function get_stats() {
        $stats = array(
            'orders_month'   => 0,
            'revenue_month'  => 0,
            'new_quotes'     => 0,
            'total_products' => 0,
            'recent_orders'  => array(),
            'recent_quotes'  => array(),
        );

        // Produits
        $stats['total_products'] = wp_count_posts( 'ipm_product' )->publish;

        // Devis
        $new_quotes = get_posts( array(
            'post_type'      => 'ipm_quote',
            'posts_per_page' => -1,
            'meta_query'     => array(
                array(
                    'key'   => '_ipm_quote_status',
                    'value' => 'new',
                ),
            ),
        ) );
        $stats['new_quotes'] = count( $new_quotes );

        // Devis récents
        $recent_quotes = get_posts( array(
            'post_type'      => 'ipm_quote',
            'posts_per_page' => 5,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ) );

        $status_labels = IPM_Quote::get_status_labels();
        foreach ( $recent_quotes as $q ) {
            $status = get_post_meta( $q->ID, '_ipm_quote_status', true );
            $stats['recent_quotes'][] = array(
                'ref'          => 'DEV-' . str_pad( $q->ID, 6, '0', STR_PAD_LEFT ),
                'client'       => get_post_meta( $q->ID, '_ipm_quote_first_name', true ) . ' ' . get_post_meta( $q->ID, '_ipm_quote_last_name', true ),
                'type'         => get_post_meta( $q->ID, '_ipm_quote_print_type', true ),
                'status'       => $status,
                'status_label' => isset( $status_labels[ $status ] ) ? $status_labels[ $status ] : $status,
                'date'         => wp_date( 'd/m/Y', strtotime( $q->post_date ) ),
                'edit_url'     => admin_url( 'post.php?post=' . $q->ID . '&action=edit' ),
            );
        }

        // Commandes WooCommerce
        if ( class_exists( 'WooCommerce' ) ) {
            $first_of_month = gmdate( 'Y-m-01' );

            $orders_month = wc_get_orders( array(
                'date_created' => '>=' . $first_of_month,
                'limit'        => -1,
                'return'       => 'ids',
            ) );
            $stats['orders_month'] = count( $orders_month );

            // Revenu
            $revenue = 0;
            foreach ( $orders_month as $order_id ) {
                $order = wc_get_order( $order_id );
                if ( $order ) {
                    $revenue += (float) $order->get_total();
                }
            }
            $stats['revenue_month'] = $revenue;

            // Commandes récentes
            $recent = wc_get_orders( array(
                'limit'   => 5,
                'orderby' => 'date',
                'order'   => 'DESC',
            ) );

            foreach ( $recent as $order ) {
                $stats['recent_orders'][] = array(
                    'number'   => $order->get_order_number(),
                    'customer' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                    'total'    => $order->get_total(),
                    'status'   => wc_get_order_status_name( $order->get_status() ),
                    'date'     => $order->get_date_created()->format( 'd/m/Y' ),
                    'edit_url' => $order->get_edit_order_url(),
                );
            }
        }

        return $stats;
    }
}
