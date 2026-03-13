<?php
/**
 * Plugin activation handler.
 *
 * @package PrintFlowPro
 */

defined( 'ABSPATH' ) || exit;

class PFP_Activator {

	/**
	 * Run activation tasks.
	 */
	public static function activate() {
		// Check PHP version.
		if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
			deactivate_plugins( PFP_PLUGIN_BASENAME );
			wp_die(
				esc_html__( 'PrintFlow Pro nécessite PHP 7.4 ou supérieur.', 'printflow-pro' ),
				'Plugin Activation Error',
				array( 'back_link' => true )
			);
		}

		// Install database tables.
		require_once PFP_PLUGIN_DIR . 'includes/class-pfp-installer.php';
		PFP_Installer::install();

		// Register roles.
		require_once PFP_PLUGIN_DIR . 'includes/class-pfp-roles.php';
		$roles = new PFP_Roles();
		$roles->create_roles();

		// Schedule cron events.
		self::schedule_events();

		// Set activation flag for setup wizard redirect.
		set_transient( 'pfp_activation_redirect', true, 30 );

		// Store installed version.
		update_option( 'pfp_version', PFP_VERSION );
		update_option( 'pfp_db_version', PFP_DB_VERSION );

		// Flush rewrite rules.
		flush_rewrite_rules();
	}

	/**
	 * Schedule recurring cron events.
	 */
	private static function schedule_events() {
		if ( ! wp_next_scheduled( 'pfp_hourly_stock_check' ) ) {
			wp_schedule_event( time(), 'hourly', 'pfp_hourly_stock_check' );
		}
		if ( ! wp_next_scheduled( 'pfp_daily_report' ) ) {
			wp_schedule_event( strtotime( 'tomorrow 08:00:00' ), 'daily', 'pfp_daily_report' );
		}
		if ( ! wp_next_scheduled( 'pfp_weekly_report' ) ) {
			wp_schedule_event( strtotime( 'next monday 08:00:00' ), 'weekly', 'pfp_weekly_report' );
		}
		if ( ! wp_next_scheduled( 'pfp_monthly_report' ) ) {
			wp_schedule_single_event( strtotime( 'first day of next month 08:00:00' ), 'pfp_monthly_report' );
		}
	}
}
