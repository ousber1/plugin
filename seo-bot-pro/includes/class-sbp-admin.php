<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin pages, menus, and asset loading.
 */
class SBP_Admin {

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

        add_submenu_page( 'sbp-dashboard', __( 'Dashboard', 'seo-bot-pro' ), __( 'Dashboard', 'seo-bot-pro' ), 'edit_posts', 'sbp-dashboard', [ $this, 'render_dashboard' ] );
        add_submenu_page( 'sbp-dashboard', __( 'AI Post Generator', 'seo-bot-pro' ), __( 'AI Post Generator', 'seo-bot-pro' ), 'publish_posts', 'sbp-generator', [ $this, 'render_generator' ] );
        add_submenu_page( 'sbp-dashboard', __( 'Bulk Optimize', 'seo-bot-pro' ), __( 'Bulk Optimize', 'seo-bot-pro' ), 'edit_posts', 'sbp-bulk', [ $this, 'render_bulk' ] );
        add_submenu_page( 'sbp-dashboard', __( 'Rank Booster', 'seo-bot-pro' ), __( 'Rank Booster', 'seo-bot-pro' ), 'edit_posts', 'sbp-rank-booster', [ $this, 'render_rank_booster' ] );
        add_submenu_page( 'sbp-dashboard', __( '404 Monitor', 'seo-bot-pro' ), __( '404 Monitor', 'seo-bot-pro' ), 'edit_posts', 'sbp-404-monitor', [ $this, 'render_404_monitor' ] );
        add_submenu_page( 'sbp-dashboard', __( 'Settings', 'seo-bot-pro' ), __( 'Settings', 'seo-bot-pro' ), 'manage_options', 'sbp-settings', [ $this, 'render_settings' ] );
        add_submenu_page( 'sbp-dashboard', __( 'Logs', 'seo-bot-pro' ), __( 'Logs', 'seo-bot-pro' ), 'edit_posts', 'sbp-logs', [ $this, 'render_logs' ] );
    }

    public function enqueue_assets( string $hook ) {
        $plugin_pages = [
            'toplevel_page_sbp-dashboard',
            'seo-bot_page_sbp-bulk',
            'seo-bot_page_sbp-settings',
            'seo-bot_page_sbp-logs',
            'seo-bot_page_sbp-generator',
            'seo-bot_page_sbp-rank-booster',
            'seo-bot_page_sbp-404-monitor',
        ];

        $is_plugin_page = in_array( $hook, $plugin_pages, true );
        $is_editor      = in_array( $hook, [ 'post.php', 'post-new.php' ], true );

        if ( ! $is_plugin_page && ! $is_editor ) {
            return;
        }

        wp_enqueue_style( 'sbp-admin', SBP_PLUGIN_URL . 'admin/css/admin.css', [], SBP_VERSION );
        wp_enqueue_script( 'sbp-admin', SBP_PLUGIN_URL . 'admin/js/admin.js', [ 'jquery' ], SBP_VERSION, true );

        wp_localize_script( 'sbp-admin', 'sbpData', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'restUrl' => rest_url( 'seo-bot/v1/' ),
            'nonce'   => wp_create_nonce( 'sbp_nonce' ),
            'i18n'    => [
                'optimizing'  => __( 'Optimizing...', 'seo-bot-pro' ),
                'success'     => __( 'Optimization complete!', 'seo-bot-pro' ),
                'error'       => __( 'Error:', 'seo-bot-pro' ),
                'confirm'     => __( 'Optimize selected posts?', 'seo-bot-pro' ),
                'generating'  => __( 'Generating...', 'seo-bot-pro' ),
                'analyzing'   => __( 'Analyzing...', 'seo-bot-pro' ),
                'noSelection' => __( 'Please select at least one post.', 'seo-bot-pro' ),
                'publishing'  => __( 'Creating post with AI...', 'seo-bot-pro' ),
                'rewriting'   => __( 'Rewriting content...', 'seo-bot-pro' ),
                'saved'       => __( 'Saved!', 'seo-bot-pro' ),
                'pinging'     => __( 'Pinging search engines...', 'seo-bot-pro' ),
                'submitting'  => __( 'Submitting...', 'seo-bot-pro' ),
                'refreshing'  => __( 'Refreshing...', 'seo-bot-pro' ),
            ],
        ] );
    }

    public function render_dashboard() {
        include SBP_PLUGIN_DIR . 'admin/views/dashboard.php';
    }

    public function render_generator() {
        include SBP_PLUGIN_DIR . 'admin/views/generator.php';
    }

    public function render_rank_booster() {
        include SBP_PLUGIN_DIR . 'admin/views/rank-booster.php';
    }

    public function render_bulk() {
        include SBP_PLUGIN_DIR . 'admin/views/bulk.php';
    }

    public function render_settings() {
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

    public function render_404_monitor() {
        // Handle form submissions
        if ( isset( $_POST['sbp_add_redirect'] ) ) {
            check_admin_referer( 'sbp_add_redirect', 'sbp_redirect_nonce' );
            $monitor = new SBP_404_Monitor();
            $monitor->add_redirect(
                sanitize_text_field( $_POST['redirect_from'] ?? '' ),
                esc_url_raw( $_POST['redirect_to'] ?? '' ),
                absint( $_POST['redirect_code'] ?? 301 )
            );
            add_settings_error( 'sbp_settings', 'redirect_added', __( 'Redirect added.', 'seo-bot-pro' ), 'updated' );
        }
        if ( isset( $_POST['sbp_delete_redirect'] ) ) {
            check_admin_referer( 'sbp_delete_redirect', 'sbp_redirect_nonce' );
            $monitor = new SBP_404_Monitor();
            $monitor->delete_redirect( absint( $_POST['redirect_index'] ?? 0 ) );
            add_settings_error( 'sbp_settings', 'redirect_deleted', __( 'Redirect deleted.', 'seo-bot-pro' ), 'updated' );
        }
        if ( isset( $_POST['sbp_clear_404'] ) ) {
            check_admin_referer( 'sbp_clear_404', 'sbp_404_nonce' );
            $monitor = new SBP_404_Monitor();
            $monitor->clear_logs();
            add_settings_error( 'sbp_settings', 'logs_cleared', __( '404 logs cleared.', 'seo-bot-pro' ), 'updated' );
        }
        settings_errors( 'sbp_settings' );
        include SBP_PLUGIN_DIR . 'admin/views/404-monitor.php';
    }

    public function render_logs() {
        include SBP_PLUGIN_DIR . 'admin/views/logs.php';
    }
}
