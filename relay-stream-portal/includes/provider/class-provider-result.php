<?php
/**
 * Normalized provider call result.
 *
 * Outcomes:
 *  - success:   provider confirmed the operation.
 *  - failed:    provider explicitly rejected it, or it was never sent. Safe to retry after fixing the cause.
 *  - ambiguous: the request may have been applied remotely (timeout, 5xx, unparseable body). Reconcile before any retry.
 *
 * @package RelayStreamPortal
 */

defined( 'ABSPATH' ) || exit;

/**
 * Provider result.
 */
class RSP_Provider_Result {

	/** @var string */
	public $outcome;
	/** @var array Parsed, non-secret data plus secret fields that callers must store encrypted. */
	public $data;
	/** @var string Redacted error message. */
	public $error;
	/** @var int */
	public $http_code;

	/**
	 * Constructor.
	 *
	 * @param string $outcome Outcome.
	 * @param array  $data Data.
	 * @param string $error Error.
	 * @param int    $http_code HTTP code.
	 */
	public function __construct( $outcome, array $data = array(), $error = '', $http_code = 0 ) {
		$this->outcome   = $outcome;
		$this->data      = $data;
		$this->error     = RSP_Audit::redact_string( (string) $error );
		$this->http_code = (int) $http_code;
	}

	/**
	 * Success?
	 *
	 * @return bool
	 */
	public function ok() {
		return 'success' === $this->outcome;
	}

	/**
	 * Redacted array for storing in job results and audit logs.
	 *
	 * @return array
	 */
	public function to_log() {
		$safe = array();
		foreach ( array( 'user_id', 'status', 'message', 'expire', 'enabled', 'country', 'credits', 'count', 'reconciled' ) as $k ) {
			if ( isset( $this->data[ $k ] ) && is_scalar( $this->data[ $k ] ) ) {
				$safe[ $k ] = (string) $this->data[ $k ];
			}
		}
		return array(
			'outcome'   => $this->outcome,
			'http_code' => $this->http_code,
			'error'     => $this->error,
			'data'      => $safe,
		);
	}
}
