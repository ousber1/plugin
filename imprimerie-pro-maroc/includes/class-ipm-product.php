<?php
/**
 * Gestion des produits d'impression
 *
 * @package ImprimerieProMaroc
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class IPM_Product {

    /**
     * ID du produit
     *
     * @var int
     */
    private $id;

    /**
     * Constructeur
     *
     * @param int $id ID du produit
     */
    public function __construct( $id = 0 ) {
        $this->id = absint( $id );
    }

    /**
     * Récupérer le prix de base
     *
     * @return float
     */
    public function get_base_price() {
        return (float) get_post_meta( $this->id, '_ipm_base_price', true );
    }

    /**
     * Récupérer le SKU
     *
     * @return string
     */
    public function get_sku() {
        return get_post_meta( $this->id, '_ipm_sku', true );
    }

    /**
     * Vérifier si le produit est actif
     *
     * @return bool
     */
    public function is_active() {
        $status = get_post_meta( $this->id, '_ipm_status', true );
        return 'active' === $status || '' === $status;
    }

    /**
     * Récupérer le délai de production
     *
     * @return string
     */
    public function get_production_delay() {
        $delay = get_post_meta( $this->id, '_ipm_production_delay', true );
        return $delay ? $delay : Imprimerie_Pro_Maroc::get_option( 'default_delay', '3-5 jours ouvrables' );
    }

    /**
     * Récupérer les options du produit
     *
     * @return array
     */
    public function get_options() {
        $options = get_post_meta( $this->id, '_ipm_options', true );
        return is_array( $options ) ? $options : array();
    }

    /**
     * Récupérer les remises volume
     *
     * @return array
     */
    public function get_volume_discounts() {
        global $wpdb;
        $table = $wpdb->prefix . 'ipm_volume_discounts';
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE product_id = %d ORDER BY min_quantity ASC",
                $this->id
            ),
            ARRAY_A
        );
    }

    /**
     * Récupérer la galerie d'images
     *
     * @return array
     */
    public function get_gallery() {
        $gallery = get_post_meta( $this->id, '_ipm_gallery', true );
        return is_array( $gallery ) ? $gallery : array();
    }

    /**
     * Récupérer le prix minimum
     *
     * @return float
     */
    public function get_minimum_price() {
        $min = get_post_meta( $this->id, '_ipm_minimum_price', true );
        if ( $min ) {
            return (float) $min;
        }
        return (float) Imprimerie_Pro_Maroc::get_option( 'minimum_price', 50 );
    }

    /**
     * Récupérer le WooCommerce product ID lié
     *
     * @return int
     */
    public function get_wc_product_id() {
        return (int) get_post_meta( $this->id, '_ipm_wc_product_id', true );
    }

    /**
     * Créer ou mettre à jour le produit WooCommerce lié
     *
     * @return int WC Product ID
     */
    public function sync_to_woocommerce() {
        if ( ! class_exists( 'WC_Product' ) ) {
            return 0;
        }

        $wc_id   = $this->get_wc_product_id();
        $product = get_post( $this->id );

        if ( $wc_id && get_post( $wc_id ) ) {
            // Mettre à jour
            $wc_product = wc_get_product( $wc_id );
        } else {
            // Créer
            $wc_product = new WC_Product_Simple();
        }

        $wc_product->set_name( $product->post_title );
        $wc_product->set_description( $product->post_content );
        $wc_product->set_short_description( $product->post_excerpt );
        $wc_product->set_regular_price( $this->get_base_price() );
        $wc_product->set_sku( $this->get_sku() );
        $wc_product->set_virtual( true ); // Pas de stock physique standard
        $wc_product->set_catalog_visibility( 'visible' );
        $wc_product->set_status( $this->is_active() ? 'publish' : 'draft' );

        // Image
        $thumbnail_id = get_post_thumbnail_id( $this->id );
        if ( $thumbnail_id ) {
            $wc_product->set_image_id( $thumbnail_id );
        }

        // Galerie
        $gallery = $this->get_gallery();
        if ( ! empty( $gallery ) ) {
            $wc_product->set_gallery_image_ids( $gallery );
        }

        $wc_id = $wc_product->save();

        // Stocker la liaison
        update_post_meta( $this->id, '_ipm_wc_product_id', $wc_id );
        update_post_meta( $wc_id, '_ipm_print_product_id', $this->id );

        return $wc_id;
    }

    /**
     * Récupérer tous les produits actifs
     *
     * @param array $args Arguments WP_Query supplémentaires
     * @return array
     */
    public static function get_all( $args = array() ) {
        $defaults = array(
            'post_type'      => 'ipm_product',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_query'     => array(
                'relation' => 'OR',
                array(
                    'key'     => '_ipm_status',
                    'value'   => 'active',
                    'compare' => '=',
                ),
                array(
                    'key'     => '_ipm_status',
                    'compare' => 'NOT EXISTS',
                ),
            ),
        );

        $args  = wp_parse_args( $args, $defaults );
        $query = new WP_Query( $args );

        return $query->posts;
    }

    /**
     * Récupérer les produits par catégorie
     *
     * @param string $category_slug Slug de la catégorie
     * @return array
     */
    public static function get_by_category( $category_slug ) {
        return self::get_all( array(
            'tax_query' => array(
                array(
                    'taxonomy' => 'ipm_category',
                    'field'    => 'slug',
                    'terms'    => $category_slug,
                ),
            ),
        ) );
    }
}
