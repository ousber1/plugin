<?php
/**
 * MegaOTT Panel API adapter (https://megaott.net/docs/, read 2026-11-24).
 *
 * Mapping (see docs/API-MAPPING.md):
 *   Auth: Authorization: Bearer <token>, Accept: application/json. Base https://megaott.net/api/v1
 *   GET  /user                          -> test connection (read-only): id, username, credit
 *   GET  /packages?page&per_page        -> packages (id, name, period, period_type, max_connections, credit…)
 *   GET  /templates?package_id          -> bouquet templates
 *   POST /subscriptions                 -> create (type, username|mac_address, package_id, max_connections,
 *                                          forced_country, adult, whatsapp_telegram, enable_vpn, paid, template_id?, note?)
 *   GET  /subscriptions/{id}            -> lookup (expiring_at "Y-m-d H:i:s GMT+0000")
 *   GET  /subscriptions/by-username     -> lookup by the username WE chose (200 / 404 / 409) — used to reconcile creates
 *   POST /subscriptions/{id}/extend     -> renew (package_id, paid) -> new_expiration_date
 *   POST /subscriptions/{id}/deactivate -> suspend ; /activate -> resume
 * Not documented: renew body (only "extend" is used), delete, rate limits, idempotency keys.
 *
 * @package RelayStreamPortal
 */

defined( 'ABSPATH' ) || exit;

/**
 * MegaOTT adapter.
 */
class RSP_Megaott_Provider implements RSP_Provider_Interface {

	/** @var string */
	private $base;
	/** @var string */
	private $token;
	/** @var string[] */
	private $hosts;
	/** @var int */
	private $timeout;
	/** @var callable */
	private $transport;
	/** @var array */
	private $defaults;

	/**
	 * Constructor.
	 *
	 * @param string        $base Base URL (…/api/v1).
	 * @param string        $token Bearer token.
	 * @param string[]      $hosts Allowed hosts.
	 * @param int           $timeout Timeout.
	 * @param array         $defaults forced_country, adult, whatsapp_telegram, enable_vpn, max_connections.
	 * @param callable|null $transport function( $url, $args ): array|WP_Error (defaults to wp_safe_remote_request).
	 */
	public function __construct( $base, $token, array $hosts, $timeout = 20, array $defaults = array(), $transport = null ) {
		$this->base      = untrailingslashit( trim( (string) $base ) );
		$this->token     = (string) $token;
		$this->hosts     = array_filter( array_map( 'strtolower', array_map( 'trim', $hosts ) ) );
		$this->timeout   = max( 5, min( 60, (int) $timeout ) );
		$this->transport = $transport ? $transport : 'wp_safe_remote_request';
		$this->defaults  = wp_parse_args( $defaults, array( 'forced_country' => 'ALL', 'adult' => 0, 'whatsapp_telegram' => '', 'enable_vpn' => 0, 'max_connections' => 1 ) );
	}

	/** {@inheritdoc} */
	public function slug() {
		return 'megaott';
	}

	/** {@inheritdoc} */
	public function capabilities() {
		return array(
			'test_connection' => array( 'supported' => true, 'note' => 'GET /user (read-only; shows credit)' ),
			'list_packages'   => array( 'supported' => true, 'note' => 'GET /packages (paginated, period + max_connections)' ),
			'create'          => array( 'supported' => true, 'note' => 'POST /subscriptions (type m3u|mag)' ),
			'renew'           => array( 'supported' => true, 'note' => 'POST /subscriptions/{id}/extend with the plan package_id' ),
			'lookup'          => array( 'supported' => true, 'note' => 'GET /subscriptions/{id} → expiring_at' ),
			'expiry'          => array( 'supported' => true, 'note' => 'expiring_at / new_expiration_date' ),
			'packages'        => array( 'supported' => true, 'note' => 'package_id per plan product; optional template_id' ),
			'device_limits'   => array( 'supported' => true, 'note' => 'max_connections set at create (bounded by the package)' ),
			'suspend'         => array( 'supported' => true, 'note' => 'POST /subscriptions/{id}/deactivate and /activate' ),
			'extend_days'     => array( 'supported' => false, 'note' => 'Extends by a package period; rewards use the configured reward package or staff review' ),
			'idempotency'     => array( 'supported' => false, 'note' => 'No idempotency key documented' ),
			'create_lookup'   => array( 'supported' => true, 'note' => 'GET /subscriptions/by-username — the portal chooses the username, so lost create responses are reconciled' ),
		);
	}

