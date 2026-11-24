<?php
/**
 * Streaming accounts repository. A WordPress user can own several accounts.
 *
 * @package RelayStreamPortal
 */

defined( 'ABSPATH' ) || exit;

/**
 * Accounts.
 */
class RSP_Accounts {

	const STATUSES = array( 'pending_payment', 'pending_activation', 'active', 'expired', 'suspended', 'review', 'cancelled' );

	/**
	 * Human label for a status.
	 *
	 * @param string $status Status.
	 * @return string
	 */
	public static function status_label( $status ) {
		$labels = array(
			'pending_payment'    => __( 'Awaiting payment', 'relay-stream-portal' ),
			'pending_activation' => __( 'Activating', 'relay-stream-portal' ),
			'active'             => __( 'Active', 'relay-stream-portal' ),
			'expired'            => __( 'Expired', 'relay-stream-portal' ),
			'suspended'          => __( 'Suspended', 'relay-stream-portal' ),
			'review'             => __( 'Under review', 'relay-stream-portal' ),
			'cancelled'          => __( 'Cancelled', 'relay-stream-portal' ),
		);
		return isset( $labels[ $status ] ) ? $labels[ $status ] : ucfirst( (string) $status );
	}

	/**
	 * Table name.
	 *
	 * @return string
	 */
	private static function t() {
		return RSP_Installer::table( 'accounts' );
	}

	/**
	 * Create an account row.
	 *
	 * @param array $data Data.
	 * @return object|null
	 */
	public static function create( array $data ) {
		global $wpdb;
		$now  = RSP_Installer::now();
		$uuid = RSP_Installer::uuid();
		$row  = array(
			'uuid'            => $uuid,
			'user_id'         => (int) $data['user_id'],
			'nickname'        => isset( $data['nickname'] ) ? substr( sanitize_text_field( $data['nickname'] ), 0, 80 ) : '',
			'provider'        => isset( $data['provider'] ) ? $data['provider'] : RSP_Provider_Factory::active_slug(),
			'provider_type'   => isset( $data['provider_type'] ) ? $data['provider_type'] : 'm3u',
			'status'          => isset( $data['status'] ) ? $data['status'] : 'pending_payment',
			'plan_label'      => isset( $data['plan_label'] ) ? substr( $data['plan_label'], 0, 120 ) : '',
			'package_id'      => isset( $data['package_id'] ) ? substr( (string) $data['package_id'], 0, 32 ) : '',
			'source_order_id' => isset( $data['source_order_id'] ) ? (int) $data['source_order_id'] : null,
			'created_at'      => $now,
			'updated_at'      => $now,
		);
		if ( '' === $row['nickname'] ) {
			/* translators: %d: account number */
			$row['nickname'] = sprintf( __( 'Account %d', 'relay-stream-portal' ), self::count_for_user( $row['user_id'] ) + 1 );
		}
		$wpdb->insert( self::t(), $row );
		return self::get_by_uuid( $uuid );
	}

