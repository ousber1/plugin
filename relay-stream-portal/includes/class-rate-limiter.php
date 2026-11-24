<?php
/**
 * Simple fixed-window rate limiter backed by transients.
 *
 * @package RelayStreamPortal
 */

defined( 'ABSPATH' ) || exit;

/**
 * Rate limiter.
 */
class RSP_Rate_Limiter {

	/**
	 * Register a hit and return whether it is allowed.
	 *
	 * @param string $bucket Bucket name (e.g. "login").
	 * @param int    $limit Max hits per window.
	 * @param int    $window Window seconds.
	 * @param string $subject Optional subject; defaults to user id or IP.
	 * @return bool
	 */
	public static function hit( $bucket, $limit, $window, $subject = '' ) {
		if ( '' === $subject ) {
			$subject = is_user_logged_in() ? 'u' . get_current_user_id() : 'ip' . self::ip();
		}
		$key   = 'rsp_rl_' . md5( $bucket . '|' . $subject );
		$count = (int) get_transient( $key );
		if ( $count >= $limit ) {
			return false;
		}
		set_transient( $key, $count + 1, $window );
		return true;
	}

	/**
	 * Client IP (REMOTE_ADDR only; proxies must be configured at server level).
	 *
	 * @return string
	 */
	public static function ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0.0.0.0';
		return (string) apply_filters( 'rsp_client_ip', $ip );
	}
}
