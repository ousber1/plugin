<?php
/**
 * Espace client
 *
 * @package ImprimerieProMaroc
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class IPM_Customer_Area {

    /**
     * Ajouter les endpoints WooCommerce
     */
    public function add_endpoints() {
        add_rewrite_endpoint( 'mes-devis', EP_ROOT | EP_PAGES );
        add_rewrite_endpoint( 'mes-fichiers', EP_ROOT | EP_PAGES );
    }

    /**
     * Ajouter les items au menu Mon Compte
     *
     * @param array $items
     * @return array
     */
    public function add_menu_items( $items ) {
        $new_items = array();

        foreach ( $items as $key => $label ) {
            $new_items[ $key ] = $label;

            if ( 'orders' === $key ) {
                $new_items['mes-devis']    = 'Mes devis';
                $new_items['mes-fichiers'] = 'Mes fichiers';
            }
        }

        return $new_items;
    }

    /**
     * Contenu de la page "Mes devis"
     */
    public static function quotes_content() {
        $customer_id = get_current_user_id();
        $quotes = IPM_Quote::get_customer_quotes( $customer_id );
        $status_labels = IPM_Quote::get_status_labels();

        echo '<h2>Mes demandes de devis</h2>';

        if ( empty( $quotes ) ) {
            echo '<p>Vous n\'avez pas encore de demande de devis.</p>';
            printf(
                '<a href="%s" class="ipm-btn ipm-btn-primary">Demander un devis</a>',
                esc_url( get_permalink( get_page_by_path( 'demande-de-devis' ) ) )
            );
            return;
        }

        echo '<table class="ipm-customer-table woocommerce-orders-table">';
        echo '<thead><tr><th>Référence</th><th>Type</th><th>Date</th><th>Statut</th><th>Montant</th></tr></thead>';
        echo '<tbody>';

        foreach ( $quotes as $quote ) {
            $ref        = 'DEV-' . str_pad( $quote->ID, 6, '0', STR_PAD_LEFT );
            $type       = get_post_meta( $quote->ID, '_ipm_quote_print_type', true );
            $date       = get_post_meta( $quote->ID, '_ipm_quote_date', true );
            $status     = get_post_meta( $quote->ID, '_ipm_quote_status', true );
            $amount     = get_post_meta( $quote->ID, '_ipm_quote_amount', true );
            $status_lbl = isset( $status_labels[ $status ] ) ? $status_labels[ $status ] : $status;

            $status_colors = array(
                'new'      => '#2196F3',
                'pending'  => '#FF9800',
                'sent'     => '#9C27B0',
                'accepted' => '#4CAF50',
                'refused'  => '#f44336',
            );
            $color = isset( $status_colors[ $status ] ) ? $status_colors[ $status ] : '#666';

            printf(
                '<tr><td>%s</td><td>%s</td><td>%s</td><td><span style="color:%s;font-weight:bold">%s</span></td><td>%s</td></tr>',
                esc_html( $ref ),
                esc_html( $type ),
                esc_html( $date ? wp_date( 'd/m/Y', strtotime( $date ) ) : '' ),
                esc_attr( $color ),
                esc_html( $status_lbl ),
                $amount ? esc_html( number_format( (float) $amount, 2, ',', ' ' ) . ' MAD' ) : '-'
            );
        }

        echo '</tbody></table>';
    }

    /**
     * Contenu de la page "Mes fichiers"
     */
    public static function files_content() {
        $customer_id = get_current_user_id();
        $files = IPM_File_Upload::get_customer_files( $customer_id );

        echo '<h2>Mes fichiers</h2>';

        if ( empty( $files ) ) {
            echo '<p>Vous n\'avez pas encore envoyé de fichier.</p>';
            printf(
                '<a href="%s" class="ipm-btn ipm-btn-primary">Envoyer un fichier</a>',
                esc_url( get_permalink( get_page_by_path( 'upload-fichier' ) ) )
            );
            return;
        }

        echo '<table class="ipm-customer-table woocommerce-orders-table">';
        echo '<thead><tr><th>Fichier</th><th>Type</th><th>Taille</th><th>Commande</th><th>Statut</th><th>Date</th></tr></thead>';
        echo '<tbody>';

        $status_labels = array(
            'uploaded'   => 'Téléversé',
            'received'   => 'Reçu',
            'verified'   => 'Vérifié',
            'rejected'   => 'Rejeté',
            'processing' => 'En traitement',
        );

        foreach ( $files as $file ) {
            $status_lbl = isset( $status_labels[ $file['status'] ] ) ? $status_labels[ $file['status'] ] : $file['status'];
            $order_ref  = $file['order_id'] ? '#' . $file['order_id'] : '-';

            printf(
                '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
                esc_html( $file['file_name'] ),
                esc_html( strtoupper( $file['file_type'] ) ),
                esc_html( IPM_File_Upload::format_file_size( $file['file_size'] ) ),
                esc_html( $order_ref ),
                esc_html( $status_lbl ),
                esc_html( wp_date( 'd/m/Y', strtotime( $file['uploaded_at'] ) ) )
            );
        }

        echo '</tbody></table>';

        printf(
            '<p><a href="%s" class="ipm-btn ipm-btn-primary">Envoyer un nouveau fichier</a></p>',
            esc_url( get_permalink( get_page_by_path( 'upload-fichier' ) ) )
        );
    }
}

// Endpoints WooCommerce content
add_action( 'woocommerce_account_mes-devis_endpoint', array( 'IPM_Customer_Area', 'quotes_content' ) );
add_action( 'woocommerce_account_mes-fichiers_endpoint', array( 'IPM_Customer_Area', 'files_content' ) );
