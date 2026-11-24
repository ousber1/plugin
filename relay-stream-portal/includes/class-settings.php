<?php
/**
 * Settings storage with defaults.
 *
 * @package RelayStreamPortal
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings.
 */
class RSP_Settings {

	const OPTION = 'rsp_settings';

	/** @var array|null */
	private static $cache = null;

	/**
	 * Portal modules (slug => label). Order here is the default navigation order.
	 *
	 * @return array
	 */
	public static function modules() {
		return array(
			'dashboard'    => __( 'Overview', 'relay-stream-portal' ),
			'accounts'     => __( 'My Accounts', 'relay-stream-portal' ),
			'apps'         => __( 'Apps & Guides', 'relay-stream-portal' ),
			'plans'        => __( 'Plans & Billing', 'relay-stream-portal' ),
			'transactions' => __( 'Transactions', 'relay-stream-portal' ),
			'referrals'    => __( 'Referrals & Gifts', 'relay-stream-portal' ),
			'redeem'       => __( 'Redeem a Gift', 'relay-stream-portal' ),
			'support'      => __( 'Support', 'relay-stream-portal' ),
			'profile'      => __( 'Profile', 'relay-stream-portal' ),
		);
	}

	/**
	 * Page roles managed by the setup wizard.
	 *
	 * @return array role => array(title, shortcode)
	 */
	public static function page_roles() {
		return array(
			'login'        => array( __( 'Sign In', 'relay-stream-portal' ), '[relay_login]' ),
			'register'     => array( __( 'Create Account', 'relay-stream-portal' ), '[relay_register]' ),
			'reset'        => array( __( 'Reset Password', 'relay-stream-portal' ), '[relay_password_reset]' ),
			'dashboard'    => array( __( 'Portal', 'relay-stream-portal' ), '[relay_portal module="dashboard"]' ),
			'accounts'     => array( __( 'My Accounts', 'relay-stream-portal' ), '[relay_portal module="accounts"]' ),
			'apps'         => array( __( 'Apps & Guides', 'relay-stream-portal' ), '[relay_portal module="apps"]' ),
			'plans'        => array( __( 'Plans & Billing', 'relay-stream-portal' ), '[relay_portal module="plans"]' ),
			'transactions' => array( __( 'Transactions', 'relay-stream-portal' ), '[relay_portal module="transactions"]' ),
			'referrals'    => array( __( 'Referrals & Gifts', 'relay-stream-portal' ), '[relay_portal module="referrals"]' ),
			'redeem'       => array( __( 'Redeem a Gift', 'relay-stream-portal' ), '[relay_portal module="redeem"]' ),
			'support'      => array( __( 'Support', 'relay-stream-portal' ), '[relay_portal module="support"]' ),
			'profile'      => array( __( 'Profile', 'relay-stream-portal' ), '[relay_portal module="profile"]' ),
		);
	}

