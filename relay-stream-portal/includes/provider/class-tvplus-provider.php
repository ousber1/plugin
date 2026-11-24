<?php
/**
 * TVPlus Panel API adapter (documentation Ver 1.81, last updated 13/08/2024).
 *
 * Mapping (see docs/API-MAPPING.md for the full table):
 *   All operations: HTTP GET {base}/api.php?action=...&api_key=...  (query-string auth only)
 *   reseller_info  -> test connection (read-only)
 *   bouquet        -> list packages (read-only)
 *   new  type=m3u  -> create (sub=1|3|6|12, pack=<id>|all, note)
 *   renew type=m3u -> renew by username+password (sub=1|3|6|12)
 *   new/renew type=mag -> same with mac
 *   device_info    -> lookup by username+password or mac (expire YYYY-MM-DD, enabled)
 * Not documented: suspend/enable/disable/delete, device limits, arbitrary-day extension,
 * idempotency keys, error bodies, rate limits. The adapter never sends anything else.
 *
 * Because api_key travels in the URL, request URLs are never logged or stored.
 *
 * @package RelayStreamPortal
 */

defined( 'ABSPATH' ) || exit;

/**
 * TVPlus adapter.
 */
class RSP_Tvplus_Provider implements RSP_Provider_Interface {

	const ALLOWED_MONTHS = array( 1, 3, 6, 12 );

	/** @var string */
	private $base_url;
	/** @var string */
	private $api_key;
	/** @var string[] */
	private $allowed_hosts;
	/** @var int */
	private $timeout;
	/** @var callable */
	private $transport;

	/**
	 * Constructor.
	 *
	 * @param string        $base_url API endpoint URL (…/api.php).
	 * @param string        $api_key Decrypted key.
	 * @param string[]      $allowed_hosts Host allowlist.
	 * @param int           $timeout Seconds.
	 * @param callable|null $transport function( string $url, array $args ): array|WP_Error. Defaults to wp_safe_remote_get.
	 */
	public function __construct( $base_url, $api_key, array $allowed_hosts, $timeout = 20, $transport = null ) {
		$this->base_url      = trim( (string) $base_url );
		$this->api_key       = (string) $api_key;
		$this->allowed_hosts = array_filter( array_map( 'strtolower', array_map( 'trim', $allowed_hosts ) ) );
		$this->timeout       = max( 5, min( 60, (int) $timeout ) );
		$this->transport     = $transport ? $transport : 'wp_safe_remote_get';
	}

	/** {@inheritdoc} */
	public function slug() {
		return 'tvplus';
	}

	/** {@inheritdoc} */
	public function capabilities() {
		return array(
			'test_connection' => array( 'supported' => true, 'note' => 'action=reseller_info (read-only)' ),
			'list_packages'   => array( 'supported' => true, 'note' => 'action=bouquet returns id + name only' ),
			'create'          => array( 'supported' => true, 'note' => 'action=new&type=m3u|mag; sub limited to 1, 3, 6 or 12 months' ),
			'renew'           => array( 'supported' => true, 'note' => 'action=renew; identified by username+password (M3U) or mac (MAG)' ),
			'lookup'          => array( 'supported' => true, 'note' => 'action=device_info returns expire (YYYY-MM-DD) and enabled' ),
			'expiry'          => array( 'supported' => true, 'note' => 'Read via device_info after create/renew' ),
			'packages'        => array( 'supported' => true, 'note' => 'pack=<bouquet id> or all' ),
			'device_limits'   => array( 'supported' => false, 'note' => 'Not documented; set manually per account if needed' ),
			'suspend'         => array( 'supported' => false, 'note' => 'No enable/disable/delete operation documented' ),
			'extend_days'     => array( 'supported' => false, 'note' => 'Only whole-month renewals; arbitrary-day rewards go to staff review' ),
			'idempotency'     => array( 'supported' => false, 'note' => 'No idempotency key; timed-out creates need staff review' ),
			'create_lookup'   => array( 'supported' => false, 'note' => 'No lookup by note; a create whose response is lost cannot be reconciled automatically' ),
		);
	}

