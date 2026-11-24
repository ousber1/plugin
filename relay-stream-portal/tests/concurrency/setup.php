<?php
/**
 * Concurrency fixture setup (manual mode, no remote calls). Prints JSON with ids.
 *
 * @package RelayStreamPortal\Tests
 */

if ( ! defined( 'WP_CLI' ) ) {
	exit;
}
define( 'RSP_TESTING', true );
add_filter( 'pre_wp_mail', '__return_true' );
global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '%rsp\\_rl\\_%'" ); // phpcs:ignore
RSP_Settings::update( array( 'api_mode' => 'manual', 'referral_enabled' => 1, 'referral_review_days' => 0, 'referral_days' => 30 ) );
$mk = static function ( $t ) {
	return RSP_Auth::register( array( 'email' => 'rspconc-' . $t . '-' . strtolower( wp_generate_password( 6, false ) ) . '@example.test', 'password' => 'Secret-pass-123' ) );
};
$buyer = $mk( 'buyer' );
$rcpt  = $mk( 'recipient' );
$friend = $mk( 'friend' );
$plans = RSP_Woocommerce::plans();
$pid   = $plans[0]['id'];
$build = static function ( $uid, $purpose ) use ( $pid ) {
	$o  = wc_create_order( array( 'customer_id' => $uid ) );
	$o->set_billing_email( get_userdata( $uid )->user_email );
	$it = new WC_Order_Item_Product();
	$it->set_product( wc_get_product( $pid ) );
	$it->set_total( 13 );
	RSP_Woocommerce::snapshot_line_item( $it, 'k', array( 'rsp_purpose' => $purpose ), $o );
	$o->add_item( $it );
	$o->calculate_totals();
	$o->save();
	return $o;
};
// Gift: paid normally (single process), code captured for the redeem race.
$g = $build( $buyer, 'gift' );
$g->payment_complete( 'C-GIFT' );
$gift = RSP_Gifts::purchased_by( $buyer )[0];
$code = RSP_Gifts::code_for_owner( $gift, $buyer );
// Order paid with fulfillment hooks detached, so workers race on on_paid().
remove_action( 'woocommerce_order_status_processing', array( 'RSP_Fulfillment', 'on_paid' ), 20 );
remove_action( 'woocommerce_payment_complete', array( 'RSP_Fulfillment', 'on_paid' ), 20 );
remove_action( 'woocommerce_payment_complete', array( 'RSP_Referrals', 'on_paid' ), 30 );
remove_action( 'woocommerce_order_status_processing', array( 'RSP_Referrals', 'on_paid' ), 30 );
$p = $build( $buyer, 'new' );
$p->payment_complete( 'C-PAY' );
// Referral ready to reward; workers race on process_rewards().
RSP_Referrals::bind( $friend, RSP_Referrals::code_for( $buyer ) );
$f = $build( $friend, 'new' );
$f->payment_complete( 'C-REF' );
RSP_Referrals::on_paid( $f->get_id() );
echo wp_json_encode( array( 'code' => $code, 'gift' => (int) $gift->id, 'rcpt' => $rcpt, 'order' => $p->get_id(), 'referrer' => $buyer ) );