	/**
	 * Defaults.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			// Branding.
			'brand_name'          => 'Relay Stream',
			'brand_tagline'       => 'Your streaming, relayed.',
			'logo_id'             => 0,
			'theme_default'       => 'dark', // auto|dark|light.
			'allow_theme_toggle'  => 1,
			'color_accent'        => '#3b82f6',
			'color_accent_2'      => '#60a5fa',
			'color_gold'          => '#e7b85b',
			'color_dark_bg'       => '#0a0e17',
			'color_dark_surface'  => '#161d2b',
			'color_light_bg'      => '#f5f7f8',
			'color_light_surface' => '#ffffff',
			'radius'              => 14,
			'nav_order'           => array_keys( self::modules() ),
			'modules_hidden'      => array(),
			'text_welcome'        => '{greeting}, {name}!',
			'text_welcome_sub'    => 'Here\'s an overview of your account',
			'layout'              => 'sidebar', // sidebar|topbar.
			'app_mode'            => 1, // Portal pages render full-screen (no theme header/footer).
			'redirect_after_login'=> 1,
			'secure_payment_text' => 'You will be redirected to a secure payment page. Your subscription activates automatically once payment is confirmed. No recurring charges — pay once, enjoy for the full duration.',
			'included_subtitle'   => 'Everything you get with every plan',
			'payment_guides_title'=> 'How to pay',
			'support_email'       => '',
			'support_url'         => '',
			'floating_support'    => 1,
			'floating_label'      => 'Need help?',
			'language'            => '', // Empty = site language.
			'full_width'          => 1,
			// Accounts / player.
			'player_enabled'      => 0,
			'player_url'          => '',
			'player_label'        => 'Start Watching',
			'show_credentials'    => 1,
			'max_accounts'        => 10,
			// Provider API.
			'api_mode'            => 'manual', // manual|live.
			'api_provider'        => 'tvplus', // tvplus|megaott.
			'megaott_base_url'    => 'https://megaott.net/api/v1',
			'megaott_token_enc'   => '',
			'megaott_forced_country' => 'ALL',
			'megaott_adult'       => 0,
			'megaott_vpn'         => 0,
			'megaott_contact'     => '',
			'megaott_connections' => 1,
			'megaott_reward_package' => '',
			'api_base_url'        => 'https://tvpluspanel.net/api/api.php',
			'api_docs_url'        => 'https://tvpluspanel.ru/api/panel_api.html',
			'api_key_enc'         => '',
			'api_allowed_hosts'   => 'tvpluspanel.net,megaott.net',
			'api_timeout'         => 20,
			'api_mapping_verified'=> 0,
			'api_default_type'    => 'm3u',
			'api_note_prefix'     => 'rsp',
			// Billing.
			'hide_stale_unpaid'   => 1,
			'stale_unpaid_hours'  => 48,
			'included_features'   => "Live channels and on-demand library from your authorized catalog\nHD and 4K where the source supports it\nSetup help from our support team\nOne-time payment, no automatic renewal",
			'payment_guides'      => array(
				array( 'title' => 'Credit Card', 'icon' => '💳', 'url' => '', 'text' => '' ),
				array( 'title' => 'Crypto Wallet', 'icon' => '👛', 'url' => '', 'text' => '' ),
			),
			'refund_policy_text'  => 'Refunds are reviewed by our team. Activated accounts may be shortened or suspended after a refund.',
			// Referrals.
			'referral_enabled'    => 1,
			'referral_days'       => 30,
			'referral_review_days'=> 7,
			'referral_min_total'  => 0,
			'referral_reversal'   => 'reverse_unapplied', // keep|reverse_unapplied|review.
			'referral_cookie_days'=> 30,
			'reward_month_conversion' => 0, // Apply 30-day blocks as a 1-month renewal when the provider supports only months.
			// Gifts.
			'gift_enabled'        => 1,
			'gift_expiry_days'    => 365,
			// Support.
			'support_categories'  => "billing|Billing & payments\nactivation|Activation & accounts\ntechnical|Playback & apps\ngeneral|Something else",
			'attach_max_mb'       => 5,
			'attach_types'        => 'jpg,jpeg,png,gif,pdf,txt',
			// Notifications.
			'email_notifications' => 1,
			'reminder_days'       => '7,3,1',
			'email_templates'     => array(),
			// Auth.
			'email_verification'  => 0,
			'registration_open'   => 1,
			// Data.
			'delete_on_uninstall' => 0,
		);
	}

	/**
	 * Email template defaults.
	 *
	 * @return array
	 */
	public static function default_templates() {
		return array(
			'ticket_reply' => array(
				'subject' => '[{site}] New reply on ticket #{ticket}',
				'body'    => "Hi {name},\n\nOur team replied to your ticket \"{subject}\".\n\nView it here: {link}\n\n— {site}",
			),
			'activation'   => array(
				'subject' => '[{site}] Your streaming account is active',
				'body'    => "Hi {name},\n\nYour account \"{account}\" is active until {expiry}.\n\nOpen your portal: {link}\n\nFor your safety we never send player credentials by email.\n\n— {site}",
			),
			'expiry'       => array(
				'subject' => '[{site}] Your account expires in {days} day(s)',
				'body'    => "Hi {name},\n\nYour account \"{account}\" expires on {expiry}. Plans never renew automatically — renew from your portal: {link}\n\n— {site}",
			),
			'gift'         => array(
				'subject' => '[{site}] You received a gift subscription',
				'body'    => "Hello,\n\n{sender} sent you a gift: {plan}.\n\nRedeem code: {code}\nRedeem here: {link}\nThe code expires on {expiry}.\n\n— {site}",
			),
			'referral'     => array(
				'subject' => '[{site}] You earned {days} free days',
				'body'    => "Hi {name},\n\nA friend you referred completed their first payment. {days} days were added to your reward balance.\n\nApply them from your portal: {link}\n\n— {site}",
			),
			'verify'       => array(
				'subject' => '[{site}] Confirm your email address',
				'body'    => "Hi {name},\n\nConfirm your email address to finish creating your account:\n{link}\n\nIf you did not sign up, ignore this message.\n\n— {site}",
			),
		);
	}

