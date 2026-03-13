<?php
/**
 * REST API base controller for PrintFlow Pro.
 *
 * @package PrintFlowPro
 * @subpackage API
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PFP_REST_API
 *
 * Registers the PrintFlow Pro REST API namespace and shared utilities.
 */
class PFP_REST_API {

	/**
	 * REST API namespace.
	 *
	 * @var string
	 */
	const NAMESPACE = 'printflow-pro/v1';

	/**
	 * Initialize REST API.
	 */
	public function init() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST API routes.
	 */
	public function register_routes() {
		$controllers = array(
			new PFP_REST_Pricing(),
			new PFP_REST_Production(),
			new PFP_REST_Inventory(),
		);

		foreach ( $controllers as $controller ) {
			$controller->register_routes();
		}
	}

	/**
	 * Check if current user has admin permissions.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return bool
	 */
	public static function admin_permissions_check( $request ) {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Check if current user has staff permissions.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return bool
	 */
	public static function staff_permissions_check( $request ) {
		return current_user_can( 'manage_woocommerce' ) || current_user_can( 'pfp_manage_production' );
	}

	/**
	 * Return a standardized error response.
	 *
	 * @param string $code    Error code.
	 * @param string $message Error message.
	 * @param int    $status  HTTP status code.
	 * @return \WP_Error
	 */
	public static function error( $code, $message, $status = 400 ) {
		return new \WP_Error( $code, $message, array( 'status' => $status ) );
	}
}
