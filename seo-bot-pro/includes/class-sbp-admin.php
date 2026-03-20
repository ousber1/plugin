<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin pages, menus, and asset loading.
 */
class SBP_Admin {

    /**
     * Register the admin menu.
     */
    public function register_menus() {
        add_menu_page(
            __( 'SEO Bot Pro', 'seo-bot-pro' ),
            __( 'SEO Bot', 'seo-bot-pro' ),
            'edit_posts',
            'sbp-dashboard',
            [ $this, 'render_dashboard' ],
            'dashicons-search',
            80
        );

        add_submenu_page(
            'sbp-dashboard',
            __( 'Dashboard', 'seo-bot-pro' ),
            __( 'Dashboard', 'seo-bot-pro' ),
            'edit_posts',
            'sbp-dashboard',
            [ $this, 'render_dashboard' ]
        );

        add_submenu_page(
            'sbp-dashboard',
            __( 'Bulk Optimize', 'seo-bot-pro' ),
            __( 'Bulk Optimize', 'seo-bot-pro' ),
            'edit_posts',
            'sbp-bulk',
            [ $this, 'render_bulk' ]
        );

        add_submenu_page(
            'sbp-dashboard',
            __( 'Settings', 'seo-bot-pro' ),
            __( 'Settings', 'seo-bot-pro' ),
            'manage_options',
            'sbp-settings',
            [ $this, 'render_settings' ]
        );

        add_submenu_page(
            'sbp-dashboard',
            __( 'Logs', 'seo-bot-pro' ),
            __( 'Logs', 'seo-bot-pro' ),
            'edit_posts',
            'sbp-logs',
            [ $this, 'render_logs' ]
        );
    }

    /**
     * Enqueue admin CSS + JS on plugin pages and post editors.
     */
    public function enqueue_assets( string $hook ) {
        $plugin_pages = [
            'toplevel_page_sbp-dashboard',
            'seo-bot_page_sbp-bulk',
            'seo-bot_page_sbp-settings',
            'seo-bot_page_sbp-logs',
        ];

        $is_plugin_page = in_array( $hook, $plugin_pages, true );
        $is_editor      = in_array( $hook, [ 'post.php', 'post-new.php' ], true );

        if ( ! $is_plugin_page && ! $is_editor ) {
            return;
        }

        wp_enqueue_style(
            'sbp-admin',
            SBP_PLUGIN_URL . 'admin/css/admin.css',
            [],
            SBP_VERSION
        );

        wp_enqueue_script(
            'sbp-admin',
            SBP_PLUGIN_URL . 'admin/js/admin.js',
            [ 'jquery' ],
            SBP_VERSION,
            true
        );

        wp_localize_script( 'sbp-admin', 'sbpData', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'restUrl' => rest_url( 'seo-bot/v1/' ),
            'nonce'   => wp_create_nonce( 'sbp_nonce' ),
            'i18n'    => [
                'optimizing'  => __( 'Optimizing…', 'seo-bot-pro' ),
                'success'     => __( 'Optimization complete!', 'seo-bot-pro' ),
                'error'       => __( 'Error:', 'seo-bot-pro' ),
                'confirm'     => __( 'Optimize selected posts?', 'seo-bot-pro' ),
                'generating'  => __( 'Generating…', 'seo-bot-pro' ),
                'analyzing'   => __( 'Analyzing…', 'seo-bot-pro' ),
                'noSelection' => __( 'Please select at least one post.', 'seo-bot-pro' ),
            ],
        ] );
    }

    // ── Page renderers ──────────────────────────────

    public function render_dashboard() {
        include SBP_PLUGIN_DIR . 'admin/views/dashboard.php';
    }

    public function render_bulk() {
        include SBP_PLUGIN_DIR . 'admin/views/bulk.php';
    }

    public function render_settings() {
        // Handle save
        if ( isset( $_POST['sbp_save_settings'] ) ) {
            check_admin_referer( 'sbp_settings_save', 'sbp_settings_nonce' );

            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( esc_html__( 'Unauthorized.', 'seo-bot-pro' ) );
            }

            $clean = SBP_Helpers::sanitize_settings( $_POST['sbp'] ?? [] );
            update_option( 'sbp_settings', $clean );

            add_settings_error( 'sbp_settings', 'saved', __( 'Settings saved.', 'seo-bot-pro' ), 'updated' );
        }

        include SBP_PLUGIN_DIR . 'admin/views/settings.php';
    }

    public function render_logs() {
        include SBP_PLUGIN_DIR . 'admin/views/logs.php';
    }
}