	/**
	 * Seed defaults without overwriting existing values.
	 */
	public static function seed_defaults() {
		$stored = get_option( self::OPTION );
		if ( ! is_array( $stored ) ) {
			add_option( self::OPTION, self::defaults(), '', true );
		}
		if ( false === get_option( 'rsp_guides' ) ) {
			add_option( 'rsp_guides', RSP_Guides::demo(), '', false );
		}
		if ( false === get_option( 'rsp_announcements' ) ) {
			add_option(
				'rsp_announcements',
				array(
					array(
						'id'          => 'welcome',
						'title'       => 'Welcome to your new portal',
						'body'        => 'Manage accounts, renew plans and get help from one place. We will never ask you to pay outside this portal.',
						'tone'        => 'info',
						'dismissible' => 1,
						'active'      => 1,
						'starts'      => '',
						'ends'        => '',
					),
				),
				'',
				false
			);
		}
		if ( false === get_option( 'rsp_pages' ) ) {
			add_option( 'rsp_pages', array(), '', true );
		}
		self::$cache = null;
	}

	/**
	 * All settings merged with defaults.
	 *
	 * @return array
	 */
	public static function all() {
		if ( null === self::$cache ) {
			$stored      = get_option( self::OPTION, array() );
			self::$cache = wp_parse_args( is_array( $stored ) ? $stored : array(), self::defaults() );
			if ( empty( self::$cache['email_templates'] ) || ! is_array( self::$cache['email_templates'] ) ) {
				self::$cache['email_templates'] = array();
			}
			self::$cache['email_templates'] = wp_parse_args( self::$cache['email_templates'], self::default_templates() );
		}
		return self::$cache;
	}

	/**
	 * Get one setting.
	 *
	 * @param string $key Key.
	 * @param mixed  $fallback Fallback.
	 * @return mixed
	 */
	public static function get( $key, $fallback = null ) {
		$all = self::all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : $fallback;
	}

	/**
	 * Update several settings.
	 *
	 * @param array $values Values.
	 */
	public static function update( array $values ) {
		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();
		update_option( self::OPTION, array_merge( $stored, $values ), true );
		self::$cache = null;
	}

	/**
	 * Visible modules in navigation order.
	 *
	 * @return array slug => label
	 */
	public static function nav_modules() {
		$all    = self::modules();
		$order  = (array) self::get( 'nav_order', array_keys( $all ) );
		$hidden = (array) self::get( 'modules_hidden', array() );
		$out    = array();
		foreach ( array_merge( $order, array_keys( $all ) ) as $slug ) {
			if ( isset( $all[ $slug ] ) && ! isset( $out[ $slug ] ) && ! in_array( $slug, $hidden, true ) ) {
				$out[ $slug ] = $all[ $slug ];
			}
		}
		if ( ! RSP_Plugin::has_woocommerce() ) {
			unset( $out['transactions'] );
		}
		return $out;
	}

	/**
	 * Support categories as slug => label.
	 *
	 * @return array
	 */
	public static function support_categories() {
		$out = array();
		foreach ( preg_split( '/\r?\n/', (string) self::get( 'support_categories' ) ) as $line ) {
			$parts = array_map( 'trim', explode( '|', $line, 2 ) );
			if ( '' === $parts[0] ) {
				continue;
			}
			$out[ sanitize_key( $parts[0] ) ] = isset( $parts[1] ) && '' !== $parts[1] ? $parts[1] : $parts[0];
		}
		return $out ? $out : array( 'general' => __( 'General', 'relay-stream-portal' ) );
	}

	/**
	 * Page URL for a role, or the portal page with ?rsp_view.
	 *
	 * @param string $role Role.
	 * @param array  $args Query args.
	 * @return string
	 */
	public static function page_url( $role, $args = array() ) {
		$pages = (array) get_option( 'rsp_pages', array() );
		if ( ! empty( $pages[ $role ] ) && 'publish' === get_post_status( (int) $pages[ $role ] ) ) {
			$url = get_permalink( (int) $pages[ $role ] );
		} elseif ( isset( self::modules()[ $role ] ) ) {
			$base = ! empty( $pages['dashboard'] ) && 'publish' === get_post_status( (int) $pages['dashboard'] ) ? get_permalink( (int) $pages['dashboard'] ) : RSP_Portal::current_base_url();
			$url  = add_query_arg( 'rsp_view', $role, $base );
		} elseif ( 'login' === $role ) {
			$url = wp_login_url();
		} elseif ( 'register' === $role ) {
			$url = wp_registration_url();
		} elseif ( 'reset' === $role ) {
			$url = wp_lostpassword_url();
		} else {
			$url = home_url( '/' );
		}
		return $args ? add_query_arg( $args, $url ) : $url;
	}
}