	/**
	 * Get by id.
	 *
	 * @param int $id Id.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::t() . ' WHERE id = %d', $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Get by uuid.
	 *
	 * @param string $uuid UUID.
	 * @return object|null
	 */
	public static function get_by_uuid( $uuid ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::t() . ' WHERE uuid = %s', $uuid ) ); // phpcs:ignore
	}

	/**
	 * Get an account only when owned by the user. This is the ownership gate.
	 *
	 * @param string $uuid UUID.
	 * @param int    $user_id User id.
	 * @return object|null
	 */
	public static function get_owned( $uuid, $user_id ) {
		global $wpdb;
		if ( ! $user_id || ! is_string( $uuid ) || ! preg_match( '/^[0-9a-f-]{36}$/', $uuid ) ) {
			return null;
		}
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::t() . ' WHERE uuid = %s AND user_id = %d', $uuid, $user_id ) ); // phpcs:ignore
	}

	/**
	 * Accounts for a user (excludes cancelled).
	 *
	 * @param int $user_id User.
	 * @return object[]
	 */
	public static function for_user( $user_id ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::t() . " WHERE user_id = %d AND status <> 'cancelled' ORDER BY created_at ASC", $user_id ) ); // phpcs:ignore
		return array_map( array( __CLASS__, 'refresh_expiry_status' ), $rows ? $rows : array() );
	}

	/**
	 * Count accounts for a user.
	 *
	 * @param int $user_id User.
	 * @return int
	 */
	public static function count_for_user( $user_id ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::t() . " WHERE user_id = %d AND status <> 'cancelled'", $user_id ) ); // phpcs:ignore
	}

	/**
	 * Mark active accounts past expiry as expired (lazy).
	 *
	 * @param object $row Row.
	 * @return object
	 */
	public static function refresh_expiry_status( $row ) {
		if ( $row && 'active' === $row->status && $row->expires_at && strtotime( $row->expires_at . ' UTC' ) < time() ) {
			self::update( (int) $row->id, array( 'status' => 'expired' ) );
			$row->status = 'expired';
		}
		return $row;
	}

	/**
	 * Update fields.
	 *
	 * @param int   $id Id.
	 * @param array $data Data.
	 * @return bool
	 */
	public static function update( $id, array $data ) {
		global $wpdb;
		$data['updated_at'] = RSP_Installer::now();
		return false !== $wpdb->update( self::t(), $data, array( 'id' => (int) $id ) );
	}

	/**
	 * Store provider credentials encrypted.
	 *
	 * @param int   $id Account id.
	 * @param array $creds username,password,mac,url,server,code.
	 */
	public static function set_credentials( $id, array $creds ) {
		$clean = array();
		foreach ( array( 'username', 'password', 'mac', 'url', 'server', 'code' ) as $k ) {
			if ( isset( $creds[ $k ] ) && '' !== (string) $creds[ $k ] ) {
				$clean[ $k ] = (string) $creds[ $k ];
			}
		}
		self::update( $id, array( 'credentials' => $clean ? RSP_Crypto::encrypt_array( $clean ) : '' ) );
	}

	/**
	 * Decrypt credentials.
	 *
	 * @param object $account Row.
	 * @return array
	 */
	public static function credentials( $account ) {
		$c = $account && $account->credentials ? RSP_Crypto::decrypt_array( $account->credentials ) : array();
		if ( empty( $c['server'] ) && ! empty( $c['url'] ) ) {
			$p = wp_parse_url( $c['url'] );
			if ( ! empty( $p['host'] ) ) {
				$c['server'] = ( isset( $p['scheme'] ) ? $p['scheme'] : 'http' ) . '://' . $p['host'] . ( isset( $p['port'] ) ? ':' . $p['port'] : '' );
			}
		}
		return $c;
	}

	/**
	 * Acquire a per-account lock. Returns token or false.
	 *
	 * @param int $id Account id.
	 * @param int $ttl Seconds.
	 * @return string|false
	 */
	public static function lock( $id, $ttl = 300 ) {
		global $wpdb;
		$token = bin2hex( random_bytes( 16 ) );
		$n     = $wpdb->query( $wpdb->prepare( 'UPDATE ' . self::t() . ' SET lock_token = %s, lock_expires = %d WHERE id = %d AND (lock_token IS NULL OR lock_expires < %d)', $token, time() + $ttl, $id, time() ) ); // phpcs:ignore
		return 1 === (int) $n ? $token : false;
	}

	/**
	 * Release a lock held with token.
	 *
	 * @param int    $id Id.
	 * @param string $token Token.
	 */
	public static function unlock( $id, $token ) {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . self::t() . ' SET lock_token = NULL, lock_expires = NULL WHERE id = %d AND lock_token = %s', $id, $token ) ); // phpcs:ignore
	}

	/**
	 * Accounts eligible for renewal / reward application.
	 *
	 * @param object $account Account.
	 * @return bool
	 */
	public static function is_renewable( $account ) {
		return $account && in_array( $account->status, array( 'active', 'expired' ), true );
	}

	/**
	 * Public-safe representation (no credentials).
	 *
	 * @param object $a Row.
	 * @return array
	 */
	public static function to_public( $a ) {
		return array(
			'uuid'            => $a->uuid,
			'nickname'        => $a->nickname,
			'plan'            => $a->plan_label,
			'status'          => $a->status,
			'status_label'    => self::status_label( $a->status ),
			'activated_at'    => $a->activated_at,
			'expires_at'      => $a->expires_at,
			'max_connections' => null === $a->max_connections ? null : (int) $a->max_connections,
			'has_credentials' => ! empty( $a->credentials ),
		);
	}
}
