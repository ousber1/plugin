<?php
/**
 * Order Management module.
 *
 * Extends WooCommerce orders with printing-specific features.
 *
 * @package PrintFlowPro
 */

defined( 'ABSPATH' ) || exit;

class PFP_Orders {

	/**
	 * Initialize the module.
	 */
	public function init() {
		// When an order is placed, create production job and financial records.
		add_action( 'woocommerce_order_status_changed', array( $this, 'handle_status_change' ), 10, 4 );
		add_action( 'woocommerce_new_order', array( $this, 'handle_new_order' ), 10, 2 );

		// Add custom columns to orders list.
		add_filter( 'manage_woocommerce_page_wc-orders_columns', array( $this, 'add_order_columns' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_pfp_get_order_timeline', array( $this, 'ajax_get_timeline' ) );
	}

	/**
	 * Handle new order creation.
	 *
	 * @param int      $order_id Order ID.
	 * @param WC_Order $order    Order object.
	 */
	public function handle_new_order( $order_id, $order ) {
		// Record order creation in timeline.
		$this->log_timeline( $order_id, 'Commande créée.' );
	}

	/**
	 * Handle order status changes.
	 *
	 * @param int      $order_id   Order ID.
	 * @param string   $old_status Old status.
	 * @param string   $new_status New status.
	 * @param WC_Order $order      Order object.
	 */
	public function handle_status_change( $order_id, $old_status, $new_status, $order ) {
		// Log timeline entry.
		$this->log_timeline(
			$order_id,
			sprintf( 'Statut changé de "%s" à "%s".', $old_status, $new_status )
		);

		// Create production job when order is processing.
		if ( 'processing' === $new_status ) {
			$this->create_production_jobs( $order );
		}

		// Record income when order is completed.
		if ( 'completed' === $new_status ) {
			$this->record_income( $order );
		}

		// Trigger notification.
		do_action( 'pfp_order_status_changed', $order_id, $old_status, $new_status, $order );
	}

	/**
	 * Create production jobs for an order.
	 *
	 * @param WC_Order $order Order object.
	 */
	private function create_production_jobs( $order ) {
		global $wpdb;
		$table = $wpdb->prefix . 'pfp_production_jobs';

		foreach ( $order->get_items() as $item_id => $item ) {
			$product = $item->get_product();
			if ( ! $product ) {
				continue;
			}

			// Only create production jobs for PrintFlow products.
			if ( 'yes' !== $product->get_meta( '_pfp_is_printflow_product' ) ) {
				continue;
			}

			// Check if job already exists.
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$table} WHERE order_id = %d AND order_item_id = %d",
					$order->get_id(),
					$item_id
				)
			);

			if ( $exists ) {
				continue;
			}

			$wpdb->insert(
				$table,
				array(
					'order_id'      => $order->get_id(),
					'order_item_id' => $item_id,
					'status'        => 'nouveau',
					'priority'      => 'normal',
				),
				array( '%d', '%d', '%s', '%s' )
			);
		}
	}

	/**
	 * Record income from completed order.
	 *
	 * @param WC_Order $order Order object.
	 */
	private function record_income( $order ) {
		global $wpdb;
		$table = $wpdb->prefix . 'pfp_income';

		// Check if already recorded.
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE order_id = %d",
				$order->get_id()
			)
		);

		if ( $exists ) {
			return;
		}

		$wpdb->insert(
			$table,
			array(
				'order_id'       => $order->get_id(),
				'amount'         => $order->get_total(),
				'payment_method' => $order->get_payment_method(),
				'category'       => 'order',
				'notes'          => sprintf( 'Revenu de la commande #%s', $order->get_order_number() ),
				'received_at'    => $order->get_date_completed() ? $order->get_date_completed()->date( 'Y-m-d H:i:s' ) : current_time( 'mysql' ),
			),
			array( '%d', '%f', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Log an entry in the order timeline.
	 *
	 * @param int    $order_id Order ID.
	 * @param string $message  Timeline message.
	 */
	public function log_timeline( $order_id, $message ) {
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'pfp_production_logs',
			array(
				'job_id'      => 0,
				'from_status' => '',
				'to_status'   => 'info',
				'user_id'     => get_current_user_id(),
				'notes'       => $message,
			),
			array( '%d', '%s', '%s', '%d', '%s' )
		);

		// Also store as order meta for easy retrieval.
		$order = wc_get_order( $order_id );
		if ( $order ) {
			$order->add_order_note( '[PrintFlow] ' . $message );
		}
	}

	/**
	 * Add custom columns to the orders list.
	 *
	 * @param array $columns Existing columns.
	 * @return array Modified columns.
	 */
	public function add_order_columns( $columns ) {
		$new_columns = array();
		foreach ( $columns as $key => $label ) {
			$new_columns[ $key ] = $label;
			if ( 'order_status' === $key ) {
				$new_columns['pfp_production'] = __( 'Production', 'printflow-pro' );
			}
		}
		return $new_columns;
	}

	/**
	 * AJAX handler: get order timeline.
	 */
	public function ajax_get_timeline() {
		check_ajax_referer( 'pfp_admin_nonce', 'nonce' );

		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		if ( ! $order_id ) {
			wp_send_json_error( array( 'message' => 'ID de commande invalide.' ) );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_send_json_error( array( 'message' => 'Commande introuvable.' ) );
		}

		$notes = wc_get_order_notes( array( 'order_id' => $order_id ) );
		$timeline = array();

		foreach ( $notes as $note ) {
			$timeline[] = array(
				'date'    => $note->date_created->date( 'd/m/Y H:i' ),
				'content' => $note->content,
				'author'  => $note->added_by,
			);
		}

		wp_send_json_success( array( 'timeline' => $timeline ) );
	}
}
