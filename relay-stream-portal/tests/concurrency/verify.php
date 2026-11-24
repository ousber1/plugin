<?php
/**
 * Concurrency verification. Prints JSON.
 *
 * @package RelayStreamPortal\Tests
 */

if ( ! defined( 'WP_CLI' ) ) {
	exit;
}
global $wpdb;
$in    = json_decode( getenv( 'RSP_CONC' ), true );
$gift  = RSP_Gifts::get( (int) $in['gift'] );
$out   = array(
	'gift_status'        => $gift->status,
	'gift_jobs'          => (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . RSP_Installer::table( 'jobs' ) . ' WHERE idempotency_key = %s', 'gift:' . $gift->id ) ), // phpcs:ignore
	'recipient_accounts' => RSP_Accounts::count_for_user( (int) $in['rcpt'] ),
	'order_jobs'         => count( RSP_Jobs::for_order( (int) $in['order'] ) ),
	'order_accounts'     => (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . RSP_Installer::table( 'accounts' ) . ' WHERE source_order_id = %d', $in['order'] ) ), // phpcs:ignore
	'referrer_balance'   => RSP_Referrals::balance( (int) $in['referrer'] ),
	'referrer_ledger'    => (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . RSP_Installer::table( 'ledger' ) . " WHERE user_id = %d AND reason = 'referral'", $in['referrer'] ) ), // phpcs:ignore
);
echo wp_json_encode( $out );
