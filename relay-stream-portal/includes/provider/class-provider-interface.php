<?php
/**
 * Provider contract.
 *
 * @package RelayStreamPortal
 */

defined( 'ABSPATH' ) || exit;

/**
 * Every provider adapter implements this. Methods that a provider does not support
 * must return a 'failed' result with a clear message and report false in capabilities().
 */
interface RSP_Provider_Interface {

	/**
	 * Slug.
	 *
	 * @return string
	 */
	public function slug();

	/**
	 * Capability matrix: capability => array( 'supported' => bool, 'note' => string ).
	 *
	 * @return array
	 */
	public function capabilities();

	/**
	 * Read-only connectivity check.
	 *
	 * @return RSP_Provider_Result
	 */
	public function test_connection();

	/**
	 * List packages.
	 *
	 * @return RSP_Provider_Result data['packages'] = array( id => name ).
	 */
	public function list_packages();

	/**
	 * Create an account.
	 *
	 * @param array $args months, package, type, note, mac.
	 * @return RSP_Provider_Result
	 */
	public function create_account( array $args );

	/**
	 * Renew an account.
	 *
	 * @param array $creds Decrypted credentials.
	 * @param int   $months Months.
	 * @param string $type m3u|mag.
	 * @return RSP_Provider_Result
	 */
	public function renew_account( array $creds, $months, $type );

	/**
	 * Look up an account (status + expiry).
	 *
	 * @param array  $creds Decrypted credentials.
	 * @param string $type m3u|mag.
	 * @return RSP_Provider_Result data['expire'] = 'Y-m-d'.
	 */
	public function lookup_account( array $creds, $type );
}
