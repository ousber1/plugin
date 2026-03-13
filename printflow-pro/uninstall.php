<?php
/**
 * PrintFlow Pro uninstall script.
 *
 * Fired when the plugin is uninstalled.
 *
 * @package PrintFlowPro
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

// Only remove data if the option is set.
$remove_data = get_option( 'pfp_remove_data_on_uninstall', 'no' );

if ( 'yes' !== $remove_data ) {
	return;
}

// Remove custom tables.
$tables = array(
	'pfp_materials',
	'pfp_material_categories',
	'pfp_stock_movements',
	'pfp_material_product_map',
	'pfp_suppliers',
	'pfp_purchase_orders',
	'pfp_purchase_order_items',
	'pfp_supplier_payments',
	'pfp_distributors',
	'pfp_distributor_orders',
	'pfp_distributor_commissions',
	'pfp_pricing_rules',
	'pfp_pricing_tiers',
	'pfp_pricing_modifiers',
	'pfp_quotes',
	'pfp_quote_items',
	'pfp_quote_history',
	'pfp_production_jobs',
	'pfp_production_checklists',
	'pfp_production_logs',
	'pfp_artwork_files',
	'pfp_artwork_comments',
	'pfp_income',
	'pfp_expenses',
	'pfp_expense_categories',
	'pfp_invoices',
	'pfp_invoice_items',
	'pfp_payments',
	'pfp_customer_notes',
	'pfp_loyalty_points',
	'pfp_deliveries',
	'pfp_delivery_zones',
	'pfp_delivery_logs',
	'pfp_notification_templates',
	'pfp_notification_log',
);

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

// Remove options.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'pfp\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

// Remove custom roles.
remove_role( 'pfp_manager' );
remove_role( 'pfp_sales_agent' );
remove_role( 'pfp_designer' );
remove_role( 'pfp_production_staff' );
remove_role( 'pfp_accountant' );
remove_role( 'pfp_delivery_staff' );

// Remove custom capabilities from admin.
$admin_role = get_role( 'administrator' );
if ( $admin_role ) {
	$caps = array(
		'manage_printflow',
		'pfp_view_dashboard',
		'pfp_manage_products',
		'pfp_manage_pricing',
		'pfp_manage_files',
		'pfp_manage_quotes',
		'pfp_manage_orders',
		'pfp_manage_production',
		'pfp_manage_inventory',
		'pfp_manage_suppliers',
		'pfp_manage_distributors',
		'pfp_manage_finance',
		'pfp_manage_crm',
		'pfp_manage_delivery',
		'pfp_manage_notifications',
		'pfp_view_reports',
		'pfp_manage_settings',
	);
	foreach ( $caps as $cap ) {
		$admin_role->remove_cap( $cap );
	}
}

// Clear scheduled cron events.
wp_clear_scheduled_hook( 'pfp_hourly_stock_check' );
wp_clear_scheduled_hook( 'pfp_daily_report' );
wp_clear_scheduled_hook( 'pfp_weekly_report' );
wp_clear_scheduled_hook( 'pfp_monthly_report' );
