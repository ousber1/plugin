<?php
/**
 * PrintFlow Pro - Frontend Hooks
 *
 * Handles frontend asset loading and initialization.
 *
 * @package PrintFlow_Pro
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PFP_Frontend {

    /**
     * Singleton instance.
     *
     * @var PFP_Frontend|null
     */
    private static $instance = null;

    /**
     * Get singleton instance.
     *
     * @return PFP_Frontend
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {}

    /**
     * Initialize frontend hooks.
     *
     * @return void
     */
    public function init() {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

        // Initialize shortcodes.
        PFP_Shortcodes::instance()->init();
    }

    /**
     * Determine if we should load PrintFlow Pro assets on the current page.
     *
     * @return bool
     */
    private function should_load_assets() {
        if ( is_product() || is_checkout() || is_cart() ) {
            return true;
        }

        global $post;
        if ( $post instanceof WP_Post ) {
            $shortcodes = array(
                'pfp_pricing_calculator',
                'pfp_quote_form',
                'pfp_order_tracking',
                'pfp_file_upload',
            );
            foreach ( $shortcodes as $shortcode ) {
                if ( has_shortcode( $post->post_content, $shortcode ) ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Enqueue frontend CSS.
     *
     * @return void
     */
    public function enqueue_styles() {
        if ( ! $this->should_load_assets() ) {
            return;
        }

        wp_enqueue_style(
            'pfp-frontend',
            PFP_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            PFP_VERSION
        );
    }

    /**
     * Enqueue frontend JavaScript.
     *
     * @return void
     */
    public function enqueue_scripts() {
        if ( ! $this->should_load_assets() ) {
            return;
        }

        // Pricing calculator.
        wp_enqueue_script(
            'pfp-pricing-calculator',
            PFP_PLUGIN_URL . 'assets/js/frontend/pricing-calculator.js',
            array( 'jquery' ),
            PFP_VERSION,
            true
        );

        // File upload.
        wp_enqueue_script(
            'pfp-file-upload',
            PFP_PLUGIN_URL . 'assets/js/frontend/file-upload.js',
            array( 'jquery' ),
            PFP_VERSION,
            true
        );

        // Localize scripts with AJAX URL, nonces, and i18n strings.
        $localize_data = array(
            'ajax_url'       => admin_url( 'admin-ajax.php' ),
            'rest_url'       => esc_url_raw( rest_url( 'printflow-pro/v1/' ) ),
            'pricing_nonce'  => wp_create_nonce( 'pfp_pricing_nonce' ),
            'upload_nonce'   => wp_create_nonce( 'pfp_upload_nonce' ),
            'quote_nonce'    => wp_create_nonce( 'pfp_quote_nonce' ),
            'tracking_nonce' => wp_create_nonce( 'pfp_tracking_nonce' ),
            'currency'       => 'MAD',
            'currency_symbol' => 'DH',
            'i18n'           => array(
                'calculating'       => __( 'Calcul en cours...', 'printflow-pro' ),
                'error'             => __( 'Une erreur est survenue. Veuillez réessayer.', 'printflow-pro' ),
                'upload_success'    => __( 'Fichier téléversé avec succès.', 'printflow-pro' ),
                'upload_error'      => __( 'Erreur lors du téléversement.', 'printflow-pro' ),
                'invalid_file_type' => __( 'Type de fichier non accepté.', 'printflow-pro' ),
                'file_too_large'    => __( 'Le fichier dépasse la taille maximale autorisée (500 Mo).', 'printflow-pro' ),
                'quote_sent'        => __( 'Votre demande de devis a été envoyée avec succès.', 'printflow-pro' ),
                'required_field'    => __( 'Ce champ est obligatoire.', 'printflow-pro' ),
                'drop_files_here'   => __( 'Déposez vos fichiers ici', 'printflow-pro' ),
            ),
            'max_upload_size' => 500 * 1024 * 1024, // 500 Mo in bytes.
            'accepted_types'  => array( 'pdf', 'ai', 'eps', 'psd', 'png', 'jpg', 'jpeg', 'tiff', 'tif' ),
        );

        wp_localize_script( 'pfp-pricing-calculator', 'pfp_frontend', $localize_data );
        wp_localize_script( 'pfp-file-upload', 'pfp_frontend', $localize_data );
    }
}
