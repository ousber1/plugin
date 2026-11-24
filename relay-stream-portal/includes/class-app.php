<?php
/**
 * Full-page app mode and sign-in routing.
 *
 * - Portal pages (mapped in Elementor & Pages) render through templates/app-canvas.php: a full
 *   viewport document with only the portal (no theme header/footer). wp_head()/wp_footer() still
 *   run, so WooCommerce, Elementor and SEO plugins keep working.
 * - Customers land on the portal dashboard after signing in or registering (portal forms,
 *   wp-login.php and WooCommerce My Account). Staff keep WordPress' default redirect.
 * - Signed-in customers who open the login/register pages go straight to the dashboard;
 *   guests opening a protected portal page are sent to the login page and back afterwards.
 *
 * @package RelayStreamPortal
 */

defined( 'ABSPATH' ) || exit;

/**
 * App mode.
 */
class RSP_App {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_filter( 'template_include', array( __CLASS__, 'template' ), 99 );
		add_action( 'template_redirect', array( __CLASS__, 'route' ), 5 );
		add_filter( 'login_redirect', array( __CLASS__, 'login_redirect' ), 20, 3 );
		add_filter( 'woocommerce_login_redirect', array( __CLASS__, 'wc_redirect' ), 20, 2 );
		add_filter( 'woocommerce_registration_redirect', array( __CLASS__, 'wc_redirect' ), 20 );
	}

	/**
	 * Role of the current page in the portal ('' when not a portal page).
	 *
	 * @return string
	 */
	public static function page_role() {
		$id = get_queried_object_id();
		if ( ! $id || ! is_page() ) {
			return '';
		}
		$role = array_search( (int) $id, array_map( 'intval', (array) get_option( 'rsp_pages', array() ) ), true );
		return false === $role ? '' : (string) $role;
	}

	/**
	 * Is the user a customer (not staff)?
	 *
	 * @param WP_User|null $user User.
	 * @return bool
	 */
	public static function is_customer( $user ) {
		return $user instanceof WP_User && $user->ID && ! user_can( $user, 'edit_posts' ) && ! user_can( $user, 'rsp_view_admin' ) && ! user_can( $user, 'manage_woocommerce' );
	}

	/**
	 * Use the full-page canvas for portal pages.
	 *
	 * @param string $template Template.
	 * @return string
	 */
	public static function template( $template ) {
		if ( RSP_Settings::get( 'app_mode' ) && '' !== self::page_role() && ! RSP_Portal::is_preview() && ! is_admin() ) {
			return RSP_DIR . 'templates/app-canvas.php';
		}
		return $template;
	}

	/**
	 * Redirect between auth pages and the portal.
	 */
	public static function route() {
		$role = self::page_role();
		if ( '' === $role || RSP_Portal::is_preview() ) {
			return;
		}
		if ( is_user_logged_in() && in_array( $role, array( 'login', 'register' ), true ) && empty( $_GET['rsp_msg'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			wp_safe_redirect( RSP_Settings::page_url( 'dashboard' ) );
			exit;
		}
		if ( ! is_user_logged_in() && RSP_Settings::get( 'app_mode' ) && ! in_array( $role, array( 'login', 'register', 'reset' ), true ) ) {
			$pages = (array) get_option( 'rsp_pages', array() );
			if ( ! empty( $pages['login'] ) ) {
				wp_safe_redirect( add_query_arg( 'redirect_to', rawurlencode( RSP_Portal::current_url() ), get_permalink( (int) $pages['login'] ) ) );
				exit;
			}
		}
	}

	/**
	 * wp-login.php redirect for customers.
	 *
	 * @param string           $to Requested.
	 * @param string           $requested Requested raw.
	 * @param WP_User|WP_Error $user User.
	 * @return string
	 */
	public static function login_redirect( $to, $requested, $user ) {
		if ( RSP_Settings::get( 'redirect_after_login' ) && self::is_customer( $user ) && ( '' === $requested || false !== strpos( $to, 'wp-admin' ) ) ) {
			return RSP_Settings::page_url( 'dashboard' );
		}
		return $to;
	}

	/**
	 * WooCommerce login/registration redirect for customers.
	 *
	 * @param string       $to Target.
	 * @param WP_User|null $user User.
	 * @return string
	 */
	public static function wc_redirect( $to, $user = null ) {
		$user = $user instanceof WP_User ? $user : wp_get_current_user();
		if ( RSP_Settings::get( 'redirect_after_login' ) && ( ! $user->ID || self::is_customer( $user ) ) && empty( $_REQUEST['redirect'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return RSP_Settings::page_url( 'dashboard' );
		}
		return $to;
	}
}
