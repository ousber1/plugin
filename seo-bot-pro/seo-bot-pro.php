<?php
/**
 * Plugin Name: SEO Bot Pro – AI SEO Optimizer
 * Plugin URI:  https://example.com/seo-bot-pro
 * Description: Automatically optimize SEO for WordPress posts, pages, and WooCommerce products using AI.
 * Version:     2.0.0
 * Author:      SEO Bot Pro
 * Author URI:  https://example.com
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: seo-bot-pro
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'SBP_VERSION', '2.0.0' );
define( 'SBP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SBP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SBP_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once SBP_PLUGIN_DIR . 'includes/class-sbp-loader.php';

/**
 * Boot the plugin.
 */
function sbp_init() {
    sbp_maybe_migrate_settings();

    $loader = new SBP_Loader();
    $loader->run();
}
add_action( 'plugins_loaded', 'sbp_init' );

/**
 * Migrate v1 settings (single api_key) to v2 format (openai_api_key / claude_api_key).
 */
function sbp_maybe_migrate_settings() {
    $settings = get_option( 'sbp_settings', [] );

    // If old api_key exists but new keys don't, migrate
    if ( ! empty( $settings['api_key'] ) && empty( $settings['openai_api_key'] ) && empty( $settings['claude_api_key'] ) ) {
        $settings['openai_api_key'] = $settings['api_key'];
        $settings['provider']       = 'openai';
        unset( $settings['api_key'] );
        update_option( 'sbp_settings', $settings );
    }
}

/**
 * Activation hook – create log table.
 */
function sbp_activate() {
    global $wpdb;
    $table   = $wpdb->prefix . 'sbp_logs';
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS {$table} (
        id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        post_id     BIGINT UNSIGNED NOT NULL,
        action_type VARCHAR(50)     NOT NULL DEFAULT 'optimize',
        status      VARCHAR(20)     NOT NULL DEFAULT 'success',
        details     TEXT            NULL,
        created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY post_id (post_id),
        KEY created_at (created_at)
    ) {$charset};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );

    update_option( 'sbp_db_version', SBP_VERSION );

    // Schedule cron
    if ( ! wp_next_scheduled( 'sbp_daily_optimization' ) ) {
        wp_schedule_event( time(), 'daily', 'sbp_daily_optimization' );
    }
}
register_activation_hook( __FILE__, 'sbp_activate' );

/**
 * Deactivation hook.
 */
function sbp_deactivate() {
    wp_clear_scheduled_hook( 'sbp_daily_optimization' );
}
register_deactivation_hook( __FILE__, 'sbp_deactivate' );
