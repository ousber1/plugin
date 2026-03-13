<?php
/**
 * REST API Production endpoint for PrintFlow Pro.
 *
 * @package PrintFlowPro
 * @subpackage API
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PFP_REST_Production
 *
 * Provides endpoints for managing production workflow via REST API.
 */
class PFP_REST_Production {

	/**
	 * Register routes.
	 */
	public function register_routes() {
		register_rest_route( PFP_REST_API::API_NAMESPACE, '/production/jobs', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_jobs' ),
			'permission_callback' => array( 'PFP_REST_API', 'staff_permissions_check' ),
			'args'                => array(
				'status'   => array(
					'type'    => 'string',
					'default' => '',
				),
				'per_page' => array(
					'type'              => 'integer',
					'default'           => 20,
					'sanitize_callback' => 'absint',
				),
				'page'     => array(
					'type'              => 'integer',
					'default'           => 1,
					'sanitize_callback' => 'absint',
				),
			),
		) );

		register_rest_route( PFP_REST_API::API_NAMESPACE, '/production/jobs/(?P<order_id>\d+)', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_job' ),
			'permission_callback' => array( 'PFP_REST_API', 'staff_permissions_check' ),
		) );

		register_rest_route( PFP_REST_API::API_NAMESPACE, '/production/jobs/(?P<order_id>\d+)/status', array(
			'methods'             => 'PUT',
			'callback'            => array( $this, 'update_job_status' ),
			'permission_callback' => array( 'PFP_REST_API', 'staff_permissions_check' ),
			'args'                => array(
				'status' => array(
					'required' => true,
					'type'     => 'string',
				),
				'notes'  => array(
					'type'    => 'string',
					'default' => '',
				),
			),
		) );
	}

	/**
	 * Get production jobs list.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_jobs( $request ) {
		$status   = $request->get_param( 'status' );
		$per_page = $request->get_param( 'per_page' );
		$page     = $request->get_param( 'page' );

		$args = array(
			'limit'   => $per_page,
			'offset'  => ( $page - 1 ) * $per_page,
			'orderby' => 'date',
			'order'   => 'DESC',
		);

		if ( $status ) {
			$args['status'] = 'pfp-' . $status;
		} else {
			$args['status'] = array(
				'pfp-file-review',
				'pfp-designing',
				'pfp-printing',
				'pfp-finishing',
				'pfp-ready-delivery',
			);
		}

		$orders = wc_get_orders( $args );
		$jobs   = array();

		foreach ( $orders as $order ) {
			$jobs[] = $this->format_job( $order );
		}

		return rest_ensure_response( array(
			'jobs'  => $jobs,
			'page'  => $page,
			'total' => count( $jobs ),
		) );
	}

	/**
	 * Get a single production job.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_job( $request ) {
		$order_id = $request->get_param( 'order_id' );
		$order    = wc_get_order( $order_id );

		if ( ! $order ) {
			return PFP_REST_API::error( 'not_found', __( 'Commande introuvable.', 'printflow-pro' ), 404 );
		}

		return rest_ensure_response( $this->format_job( $order ) );
	}

	/**
	 * Update production job status.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_job_status( $request ) {
		$order_id = $request->get_param( 'order_id' );
		$status   = $request->get_param( 'status' );
		$notes    = $request->get_param( 'notes' );

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return PFP_REST_API::error( 'not_found', __( 'Commande introuvable.', 'printflow-pro' ), 404 );
		}

		$valid_statuses = array( 'file-review', 'designing', 'printing', 'finishing', 'ready-delivery' );
		if ( ! in_array( $status, $valid_statuses, true ) ) {
			return PFP_REST_API::error( 'invalid_status', __( 'Statut invalide.', 'printflow-pro' ) );
		}

		$order->update_status( 'pfp-' . $status, $notes );
		$order->update_meta_data( '_pfp_production_status', $status );
		$order->save();

		return rest_ensure_response( $this->format_job( $order ) );
	}

	/**
	 * Format an order as a production job.
	 *
	 * @param \WC_Order $order Order object.
	 * @return array
	 */
	private function format_job( $order ) {
		return array(
			'order_id'          => $order->get_id(),
			'order_number'      => $order->get_order_number(),
			'status'            => $order->get_status(),
			'production_status' => $order->get_meta( '_pfp_production_status' ),
			'customer'          => $order->get_formatted_billing_full_name(),
			'total'             => $order->get_total(),
			'date_created'      => $order->get_date_created() ? $order->get_date_created()->format( 'Y-m-d H:i:s' ) : null,
			'project_name'      => $order->get_meta( '_pfp_project_name' ),
			'deadline'          => $order->get_meta( '_pfp_delivery_deadline' ),
			'instructions'      => $order->get_meta( '_pfp_special_instructions' ),
		);
	}
}
