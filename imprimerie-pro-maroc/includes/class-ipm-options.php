<?php
/**
 * Gestion des options de personnalisation des produits
 *
 * @package ImprimerieProMaroc
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class IPM_Options {

    /**
     * Options prédéfinies disponibles
     *
     * @return array
     */
    public static function get_predefined_options() {
        return array(
            'format' => array(
                'label'    => 'Format',
                'type'     => 'select',
                'choices'  => array(
                    'a3'     => 'A3 (297 x 420 mm)',
                    'a4'     => 'A4 (210 x 297 mm)',
                    'a5'     => 'A5 (148 x 210 mm)',
                    'a6'     => 'A6 (105 x 148 mm)',
                    'dl'     => 'DL (99 x 210 mm)',
                    '10x15'  => '10 x 15 cm',
                    'carre'  => 'Carré (148 x 148 mm)',
                    'custom' => 'Format personnalisé',
                ),
                'required' => true,
            ),
            'dimensions' => array(
                'label'    => 'Dimensions personnalisées',
                'type'     => 'dimensions',
                'required' => false,
                'depends'  => array( 'format' => 'custom' ),
            ),
            'quantity' => array(
                'label'    => 'Quantité',
                'type'     => 'select',
                'choices'  => array(
                    '50'    => '50 exemplaires',
                    '100'   => '100 exemplaires',
                    '250'   => '250 exemplaires',
                    '500'   => '500 exemplaires',
                    '1000'  => '1 000 exemplaires',
                    '2000'  => '2 000 exemplaires',
                    '5000'  => '5 000 exemplaires',
                    '10000' => '10 000 exemplaires',
                    'custom' => 'Quantité personnalisée',
                ),
                'required' => true,
            ),
            'paper_type' => array(
                'label'    => 'Type de papier',
                'type'     => 'select',
                'choices'  => array(
                    'couche_mat'      => 'Couché mat',
                    'couche_brillant' => 'Couché brillant',
                    'offset'          => 'Offset',
                    'recycle'         => 'Recyclé',
                    'creation'        => 'Création / Texturé',
                    'bristol'         => 'Bristol',
                    'kraft'           => 'Kraft',
                    'adhesif'         => 'Adhésif',
                    'transparent'     => 'Transparent',
                ),
                'required' => true,
            ),
            'grammage' => array(
                'label'    => 'Grammage',
                'type'     => 'select',
                'choices'  => array(
                    '80'   => '80 g/m²',
                    '100'  => '100 g/m²',
                    '135'  => '135 g/m²',
                    '170'  => '170 g/m²',
                    '200'  => '200 g/m²',
                    '250'  => '250 g/m²',
                    '300'  => '300 g/m²',
                    '350'  => '350 g/m²',
                    '400'  => '400 g/m²',
                ),
                'required' => true,
            ),
            'finish' => array(
                'label'    => 'Finition',
                'type'     => 'checkbox_group',
                'choices'  => array(
                    'mat'              => 'Mat',
                    'brillant'         => 'Brillant',
                    'pelliculage_mat'  => 'Pelliculage mat',
                    'pelliculage_bri'  => 'Pelliculage brillant',
                    'vernis_selectif'  => 'Vernis sélectif',
                    'dorure'           => 'Dorure à chaud',
                    'gaufrage'         => 'Gaufrage',
                ),
                'required' => false,
            ),
            'print_sides' => array(
                'label'    => 'Impression',
                'type'     => 'radio',
                'choices'  => array(
                    'recto'        => 'Recto uniquement',
                    'recto_verso'  => 'Recto-verso',
                ),
                'required' => true,
            ),
            'color_mode' => array(
                'label'    => 'Mode couleur',
                'type'     => 'radio',
                'choices'  => array(
                    'couleur'       => 'Couleur',
                    'noir_blanc'    => 'Noir et blanc',
                ),
                'required' => true,
            ),
            'rounded_corners' => array(
                'label'    => 'Coins arrondis',
                'type'     => 'checkbox',
                'required' => false,
                'price'    => array(
                    'type'  => 'fixed',
                    'value' => 20,
                ),
            ),
            'custom_cut' => array(
                'label'    => 'Découpe personnalisée',
                'type'     => 'checkbox',
                'required' => false,
                'price'    => array(
                    'type'  => 'percentage',
                    'value' => 15,
                ),
            ),
            'support_material' => array(
                'label'    => 'Support / Matière',
                'type'     => 'select',
                'choices'  => array(
                    'pvc'        => 'PVC',
                    'forex'      => 'Forex',
                    'dibond'     => 'Dibond',
                    'plexiglas'  => 'Plexiglas',
                    'bois'       => 'Bois',
                    'textile'    => 'Textile',
                    'bache'      => 'Bâche',
                    'vinyle'     => 'Vinyle',
                ),
                'required' => false,
            ),
            'design_service' => array(
                'label'    => 'Service de design graphique',
                'type'     => 'checkbox',
                'required' => false,
                'price'    => array(
                    'type'  => 'fixed',
                    'value' => 150,
                ),
            ),
            'express_delivery' => array(
                'label'    => 'Livraison express',
                'type'     => 'checkbox',
                'required' => false,
                'price'    => array(
                    'type'  => 'percentage',
                    'value' => 30,
                ),
            ),
        );
    }

    /**
     * Récupérer les options configurées pour un produit
     *
     * @param int $product_id ID du produit
     * @return array
     */
    public static function get_product_options( $product_id ) {
        $options = get_post_meta( $product_id, '_ipm_options', true );
        if ( ! is_array( $options ) ) {
            return array();
        }
        return $options;
    }

    /**
     * Sauvegarder les options d'un produit
     *
     * @param int   $product_id ID du produit
     * @param array $options    Options à sauvegarder
     */
    public static function save_product_options( $product_id, $options ) {
        update_post_meta( $product_id, '_ipm_options', $options );
    }

    /**
     * Récupérer les prix d'options pour un produit
     *
     * @param int $product_id ID du produit
     * @return array
     */
    public static function get_option_prices( $product_id ) {
        $prices = get_post_meta( $product_id, '_ipm_option_prices', true );
        return is_array( $prices ) ? $prices : array();
    }

    /**
     * Sauvegarder les prix d'options
     *
     * @param int   $product_id ID du produit
     * @param array $prices     Prix des options
     */
    public static function save_option_prices( $product_id, $prices ) {
        update_post_meta( $product_id, '_ipm_option_prices', $prices );
    }
}
