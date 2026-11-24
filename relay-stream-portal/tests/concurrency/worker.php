<?php
/**
 * Concurrency worker: RSP_CONC_OP = redeem|pay|reward.
 *
 * @package RelayStreamPortal\Tests
 */

if ( ! defined( 'WP_CLI' ) ) {
	exit;
}
define( 'RSP_TESTING', true );
add_filter( 'pre_wp_mail', '__return_true' );
$in = json_decode( getenv( 'RSP_CONC' ), true );
switch ( getenv( 'RSP_CONC_OP' ) ) {
	case 'redeem':
		$r = RSP_Gifts::redeem( $in['code'], (int) $in['rcpt'], 'new', 'Race' );
		echo is_wp_error( $r ) ? 'LOST' : 'WON';
		break;
	case 'pay':
		RSP_Fulfillment::on_paid( (int) $in['order'] );
		echo 'DONE';
		break;
	case 'reward':
		RSP_Referrals::process_rewards();
		echo 'DONE';
		break;
}
