<?php
/**
 * Manual provider: no remote calls. Every provisioning job waits for staff to
 * enter the account details and confirmed expiry in the admin.
 *
 * @package RelayStreamPortal
 */

defined( 'ABSPATH' ) || exit;

/**
 * Manual provider.
 */
class RSP_Manual_Provider implements RSP_Provider_Interface {

	/** {@inheritdoc} */
	public function slug() {
		return 'manual';
	}

	/** {@inheritdoc} */
	public function capabilities() {
		$staff = array( 'supported' => true, 'note' => 'Performed by staff in the admin and recorded in the audit log' );
		return array(
			'test_connection' => array( 'supported' => false, 'note' => 'No remote panel in manual mode' ),
			'list_packages'   => array( 'supported' => false, 'note' => 'Enter package identifiers manually' ),
			'create'          => $staff,
			'renew'           => $staff,
			'lookup'          => array( 'supported' => false, 'note' => 'Staff record expiry manually' ),
			'expiry'          => $staff,
			'packages'        => $staff,
			'device_limits'   => $staff,
			'suspend'         => $staff,
			'extend_days'     => $staff,
			'idempotency'     => array( 'supported' => true, 'note' => 'Jobs are unique per order item' ),
			'create_lookup'   => array( 'supported' => false, 'note' => '' ),
		);
	}

	/**
	 * Manual result.
	 *
	 * @return RSP_Provider_Result
	 */
	private function manual() {
		return new RSP_Provider_Result( 'failed', array( 'manual' => true ), 'Manual mode: awaiting staff provisioning.' );
	}

	/** {@inheritdoc} */
	public function test_connection() {
		return $this->manual();
	}

	/** {@inheritdoc} */
	public function list_packages() {
		return $this->manual();
	}

	/** {@inheritdoc} */
	public function create_account( array $args ) {
		return $this->manual();
	}

	/** {@inheritdoc} */
	public function renew_account( array $creds, $months, $type ) {
		return $this->manual();
	}

	/** {@inheritdoc} */
	public function lookup_account( array $creds, $type ) {
		return $this->manual();
	}
}
