<?php
/**
 * Gestion admin des produits
 *
 * @package ImprimerieProMaroc
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class IPM_Admin_Products {

    /**
     * Initialiser
     */
    public static function init() {
        add_filter( 'manage_ipm_product_posts_columns', array( __CLASS__, 'add_columns' ) );
        add_action( 'manage_ipm_product_posts_custom_column', array( __CLASS__, 'render_columns' ), 10, 2 );
    }

    /**
     * Ajouter les colonnes à la liste
     *
     * @param array $columns
     * @return array
     */
    public static function add_columns( $columns ) {
        $new = array();
        foreach ( $columns as $key => $label ) {
            $new[ $key ] = $label;
            if ( 'title' === $key ) {
                $new['ipm_price']    = 'Prix (MAD)';
                $new['ipm_sku']      = 'SKU';
                $new['ipm_status']   = 'Statut';
                $new['ipm_wc_sync']  = 'WooCommerce';
            }
        }
        return $new;
    }

    /**
     * Contenu des colonnes
     *
     * @param string $column
     * @param int    $post_id
     */
    public static function render_columns( $column, $post_id ) {
        switch ( $column ) {
            case 'ipm_price':
                $price = get_post_meta( $post_id, '_ipm_base_price', true );
                echo $price ? esc_html( number_format( (float) $price, 2, ',', ' ' ) . ' MAD' ) : '-';
                break;

            case 'ipm_sku':
                echo esc_html( get_post_meta( $post_id, '_ipm_sku', true ) ?: '-' );
                break;

            case 'ipm_status':
                $status = get_post_meta( $post_id, '_ipm_status', true ) ?: 'active';
                $color  = 'active' === $status ? '#4CAF50' : '#f44336';
                $label  = 'active' === $status ? 'Actif' : 'Inactif';
                printf( '<span style="color:%s;font-weight:bold">%s</span>', esc_attr( $color ), esc_html( $label ) );
                break;

            case 'ipm_wc_sync':
                $wc_id = get_post_meta( $post_id, '_ipm_wc_product_id', true );
                if ( $wc_id && get_post( $wc_id ) ) {
                    printf(
                        '<a href="%s" style="color:#4CAF50">Lié #%d</a>',
                        esc_url( admin_url( 'post.php?post=' . $wc_id . '&action=edit' ) ),
                        $wc_id
                    );
                } else {
                    echo '<span style="color:#999">Non lié</span>';
                }
                break;
        }
    }
}

IPM_Admin_Products::init();