	/**
	 * Validate endpoint.
	 *
	 * @return true|string
	 */
	public function validate_endpoint() {
		$p = wp_parse_url( $this->base );
		if ( empty( $p['scheme'] ) || 'https' !== strtolower( $p['scheme'] ) ) {
			return 'API base URL must use HTTPS.';
		}
		if ( empty( $p['host'] ) || isset( $p['user'] ) || isset( $p['pass'] ) || isset( $p['query'] ) || isset( $p['fragment'] ) ) {
			return 'API base URL must be a plain https://host/path URL.';
		}
		if ( ! in_array( strtolower( $p['host'] ), $this->hosts, true ) ) {
			return 'API host is not in the allowed hosts list.';
		}
		if ( '' === $this->token ) {
			return 'API token is not configured.';
		}
		return true;
	}

	/**
	 * HTTP call.
	 *
	 * @param string $method GET|POST.
	 * @param string $path Path starting with /.
	 * @param array  $params Query (GET) or form body (POST).
	 * @return RSP_Provider_Result data = decoded JSON (+ '_status').
	 */
	private function call( $method, $path, array $params = array() ) {
		$valid = $this->validate_endpoint();
		if ( true !== $valid ) {
			return new RSP_Provider_Result( 'failed', array(), $valid );
		}
		$is_write = 'POST' === $method;
		$url      = $this->base . $path . ( ! $is_write && $params ? '?' . http_build_query( $params, '', '&', PHP_QUERY_RFC3986 ) : '' );
		$args     = array(
			'method'              => $method,
			'timeout'             => $this->timeout,
			'redirection'         => 0,
			'sslverify'           => true,
			'reject_unsafe_urls'  => true,
			'limit_response_size' => 2097152,
			'user-agent'          => 'RelayStreamPortal/' . RSP_VERSION,
			'headers'             => array( 'Authorization' => 'Bearer ' . $this->token, 'Accept' => 'application/json' ),
		);
		if ( $is_write ) {
			$args['body'] = $params;
		}
		$response = call_user_func( $this->transport, $url, $args );
		if ( is_wp_error( $response ) ) {
			$msg = $response->get_error_message();
			if ( preg_match( '/cURL error (6|7|35|60):/i', $msg ) || in_array( $msg, array( 'A valid URL was not provided.', 'User has blocked requests through HTTP.' ), true ) ) {
				return new RSP_Provider_Result( 'failed', array( 'transport' => 'not_sent' ), 'Connection failed before the request was sent: ' . $msg );
			}
			return new RSP_Provider_Result( $is_write ? 'ambiguous' : 'failed', array( 'transport' => 'error' ), 'Transport error: ' . $msg );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$json = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$data = is_array( $json ) ? $json : array();
		$data['_status'] = $code;
		if ( $code >= 200 && $code < 300 ) {
			if ( ! is_array( $json ) ) {
				return new RSP_Provider_Result( $is_write ? 'ambiguous' : 'failed', $data, 'Response was not JSON.', $code );
			}
			if ( isset( $json['status'] ) && false === $json['status'] ) {
				return new RSP_Provider_Result( 'failed', $data, isset( $json['message'] ) ? (string) $json['message'] : 'Provider reported failure.', $code );
			}
			return new RSP_Provider_Result( 'success', $data, '', $code );
		}
		$msg = isset( $data['message'] ) ? (string) $data['message'] : 'HTTP ' . $code;
		if ( isset( $data['errors'] ) && is_array( $data['errors'] ) ) {
			$msg .= ' (' . implode( ', ', array_keys( $data['errors'] ) ) . ')';
		}
		// Documented client errors (auth, permission, not found, conflict, validation) mean nothing was applied.
		if ( in_array( $code, array( 400, 401, 403, 404, 409, 422 ), true ) ) {
			$data['permanent'] = true; // Retrying the same request cannot succeed (auth, permission, validation).
			return new RSP_Provider_Result( 'failed', $data, $msg, $code );
		}
		return new RSP_Provider_Result( $is_write ? 'ambiguous' : 'failed', $data, $msg, $code );
	}

	/**
	 * Normalize a subscription object into our credentials shape.
	 *
	 * @param array $s Subscription.
	 * @return array
	 */
	private static function creds_from( array $s ) {
		$server = isset( $s['dns_link'] ) ? (string) $s['dns_link'] : '';
		$c      = array(
			'username' => isset( $s['username'] ) ? (string) $s['username'] : '',
			'password' => isset( $s['password'] ) ? (string) $s['password'] : '',
			'server'   => $server,
			'mac'      => isset( $s['mac_address'] ) ? (string) $s['mac_address'] : '',
		);
		if ( $server && $c['username'] && $c['password'] ) {
			$c['url'] = untrailingslashit( $server ) . '/get.php?username=' . rawurlencode( $c['username'] ) . '&password=' . rawurlencode( $c['password'] ) . '&type=m3u_plus&output=ts';
		} elseif ( ! empty( $s['portal_link'] ) ) {
			$c['url'] = (string) $s['portal_link'];
		}
		return array_filter( $c, 'strlen' );
	}

	/**
	 * Expiry "Y-m-d H:i:s GMT+0000" or "Y-m-d H:i:s" -> Y-m-d (UTC).
	 *
	 * @param string $v Value.
	 * @return string
	 */
	public static function expiry( $v ) {
		$v = trim( str_replace( 'GMT', '', (string) $v ) );
		$t = strtotime( $v );
		return $t ? gmdate( 'Y-m-d', $t ) : '';
	}

	/** {@inheritdoc} */
	public function test_connection() {
		$r = $this->call( 'GET', '/user' );
		if ( $r->ok() ) {
			$r->data = array( 'status' => 'true', 'username' => isset( $r->data['username'] ) ? RSP_Crypto::mask( $r->data['username'] ) : '', 'credits' => isset( $r->data['credit'] ) ? (string) $r->data['credit'] : '' );
		}
		return $r;
	}

	/** {@inheritdoc} */
	public function list_packages() {
		$packages = array();
		$meta     = array();
		for ( $page = 1; $page <= 10; $page++ ) {
			$r = $this->call( 'GET', '/packages', array( 'page' => $page, 'per_page' => 100 ) );
			if ( ! $r->ok() ) {
				return $packages ? new RSP_Provider_Result( 'success', array( 'packages' => $packages, 'meta' => $meta, 'count' => count( $packages ) ) ) : $r;
			}
			foreach ( isset( $r->data['data'] ) ? (array) $r->data['data'] : array() as $p ) {
				if ( isset( $p['id'], $p['name'] ) ) {
					$id              = (string) $p['id'];
					$packages[ $id ] = sanitize_text_field( $p['name'] . ( isset( $p['period'], $p['period_type'] ) ? ' (' . $p['period'] . ' ' . $p['period_type'] . ')' : '' ) );
					$meta[ $id ]     = array(
						'period'          => isset( $p['period'] ) ? (int) $p['period'] : 0,
						'period_type'     => isset( $p['period_type'] ) ? sanitize_key( $p['period_type'] ) : '',
						'max_connections' => isset( $p['max_connections'] ) ? (int) $p['max_connections'] : 1,
						'is_trial'        => ! empty( $p['is_trial'] ),
					);
				}
			}
			if ( empty( $r->data['next_page_url'] ) ) {
				break;
			}
		}
		return new RSP_Provider_Result( 'success', array( 'packages' => $packages, 'meta' => $meta, 'count' => count( $packages ) ) );
	}

	/**
	 * Username we generate before sending the create (stored first so the create can be reconciled).
	 *
	 * @return string
	 */
	public static function generate_username() {
		return strtolower( RSP_Crypto::random_code( 10 ) );
	}

	/** {@inheritdoc} */
	public function create_account( array $args ) {
		if ( empty( $args['package'] ) || ! ctype_digit( (string) $args['package'] ) ) {
			return new RSP_Provider_Result( 'failed', array( 'permanent' => true ), 'This plan is not mapped to a MegaOTT package ID (Products → Streaming plan → Provider package).' );
		}
		$type   = isset( $args['type'] ) && 'mag' === $args['type'] ? 'mag' : 'm3u';
		$params = array(
			'type'              => $type,
			'package_id'        => (int) $args['package'],
			'max_connections'   => max( 1, (int) ( ! empty( $args['max_connections'] ) ? $args['max_connections'] : $this->defaults['max_connections'] ) ),
			'forced_country'    => $this->defaults['forced_country'] ? $this->defaults['forced_country'] : 'ALL',
			'adult'             => $this->defaults['adult'] ? 1 : 0,
			'whatsapp_telegram' => (string) $this->defaults['whatsapp_telegram'],
			'enable_vpn'        => $this->defaults['enable_vpn'] ? 1 : 0,
			'paid'              => 1,
		);
		if ( 'mag' === $type ) {
			if ( empty( $args['mac'] ) || ! preg_match( '/^([0-9A-F]{2}:){5}[0-9A-F]{2}$/i', $args['mac'] ) ) {
				return new RSP_Provider_Result( 'failed', array( 'permanent' => true ), 'A valid MAC address is required for MAG accounts.' );
			}
			$params['mac_address'] = strtoupper( $args['mac'] );
		} else {
			if ( empty( $args['username'] ) ) {
				return new RSP_Provider_Result( 'failed', array(), 'Username was not prepared.' );
			}
			$params['username'] = $args['username'];
		}
		if ( ! empty( $args['template'] ) ) {
			$params['template_id'] = (int) $args['template'];
		}
		if ( ! empty( $args['note'] ) ) {
			$params['note'] = substr( preg_replace( '/[^A-Za-z0-9 _:.-]/', '', (string) $args['note'] ), 0, 120 );
		}
		$r = $this->call( 'POST', '/subscriptions', $params );
		if ( $r->ok() ) {
			if ( empty( $r->data['id'] ) ) {
				return new RSP_Provider_Result( 'ambiguous', array(), 'Create returned no subscription id.', $r->http_code );
			}
			return $this->normalize( $r );
		}
		return $r;
	}

	/**
	 * Normalize a subscription response.
	 *
	 * @param RSP_Provider_Result $r Result.
	 * @return RSP_Provider_Result
	 */
	private function normalize( RSP_Provider_Result $r ) {
		$s       = $r->data;
		$r->data = array(
			'status'          => 'true',
			'user_id'         => (string) $s['id'],
			'expire'          => self::expiry( isset( $s['expiring_at'] ) ? $s['expiring_at'] : '' ),
			'max_connections' => isset( $s['max_connections'] ) ? (int) $s['max_connections'] : null,
			'credentials'     => self::creds_from( $s ),
			'message'         => '',
		);
		return $r;
	}

	/**
	 * Reconcile a create whose response was lost: look up the username we chose.
	 *
	 * @param array $args username, type.
	 * @return RSP_Provider_Result success = exists (normalized); failed with data[not_found] = safe to retry.
	 */
	public function find_created( array $args ) {
		if ( empty( $args['username'] ) ) {
			return new RSP_Provider_Result( 'ambiguous', array(), 'No username to reconcile (MAG creates cannot be looked up).' );
		}
		$r = $this->call( 'GET', '/subscriptions/by-username', array( 'username' => $args['username'] ) );
		if ( $r->ok() ) {
			return $this->normalize( $r );
		}
		if ( 404 === $r->http_code ) {
			return new RSP_Provider_Result( 'failed', array( 'not_found' => true ), 'No subscription with that username: the create was not applied.', 404 );
		}
		return new RSP_Provider_Result( 'ambiguous', array(), $r->error, $r->http_code );
	}

	/** {@inheritdoc} */
	public function renew_account( array $creds, $months, $type ) {
		if ( empty( $creds['provider_user_id'] ) || empty( $creds['package'] ) || ! ctype_digit( (string) $creds['package'] ) ) {
			return new RSP_Provider_Result( 'failed', array( 'permanent' => true ), 'Missing MegaOTT subscription id or package id for the extension.' );
		}
		$r = $this->call( 'POST', '/subscriptions/' . (int) $creds['provider_user_id'] . '/extend', array( 'package_id' => (int) $creds['package'], 'paid' => 1 ) );
		if ( $r->ok() ) {
			$r->data = array( 'status' => 'true', 'message' => isset( $r->data['message'] ) ? (string) $r->data['message'] : '', 'expire' => self::expiry( isset( $r->data['new_expiration_date'] ) ? $r->data['new_expiration_date'] : '' ) );
		}
		return $r;
	}

	/** {@inheritdoc} */
	public function lookup_account( array $creds, $type ) {
		if ( ! empty( $creds['provider_user_id'] ) ) {
			$r = $this->call( 'GET', '/subscriptions/' . (int) $creds['provider_user_id'] );
		} elseif ( ! empty( $creds['username'] ) ) {
			$r = $this->call( 'GET', '/subscriptions/by-username', array( 'username' => $creds['username'] ) );
		} else {
			return new RSP_Provider_Result( 'failed', array(), 'Missing subscription id for lookup.' );
		}
		if ( ! $r->ok() ) {
			return $r;
		}
		$r = $this->normalize( $r );
		if ( '' === $r->data['expire'] ) {
			return new RSP_Provider_Result( 'failed', array(), 'Lookup returned no valid expiry date.', $r->http_code );
		}
		$r->data['enabled'] = '';
		return $r;
	}

	/**
	 * Suspend / resume.
	 *
	 * @param string $id Subscription id.
	 * @param bool   $active Activate (true) or deactivate.
	 * @return RSP_Provider_Result
	 */
	public function set_active( $id, $active ) {
		if ( ! ctype_digit( (string) $id ) ) {
			return new RSP_Provider_Result( 'failed', array(), 'Missing subscription id.' );
		}
		return $this->call( 'POST', '/subscriptions/' . (int) $id . ( $active ? '/activate' : '/deactivate' ) );
	}
}
