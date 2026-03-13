<?php
/**
 * Plugin deactivation handler.
 *
 * @package PrintFlowPro
 */

defined( 'ABSPATH' ) || exit;

class PFP_Deactivator {

	/**
	 * Run deactivation tasks.
	 */
	public static function deactivate() {
		// Clear scheduled cron events.
		wp_clear_scheduled_hook( 'pfp_hourly_stock_check' );
		wp_clear_scheduled_hook( 'pfp_daily_report' );
		wp_clear_scheduled_hook( 'pfp_weekly_report' );
		wp_clear_scheduled_hook( 'pfp_monthly_report' );

		// Flush rewrite rules.
		flush_rewrite_rules();
	}
}
