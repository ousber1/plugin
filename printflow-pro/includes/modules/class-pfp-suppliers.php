<?php
/**
 * Supplier Management module.
 *
 * @package PrintFlowPro
 */

defined( 'ABSPATH' ) || exit;

class PFP_Suppliers {

	/**
	 * Initialize the module.
	 */
	public function init() {
		add_action( 'wp_ajax_pfp_save_supplier', array( $this, 'ajax_save_supplier' ) );
		add_action( 'wp_ajax_pfp_delete_supplier', array( $this, 'ajax_delete_supplier' ) );
		add_action( 'wp_ajax_pfp_create_purchase_order', array( $this, 'ajax_create_purchase_order' ) );
		add_action( 'wp_ajax_pfp_update_purchase_order', array( $this, 'ajax_update_purchase_order' ) );
		add_action( 'wp_ajax_pfp_record_supplier_payment', array( $this, 'ajax_record_payment' ) );
	}

	/**
	 * Get all suppliers.
	 *
	 * @param array $args Query args.
	 * @return array
	 */
	public function get_suppliers( $args = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'pfp_suppliers';

		$where = "1=1";
		$params = array();

		if ( ! empty( $args['status'] ) ) {
			$where .= ' AND status = %s';
			$params[] = $args['status'];
		}

		$sql = "SELECT * FROM {$table} WHERE {$where} ORDER BY name ASC";

		if ( ! empty( $params ) ) {
			return $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
		}
		return $wpdb->get_results( $sql, ARRAY_A );
	}

