<?php
/**
 * FIXTURE: an in-memory stand-in for the TVPlus Panel API used ONLY by the test suite.
 * Response shapes follow the documented examples (JSON array wrapping one object,
 * status "true", renew responses using the documented "messasge" key, bouquet as a list).
 * It is NOT a verified copy of the real panel's behaviour.
 *
 * @package RelayStreamPortal\Tests
 */

defined( 'ABSPATH' ) || exit;

/**
 * Fake panel transport.
 */
class RSP_Fake_Panel {

	/** @var array username => array(password, expire, user_id, enabled) */
	public $lines = array();
	/** @var array Request log (params without api_key). */
	public $calls = array();
	/** @var array Queue of forced behaviours: 'timeout_after_apply', 'timeout_before_apply', 'http500', 'redirect', 'garbage', 'reject'. */
	public $next = array();
	/** @var string */
	public $expected_key = 'TEST-KEY-123';
	/** @var int */
	private $seq = 5000;

	/**
	 * Make the transport callable.
	 *
	 * @return callable
	 */
	public function transport() {
		return function ( $url, $args ) {
			return $this->handle( $url, $args );
		};
	}

	/**
	 * JSON response.
	 *
	 * @param mixed $body Body.
	 * @param int   $code Code.
	 * @return array
	 */
	private function json( $body, $code = 200 ) {
		return array( 'headers' => array(), 'body' => wp_json_encode( $body ), 'response' => array( 'code' => $code, 'message' => 'OK' ), 'cookies' => array() );
	}

	/**
	 * Handle a request.
	 *
	 * @param string $url URL.
	 * @param array  $args Args.
	 * @return array|WP_Error
	 */
	public function handle( $url, $args ) {
		$q = array();
		parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $q );
		$key = isset( $q['api_key'] ) ? $q['api_key'] : '';
		unset( $q['api_key'] );
		$this->calls[] = $q;
		$mode = array_shift( $this->next );

		if ( 'connect_fail' === $mode ) {
			return new WP_Error( 'http_request_failed', 'cURL error 7: Failed to connect to host' );
		}
		if ( 'timeout_before_apply' === $mode ) {
			return new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out after 20001 milliseconds' );
		}
		if ( 'http500' === $mode ) {
			return $this->json( array( 'error' => 'server' ), 500 );
		}
		if ( 'redirect' === $mode ) {
			return array( 'headers' => array( 'location' => 'https://evil.example/' ), 'body' => '', 'response' => array( 'code' => 302, 'message' => 'Found' ), 'cookies' => array() );
		}
		if ( 'garbage' === $mode ) {
			return array( 'headers' => array(), 'body' => '<html>oops</html>', 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array() );
		}
		if ( $key !== $this->expected_key || 'reject' === $mode ) {
			return $this->json( array( array( 'status' => 'false', 'message' => 'Invalid request' ) ) );
		}

		$action = isset( $q['action'] ) ? $q['action'] : '';
		$result = null;
		switch ( $action ) {
			case 'reseller_info':
				$result = $this->json( array( array( 'status' => 'true', 'username' => 'reseller_demo', 'enabled' => '1', 'credits' => '50' ) ) );
				break;
			case 'bouquet':
				$result = $this->json( array( array( 'id' => '132', 'name' => 'SAMPLE - SMALL' ), array( 'id' => '152', 'name' => 'SAMPLE - FAMILY' ) ) );
				break;
			case 'new':
				$uid  = (string) ++$this->seq;
				$user = 'u' . $uid;
				$pass = 'p' . wp_generate_password( 8, false );
				$this->lines[ $user ] = array( 'password' => $pass, 'expire' => gmdate( 'Y-m-d', strtotime( '+' . (int) $q['sub'] . ' months' ) ), 'user_id' => $uid, 'enabled' => '1', 'note' => isset( $q['note'] ) ? $q['note'] : '' );
				$result = $this->json( array( array( 'status' => 'true', 'user_id' => $uid, 'message' => 'Add M3U successful', 'url' => 'http://line.example.test:8080/get.php?username=' . $user . '&password=' . $pass . '&type=m3u_plus&output=ts' ) ) );
				break;
			case 'renew':
				$u = isset( $q['username'] ) ? $q['username'] : '';
				if ( ! isset( $this->lines[ $u ] ) || $this->lines[ $u ]['password'] !== ( isset( $q['password'] ) ? $q['password'] : '' ) ) {
					$result = $this->json( array( array( 'status' => 'false', 'messasge' => 'Line not found' ) ) );
					break;
				}
				$base = max( strtotime( $this->lines[ $u ]['expire'] ), time() );
				$this->lines[ $u ]['expire'] = gmdate( 'Y-m-d', strtotime( '+' . (int) $q['sub'] . ' months', $base ) );
				$result = $this->json( array( array( 'status' => 'true', 'messasge' => 'M3U renew successful' ) ) );
				break;
			case 'device_info':
				$u = isset( $q['username'] ) ? $q['username'] : '';
				if ( ! isset( $this->lines[ $u ] ) || $this->lines[ $u ]['password'] !== ( isset( $q['password'] ) ? $q['password'] : '' ) ) {
					$result = $this->json( array( array( 'status' => 'false', 'message' => 'Not found' ) ) );
					break;
				}
				$l      = $this->lines[ $u ];
				$result = $this->json( array( array( 'status' => 'true', 'username' => $u, 'password' => $l['password'], 'expire' => $l['expire'], 'country' => 'NL', 'user_id' => $l['user_id'], 'note' => $l['note'], 'url' => 'http://line.example.test:8080/get.php', 'enabled' => $l['enabled'] ) ) );
				break;
			default:
				$result = $this->json( array( array( 'status' => 'false', 'message' => 'Unknown action' ) ) );
		}
		if ( 'timeout_after_apply' === $mode ) {
			// The panel applied the change but the response was lost.
			return new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out after 20001 milliseconds with 0 bytes received' );
		}
		return $result;
	}

	/**
	 * Count create calls.
	 *
	 * @return int
	 */
	public function creates() {
		return count( array_filter( $this->calls, static function ( $c ) { return isset( $c['action'] ) && 'new' === $c['action']; } ) );
	}
}
