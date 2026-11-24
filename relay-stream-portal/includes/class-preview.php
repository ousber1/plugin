<?php
/**
 * Synthetic data for the Elementor editor and the admin preview. Never derived from real
 * customers and never persisted. Rendering happens at runtime, so Elementor's template JSON
 * only stores widget settings.
 *
 * @package RelayStreamPortal
 */

defined( 'ABSPATH' ) || exit;

/**
 * Preview data.
 */
class RSP_Preview {

	/**
	 * Build a view model shaped like RSP_Portal::view_model().
	 *
	 * @return array
	 */
	public static function view_model() {
		$d   = static function ( $days ) {
			return gmdate( 'Y-m-d H:i:s', time() + $days * DAY_IN_SECONDS );
		};
		$cur = function_exists( 'get_woocommerce_currency_symbol' ) ? html_entity_decode( get_woocommerce_currency_symbol() ) : '$';
		$fmt = static function ( $n ) use ( $cur ) {
			return esc_html( $cur . number_format_i18n( $n, 2 ) );
		};
		return array(
			'preview'       => true,
			'user'          => array( 'id' => 0, 'name' => 'Sample Customer', 'first' => 'Sample', 'email' => 'customer@example.test', 'initials' => 'SC' ),
			'accounts'      => array(
				array( 'uuid' => '00000000-0000-4000-8000-000000000001', 'nickname' => 'Living room', 'plan' => 'Relay Annual', 'status' => 'active', 'status_label' => RSP_Accounts::status_label( 'active' ), 'activated_at' => $d( -120 ), 'expires_at' => $d( 245 ), 'days_left' => 245, 'max_connections' => 2, 'has_credentials' => true, 'job' => null, 'renewable' => true, 'review_reason' => '' ),
				array( 'uuid' => '00000000-0000-4000-8000-000000000002', 'nickname' => 'Holiday home', 'plan' => 'Relay Monthly', 'status' => 'expired', 'status_label' => RSP_Accounts::status_label( 'expired' ), 'activated_at' => $d( -60 ), 'expires_at' => $d( -3 ), 'days_left' => 0, 'max_connections' => null, 'has_credentials' => true, 'job' => null, 'renewable' => true, 'review_reason' => '' ),
				array( 'uuid' => '00000000-0000-4000-8000-000000000003', 'nickname' => 'Kids tablet', 'plan' => 'Relay Quarter', 'status' => 'pending_activation', 'status_label' => RSP_Accounts::status_label( 'pending_activation' ), 'activated_at' => null, 'expires_at' => null, 'days_left' => null, 'max_connections' => null, 'has_credentials' => false, 'job' => array( 'state' => 'pending', 'label' => RSP_Jobs::state_label( 'pending' ) ), 'renewable' => false, 'review_reason' => '' ),
			),
			'pending_orders' => array( array( 'number' => '1042', 'plan' => 'Relay Half-Year', 'pay_url' => '#', 'date' => $d( -1 ) ) ),
			'orders'        => array(
				'rows'  => array(
					array( 'id' => 1041, 'number' => '1041', 'date' => $d( -2 ), 'total' => $fmt( 120 ), 'method' => 'Card', 'status' => 'completed', 'status_label' => 'Completed', 'prov' => 'succeeded', 'prov_label' => RSP_Fulfillment::status_label( 'succeeded' ), 'view_url' => '#', 'pay_url' => '' ),
					array( 'id' => 1042, 'number' => '1042', 'date' => $d( -1 ), 'total' => $fmt( 66 ), 'method' => 'Bitcoin', 'status' => 'pending', 'status_label' => 'Pending payment', 'prov' => 'awaiting', 'prov_label' => RSP_Fulfillment::status_label( 'awaiting' ), 'view_url' => '#', 'pay_url' => '#' ),
					array( 'id' => 1039, 'number' => '1039', 'date' => $d( -40 ), 'total' => $fmt( 36 ), 'method' => 'Card', 'status' => 'processing', 'status_label' => 'Processing', 'prov' => 'needs_review', 'prov_label' => RSP_Fulfillment::status_label( 'needs_review' ), 'view_url' => '#', 'pay_url' => '' ),
				),
				'pages' => 1,
				'total' => 3,
				'page'  => 1,
			),
			'order_count'   => 3,
			'plans'         => array(
				array( 'id' => 1, 'name' => 'Relay Monthly', 'months' => 1, 'price_html' => $fmt( 13 ), 'monthly_html' => $fmt( 13 ), 'savings' => 0, 'featured' => false, 'badge' => '', 'features' => array( 'Full authorized catalog', 'Every supported device' ), 'giftable' => true ),
				array( 'id' => 2, 'name' => 'Relay Quarter', 'months' => 3, 'price_html' => $fmt( 36 ), 'monthly_html' => $fmt( 12 ), 'savings' => 8, 'featured' => false, 'badge' => '', 'features' => array( 'Full authorized catalog', 'Every supported device' ), 'giftable' => true ),
				array( 'id' => 3, 'name' => 'Relay Half-Year', 'months' => 6, 'price_html' => $fmt( 66 ), 'monthly_html' => $fmt( 11 ), 'savings' => 15, 'featured' => true, 'badge' => 'Popular', 'features' => array( 'Full authorized catalog', 'Every supported device' ), 'giftable' => true ),
				array( 'id' => 4, 'name' => 'Relay Annual', 'months' => 12, 'price_html' => $fmt( 120 ), 'monthly_html' => $fmt( 10 ), 'savings' => 23, 'featured' => false, 'badge' => 'Best value', 'features' => array( 'Full authorized catalog', 'Every supported device' ), 'giftable' => true ),
			),
			'gateways'      => array( 'card' => 'Credit / debit card', 'btc' => 'Bitcoin & Lightning' ),
			'referral'      => array(
				'code'    => 'RDEMO2468',
				'link'    => home_url( '/register/?rsp_ref=RDEMO2468' ),
				'stats'   => array( 'invited' => 4, 'converted' => 2, 'pending' => 1, 'earned' => 60, 'balance' => 30 ),
				'history' => array(
					array( 'label' => 'J•••', 'status' => 'rewarded', 'days' => 30, 'date' => $d( -30 ) ),
					array( 'label' => 'M•••', 'status' => 'pending', 'days' => 0, 'date' => $d( -4 ) ),
				),
				'has_referrer' => false,
			),
			'gifts'         => array(
				array( 'id' => 1, 'plan' => 'Relay Quarter', 'status' => 'issued', 'code' => 'ABCD-EFGH-JKLM', 'hint' => 'JKLM', 'expires_at' => $d( 300 ), 'recipient' => '' ),
			),
			'tickets'       => array(
				array( 'id' => 12, 'subject' => 'Guide loads slowly on my TV', 'status' => 'awaiting_customer', 'status_label' => RSP_Tickets::status_label( 'awaiting_customer' ), 'category' => 'technical', 'updated_at' => $d( -1 ) ),
			),
			'notifications' => array(
				array( 'id' => 1, 'title' => 'Your account was extended', 'body' => 'Living room is active for another year.', 'link' => '#', 'read_at' => null, 'created_at' => $d( -2 ) ),
			),
			'unread'        => 1,
			'announcements' => array(
				array( 'id' => 'preview', 'title' => 'Preview announcement', 'body' => 'Announcements you publish in the admin appear here.', 'tone' => 'info', 'dismissible' => 1 ),
			),
			'billing'       => true,
			'player'        => array( 'enabled' => true, 'url' => '#', 'label' => RSP_Settings::get( 'player_label' ) ),
		);
	}
}
