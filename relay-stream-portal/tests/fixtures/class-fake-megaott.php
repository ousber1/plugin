<?php
/**
 * FIXTURE: in-memory stand-in for the MegaOTT API v1, shaped after https://megaott.net/docs/
 * (Bearer auth, paginated packages, subscription objects with expiring_at "… GMT+0000",
 * by-username 200/404, extend → new_expiration_date). Test use only; not a verified copy.
 *
 * @package RelayStreamPortal\Tests
 */

defined( 'ABSPATH' ) || exit;

/**
 * Fake MegaOTT.
 */
class RSP_Fake_Megaott {

	/** @var string */
	public $token = 'MEGA-TOKEN-XYZ';
	/** @var array id => subscription */
	public $subs = array();
	/** @var array */
	public $calls = array();
	/** @var array Forced behaviours. */
	public $next = array();
	/** @var int */
	private $seq = 120;

	/**
	 * Transport callable.
	 *
	 * @return callable
	 */
	public function transport() {
		return function ( $url, $args ) {
			return $this->handle( $url, $args );
		};
	}

	/**
	 * Response helper.
	 *
	 * @param int   $code Code.
	 * @param mixed $body Body.
	 * @return array
	 */
	private function r( $code, $body ) {
		return array( 'headers' => array(), 'body' => wp_json_encode( $body ), 'response' => array( 'code' => $code, 'message' => '' ), 'cookies' => array() );
	}

	/**
	 * Count creates.
	 *
	 * @return int
	 */
	public function creates() {
		return count( array_filter( $this->calls, static function ( $c ) { return 'POST /subscriptions' === $c['route']; } ) );
	}

