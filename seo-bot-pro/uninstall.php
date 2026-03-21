<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Remove options
delete_option( 'sbp_settings' );
delete_option( 'sbp_db_version' );
delete_option( 'sbp_404_logs' );
delete_option( 'sbp_redirects' );

// Remove log table
global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}sbp_logs" );

// Remove all post meta
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_sbp_%'" );

// Clear scheduled hooks
wp_clear_scheduled_hook( 'sbp_daily_optimization' );
wp_clear_scheduled_hook( 'sbp_weekly_rank_boost' );
