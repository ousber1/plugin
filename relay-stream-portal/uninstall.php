<?php
/**
 * Uninstall. Data is kept unless the administrator enabled
 * "Delete all plugin data when the plugin is deleted" in Branding.
 *
 * @package RelayStreamPortal
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$rsp_settings = get_option( 'rsp_settings', array() );
if ( empty( $rsp_settings['delete_on_uninstall'] ) ) {
	return;
}

global $wpdb;
foreach ( array( 'accounts', 'jobs', 'audit', 'referrals', 'ledger', 'gifts', 'tickets', 'ticket_messages', 'attachments', 'notifications' ) as $rsp_t ) {
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'rsp_' . $rsp_t ); // phpcs:ignore
}
foreach ( array( 'rsp_settings', 'rsp_guides', 'rsp_announcements', 'rsp_pages', 'rsp_db_version', 'rsp_db_migrations', 'rsp_packages_cache', 'rsp_activated_at' ) as $rsp_o ) {
	delete_option( $rsp_o );
}
$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'rsp\\_%'" ); // phpcs:ignore
$rsp_caps = array( 'rsp_view_admin', 'rsp_manage_settings', 'rsp_manage_billing', 'rsp_manage_provisioning', 'rsp_manage_support', 'rsp_view_credentials', 'rsp_export_data' );
foreach ( wp_roles()->roles as $rsp_role => $rsp_def ) {
	$rsp_r = get_role( $rsp_role );
	foreach ( $rsp_caps as $rsp_c ) {
		$rsp_r && $rsp_r->remove_cap( $rsp_c );
	}
}
remove_role( 'rsp_support_agent' );
remove_role( 'rsp_fulfillment' );
remove_role( 'rsp_billing_manager' );
$rsp_dir = wp_upload_dir( null, false )['basedir'] . '/rsp-private';
if ( is_dir( $rsp_dir ) ) {
	array_map( 'unlink', glob( $rsp_dir . '/{,.}*[!.]*', GLOB_BRACE ) ?: array() );
	@rmdir( $rsp_dir ); // phpcs:ignore
}
