<?php
/**
 * Audit log. Context is scrubbed of anything that looks like a secret.
 *
 * @package RelayStreamPortal
 */

defined( 'ABSPATH' ) || exit;

/**
 * Audit.
 */
class RSP_Audit {

	const SECRET_KEYS = array( 'password', 'pass', 'api_key', 'apikey', 'key', 'secret', 'token', 'code', 'url', 'm3u', 'credentials', 'username', 'mac' );

	/**
	 * Record an action.
	 *
	 * @param string     $action Action slug.
	 * @param string     $target_type Target type.
	 * @param string|int $target_id Target id.
	 * @param string     $reason Free text reason supplied by staff.
	 * @param array      $context Extra non-secret context.
	 * @param int|null   $actor Actor (defaults to current user; 0 = system).
	 * @return int Insert id.
	 */
	public static function log( $action, $target_type = '', $target_id = '', $reason = '', array $context = array(), $actor = null ) {
		global $wpdb;
		$wpdb->insert(
			RSP_Installer::table( 'audit' ),
			array(
				'actor_id'    => null === $actor ? get_current_user_id() : (int) $actor,
				'action'      => substr( sanitize_key( $action ), 0, 64 ),
				'target_type' => substr( sanitize_key( $target_type ), 0, 32 ),
				'target_id'   => substr( (string) $target_id, 0, 64 ),
				'reason'      => substr( sanitize_textarea_field( (string) $reason ), 0, 500 ),
				'context'     => wp_json_encode( self::scrub( $context ) ),
				'created_at'  => RSP_Installer::now(),
			)
		);
		return (int) $wpdb->insert_id;
	}

	/**
	 * Remove secret-looking keys recursively.
	 *
	 * @param array $data Data.
	 * @return array
	 */
	public static function scrub( array $data ) {
		foreach ( $data as $k => $v ) {
			if ( is_string( $k ) && in_array( strtolower( $k ), self::SECRET_KEYS, true ) ) {
				$data[ $k ] = '[redacted]';
			} elseif ( is_array( $v ) ) {
				$data[ $k ] = self::scrub( $v );
			} elseif ( is_string( $v ) ) {
				$data[ $k ] = self::redact_string( $v );
			}
		}
		return $data;
	}

	/**
	 * Redact secrets embedded in strings (query strings, URLs with credentials).
	 *
	 * @param string $s String.
	 * @return string
	 */
	public static function redact_string( $s ) {
		$s = preg_replace( '/((?:api_key|password|username|pass|mac|key|token)=)[^&\s"\']+/i', '$1[redacted]', (string) $s );
		$s = preg_replace( '#(https?://)[^/\s:@]+:[^/\s@]+@#i', '$1[redacted]@', $s );
		return $s;
	}
}
