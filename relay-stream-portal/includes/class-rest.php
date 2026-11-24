<?php
/**
 * Authenticated REST endpoints (namespace rsp/v1). All require a logged-in user with a valid
 * wp_rest nonce and enforce ownership. Responses are marked no-store and never contain
 * provider API secrets. Player credentials are only returned by the explicit reveal endpoint.
 *
 * @package RelayStreamPortal
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST.
 */
class RSP_Rest {

	const NS = 'rsp/v1';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_filter( 'rest_post_dispatch', array( __CLASS__, 'no_store' ), 10, 3 );
	}

	/**
	 * Permission callback.
	 *
	 * @return bool|WP_Error
	 */
	public static function logged_in() {
		return is_user_logged_in() ? true : new WP_Error( 'rest_forbidden', __( 'Please sign in.', 'relay-stream-portal' ), array( 'status' => 401 ) );
	}

	/**
	 * Routes.
	 */
	public static function routes() {
		register_rest_route(
			self::NS,
			'/accounts',
			array(
				'methods'             => 'GET',
				'permission_callback' => array( __CLASS__, 'logged_in' ),
				'callback'            => array( __CLASS__, 'accounts' ),
			)
		);
		register_rest_route(
			self::NS,
			'/accounts/(?P<uuid>[0-9a-f-]{36})/credentials',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( __CLASS__, 'logged_in' ),
				'callback'            => array( __CLASS__, 'reveal' ),
			)
		);
		register_rest_route(
			self::NS,
			'/announcements/(?P<id>[a-z0-9_-]{1,40})/dismiss',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( __CLASS__, 'logged_in' ),
				'callback'            => array( __CLASS__, 'dismiss' ),
			)
		);
		register_rest_route(
			self::NS,
			'/notifications/read',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( __CLASS__, 'logged_in' ),
				'callback'            => array( __CLASS__, 'read_all' ),
			)
		);
	}

	/**
	 * Never cache our responses.
	 *
	 * @param WP_REST_Response $response Response.
	 * @param WP_REST_Server   $server Server.
	 * @param WP_REST_Request  $request Request.
	 * @return WP_REST_Response
	 */
	public static function no_store( $response, $server, $request ) {
		if ( 0 === strpos( $request->get_route(), '/' . self::NS ) && $response instanceof WP_REST_Response ) {
			$response->header( 'Cache-Control', 'no-store, private, max-age=0' );
			$response->header( 'Pragma', 'no-cache' );
		}
		return $response;
	}

	/**
	 * Public-safe account summaries (used to poll activation status).
	 *
	 * @return WP_REST_Response
	 */
	public static function accounts() {
		$out = array();
		foreach ( RSP_Accounts::for_user( get_current_user_id() ) as $a ) {
			$row        = RSP_Accounts::to_public( $a );
			$job        = RSP_Jobs::open_for_account( (int) $a->id );
			$row['job'] = $job ? array( 'state' => $job->state, 'label' => RSP_Jobs::state_label( $job->state ) ) : null;
			$out[]      = $row;
		}
		return rest_ensure_response( $out );
	}

	/**
	 * Reveal player credentials for an owned account.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function reveal( WP_REST_Request $req ) {
		if ( ! RSP_Settings::get( 'show_credentials' ) ) {
			return new WP_Error( 'rsp_disabled', __( 'Player details are not available here. Contact support.', 'relay-stream-portal' ), array( 'status' => 403 ) );
		}
		if ( ! RSP_Rate_Limiter::hit( 'reveal', 20, 10 * MINUTE_IN_SECONDS ) ) {
			return new WP_Error( 'rsp_rate', __( 'Too many requests. Please wait a few minutes.', 'relay-stream-portal' ), array( 'status' => 429 ) );
		}
		$acc = RSP_Accounts::get_owned( (string) $req['uuid'], get_current_user_id() );
		if ( ! $acc ) {
			return new WP_Error( 'rsp_not_found', __( 'Account not found.', 'relay-stream-portal' ), array( 'status' => 404 ) );
		}
		if ( ! in_array( $acc->status, array( 'active', 'expired' ), true ) ) {
			return new WP_Error( 'rsp_not_ready', __( 'Player details appear once the account is active.', 'relay-stream-portal' ), array( 'status' => 409 ) );
		}
		$c = RSP_Accounts::credentials( $acc );
		RSP_Audit::log( 'credentials_revealed', 'account', $acc->uuid, '', array( 'by' => 'owner' ) );
		return rest_ensure_response(
			array(
				'server'   => isset( $c['server'] ) ? $c['server'] : '',
				'username' => isset( $c['username'] ) ? $c['username'] : '',
				'password' => isset( $c['password'] ) ? $c['password'] : '',
				'm3u'      => isset( $c['url'] ) ? $c['url'] : '',
				'mac'      => isset( $c['mac'] ) ? $c['mac'] : '',
			)
		);
	}

	/**
	 * Dismiss an announcement.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	public static function dismiss( WP_REST_Request $req ) {
		$ids   = (array) get_user_meta( get_current_user_id(), 'rsp_dismissed', true );
		$ids[] = sanitize_key( $req['id'] );
		update_user_meta( get_current_user_id(), 'rsp_dismissed', array_slice( array_values( array_unique( array_filter( $ids ) ) ), -100 ) );
		return rest_ensure_response( array( 'ok' => true ) );
	}

	/**
	 * Mark notifications read.
	 *
	 * @return WP_REST_Response
	 */
	public static function read_all() {
		RSP_Notifications::mark_all_read( get_current_user_id() );
		return rest_ensure_response( array( 'ok' => true ) );
	}
}
