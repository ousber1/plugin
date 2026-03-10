<?php
/**
 * Gestion des statuts de commande personnalisés
 *
 * @package ImprimerieProMaroc
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class IPM_Order_Status {

    /**
     * Statuts personnalisés pour l'impression
     *
     * @return array
     */
    public static function get_custom_statuses() {
        return array(
            'wc-ipm-file-received'  => array(
                'label'       => 'Fichier reçu',
                'label_count' => _n_noop( 'Fichier reçu <span class="count">(%s)</span>', 'Fichier reçu <span class="count">(%s)</span>', 'suspended-pro-maroc' ),
                'color'       => '#2196F3',
            ),
            'wc-ipm-file-check'     => array(
                'label'       => 'Vérification fichier',
                'label_count' => _n_noop( 'Vérification fichier <span class="count">(%s)</span>', 'Vérification fichier <span class="count">(%s)</span>', 'suspended-pro-maroc' ),
                'color'       => '#FF9800',
            ),
            'wc-ipm-preparing'      => array(
                'label'       => 'En préparation',
                'label_count' => _n_noop( 'En préparation <span class="count">(%s)</span>', 'En préparation <span class="count">(%s)</span>', 'suspended-pro-maroc' ),
                'color'       => '#9C27B0',
            ),
            'wc-ipm-printing'       => array(
                'label'       => 'En impression',
                'label_count' => _n_noop( 'En impression <span class="count">(%s)</span>', 'En impression <span class="count">(%s)</span>', 'suspended-pro-maroc' ),
                'color'       => '#E91E63',
            ),
            'wc-ipm-finishing'      => array(
                'label'       => 'Finition',
                'label_count' => _n_noop( 'Finition <span class="count">(%s)</span>', 'Finition <span class="count">(%s)</span>', 'suspended-pro-maroc' ),
                'color'       => '#00BCD4',
            ),
            'wc-ipm-ready'          => array(
                'label'       => 'Prêt pour livraison',
                'label_count' => _n_noop( 'Prêt pour livraison <span class="count">(%s)</span>', 'Prêt pour livraison <span class="count">(%s)</span>', 'suspended-pro-maroc' ),
                'color'       => '#8BC34A',
            ),
            'wc-ipm-shipped'        => array(
                'label'       => 'Expédié',
                'label_count' => _n_noop( 'Expédié <span class="count">(%s)</span>', 'Expédié <span class="count">(%s)</span>', 'suspended-pro-maroc' ),
                'color'       => '#3F51B5',
            ),
            'wc-ipm-delivered'      => array(
                'label'       => 'Livré',
                'label_count' => _n_noop( 'Livré <span class="count">(%s)</span>', 'Livré <span class="count">(%s)</span>', 'suspended-pro-maroc' ),
                'color'       => '#4CAF50',
            ),
        );
    }

    /**
     * Enregistrer les statuts personnalisés
     */
    public function register_statuses() {
        foreach ( self::get_custom_statuses() as $status => $args ) {
            register_post_status( $status, array(
                'label'                     => $args['label'],
                'public'                    => true,
                'exclude_from_search'       => false,
                'show_in_admin_all_list'    => true,
                'show_in_admin_status_list' => true,
                'label_count'               => $args['label_count'],
            ) );
        }
    }

    /**
     * Ajouter les statuts à WooCommerce
     *
     * @param array $statuses Statuts existants
     * @return array
     */
    public function add_statuses( $statuses ) {
        $custom = self::get_custom_statuses();
        $new_statuses = array();

        foreach ( $statuses as $key => $label ) {
            $new_statuses[ $key ] = $label;

            // Insérer après "processing"
            if ( 'wc-processing' === $key ) {
                foreach ( $custom as $ckey => $cargs ) {
                    $new_statuses[ $ckey ] = $cargs['label'];
                }
            }
        }

        return $new_statuses;
    }

    /**
     * Envoyer une notification au changement de statut
     *
     * @param int    $order_id   ID de la commande
     * @param string $old_status Ancien statut
     * @param string $new_status Nouveau statut
     */
    public static function status_changed( $order_id, $old_status, $new_status ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        $custom = self::get_custom_statuses();
        $full_status = 'wc-' . $new_status;

        if ( ! isset( $custom[ $full_status ] ) ) {
            return;
        }

        $email   = $order->get_billing_email();
        $name    = $order->get_billing_first_name();
        $label   = $custom[ $full_status ]['label'];
        $headers = array( 'Content-Type: text/html; charset=UTF-8' );

        $subject = sprintf( 'Mise à jour de votre commande #%s - %s', $order->get_order_number(), $label );

        $body = sprintf( '<h2>Bonjour %s,</h2>', esc_html( $name ) );
        $body .= sprintf( '<p>Le statut de votre commande <strong>#%s</strong> a été mis à jour :</p>', $order->get_order_number() );
        $body .= sprintf( '<p style="font-size:18px;color:%s;font-weight:bold">%s</p>', esc_attr( $custom[ $full_status ]['color'] ), esc_html( $label ) );

        $messages = array(
            'wc-ipm-file-received' => 'Nous avons bien reçu votre fichier. Il va être vérifié par notre équipe.',
            'wc-ipm-file-check'    => 'Votre fichier est en cours de vérification par notre équipe technique.',
            'wc-ipm-preparing'     => 'Votre commande est en cours de préparation.',
            'wc-ipm-printing'      => 'Votre commande est actuellement en cours d\'impression.',
            'wc-ipm-finishing'     => 'Votre commande est en phase de finition.',
            'wc-ipm-ready'         => 'Votre commande est prête et sera bientôt expédiée.',
            'wc-ipm-shipped'       => 'Votre commande a été expédiée ! Vous la recevrez bientôt.',
            'wc-ipm-delivered'     => 'Votre commande a été livrée. Merci pour votre confiance !',
        );

        if ( isset( $messages[ $full_status ] ) ) {
            $body .= '<p>' . $messages[ $full_status ] . '</p>';
        }

        $body .= '<p>Cordialement,<br>L\'équipe Imprimerie Pro Maroc</p>';

        wp_mail( $email, $subject, $body, $headers );
    }
}

// Hook pour les changements de statut
add_action( 'woocommerce_order_status_changed', array( 'IPM_Order_Status', 'status_changed' ), 10, 3 );