	/**
	 * Get a single supplier.
	 *
	 * @param int $supplier_id Supplier ID.
	 * @return array|null
	 */
	public function get_supplier( $supplier_id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}pfp_suppliers WHERE id = %d",
				$supplier_id
			),
			ARRAY_A
		);
	}

	/**
	 * Save supplier.
	 *
	 * @param array $data Supplier data.
	 * @return int Supplier ID.
	 */
	public function save_supplier( $data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'pfp_suppliers';

		$db_data = array(
			'name'              => $data['name'],
			'company'           => $data['company'] ?? '',
			'phone'             => $data['phone'] ?? '',
			'email'             => $data['email'] ?? '',
			'city'              => $data['city'] ?? '',
			'address'           => $data['address'] ?? '',
			'relationship_type' => $data['relationship_type'] ?? 'supplier',
			'payment_terms'     => $data['payment_terms'] ?? '',
			'performance_rating'=> $data['performance_rating'] ?? 0,
			'status'            => $data['status'] ?? 'active',
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
	 * Create a purchase order.
	 *
	 * @param array $data Purchase order data.
	 * @return int Purchase order ID.
	 */
	public function create_purchase_order( $data ) {
		global $wpdb;
		$prefix = $wpdb->prefix;

		$wpdb->insert(
			"{$prefix}pfp_purchase_orders",
			array(
				'supplier_id'  => $data['supplier_id'],
				'status'       => 'draft',
				'total_amount' => 0,
				'notes'        => $data['notes'] ?? '',
			)
		);

		$po_id = $wpdb->insert_id;
		$total = 0;

		if ( ! empty( $data['items'] ) ) {
			foreach ( $data['items'] as $item ) {
				$item_total = $item['quantity'] * $item['unit_price'];
				$wpdb->insert(
					"{$prefix}pfp_purchase_order_items",
					array(
						'purchase_order_id' => $po_id,
						'material_id'       => $item['material_id'],
						'quantity'          => $item['quantity'],
						'unit_price'        => $item['unit_price'],
						'total_price'       => $item_total,
					)
				);
				$total += $item_total;
			}
		}

		$wpdb->update(
			"{$prefix}pfp_purchase_orders",
			array( 'total_amount' => $total ),
			array( 'id' => $po_id )
		);

		return $po_id;
	}

	/**
	 * Get purchase orders.
	 *
	 * @param array $args Query args.
	 * @return array
	 */
	public function get_purchase_orders( $args = array() ) {
		global $wpdb;
		$prefix = $wpdb->prefix;

		$where = '1=1';
		$params = array();

		if ( ! empty( $args['supplier_id'] ) ) {
			$where .= ' AND po.supplier_id = %d';
			$params[] = $args['supplier_id'];
		}

		if ( ! empty( $args['status'] ) ) {
			$where .= ' AND po.status = %s';
			$params[] = $args['status'];
		}

		$sql = "SELECT po.*, s.name as supplier_name
				FROM {$prefix}pfp_purchase_orders po
				LEFT JOIN {$prefix}pfp_suppliers s ON po.supplier_id = s.id
				WHERE {$where}
				ORDER BY po.created_at DESC";

		if ( ! empty( $params ) ) {
			return $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
		}
		return $wpdb->get_results( $sql, ARRAY_A );
	}

	/**
	 * Receive a purchase order (mark as received and add stock).
	 *
	 * @param int $po_id Purchase order ID.
	 * @return bool
	 */
	public function receive_purchase_order( $po_id ) {
		global $wpdb;
		$prefix = $wpdb->prefix;

		$po = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$prefix}pfp_purchase_orders WHERE id = %d", $po_id ),
			ARRAY_A
		);

		if ( ! $po || 'received' === $po['status'] ) {
			return false;
		}

		// Get items.
		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$prefix}pfp_purchase_order_items WHERE purchase_order_id = %d",
				$po_id
			),
			ARRAY_A
		);

		// Add stock for each item.
		$inventory = PrintFlow_Pro::instance()->get_module( 'inventory' );
		if ( $inventory ) {
			foreach ( $items as $item ) {
				$inventory->record_movement(
					$item['material_id'],
					'in',
					$item['quantity'],
					'purchase_order',
					$po_id,
					sprintf( 'Réception bon de commande #%d', $po_id )
				);
			}
		}

		// Update PO status.
		$wpdb->update(
			"{$prefix}pfp_purchase_orders",
			array(
				'status'      => 'received',
				'received_at' => current_time( 'mysql' ),
			),
			array( 'id' => $po_id )
		);

		return true;
	}

	// AJAX handlers.

	public function ajax_save_supplier() {
		check_ajax_referer( 'pfp_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'pfp_manage_suppliers' ) ) {
			wp_send_json_error( array( 'message' => 'Accès refusé.' ) );
		}

		$data = array(
			'id'                => absint( $_POST['id'] ?? 0 ),
			'name'              => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
			'company'           => sanitize_text_field( wp_unslash( $_POST['company'] ?? '' ) ),
			'phone'             => sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) ),
			'email'             => sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ),
			'city'              => sanitize_text_field( wp_unslash( $_POST['city'] ?? '' ) ),
			'address'           => sanitize_textarea_field( wp_unslash( $_POST['address'] ?? '' ) ),
			'relationship_type' => sanitize_text_field( wp_unslash( $_POST['relationship_type'] ?? 'supplier' ) ),
			'payment_terms'     => sanitize_textarea_field( wp_unslash( $_POST['payment_terms'] ?? '' ) ),
			'status'            => sanitize_text_field( wp_unslash( $_POST['status'] ?? 'active' ) ),
		);

		if ( empty( $data['name'] ) ) {
			wp_send_json_error( array( 'message' => 'Le nom est obligatoire.' ) );
		}

		$supplier_id = $this->save_supplier( $data );
		wp_send_json_success( array( 'supplier_id' => $supplier_id, 'message' => 'Fournisseur enregistré.' ) );
	}

	public function ajax_delete_supplier() {
		check_ajax_referer( 'pfp_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'pfp_manage_suppliers' ) ) {
			wp_send_json_error( array( 'message' => 'Accès refusé.' ) );
		}

		$id = absint( $_POST['id'] ?? 0 );
		if ( ! $id ) {
			wp_send_json_error( array( 'message' => 'ID invalide.' ) );
		}

		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . 'pfp_suppliers',
			array( 'status' => 'inactive' ),
			array( 'id' => $id )
		);

		wp_send_json_success( array( 'message' => 'Fournisseur désactivé.' ) );
	}

	public function ajax_create_purchase_order() {
		check_ajax_referer( 'pfp_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'pfp_manage_suppliers' ) ) {
			wp_send_json_error( array( 'message' => 'Accès refusé.' ) );
		}

		$data = array(
			'supplier_id' => absint( $_POST['supplier_id'] ?? 0 ),
			'notes'       => sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) ),
			'items'       => isset( $_POST['items'] ) ? $_POST['items'] : array(),
		);

		if ( ! $data['supplier_id'] ) {
			wp_send_json_error( array( 'message' => 'Fournisseur invalide.' ) );
		}

		$po_id = $this->create_purchase_order( $data );
		wp_send_json_success( array( 'po_id' => $po_id, 'message' => 'Bon de commande créé.' ) );
	}

	public function ajax_update_purchase_order() {
		check_ajax_referer( 'pfp_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'pfp_manage_suppliers' ) ) {
			wp_send_json_error( array( 'message' => 'Accès refusé.' ) );
		}

		$po_id  = absint( $_POST['po_id'] ?? 0 );
		$action = sanitize_text_field( wp_unslash( $_POST['po_action'] ?? '' ) );

		if ( ! $po_id ) {
			wp_send_json_error( array( 'message' => 'ID invalide.' ) );
		}

		if ( 'receive' === $action ) {
			$this->receive_purchase_order( $po_id );
			wp_send_json_success( array( 'message' => 'Bon de commande reçu. Stock mis à jour.' ) );
		}

		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . 'pfp_purchase_orders',
			array( 'status' => $action ),
			array( 'id' => $po_id )
		);

		wp_send_json_success( array( 'message' => 'Bon de commande mis à jour.' ) );
	}

	public function ajax_record_payment() {
		check_ajax_referer( 'pfp_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'pfp_manage_suppliers' ) ) {
			wp_send_json_error( array( 'message' => 'Accès refusé.' ) );
		}

		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'pfp_supplier_payments',
			array(
				'supplier_id'       => absint( $_POST['supplier_id'] ?? 0 ),
				'purchase_order_id' => absint( $_POST['po_id'] ?? 0 ),
				'amount'            => floatval( $_POST['amount'] ?? 0 ),
				'method'            => sanitize_text_field( wp_unslash( $_POST['method'] ?? 'cash' ) ),
				'reference'         => sanitize_text_field( wp_unslash( $_POST['reference'] ?? '' ) ),
				'paid_at'           => sanitize_text_field( wp_unslash( $_POST['paid_at'] ?? current_time( 'mysql' ) ) ),
			)
		);

		wp_send_json_success( array( 'message' => 'Paiement enregistré.' ) );
	}
}
