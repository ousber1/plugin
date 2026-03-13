<?php
/**
 * REST API Inventory endpoint for PrintFlow Pro.
 *
 * @package PrintFlowPro
 * @subpackage API
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PFP_REST_Inventory
 *
 * Provides endpoints for managing inventory and stock levels.
 */
class PFP_REST_Inventory {

	/**
	 * Register routes.
	 */
	public function register_routes() {
		register_rest_route( PFP_REST_API::API_NAMESPACE, '/inventory/materials', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_materials' ),
			'permission_callback' => array( 'PFP_REST_API', 'staff_permissions_check' ),
			'args'                => array(
				'category' => array(
					'type'    => 'string',
					'default' => '',
				),
				'low_stock' => array(
					'type'    => 'boolean',
					'default' => false,
				),
			),
		) );

		register_rest_route( PFP_REST_API::API_NAMESPACE, '/inventory/materials/(?P<id>\d+)', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_material' ),
			'permission_callback' => array( 'PFP_REST_API', 'staff_permissions_check' ),
		) );

		register_rest_route( PFP_REST_API::API_NAMESPACE, '/inventory/materials/(?P<id>\d+)/stock', array(
			'methods'             => 'PUT',
			'callback'            => array( $this, 'update_stock' ),
			'permission_callback' => array( 'PFP_REST_API', 'admin_permissions_check' ),
			'args'                => array(
				'quantity'  => array(
					'required' => true,
					'type'     => 'number',
				),
				'operation' => array(
					'type'    => 'string',
					'default' => 'set',
					'enum'    => array( 'set', 'add', 'subtract' ),
				),
				'reason'    => array(
					'type'    => 'string',
					'default' => '',
				),
			),
		) );

		register_rest_route( PFP_REST_API::API_NAMESPACE, '/inventory/alerts', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_alerts' ),
			'permission_callback' => array( 'PFP_REST_API', 'staff_permissions_check' ),
		) );
	}

	/**
	 * Get materials list.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_materials( $request ) {
		global $wpdb;

		$table    = $wpdb->prefix . 'pfp_materials';
		$category = $request->get_param( 'category' );
		$low_stock = $request->get_param( 'low_stock' );

		$where = array( '1=1' );
		$values = array();

		if ( $category ) {
			$where[]  = 'category = %s';
			$values[] = $category;
		}

		if ( $low_stock ) {
			$where[] = 'quantity <= min_alert_qty';
		}

		$where_clause = implode( ' AND ', $where );

		if ( ! empty( $values ) ) {
			$query = $wpdb->prepare(
				"SELECT * FROM {$table} WHERE {$where_clause} ORDER BY name ASC",
				$values
			);
		} else {
			$query = "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY name ASC";
		}

		$materials = $wpdb->get_results( $query, ARRAY_A );

		if ( null === $materials ) {
			$materials = array();
		}

		return rest_ensure_response( array(
			'materials' => $materials,
			'total'     => count( $materials ),
		) );
	}

	/**
	 * Get a single material.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_material( $request ) {
		global $wpdb;

		$id       = absint( $request->get_param( 'id' ) );
		$table    = $wpdb->prefix . 'pfp_materials';
		$material = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );

		if ( ! $material ) {
			return PFP_REST_API::error( 'not_found', __( 'Matériau introuvable.', 'printflow-pro' ), 404 );
		}

		return rest_ensure_response( $material );
	}

	/**
	 * Update stock level for a material.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_stock( $request ) {
		global $wpdb;

		$id        = absint( $request->get_param( 'id' ) );
		$quantity  = (float) $request->get_param( 'quantity' );
		$operation = $request->get_param( 'operation' );
		$reason    = $request->get_param( 'reason' );

		$table    = $wpdb->prefix . 'pfp_materials';
		$material = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );

		if ( ! $material ) {
			return PFP_REST_API::error( 'not_found', __( 'Matériau introuvable.', 'printflow-pro' ), 404 );
		}

		$current = (float) $material['quantity'];

		switch ( $operation ) {
			case 'add':
				$new_stock = $current + $quantity;
				break;
			case 'subtract':
				$new_stock = max( 0, $current - $quantity );
				break;
			default:
				$new_stock = $quantity;
				break;
		}

		$wpdb->update(
			$table,
			array(
				'quantity' => $new_stock,
				'updated_at'    => current_time( 'mysql' ),
			),
			array( 'id' => $id ),
			array( '%f', '%s' ),
			array( '%d' )
		);

		// Log the stock movement.
		$log_table = $wpdb->prefix . 'pfp_stock_movements';
		$wpdb->insert( $log_table, array(
			'material_id'    => $id,
			'type'           => $operation,
			'quantity'       => $quantity,
			'reference_type' => 'api',
			'reason'         => $reason,
			'user_id'        => get_current_user_id(),
			'created_at'     => current_time( 'mysql' ),
		) );

		$material['quantity'] = $new_stock;

		return rest_ensure_response( $material );
	}

	/**
	 * Get low stock alerts.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_alerts( $request ) {
		global $wpdb;

		$table  = $wpdb->prefix . 'pfp_materials';
		$alerts = $wpdb->get_results(
			"SELECT * FROM {$table} WHERE quantity <= min_alert_qty ORDER BY (quantity / min_alert_qty) ASC",
			ARRAY_A
		);

		if ( null === $alerts ) {
			$alerts = array();
		}

		return rest_ensure_response( array(
			'alerts' => $alerts,
			'total'  => count( $alerts ),
		) );
	}
}
