<?php
/**
 * Module de livraison Maroc
 *
 * @package ImprimerieProMaroc
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class IPM_Shipping {

    public function __construct() {
        // Charger la classe de méthode de livraison
    }

    /**
     * Récupérer toutes les zones de livraison
     *
     * @return array
     */
    public static function get_zones() {
        global $wpdb;
        $table = $wpdb->prefix . 'ipm_shipping_zones';
        return $wpdb->get_results( "SELECT * FROM $table WHERE is_active = 1 ORDER BY zone_name ASC", ARRAY_A );
    }

    /**
     * Récupérer une zone par ville
     *
     * @param string $city Ville
     * @return array|null
     */
    public static function get_zone_by_city( $city ) {
        $zones = self::get_zones();
        $city  = mb_strtolower( trim( $city ) );

        foreach ( $zones as $zone ) {
            $cities = array_map( 'trim', array_map( 'mb_strtolower', explode( ',', $zone['cities'] ) ) );
            if ( in_array( $city, $cities, true ) ) {
                return $zone;
            }
        }

        // Retourner la dernière zone (Autres villes) par défaut
        return end( $zones ) ?: null;
    }

    /**
     * Calculer les frais de livraison
     *
     * @param string $city    Ville de livraison
     * @param bool   $express Livraison express
     * @param float  $total   Total de la commande
     * @return array
     */
    public static function calculate( $city, $express = false, $total = 0 ) {
        $zone = self::get_zone_by_city( $city );

        if ( ! $zone ) {
            return array(
                'cost'  => 0,
                'delay' => '',
                'free'  => false,
                'error' => 'Zone de livraison non trouvée.',
            );
        }

        $cost  = $express ? (float) $zone['express_price'] : (float) $zone['standard_price'];
        $delay = $express ? $zone['express_days'] : $zone['standard_days'];
        $free  = false;

        // Vérifier le seuil de livraison gratuite
        if ( ! $express && $zone['free_threshold'] && $total >= (float) $zone['free_threshold'] ) {
            $cost = 0;
            $free = true;
        }

        $delay_text = $delay > 1
            ? sprintf( '%d jours ouvrables', $delay )
            : '24 heures';

        return array(
            'cost'       => $cost,
            'delay'      => $delay_text,
            'free'       => $free,
            'zone_name'  => $zone['zone_name'],
            'threshold'  => $zone['free_threshold'],
        );
    }

    /**
     * Sauvegarder une zone de livraison
     *
     * @param array $data Données de la zone
     * @return int|false
     */
    public static function save_zone( $data ) {
        global $wpdb;
        $table = $wpdb->prefix . 'ipm_shipping_zones';

        $sanitized = array(
            'zone_name'      => sanitize_text_field( $data['zone_name'] ),
            'cities'         => sanitize_text_field( $data['cities'] ),
            'standard_price' => (float) $data['standard_price'],
            'express_price'  => (float) $data['express_price'],
            'standard_days'  => absint( $data['standard_days'] ),
            'express_days'   => absint( $data['express_days'] ),
            'free_threshold' => isset( $data['free_threshold'] ) ? (float) $data['free_threshold'] : null,
            'is_active'      => isset( $data['is_active'] ) ? 1 : 0,
        );

        if ( ! empty( $data['id'] ) ) {
            return $wpdb->update( $table, $sanitized, array( 'id' => absint( $data['id'] ) ) );
        }

        return $wpdb->insert( $table, $sanitized ) ? $wpdb->insert_id : false;
    }
}

/**
 * Méthode de livraison WooCommerce
 */
if ( class_exists( 'WC_Shipping_Method' ) ) {

    class IPM_Shipping_Method extends WC_Shipping_Method {

        public function __construct( $instance_id = 0 ) {
            $this->id                 = 'ipm_morocco_shipping';
            $this->instance_id        = absint( $instance_id );
            $this->method_title       = 'Livraison Maroc';
            $this->method_description = 'Livraison partout au Maroc avec tarifs par zone.';
            $this->supports           = array( 'shipping-zones', 'instance-settings' );
            $this->enabled            = 'yes';
            $this->title              = 'Livraison Maroc';

            $this->init();
        }

        public function init() {
            $this->init_form_fields();
            $this->init_settings();
            add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
        }

        public function init_form_fields() {
            $this->instance_form_fields = array(
                'title' => array(
                    'title'   => 'Titre',
                    'type'    => 'text',
                    'default' => 'Livraison Maroc',
                ),
                'pickup' => array(
                    'title'   => 'Retrait en magasin',
                    'type'    => 'checkbox',
                    'label'   => 'Proposer le retrait en magasin',
                    'default' => 'yes',
                ),
            );
        }

        public function calculate_shipping( $package = array() ) {
            $city    = isset( $package['destination']['city'] ) ? $package['destination']['city'] : '';
            $total   = isset( $package['contents_cost'] ) ? (float) $package['contents_cost'] : 0;

            if ( ! $city ) {
                $city = 'Casablanca'; // Par défaut
            }

            // Livraison standard
            $standard = IPM_Shipping::calculate( $city, false, $total );
            $this->add_rate( array(
                'id'    => $this->id . '_standard',
                'label' => $standard['free']
                    ? sprintf( 'Livraison standard (%s) - GRATUITE', $standard['zone_name'] )
                    : sprintf( 'Livraison standard (%s - %s)', $standard['zone_name'], $standard['delay'] ),
                'cost'  => $standard['cost'],
            ) );

            // Livraison express
            $express = IPM_Shipping::calculate( $city, true, $total );
            $this->add_rate( array(
                'id'    => $this->id . '_express',
                'label' => sprintf( 'Livraison express (%s - %s)', $express['zone_name'], $express['delay'] ),
                'cost'  => $express['cost'],
            ) );

            // Retrait en magasin
            $pickup = $this->get_option( 'pickup', 'yes' );
            if ( 'yes' === $pickup ) {
                $this->add_rate( array(
                    'id'    => $this->id . '_pickup',
                    'label' => 'Retrait en magasin (gratuit)',
                    'cost'  => 0,
                ) );
            }
        }
    }
}
