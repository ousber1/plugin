<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Central loader – wires every component.
 */
class SBP_Loader {

    public function run() {
        $this->load_dependencies();
        $this->register_hooks();
    }

    private function load_dependencies() {
        $dir = SBP_PLUGIN_DIR . 'includes/';

        require_once $dir . 'class-sbp-helpers.php';
        require_once $dir . 'class-sbp-ai-service.php';
        require_once $dir . 'class-sbp-rest-api.php';
        require_once $dir . 'class-sbp-admin.php';
        require_once $dir . 'class-sbp-meta-box.php';
        require_once $dir . 'class-sbp-content-analysis.php';
        require_once $dir . 'class-sbp-faq-generator.php';
        require_once $dir . 'class-sbp-internal-links.php';
        require_once $dir . 'class-sbp-image-alt.php';
        require_once $dir . 'class-sbp-cron.php';
        require_once $dir . 'class-sbp-logger.php';
    }

    private function register_hooks() {
        // Admin
        $admin = new SBP_Admin();
        add_action( 'admin_menu', [ $admin, 'register_menus' ] );
        add_action( 'admin_enqueue_scripts', [ $admin, 'enqueue_assets' ] );

        // REST API
        $api = new SBP_REST_API();
        add_action( 'rest_api_init', [ $api, 'register_routes' ] );

        // Meta box / editor button
        $meta = new SBP_Meta_Box();
        add_action( 'add_meta_boxes', [ $meta, 'register' ] );

        // CRON
        $cron = new SBP_Cron();
        add_action( 'sbp_daily_optimization', [ $cron, 'run' ] );

        // AJAX endpoints (admin)
        add_action( 'wp_ajax_sbp_optimize_post', [ $api, 'ajax_optimize_post' ] );
        add_action( 'wp_ajax_sbp_bulk_optimize', [ $api, 'ajax_bulk_optimize' ] );
        add_action( 'wp_ajax_sbp_generate_faq', [ $api, 'ajax_generate_faq' ] );
        add_action( 'wp_ajax_sbp_suggest_links', [ $api, 'ajax_suggest_links' ] );
        add_action( 'wp_ajax_sbp_fix_image_alts', [ $api, 'ajax_fix_image_alts' ] );
        add_action( 'wp_ajax_sbp_analyze_content', [ $api, 'ajax_analyze_content' ] );
    }
}