	/**
	 * Validate the endpoint before any request.
	 *
	 * @return true|string True or error message.
	 */
	public function validate_endpoint() {
		$p = wp_parse_url( $this->base_url );
		if ( empty( $p['scheme'] ) || 'https' !== strtolower( $p['scheme'] ) ) {
			return 'API base URL must use HTTPS.';
		}
		if ( empty( $p['host'] ) || isset( $p['user'] ) || isset( $p['pass'] ) || isset( $p['query'] ) || isset( $p['fragment'] ) ) {
			return 'API base URL must be a plain https://host/path URL without credentials, query or fragment.';
		}
		if ( ! in_array( strtolower( $p['host'] ), $this->allowed_hosts, true ) ) {
			return 'API host is not in the allowed hosts list.';
		}
		if ( '' === $this->api_key ) {
			return 'API key is not configured.';
		}
		return true;
	}

	/**
	 * Perform a GET call.
	 *
	 * @param array $params Query params (without api_key).
	 * @param bool  $is_write Whether the call changes remote state.
	 * @return RSP_Provider_Result
	 */
	private function call( array $params, $is_write ) {
		$valid = $this->validate_endpoint();
		if ( true !== $valid ) {
			return new RSP_Provider_Result( 'failed', array(), $valid );
		}
		$params['api_key'] = $this->api_key;
		$url               = $this->base_url . '?' . http_build_query( $params, '', '&', PHP_QUERY_RFC3986 );

		$response = call_user_func(
			$this->transport,
			$url,
			array(
				'timeout'             => $this->timeout,
				'redirection'         => 0,
				'sslverify'           => true,
				'reject_unsafe_urls'  => true,
				'limit_response_size' => 1048576,
				'user-agent'          => 'RelayStreamPortal/' . RSP_VERSION,
				'headers'             => array( 'Accept' => 'application/json' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			$msg = $response->get_error_message();
			// cURL could not resolve or connect: the request never reached the panel.
			if ( preg_match( '/cURL error (6|7|35|60):/i', $msg ) || in_array( $response->get_error_message(), array( 'A valid URL was not provided.', 'User has blocked requests through HTTP.' ), true ) ) {
				return new RSP_Provider_Result( 'failed', array( 'transport' => 'not_sent' ), 'Connection failed before the request was sent: ' . $msg );
			}
			return new RSP_Provider_Result( $is_write ? 'ambiguous' : 'failed', array( 'transport' => 'error' ), 'Transport error: ' . $msg );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = (string) wp_remote_retrieve_body( $response );

		if ( $code >= 300 && $code < 400 ) {
			return new RSP_Provider_Result( $is_write ? 'ambiguous' : 'failed', array(), 'Provider attempted a redirect; redirects are not followed.', $code );
		}
		if ( 200 !== $code ) {
			return new RSP_Provider_Result( $is_write ? 'ambiguous' : 'failed', array(), 'Unexpected HTTP status ' . $code, $code );
		}

		$json = json_decode( $body, true );
		if ( ! is_array( $json ) ) {
			return new RSP_Provider_Result( $is_write ? 'ambiguous' : 'failed', array(), 'Response was not JSON.', $code );
		}
		// Documented responses are a JSON array wrapping one object (except bouquet, a list).
		$first = ( isset( $json[0] ) && is_array( $json[0] ) ) ? $json[0] : $json;
		$data  = array( 'raw_list' => isset( $json[0] ) ? $json : array( $json ) );
		foreach ( $first as $k => $v ) {
			if ( is_scalar( $v ) || null === $v ) {
				$data[ $k ] = $v;
			}
		}
		// The docs misspell "message" as "messasge" in renew responses.
		if ( ! isset( $data['message'] ) && isset( $data['messasge'] ) ) {
			$data['message'] = $data['messasge'];
		}

		$status = isset( $first['status'] ) ? strtolower( (string) $first['status'] ) : '';
		if ( 'true' === $status || true === ( isset( $first['status'] ) ? $first['status'] : null ) ) {
			return new RSP_Provider_Result( 'success', $data, '', $code );
		}
		$err = isset( $data['message'] ) ? (string) $data['message'] : ( isset( $data['error'] ) ? (string) $data['error'] : 'Provider did not report success.' );
		// An explicit non-true status is a provider rejection; the operation was not applied.
		if ( '' !== $status ) {
			return new RSP_Provider_Result( 'failed', $data, $err, $code );
		}
		return new RSP_Provider_Result( $is_write ? 'ambiguous' : 'failed', $data, 'Response had no status field.', $code );
	}

	/** {@inheritdoc} */
	public function test_connection() {
		$r = $this->call( array( 'action' => 'reseller_info' ), false );
		if ( $r->ok() ) {
			$r->data = array(
				'status'   => 'true',
				'username' => isset( $r->data['username'] ) ? RSP_Crypto::mask( $r->data['username'] ) : '',
				'enabled'  => isset( $r->data['enabled'] ) ? (string) $r->data['enabled'] : '',
				'credits'  => isset( $r->data['credits'] ) ? (string) $r->data['credits'] : '',
			);
		}
		return $r;
	}

	/** {@inheritdoc} */
	public function list_packages() {
		$r = $this->call( array( 'action' => 'bouquet' ), false );
		// The bouquet response is a list of {id,name} without a status field.
		if ( ! $r->ok() && ! empty( $r->data['raw_list'] ) ) {
			$packages = array();
			foreach ( $r->data['raw_list'] as $row ) {
				if ( is_array( $row ) && isset( $row['id'], $row['name'] ) ) {
					$packages[ (string) $row['id'] ] = sanitize_text_field( (string) $row['name'] );
				}
			}
			if ( $packages ) {
				return new RSP_Provider_Result( 'success', array( 'packages' => $packages, 'count' => count( $packages ) ), '', $r->http_code );
			}
		}
		if ( $r->ok() ) {
			$packages = array();
			foreach ( $r->data['raw_list'] as $row ) {
				if ( is_array( $row ) && isset( $row['id'], $row['name'] ) ) {
					$packages[ (string) $row['id'] ] = sanitize_text_field( (string) $row['name'] );
				}
			}
			$r->data = array( 'packages' => $packages, 'count' => count( $packages ) );
		}
		return $r;
	}

	/** {@inheritdoc} */
	public function create_account( array $args ) {
		$months = (int) $args['months'];
		if ( ! in_array( $months, self::ALLOWED_MONTHS, true ) ) {
			return new RSP_Provider_Result( 'failed', array(), 'Duration must be 1, 3, 6 or 12 months for this provider.' );
		}
		$type   = isset( $args['type'] ) && 'mag' === $args['type'] ? 'mag' : 'm3u';
		$params = array(
			'action' => 'new',
			'type'   => $type,
			'sub'    => $months,
			'pack'   => ( isset( $args['package'] ) && '' !== (string) $args['package'] ) ? (string) $args['package'] : 'all',
		);
		if ( 'mag' === $type ) {
			if ( empty( $args['mac'] ) || ! preg_match( '/^([0-9A-F]{2}:){5}[0-9A-F]{2}$/i', $args['mac'] ) ) {
				return new RSP_Provider_Result( 'failed', array(), 'A valid MAC address is required for MAG accounts.' );
			}
			$params['mac'] = strtoupper( $args['mac'] );
		}
		if ( ! empty( $args['note'] ) ) {
			$params['note'] = substr( preg_replace( '/[^A-Za-z0-9 _:.-]/', '', (string) $args['note'] ), 0, 64 );
		}
		$r = $this->call( $params, true );
		if ( $r->ok() ) {
			$creds = self::parse_m3u_url( isset( $r->data['url'] ) ? (string) $r->data['url'] : '' );
			if ( 'mag' === $type ) {
				$creds['mac'] = $params['mac'];
			}
			if ( empty( $r->data['user_id'] ) || ( 'm3u' === $type && ( empty( $creds['username'] ) || empty( $creds['password'] ) ) ) ) {
				// Provider said success but the identifiers we need are missing: account likely exists remotely.
				return new RSP_Provider_Result( 'ambiguous', array( 'user_id' => isset( $r->data['user_id'] ) ? $r->data['user_id'] : '' ), 'Create reported success but identifiers were missing.', $r->http_code );
			}
			$r->data = array(
				'status'      => 'true',
				'user_id'     => (string) $r->data['user_id'],
				'message'     => isset( $r->data['message'] ) ? (string) $r->data['message'] : '',
				'credentials' => $creds,
			);
		}
		return $r;
	}

	/** {@inheritdoc} */
	public function renew_account( array $creds, $months, $type ) {
		$months = (int) $months;
		if ( ! in_array( $months, self::ALLOWED_MONTHS, true ) ) {
			return new RSP_Provider_Result( 'failed', array(), 'Duration must be 1, 3, 6 or 12 months for this provider.' );
		}
		$params = array( 'action' => 'renew', 'type' => 'mag' === $type ? 'mag' : 'm3u', 'sub' => $months );
		if ( 'mag' === $type ) {
			if ( empty( $creds['mac'] ) ) {
				return new RSP_Provider_Result( 'failed', array(), 'Missing MAC for renewal.' );
			}
			$params['mac'] = $creds['mac'];
		} else {
			if ( empty( $creds['username'] ) || empty( $creds['password'] ) ) {
				return new RSP_Provider_Result( 'failed', array(), 'Missing stored username/password for renewal.' );
			}
			$params['username'] = $creds['username'];
			$params['password'] = $creds['password'];
		}
		$r = $this->call( $params, true );
		if ( $r->ok() ) {
			$r->data = array( 'status' => 'true', 'message' => isset( $r->data['message'] ) ? (string) $r->data['message'] : '' );
		}
		return $r;
	}

	/** {@inheritdoc} */
	public function lookup_account( array $creds, $type ) {
		$params = array( 'action' => 'device_info' );
		if ( 'mag' === $type ) {
			if ( empty( $creds['mac'] ) ) {
				return new RSP_Provider_Result( 'failed', array(), 'Missing MAC for lookup.' );
			}
			$params['mac'] = $creds['mac'];
		} else {
			if ( empty( $creds['username'] ) || empty( $creds['password'] ) ) {
				return new RSP_Provider_Result( 'failed', array(), 'Missing username/password for lookup.' );
			}
			$params['username'] = $creds['username'];
			$params['password'] = $creds['password'];
		}
		$r = $this->call( $params, false );
		if ( $r->ok() ) {
			$expire = isset( $r->data['expire'] ) ? (string) $r->data['expire'] : '';
			if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}/', $expire ) ) {
				return new RSP_Provider_Result( 'failed', array(), 'Lookup returned no valid expiry date.', $r->http_code );
			}
			$r->data = array(
				'status'  => 'true',
				'user_id' => isset( $r->data['user_id'] ) ? (string) $r->data['user_id'] : '',
				'expire'  => substr( $expire, 0, 10 ),
				'enabled' => isset( $r->data['enabled'] ) ? (string) $r->data['enabled'] : '',
				'url'     => isset( $r->data['url'] ) ? (string) $r->data['url'] : '',
			);
		}
		return $r;
	}

	/**
	 * Extract username/password/server from the returned M3U URL (get.php?username=..&password=..).
	 *
	 * @param string $url URL.
	 * @return array
	 */
	public static function parse_m3u_url( $url ) {
		$out = array( 'url' => $url );
		$p   = wp_parse_url( $url );
		if ( ! empty( $p['query'] ) ) {
			parse_str( $p['query'], $q );
			if ( isset( $q['username'] ) ) {
				$out['username'] = (string) $q['username'];
			}
			if ( isset( $q['password'] ) ) {
				$out['password'] = (string) $q['password'];
			}
		}
		if ( ! empty( $p['host'] ) ) {
			$out['server'] = ( isset( $p['scheme'] ) ? $p['scheme'] : 'http' ) . '://' . $p['host'] . ( isset( $p['port'] ) ? ':' . $p['port'] : '' );
		}
		return $out;
	}
}