	/**
	 * Handle.
	 *
	 * @param string $url URL.
	 * @param array  $args Args.
	 * @return array|WP_Error
	 */
	public function handle( $url, $args ) {
		$method = isset( $args['method'] ) ? $args['method'] : 'GET';
		$p      = wp_parse_url( $url );
		$path   = substr( $p['path'], strlen( '/api/v1' ) );
		$q      = array();
		parse_str( isset( $p['query'] ) ? $p['query'] : '', $q );
		$body   = isset( $args['body'] ) ? (array) $args['body'] : array();
		$route  = $method . ' ' . preg_replace( '#/\d+#', '/{id}', $path );
		$this->calls[] = array( 'route' => $route, 'q' => $q, 'body' => $body, 'auth' => isset( $args['headers']['Authorization'] ) ? $args['headers']['Authorization'] : '' );
		$mode = array_shift( $this->next );
		if ( 'timeout_before_apply' === $mode ) {
			return new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out' );
		}
		if ( 'http500' === $mode ) {
			return $this->r( 500, array( 'message' => 'Server Error' ) );
		}
		if ( 'Bearer ' . $this->token !== end( $this->calls )['auth'] ) {
			return $this->r( 401, array( 'message' => 'Unauthenticated.' ) );
		}
		$res = null;
		if ( 'GET /user' === $route ) {
			$res = $this->r( 200, array( 'id' => 1, 'username' => 'reseller_demo', 'credit' => 120.5 ) );
		} elseif ( 'GET /packages' === $route ) {
			$all  = array(
				array( 'id' => 4, 'name' => '1 Month', 'credit' => 1, 'extend_credit' => null, 'period' => 1, 'period_type' => 'month', 'max_connections' => 3, 'has_adult' => false, 'is_trial' => false, 'is_paid_trial' => false ),
				array( 'id' => 5, 'name' => '3 Months', 'credit' => 3, 'extend_credit' => null, 'period' => 3, 'period_type' => 'month', 'max_connections' => 3, 'has_adult' => false, 'is_trial' => false, 'is_paid_trial' => false ),
				array( 'id' => 9, 'name' => '24h Trial', 'credit' => 0, 'extend_credit' => null, 'period' => 24, 'period_type' => 'hour', 'max_connections' => 1, 'has_adult' => false, 'is_trial' => true, 'is_paid_trial' => false ),
			);
			$page = isset( $q['page'] ) ? (int) $q['page'] : 1;
			$per  = 2; // Force pagination in the fixture.
			$res  = $this->r( 200, array( 'current_page' => $page, 'data' => array_slice( $all, ( $page - 1 ) * $per, $per ), 'next_page_url' => $page * $per < count( $all ) ? 'https://megaott.net/api/v1/packages?page=' . ( $page + 1 ) : null, 'total' => count( $all ) ) );
		} elseif ( 'POST /subscriptions' === $route ) {
			if ( empty( $body['package_id'] ) || ! in_array( (int) $body['package_id'], array( 4, 5, 9 ), true ) ) {
				$res = $this->r( 422, array( 'message' => 'The given data was invalid.', 'errors' => array( 'package_id' => array( 'invalid' ) ) ) );
			} else {
				foreach ( $this->subs as $s ) {
					if ( 'm3u' === $body['type'] && $s['username'] === $body['username'] ) {
						return $this->r( 422, array( 'message' => 'The username has already been taken.', 'errors' => array( 'username' => array( 'taken' ) ) ) );
					}
				}
				$id                = ++$this->seq;
				$months            = 5 === (int) $body['package_id'] ? 3 : 1;
				$this->subs[ $id ] = array( 'type' => 'M3U', 'id' => $id, 'username' => isset( $body['username'] ) ? $body['username'] : null, 'password' => 'PW' . $id . 'X', 'mac_address' => isset( $body['mac_address'] ) ? $body['mac_address'] : null, 'package' => array( 'id' => (int) $body['package_id'], 'name' => $months . ' month' ), 'template' => null, 'max_connections' => (int) $body['max_connections'], 'forced_country' => $body['forced_country'], 'adult' => (bool) $body['adult'], 'note' => isset( $body['note'] ) ? $body['note'] : '', 'whatsapp_telegram' => $body['whatsapp_telegram'], 'paid' => (bool) $body['paid'], 'expiring_at' => gmdate( 'Y-m-d H:i:s', strtotime( "+$months months" ) ) . ' GMT+0000', 'dns_link' => 'http://line.mega.example.test', 'dns_link_for_samsung_lg' => 'http://smart.mega.example.test', 'portal_link' => null, 'active' => true );
				$res               = $this->r( 200, $this->subs[ $id ] );
			}
		} elseif ( 'GET /subscriptions/by-username' === $route ) {
			$found = array_values( array_filter( $this->subs, static function ( $s ) use ( $q ) { return $s['username'] === $q['username']; } ) );
			$res   = $found ? $this->r( 200, $found[0] ) : $this->r( 404, array( 'status' => false, 'message' => 'Subscription not found with username: ' . $q['username'] ) );
		} elseif ( 'GET /subscriptions/{id}' === $route ) {
			$id  = (int) basename( $path );
			$res = isset( $this->subs[ $id ] ) ? $this->r( 200, $this->subs[ $id ] ) : $this->r( 404, array( 'message' => 'Not found' ) );
		} elseif ( 'POST /subscriptions/{id}/extend' === $route ) {
			$id = (int) explode( '/', trim( $path, '/' ) )[1];
			if ( ! isset( $this->subs[ $id ] ) ) {
				$res = $this->r( 404, array( 'message' => 'Not found' ) );
			} else {
				$months = 5 === (int) $body['package_id'] ? 3 : 1;
				$base   = max( strtotime( str_replace( ' GMT+0000', '', $this->subs[ $id ]['expiring_at'] ) ), time() );
				$new    = gmdate( 'Y-m-d H:i:s', strtotime( "+$months months", $base ) );
				$this->subs[ $id ]['expiring_at'] = $new . ' GMT+0000';
				$res    = $this->r( 200, array( 'status' => true, 'message' => 'Subscription extended successfully', 'new_expiration_date' => $new ) );
			}
		} elseif ( preg_match( '#^POST /subscriptions/\{id\}/(activate|deactivate)$#', $route, $m ) ) {
			$id                         = (int) explode( '/', trim( $path, '/' ) )[1];
			$this->subs[ $id ]['active'] = 'activate' === $m[1];
			$res                        = $this->r( 200, array( 'status' => true, 'message' => 'Subscription ' . $m[1] . 'd successfully' ) );
		} else {
			$res = $this->r( 404, array( 'message' => 'Route not found' ) );
		}
		if ( 'timeout_after_apply' === $mode ) {
			return new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out after 20001 milliseconds' );
		}
		return $res;
	}
}
