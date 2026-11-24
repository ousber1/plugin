<?php
/**
 * Shortcodes. Each maps to the same renderer used by the Elementor widgets.
 *
 * @package RelayStreamPortal
 */

defined( 'ABSPATH' ) || exit;

/**
 * Shortcodes.
 */
class RSP_Shortcodes {

	/**
	 * Shortcode => module map (also used by docs and the admin reference).
	 *
	 * @return array tag => array(module, description)
	 */
	public static function map() {
		return array(
			'relay_portal'               => array( 'shell', __( 'Full portal with header and navigation. Attribute module="dashboard|accounts|apps|plans|transactions|referrals|redeem|support|profile".', 'relay-stream-portal' ) ),
			'relay_dashboard'            => array( 'dashboard', __( 'Overview module only.', 'relay-stream-portal' ) ),
			'relay_accounts'             => array( 'accounts', __( 'My Accounts module.', 'relay-stream-portal' ) ),
			'relay_apps'                 => array( 'apps', __( 'Apps & Guides module.', 'relay-stream-portal' ) ),
			'relay_plans'                => array( 'plans', __( 'Plans & Billing module.', 'relay-stream-portal' ) ),
			'relay_transactions'         => array( 'transactions', __( 'Transactions module.', 'relay-stream-portal' ) ),
			'relay_referrals'            => array( 'referrals', __( 'Referrals & Gifts module.', 'relay-stream-portal' ) ),
			'relay_gifts'                => array( 'gifts', __( 'Referrals & Gifts module opened on the Gifts tab.', 'relay-stream-portal' ) ),
			'relay_gift_redeem'          => array( 'redeem', __( 'Gift redemption form.', 'relay-stream-portal' ) ),
			'relay_support'              => array( 'support', __( 'Support tickets module.', 'relay-stream-portal' ) ),
			'relay_profile'              => array( 'profile', __( 'Profile editing.', 'relay-stream-portal' ) ),
			'relay_login'                => array( 'login', __( 'Sign-in form.', 'relay-stream-portal' ) ),
			'relay_register'             => array( 'register', __( 'Registration form.', 'relay-stream-portal' ) ),
			'relay_password_reset'       => array( 'reset', __( 'Forgot / reset password form.', 'relay-stream-portal' ) ),
			'relay_plan_cards'           => array( 'plan_cards', __( 'Plan cards only.', 'relay-stream-portal' ) ),
			'relay_subscription_summary' => array( 'subscription_summary', __( 'Subscription and expiry summary.', 'relay-stream-portal' ) ),
			'relay_announcements'        => array( 'announcements', __( 'Active announcements.', 'relay-stream-portal' ) ),
			'relay_device_guides'        => array( 'device_guides', __( 'Device tabs with setup guides.', 'relay-stream-portal' ) ),
			'relay_support_button'       => array( 'support_button', __( 'Support call-to-action button.', 'relay-stream-portal' ) ),
		);
	}

	/**
	 * Register.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ), 5 );
	}

	/**
	 * Add shortcodes (on init, after translations are available).
	 */
	public static function register() {
		foreach ( self::map() as $tag => $def ) {
			add_shortcode(
				$tag,
				static function ( $atts ) use ( $def ) {
					return RSP_Shortcodes::render( $def[0], (array) $atts );
				}
			);
		}
	}

	/**
	 * Render.
	 *
	 * @param string $module Module.
	 * @param array  $atts Attributes.
	 * @return string
	 */
	public static function render( $module, array $atts ) {
		$atts = shortcode_atts(
			array(
				'module' => 'dashboard',
				'class'  => '',
				'title'  => '',
				'tab'    => '',
				'device' => '',
				'label'  => '',
			),
			$atts
		);
		$args = array(
			'class'  => $atts['class'],
			'tab'    => sanitize_key( $atts['tab'] ),
			'device' => sanitize_key( $atts['device'] ),
			'labels' => array_filter( array( 'title' => sanitize_text_field( $atts['title'] ), 'button' => sanitize_text_field( $atts['label'] ) ) ),
		);
		if ( 'shell' === $module ) {
			$args['module'] = sanitize_key( $atts['module'] );
			return RSP_Portal::render_shell( $args );
		}
		return RSP_Portal::render_module( $module, $args );
	}
}
