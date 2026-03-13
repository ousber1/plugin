<?php
/**
 * Delivery Management module.
 *
 * @package PrintFlowPro
 */

defined( 'ABSPATH' ) || exit;

class PFP_Delivery {

	private $statuses = array(
		'pending'     => 'En attente',
		'assigned'    => 'Assignée',
		'in_transit'  => 'En cours de livraison',
		'delivered'   => 'Livrée',
		'failed'      => 'Échouée',
		'returned'    => 'Retournée',
	);

	public function init() {
		add_action( 'wp_ajax_pfp_create_delivery', array( $this, 'ajax_create_delivery' ) );
		add_action( 'wp_ajax_pfp_update_delivery', array( $this, 'ajax_update_delivery' ) );
		add_action( 'wp_ajax_pfp_get_deliveries', array( $this, 'ajax_get_deliveries' ) );
	}

	public function get_deliveries( $args = array() ) {
		global $wpdb;
		$prefix = $wpdb->prefix;
		$where  = '1=1';
		$params = array();

		if ( ! empty( $args['status'] ) ) {
			$where .= ' AND d.status = %s';
			$params[] = $args['status'];
		}
		if ( ! empty( $args['assigned_to'] ) ) {
			$where .= ' AND d.assigned_to = %d';
			$params[] = $args['assigned_to'];
		}

		$sql = "SELECT d.*, u.display_name as driver_name, dz.name as zone_name, dz.city as zone_city
				FROM {$prefix}pfp_deliveries d
				LEFT JOIN {$wpdb->users} u ON d.assigned_to = u.ID
				LEFT JOIN {$prefix}pfp_delivery_zones dz ON d.delivery_zone_id = dz.id
				WHERE {$where}
				ORDER BY d.created_at DESC
				LIMIT 50";

		if ( ! empty( $params ) ) {
			return $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
		}
		return $wpdb->get_results( $sql, ARRAY_A );
	}

	public function create_delivery( $data ) {
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'pfp_deliveries',
			array(
				'order_id'         => $data['order_id'],
				'assigned_to'      => $data['assigned_to'] ?? 0,
				'status'           => $data['assigned_to'] ? 'assigned' : 'pending',
				'delivery_zone_id' => $data['delivery_zone_id'] ?? 0,
				'tracking_ref'     => $data['tracking_ref'] ?? '',
				'delivery_cost'    => $data['delivery_cost'] ?? 0,
				'notes'            => $data['notes'] ?? '',
				'scheduled_at'     => $data['scheduled_at'] ?? null,
			)
		);
		return $wpdb->insert_id;
	}

	public function update_delivery_status( $delivery_id, $new_status, $notes = '' ) {
		global $wpdb;
		$prefix = $wpdb->prefix;

		$old_status = $wpdb->get_var(
			$wpdb->prepare( "SELECT status FROM {$prefix}pfp_deliveries WHERE id = %d", $delivery_id )
		);

		$update = array( 'status' => $new_status );
		if ( 'delivered' === $new_status ) {
			$update['delivered_at'] = current_time( 'mysql' );
		}

		$wpdb->update( "{$prefix}pfp_deliveries", $update, array( 'id' => $delivery_id ) );

		// Log.
		$wpdb->insert(
			"{$prefix}pfp_delivery_logs",
			array(
				'delivery_id' => $delivery_id,
				'from_status' => $old_status,
				'to_status'   => $new_status,
				'user_id'     => get_current_user_id(),
				'notes'       => $notes,
			)
		);

		// Update WooCommerce order status on delivery.
		if ( 'delivered' === $new_status ) {
			$order_id = $wpdb->get_var(
				$wpdb->prepare( "SELECT order_id FROM {$prefix}pfp_deliveries WHERE id = %d", $delivery_id )
			);
			if ( $order_id ) {
				$order = wc_get_order( $order_id );
				if ( $order ) {
					$order->update_status( 'completed', __( 'Livraison confirmée.', 'printflow-pro' ) );
				}
			}
		}

		return true;
	}

	public function get_delivery_zones() {
		global $wpdb;
		return $wpdb->get_results(
			"SELECT * FROM {$wpdb->prefix}pfp_delivery_zones WHERE status = 'active' ORDER BY city",
			ARRAY_A
		);
	}

	// AJAX handlers.

	public function ajax_create_delivery() {
		check_ajax_referer( 'pfp_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'pfp_manage_delivery' ) ) {
			wp_send_json_error( array( 'message' => 'Accès refusé.' ) );
		}

		$data = array(
			'order_id'         => absint( $_POST['order_id'] ?? 0 ),
			'assigned_to'      => absint( $_POST['assigned_to'] ?? 0 ),
			'delivery_zone_id' => absint( $_POST['delivery_zone_id'] ?? 0 ),
			'delivery_cost'    => floatval( $_POST['delivery_cost'] ?? 0 ),
			'notes'            => sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) ),
			'scheduled_at'     => sanitize_text_field( wp_unslash( $_POST['scheduled_at'] ?? '' ) ),
		);

		if ( ! $data['order_id'] ) {
			wp_send_json_error( array( 'message' => 'ID commande invalide.' ) );
		}

		$id = $this->create_delivery( $data );
		wp_send_json_success( array( 'id' => $id, 'message' => 'Livraison créée.' ) );
	}

	public function ajax_update_delivery() {
		check_ajax_referer( 'pfp_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'pfp_manage_delivery' ) ) {
			wp_send_json_error( array( 'message' => 'Accès refusé.' ) );
		}

		$id     = absint( $_POST['delivery_id'] ?? 0 );
		$status = sanitize_text_field( wp_unslash( $_POST['status'] ?? '' ) );
		$notes  = sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) );

		if ( ! $id || ! isset( $this->statuses[ $status ] ) ) {
			wp_send_json_error( array( 'message' => 'Paramètres invalides.' ) );
		}

		$this->update_delivery_status( $id, $status, $notes );
		wp_send_json_success( array( 'message' => 'Livraison mise à jour.' ) );
	}

	public function ajax_get_deliveries() {
		check_ajax_referer( 'pfp_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'pfp_manage_delivery' ) ) {
			wp_send_json_error( array( 'message' => 'Accès refusé.' ) );
		}

		$args = array();
		if ( ! empty( $_POST['status'] ) ) {
			$args['status'] = sanitize_text_field( wp_unslash( $_POST['status'] ) );
		}

		wp_send_json_success( $this->get_deliveries( $args ) );
	}

	public function get_statuses() {
		return $this->statuses;
	}
}
