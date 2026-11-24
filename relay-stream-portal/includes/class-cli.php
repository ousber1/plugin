<?php
/**
 * WP-CLI commands: `wp rsp queue`, `wp rsp status`, `wp rsp test-connection`, `wp rsp setup-pages`.
 *
 * @package RelayStreamPortal
 */

defined( 'ABSPATH' ) || exit;

/**
 * CLI.
 */
class RSP_Cli {

	/**
	 * Register.
	 */
	public static function register() {
		WP_CLI::add_command( 'rsp queue', array( __CLASS__, 'queue' ) );
		WP_CLI::add_command( 'rsp status', array( __CLASS__, 'status' ) );
		WP_CLI::add_command( 'rsp test-connection', array( __CLASS__, 'test_connection' ) );
		WP_CLI::add_command( 'rsp setup-pages', array( __CLASS__, 'setup_pages' ) );
	}

	/**
	 * Process pending provisioning jobs.
	 *
	 * [--limit=<n>]
	 *
	 * @param array $args Args.
	 * @param array $assoc Assoc.
	 */
	public static function queue( $args, $assoc ) {
		$n = RSP_Jobs::process_queue( isset( $assoc['limit'] ) ? (int) $assoc['limit'] : 25 );
		WP_CLI::success( "Processed $n job(s)." );
	}

	/**
	 * Print queue and mode summary.
	 */
	public static function status() {
		global $wpdb;
		WP_CLI::line( 'Mode: ' . ( RSP_Provider_Factory::is_live() ? 'live' : 'manual' ) );
		$rows = $wpdb->get_results( 'SELECT state, COUNT(*) c FROM ' . RSP_Installer::table( 'jobs' ) . ' GROUP BY state', ARRAY_A ); // phpcs:ignore
		WP_CLI\Utils\format_items( 'table', $rows ? $rows : array( array( 'state' => '-', 'c' => 0 ) ), array( 'state', 'c' ) );
	}

	/**
	 * Run the read-only reseller_info check and print a redacted result.
	 */
	public static function test_connection() {
		$r = RSP_Provider_Factory::tvplus()->test_connection();
		WP_CLI::line( wp_json_encode( $r->to_log(), JSON_PRETTY_PRINT ) );
		$r->ok() ? WP_CLI::success( 'Connected.' ) : WP_CLI::error( $r->error, false );
	}

	/**
	 * Create missing portal pages.
	 *
	 * [--all]
	 * [--elementor]
	 *
	 * @param array $args Args.
	 * @param array $assoc Assoc.
	 */
	public static function setup_pages( $args, $assoc ) {
		$n = RSP_Setup::create_pages( isset( $assoc['all'] ), isset( $assoc['elementor'] ) );
		WP_CLI::success( "Created $n page(s)." );
	}
}
