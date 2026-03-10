<?php
/**
 * Classe publique (frontend)
 *
 * @package ImprimerieProMaroc
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class IPM_Public {

    /**
     * Charger les styles publics
     */
    public function enqueue_styles() {
        wp_enqueue_style(
            'ipm-public',
            IPM_PLUGIN_URL . 'public/css/ipm-public.css',
            array(),
            IPM_VERSION
        );
    }

    /**
     * Charger les scripts publics
     */
    public function enqueue_scripts() {
        wp_enqueue_script(
            'ipm-public',
            IPM_PLUGIN_URL . 'public/js/ipm-public.js',
            array( 'jquery' ),
            IPM_VERSION,
            true
        );

        wp_localize_script( 'ipm-public', 'ipmPublic', array(
            'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
            'nonce'        => wp_create_nonce( 'ipm_calculate_price' ),
            'quoteNonce'   => wp_create_nonce( 'ipm_submit_quote' ),
            'uploadNonce'  => wp_create_nonce( 'ipm_upload_file' ),
            'trackNonce'   => wp_create_nonce( 'ipm_track_order' ),
            'currency'     => 'MAD',
            'maxFileSize'  => (int) Imprimerie_Pro_Maroc::get_option( 'max_file_size', 50 ),
            'allowedTypes' => Imprimerie_Pro_Maroc::get_option( 'allowed_file_types', 'pdf,png,jpg,jpeg,ai,psd,svg,zip' ),
            'messages'     => array(
                'calculating'   => 'Calcul en cours...',
                'error'         => 'Une erreur est survenue. Veuillez réessayer.',
                'fileTooLarge'  => 'Le fichier est trop volumineux.',
                'invalidType'   => 'Type de fichier non autorisé.',
                'uploading'     => 'Téléversement en cours...',
                'success'       => 'Opération réussie !',
                'addedToCart'   => 'Produit ajouté au panier !',
            ),
        ) );
    }
}
