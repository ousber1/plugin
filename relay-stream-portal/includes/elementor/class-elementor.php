<?php
/**
 * Elementor (Free) integration. Loaded only on `elementor/init`, so the portal keeps working
 * through shortcodes when Elementor is inactive.
 *
 * @package RelayStreamPortal
 */

defined( 'ABSPATH' ) || exit;

/**
 * Elementor bootstrap.
 */
class RSP_Elementor {

	const CATEGORY = 'relay-stream-portal';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'elementor/elements/categories_registered', array( __CLASS__, 'category' ) );
		add_action( 'elementor/widgets/register', array( __CLASS__, 'widgets' ) );
		add_action( 'elementor/editor/after_enqueue_styles', array( __CLASS__, 'editor_styles' ) );
		add_action( 'elementor/preview/enqueue_styles', array( 'RSP_Portal', 'enqueue' ) );
	}

	/**
	 * Category.
	 *
	 * @param \Elementor\Elements_Manager $manager Manager.
	 */
	public static function category( $manager ) {
		$manager->add_category( self::CATEGORY, array( 'title' => __( 'Relay Stream Portal', 'relay-stream-portal' ), 'icon' => 'eicon-video-playlist' ) );
	}

	/**
	 * Widget definitions: class suffix => array(name, title, module, icon).
	 *
	 * @return array
	 */
	public static function definitions() {
		return array(
			'Shell'               => array( 'rsp-portal-shell', __( 'Portal Shell', 'relay-stream-portal' ), 'shell', 'eicon-site-identity' ),
			'Dashboard'           => array( 'rsp-dashboard', __( 'Portal: Overview', 'relay-stream-portal' ), 'dashboard', 'eicon-dashboard' ),
			'Accounts'            => array( 'rsp-accounts', __( 'Portal: My Accounts', 'relay-stream-portal' ), 'accounts', 'eicon-person' ),
			'Apps'                => array( 'rsp-apps', __( 'Portal: Apps & Guides', 'relay-stream-portal' ), 'apps', 'eicon-download-button' ),
			'Plans'               => array( 'rsp-plans', __( 'Portal: Plans & Billing', 'relay-stream-portal' ), 'plans', 'eicon-price-table' ),
			'Transactions'        => array( 'rsp-transactions', __( 'Portal: Transactions', 'relay-stream-portal' ), 'transactions', 'eicon-table' ),
			'Referrals'           => array( 'rsp-referrals', __( 'Portal: Referrals & Gifts', 'relay-stream-portal' ), 'referrals', 'eicon-share' ),
			'Redeem'              => array( 'rsp-gift-redeem', __( 'Portal: Gift Redemption', 'relay-stream-portal' ), 'redeem', 'eicon-heart-o' ),
			'Support'             => array( 'rsp-support', __( 'Portal: Support', 'relay-stream-portal' ), 'support', 'eicon-help-o' ),
			'Profile'             => array( 'rsp-profile', __( 'Portal: Profile', 'relay-stream-portal' ), 'profile', 'eicon-user-circle-o' ),
			'Login'               => array( 'rsp-login', __( 'Portal: Login', 'relay-stream-portal' ), 'login', 'eicon-lock-user' ),
			'Register'            => array( 'rsp-register', __( 'Portal: Register', 'relay-stream-portal' ), 'register', 'eicon-person' ),
			'Reset'               => array( 'rsp-password-reset', __( 'Portal: Forgot / Reset Password', 'relay-stream-portal' ), 'reset', 'eicon-lock' ),
			'Plan_Cards'          => array( 'rsp-plan-cards', __( 'Plan Cards', 'relay-stream-portal' ), 'plan_cards', 'eicon-price-list' ),
			'Summary'             => array( 'rsp-subscription-summary', __( 'Subscription Summary', 'relay-stream-portal' ), 'subscription_summary', 'eicon-info-box' ),
			'Announcements'       => array( 'rsp-announcements', __( 'Announcements', 'relay-stream-portal' ), 'announcements', 'eicon-alert' ),
			'Device_Guides'       => array( 'rsp-device-guides', __( 'Device Guides', 'relay-stream-portal' ), 'device_guides', 'eicon-tabs' ),
			'Support_Button'      => array( 'rsp-support-button', __( 'Support Button', 'relay-stream-portal' ), 'support_button', 'eicon-button' ),
		);
	}

	/**
	 * Register widgets.
	 *
	 * @param \Elementor\Widgets_Manager $manager Manager.
	 */
	public static function widgets( $manager ) {
		require_once __DIR__ . '/widgets.php';
		foreach ( array_keys( self::definitions() ) as $suffix ) {
			$class = 'RSP_Widget_' . $suffix;
			if ( class_exists( $class ) ) {
				$manager->register( new $class() );
			}
		}
	}

	/**
	 * Editor panel tweaks.
	 */
	public static function editor_styles() {
		wp_add_inline_style( 'elementor-editor', '.elementor-panel .elementor-element .eicon-video-playlist{color:#14b8a6}' );
	}
}
