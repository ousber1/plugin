<?php
/**
 * Inventory / Raw Materials Management module.
 *
 * @package PrintFlowPro
 */

defined( 'ABSPATH' ) || exit;

class PFP_Inventory {

	/**
	 * Initialize the module.
	 */
	public function init() {
		add_action( 'wp_ajax_pfp_save_material', array( $this, 'ajax_save_material' ) );
		add_action( 'wp_ajax_pfp_record_stock_movement', array( $this, 'ajax_record_movement' ) );
		add_action( 'wp_ajax_pfp_adjust_stock', array( $this, 'ajax_adjust_stock' ) );
		add_action( 'wp_ajax_pfp_get_materials', array( $this, 'ajax_get_materials' ) );

		// Auto-deduct materials when production starts.
		add_action( 'pfp_production_started', array( $this, 'auto_deduct_materials' ), 10, 2 );
	}

	/**
	 * Get all materials.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public function get_materials( $args = array() ) {
		global $wpdb;
		$prefix = $wpdb->prefix;

		$where  = '1=1';
		$params = array();

		if ( ! empty( $args['status'] ) ) {
			$where   .= ' AND m.status = %s';
			$params[] = $args['status'];
		}

		if ( ! empty( $args['category_id'] ) ) {
			$where   .= ' AND m.category_id = %d';
			$params[] = $args['category_id'];
		}

		if ( ! empty( $args['low_stock'] ) ) {
			$where .= ' AND m.quantity <= m.min_alert_qty';
		}

		$sql = "SELECT m.*, mc.name as category_name, s.name as supplier_name
				FROM {$prefix}pfp_materials m
				LEFT JOIN {$prefix}pfp_material_categories mc ON m.category_id = mc.id
				LEFT JOIN {$prefix}pfp_suppliers s ON m.supplier_id = s.id
				WHERE {$where}
				ORDER BY m.name ASC";

		if ( ! empty( $params ) ) {
			return $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
		}
		return $wpdb->get_results( $sql, ARRAY_A );
	}

	/**
	 * Get a single material.
	 *
	 * @param int $material_id Material ID.
	 * @return array|null
	 */
	public function get_material( $material_id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT m.*, mc.name as category_name, s.name as supplier_name
				FROM {$wpdb->prefix}pfp_materials m
				LEFT JOIN {$wpdb->prefix}pfp_material_categories mc ON m.category_id = mc.id
				LEFT JOIN {$wpdb->prefix}pfp_suppliers s ON m.supplier_id = s.id
				WHERE m.id = %d",
				$material_id
			),
			ARRAY_A
		);
	}

	/**
	 * Save material (create or update).
	 *
	 * @param array $data Material data.
	 * @return int Material ID.
	 */
	public function save_material( $data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'pfp_materials';

		$db_data = array(
			'name'          => $data['name'],
			'code'          => $data['code'],
			'category_id'   => $data['category_id'] ?? 0,
			'unit'          => $data['unit'] ?? 'pièce',
			'quantity'      => $data['quantity'] ?? 0,
			'min_alert_qty' => $data['min_alert_qty'] ?? 0,
			'purchase_cost' => $data['purchase_cost'] ?? 0,
			'supplier_id'   => $data['supplier_id'] ?? 0,
			'status'        => $data['status'] ?? 'active',
		);

		if ( ! empty( $data['id'] ) ) {
			$wpdb->update( $table, $db_data, array( 'id' => $data['id'] ) );
			return $data['id'];
		} else {
			$wpdb->insert( $table, $db_data );
			return $wpdb->insert_id;
		}
	}

	/**
	 * Record a stock movement.
	 *
	 * @param int    $material_id    Material ID.
	 * @param string $type           Movement type: in, out, adjustment.
	 * @param float  $quantity       Quantity.
	 * @param string $reference_type Reference type.
	 * @param int    $reference_id   Reference ID.
	 * @param string $reason         Reason.
	 * @return bool
	 */
	public function record_movement( $material_id, $type, $quantity, $reference_type = '', $reference_id = 0, $reason = '' ) {
		global $wpdb;
		$prefix = $wpdb->prefix;

		// Insert movement record.
		$wpdb->insert(
			"{$prefix}pfp_stock_movements",
			array(
				'material_id'    => $material_id,
				'type'           => $type,
				'quantity'       => $quantity,
				'reference_type' => $reference_type,
				'reference_id'   => $reference_id,
				'reason'         => $reason,
				'user_id'        => get_current_user_id(),
			),
			array( '%d', '%s', '%f', '%s', '%d', '%s', '%d' )
		);

		// Update material quantity.
		if ( 'in' === $type ) {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$prefix}pfp_materials SET quantity = quantity + %f WHERE id = %d",
					$quantity,
					$material_id
				)
			);
		} elseif ( 'out' === $type ) {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$prefix}pfp_materials SET quantity = GREATEST(0, quantity - %f) WHERE id = %d",
					$quantity,
					$material_id
				)
			);
		} elseif ( 'adjustment' === $type ) {
			$wpdb->update(
				"{$prefix}pfp_materials",
				array( 'quantity' => $quantity ),
				array( 'id' => $material_id ),
				array( '%f' ),
				array( '%d' )
			);
		}

		return true;
	}

	/**
	 * Auto-deduct materials when production starts.
	 *
	 * @param int   $job_id Job ID.
	 * @param array $job    Job data.
	 */
	public function auto_deduct_materials( $job_id, $job ) {
		if ( empty( $job['order_id'] ) ) {
			return;
		}

		$order = wc_get_order( $job['order_id'] );
		if ( ! $order ) {
			return;
		}

		// Get the specific order item.
		$item = $order->get_item( $job['order_item_id'] );
		if ( ! $item ) {
			return;
		}

		$product_id = $item->get_product_id();
		$quantity   = $item->get_quantity();

		// Get material mappings for this product.
		global $wpdb;
		$mappings = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}pfp_material_product_map WHERE product_id = %d",
				$product_id
			),
			ARRAY_A
		);

		foreach ( $mappings as $mapping ) {
			$deduct_qty = $mapping['quantity_per_unit'] * $quantity;
			$this->record_movement(
				$mapping['material_id'],
				'out',
				$deduct_qty,
				'production',
				$job_id,
				sprintf( 'Déduction automatique - Commande #%s, %d unités', $order->get_order_number(), $quantity )
			);
		}
	}

	/**
	 * Check for low stock materials and send alerts.
	 */
	public function check_low_stock_alerts() {
		$low_stock = $this->get_materials( array( 'low_stock' => true, 'status' => 'active' ) );

		if ( empty( $low_stock ) ) {
			return;
		}

		foreach ( $low_stock as $material ) {
			do_action( 'pfp_low_stock_alert', $material );
		}
	}

	/**
	 * Get stock movement history for a material.
	 *
	 * @param int $material_id Material ID.
	 * @param int $limit       Limit.
	 * @return array
	 */
	public function get_movements( $material_id, $limit = 50 ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT sm.*, u.display_name as user_name
				FROM {$wpdb->prefix}pfp_stock_movements sm
				LEFT JOIN {$wpdb->users} u ON sm.user_id = u.ID
				WHERE sm.material_id = %d
				ORDER BY sm.created_at DESC
				LIMIT %d",
				$material_id,
				$limit
			),
			ARRAY_A
		);
	}

	/**
	 * Import predefined materials from JSON.
	 *
	 * @return int Number of materials imported.
	 */
	public function import_default_materials() {
		$file = PFP_PLUGIN_DIR . 'includes/data/materials-catalog.json';
		if ( ! file_exists( $file ) ) {
			return 0;
		}

		$materials = json_decode( file_get_contents( $file ), true );
		if ( empty( $materials ) ) {
			return 0;
		}

		$count = 0;
		foreach ( $materials as $material ) {
			// Check if code already exists.
			global $wpdb;
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}pfp_materials WHERE code = %s",
					$material['code']
				)
			);
			if ( ! $exists ) {
				$this->save_material( $material );
				$count++;
			}
		}

		return $count;
	}

	/**
	 * AJAX handler: save material.
	 */
	public function ajax_save_material() {
		check_ajax_referer( 'pfp_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'pfp_manage_inventory' ) ) {
			wp_send_json_error( array( 'message' => 'Accès refusé.' ) );
		}

		$data = array(
			'id'            => isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0,
			'name'          => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
			'code'          => sanitize_text_field( wp_unslash( $_POST['code'] ?? '' ) ),
			'category_id'   => absint( $_POST['category_id'] ?? 0 ),
			'unit'          => sanitize_text_field( wp_unslash( $_POST['unit'] ?? 'pièce' ) ),
			'quantity'      => floatval( $_POST['quantity'] ?? 0 ),
			'min_alert_qty' => floatval( $_POST['min_alert_qty'] ?? 0 ),
			'purchase_cost' => floatval( $_POST['purchase_cost'] ?? 0 ),
			'supplier_id'   => absint( $_POST['supplier_id'] ?? 0 ),
			'status'        => sanitize_text_field( wp_unslash( $_POST['status'] ?? 'active' ) ),
		);

		if ( empty( $data['name'] ) || empty( $data['code'] ) ) {
			wp_send_json_error( array( 'message' => 'Nom et code sont obligatoires.' ) );
		}

		$material_id = $this->save_material( $data );

		wp_send_json_success( array( 'material_id' => $material_id, 'message' => 'Matière enregistrée.' ) );
	}

	/**
	 * AJAX handler: record stock movement.
	 */
	public function ajax_record_movement() {
		check_ajax_referer( 'pfp_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'pfp_manage_inventory' ) ) {
			wp_send_json_error( array( 'message' => 'Accès refusé.' ) );
		}

		$material_id = absint( $_POST['material_id'] ?? 0 );
		$type        = sanitize_text_field( wp_unslash( $_POST['type'] ?? '' ) );
		$quantity    = floatval( $_POST['quantity'] ?? 0 );
		$reason      = sanitize_textarea_field( wp_unslash( $_POST['reason'] ?? '' ) );

		if ( ! $material_id || ! in_array( $type, array( 'in', 'out' ), true ) || $quantity <= 0 ) {
			wp_send_json_error( array( 'message' => 'Paramètres invalides.' ) );
		}

		$this->record_movement( $material_id, $type, $quantity, 'manual', 0, $reason );

		$type_label = 'in' === $type ? 'Entrée' : 'Sortie';
		wp_send_json_success( array( 'message' => "{$type_label} de stock enregistrée." ) );
	}

	/**
	 * AJAX handler: adjust stock.
	 */
	public function ajax_adjust_stock() {
		check_ajax_referer( 'pfp_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'pfp_manage_inventory' ) ) {
			wp_send_json_error( array( 'message' => 'Accès refusé.' ) );
		}

		$material_id = absint( $_POST['material_id'] ?? 0 );
		$new_qty     = floatval( $_POST['new_quantity'] ?? 0 );
		$reason      = sanitize_textarea_field( wp_unslash( $_POST['reason'] ?? '' ) );

		if ( ! $material_id ) {
			wp_send_json_error( array( 'message' => 'ID matière invalide.' ) );
		}

		$this->record_movement( $material_id, 'adjustment', $new_qty, 'audit', 0, $reason );

		wp_send_json_success( array( 'message' => 'Stock ajusté.' ) );
	}

	/**
	 * AJAX handler: get materials list.
	 */
	public function ajax_get_materials() {
		check_ajax_referer( 'pfp_admin_nonce', 'nonce' );

		$args = array();
		if ( ! empty( $_POST['low_stock'] ) ) {
			$args['low_stock'] = true;
		}

		wp_send_json_success( $this->get_materials( $args ) );
	}
}
