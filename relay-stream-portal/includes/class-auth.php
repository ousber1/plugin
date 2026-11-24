<?php
/**
 * Branded authentication built on native WordPress functions.
 *
 * @package RelayStreamPortal
 */

defined( 'ABSPATH' ) || exit;

/**
 * Auth.
 */
class RSP_Auth {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_filter( 'wp_authenticate_user', array( __CLASS__, 'block_unverified' ), 20 );
		add_action( 'init', array( __CLASS__, 'maybe_verify_email' ) );
		add_filter( 'retrieve_password_notification_email', array( __CLASS__, 'reset_email_link' ), 10, 4 );
	}

	/**
	 * Safe redirect target from a requested URL; falls back to the dashboard page.
	 *
	 * @param string $requested Requested.
	 * @return string
	 */
	public static function safe_redirect_target( $requested ) {
		$fallback = RSP_Settings::page_url( 'dashboard' );
		$requested = is_string( $requested ) ? trim( $requested ) : '';
		if ( '' === $requested ) {
			return $fallback;
		}
		$valid = wp_validate_redirect( esc_url_raw( $requested ), '' );
		if ( ! $valid || false !== strpos( $valid, 'wp-login.php' ) || false !== strpos( $valid, 'admin-post.php' ) ) {
			return $fallback;
		}
		return $valid;
	}

	/**
	 * Block login for users who have not verified their email (only when verification applied at signup).
	 *
	 * @param WP_User|WP_Error $user User.
	 * @return WP_User|WP_Error
	 */
	public static function block_unverified( $user ) {
		if ( $user instanceof WP_User && '0' === get_user_meta( $user->ID, 'rsp_email_verified', true ) ) {
			return new WP_Error( 'rsp_unverified', __( 'Please confirm your email address first. Check your inbox for the link.', 'relay-stream-portal' ) );
		}
		return $user;
	}

	/**
	 * Log in.
	 *
	 * @param string $login Username or email.
	 * @param string $password Password.
	 * @param bool   $remember Remember.
	 * @return WP_User|WP_Error
	 */
	public static function login( $login, $password, $remember ) {
		$login = sanitize_text_field( $login );
		if ( ! RSP_Rate_Limiter::hit( 'login', 8, 15 * MINUTE_IN_SECONDS ) || ! RSP_Rate_Limiter::hit( 'login_' . strtolower( $login ), 8, 15 * MINUTE_IN_SECONDS, 'acct' ) ) {
			return new WP_Error( 'rsp_rate', __( 'Too many attempts. Please wait a few minutes.', 'relay-stream-portal' ) );
		}
		if ( is_email( $login ) ) {
			$u = get_user_by( 'email', $login );
			if ( $u ) {
				$login = $u->user_login;
			}
		}
		$user = wp_signon( array( 'user_login' => $login, 'user_password' => $password, 'remember' => (bool) $remember ), is_ssl() );
		if ( is_wp_error( $user ) ) {
			if ( 'rsp_unverified' === $user->get_error_code() ) {
				return $user;
			}
			return new WP_Error( 'rsp_login', __( 'The email/username or password is incorrect.', 'relay-stream-portal' ) );
		}
		return $user;
	}

	/**
	 * Register a customer.
	 *
	 * @param array $data email, password, first_name, referral_code.
	 * @return int|WP_Error User id.
	 */
	public static function register( array $data ) {
		if ( ! RSP_Settings::get( 'registration_open' ) ) {
			return new WP_Error( 'rsp_closed', __( 'Registration is currently closed.', 'relay-stream-portal' ) );
		}
		if ( ! RSP_Rate_Limiter::hit( 'register', 5, HOUR_IN_SECONDS ) ) {
			return new WP_Error( 'rsp_rate', __( 'Too many attempts. Please try again later.', 'relay-stream-portal' ) );
		}
		$email = sanitize_email( isset( $data['email'] ) ? $data['email'] : '' );
		$pass  = isset( $data['password'] ) ? (string) $data['password'] : '';
		$first = sanitize_text_field( isset( $data['first_name'] ) ? $data['first_name'] : '' );
		if ( ! is_email( $email ) ) {
			return new WP_Error( 'rsp_email', __( 'Enter a valid email address.', 'relay-stream-portal' ) );
		}
		if ( email_exists( $email ) ) {
			return new WP_Error( 'rsp_email', __( 'An account with this email already exists. Try signing in or resetting your password.', 'relay-stream-portal' ) );
		}
		if ( strlen( $pass ) < 8 ) {
			return new WP_Error( 'rsp_pass', __( 'Use at least 8 characters for your password.', 'relay-stream-portal' ) );
		}
		$base  = sanitize_user( current( explode( '@', $email ) ), true );
		$base  = $base ? $base : 'customer';
		$login = $base;
		$i     = 1;
		while ( username_exists( $login ) ) {
			$login = $base . ( ++$i );
		}
		$user_id = wp_insert_user(
			array(
				'user_login'   => $login,
				'user_email'   => $email,
				'user_pass'    => $pass,
				'first_name'   => $first,
				'display_name' => $first ? $first : $base,
				'role'         => get_role( 'customer' ) ? 'customer' : get_option( 'default_role', 'subscriber' ),
			)
		);
		if ( is_wp_error( $user_id ) ) {
			return new WP_Error( 'rsp_register', __( 'We could not create your account. Please try again.', 'relay-stream-portal' ) );
		}
		if ( RSP_Settings::get( 'email_verification' ) ) {
			self::send_verification( $user_id );
		}
		RSP_Referrals::code_for( $user_id );
		return $user_id;
	}

	/**
	 * Send email verification link.
	 *
	 * @param int $user_id User.
	 */
	public static function send_verification( $user_id ) {
		$token = wp_generate_password( 32, false );
		update_user_meta( $user_id, 'rsp_email_verified', '0' );
		update_user_meta( $user_id, 'rsp_verify_hash', wp_hash_password( $token ) );
		update_user_meta( $user_id, 'rsp_verify_expires', time() + 2 * DAY_IN_SECONDS );
		$user = get_userdata( $user_id );
		$link = add_query_arg( array( 'rsp_verify' => $token, 'uid' => $user_id ), RSP_Settings::page_url( 'login' ) );
		RSP_Notifications::send_template( $user->user_email, 'verify', array( 'name' => $user->display_name, 'link' => $link ) );
	}

	/**
	 * Handle verification links.
	 */
	public static function maybe_verify_email() {
		if ( empty( $_GET['rsp_verify'] ) || empty( $_GET['uid'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$uid   = (int) $_GET['uid']; // phpcs:ignore WordPress.Security.NonceVerification
		$token = sanitize_text_field( wp_unslash( $_GET['rsp_verify'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$hash  = (string) get_user_meta( $uid, 'rsp_verify_hash', true );
		$exp   = (int) get_user_meta( $uid, 'rsp_verify_expires', true );
		$ok    = $hash && $exp > time() && RSP_Rate_Limiter::hit( 'verify', 10, HOUR_IN_SECONDS ) && wp_check_password( $token, $hash, $uid );
		if ( $ok ) {
			update_user_meta( $uid, 'rsp_email_verified', '1' );
			delete_user_meta( $uid, 'rsp_verify_hash' );
			delete_user_meta( $uid, 'rsp_verify_expires' );
		}
		wp_safe_redirect( RSP_Settings::page_url( 'login', array( 'rsp_msg' => $ok ? 'verified' : 'verify_failed' ) ) );
		exit;
	}

	/**
	 * Point WP's reset email at the branded reset page when one is assigned.
	 *
	 * @param array   $email Email defaults.
	 * @param string  $key Key.
	 * @param string  $login Login.
	 * @param WP_User $user User.
	 * @return array
	 */
	public static function reset_email_link( $email, $key, $login, $user ) {
		$pages = (array) get_option( 'rsp_pages', array() );
		if ( empty( $pages['reset'] ) ) {
			return $email;
		}
		$wp_link        = network_site_url( "wp-login.php?action=rp&key=$key&login=" . rawurlencode( $login ), 'login' );
		$our_link       = add_query_arg( array( 'key' => $key, 'login' => rawurlencode( $login ) ), get_permalink( (int) $pages['reset'] ) );
		$email['message'] = str_replace( $wp_link, $our_link, $email['message'] );
		return $email;
	}

	/**
	 * Request a password reset (always reports success to avoid account enumeration).
	 *
	 * @param string $login Email or username.
	 * @return true|WP_Error
	 */
	public static function request_reset( $login ) {
		if ( ! RSP_Rate_Limiter::hit( 'lostpw', 5, HOUR_IN_SECONDS ) ) {
			return new WP_Error( 'rsp_rate', __( 'Too many attempts. Please try again later.', 'relay-stream-portal' ) );
		}
		$login = sanitize_text_field( $login );
		if ( '' !== $login ) {
			retrieve_password( $login );
		}
		return true;
	}

	/**
	 * Complete a reset.
	 *
	 * @param string $key Key.
	 * @param string $login Login.
	 * @param string $pass New password.
	 * @return true|WP_Error
	 */
	public static function complete_reset( $key, $login, $pass ) {
		$user = check_password_reset_key( $key, $login );
		if ( is_wp_error( $user ) ) {
			return new WP_Error( 'rsp_key', __( 'This reset link is invalid or has expired. Request a new one.', 'relay-stream-portal' ) );
		}
		if ( strlen( $pass ) < 8 ) {
			return new WP_Error( 'rsp_pass', __( 'Use at least 8 characters for your password.', 'relay-stream-portal' ) );
		}
		reset_password( $user, $pass );
		return true;
	}

	/**
	 * Update profile.
	 *
	 * @param int   $user_id User.
	 * @param array $data Data.
	 * @return true|WP_Error
	 */
	public static function update_profile( $user_id, array $data ) {
		$user = get_userdata( $user_id );
		$upd  = array( 'ID' => $user_id );
		foreach ( array( 'first_name', 'last_name', 'display_name' ) as $f ) {
			if ( isset( $data[ $f ] ) ) {
				$upd[ $f ] = substr( sanitize_text_field( $data[ $f ] ), 0, 80 );
			}
		}
		$email   = isset( $data['email'] ) ? sanitize_email( $data['email'] ) : $user->user_email;
		$newpass = isset( $data['new_password'] ) ? (string) $data['new_password'] : '';
		if ( strtolower( $email ) !== strtolower( $user->user_email ) || '' !== $newpass ) {
			if ( empty( $data['current_password'] ) || ! wp_check_password( (string) $data['current_password'], $user->user_pass, $user_id ) ) {
				return new WP_Error( 'rsp_pass', __( 'Enter your current password to change your email or password.', 'relay-stream-portal' ) );
			}
			if ( strtolower( $email ) !== strtolower( $user->user_email ) ) {
				if ( ! is_email( $email ) || email_exists( $email ) ) {
					return new WP_Error( 'rsp_email', __( 'That email address cannot be used.', 'relay-stream-portal' ) );
				}
				$upd['user_email'] = $email;
			}
			if ( '' !== $newpass ) {
				if ( strlen( $newpass ) < 8 ) {
					return new WP_Error( 'rsp_pass', __( 'Use at least 8 characters for your password.', 'relay-stream-portal' ) );
				}
				$upd['user_pass'] = $newpass;
			}
		}
		$r = wp_update_user( $upd );
		if ( is_wp_error( $r ) ) {
			return new WP_Error( 'rsp_profile', __( 'Could not save your profile.', 'relay-stream-portal' ) );
		}
		update_user_meta( $user_id, 'rsp_email_opt_in', empty( $data['email_opt_in'] ) ? '0' : '1' );
		if ( isset( $upd['user_pass'] ) ) {
			wp_set_auth_cookie( $user_id, true, is_ssl() );
		}
		return true;
	}
}
