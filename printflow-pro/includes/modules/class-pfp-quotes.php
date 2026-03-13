<?php
/**
 * Quote Request Management module.
 *
 * @package PrintFlowPro
 */

defined( 'ABSPATH' ) || exit;

class PFP_Quotes {

	/**
	 * Valid quote statuses.
	 *
	 * @var array
	 */
	private $statuses = array(
		'nouveau'  => 'Nouveau',
		'en_cours' => 'En cours',
		'envoye'   => 'Envoyé',
		'accepte'  => 'Accepté',
		'refuse'   => 'Refusé',
		'expire'   => 'Expiré',
	);

	/**
	 * Initialize the module.
	 */
	public function init() {
		add_action( 'wp_ajax_pfp_submit_quote_request', array( $this, 'ajax_submit_quote' ) );
		add_action( 'wp_ajax_nopriv_pfp_submit_quote_request', array( $this, 'ajax_submit_quote' ) );
		add_action( 'wp_ajax_pfp_update_quote', array( $this, 'ajax_update_quote' ) );
		add_action( 'wp_ajax_pfp_convert_quote_to_order', array( $this, 'ajax_convert_to_order' ) );
	}

	/**
	 * Get all quotes with optional filtering.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public function get_quotes( $args = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'pfp_quotes';

		$where = '1=1';
		$params = array();

		if ( ! empty( $args['status'] ) ) {
			$where .= ' AND status = %s';
			$params[] = $args['status'];
		}

		if ( ! empty( $args['customer_id'] ) ) {
			$where .= ' AND customer_id = %d';
			$params[] = $args['customer_id'];
		}

		$orderby = 'created_at DESC';
		$limit   = isset( $args['limit'] ) ? absint( $args['limit'] ) : 20;
		$offset  = isset( $args['offset'] ) ? absint( $args['offset'] ) : 0;

		$sql = "SELECT * FROM {$table} WHERE {$where} ORDER BY {$orderby} LIMIT %d OFFSET %d";
		$params[] = $limit;
		$params[] = $offset;

		return $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
	}

	/**
	 * Get a single quote by ID.
	 *
	 * @param int $quote_id Quote ID.
	 * @return array|null
	 */
	public function get_quote( $quote_id ) {
		global $wpdb;
		$quote = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}pfp_quotes WHERE id = %d",
				$quote_id
			),
			ARRAY_A
		);

		if ( $quote ) {
			$quote['items'] = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}pfp_quote_items WHERE quote_id = %d",
					$quote_id
				),
				ARRAY_A
			);
		}

		return $quote;
	}

	/**
	 * Create a new quote.
	 *
	 * @param array $data Quote data.
	 * @return int|false Quote ID or false.
	 */
	public function create_quote( $data ) {
		global $wpdb;

		$quote_number = $this->generate_quote_number();

		$wpdb->insert(
			$wpdb->prefix . 'pfp_quotes',
			array(
				'quote_number'   => $quote_number,
				'customer_id'    => $data['customer_id'] ?? 0,
				'customer_name'  => $data['customer_name'] ?? '',
				'customer_email' => $data['customer_email'] ?? '',
				'customer_phone' => $data['customer_phone'] ?? '',
				'status'         => 'nouveau',
				'total_amount'   => $data['total_amount'] ?? 0,
				'valid_until'    => $data['valid_until'] ?? gmdate( 'Y-m-d', strtotime( '+30 days' ) ),
				'notes'          => $data['notes'] ?? '',
			)
		);

		$quote_id = $wpdb->insert_id;

		// Insert quote items.
		if ( $quote_id && ! empty( $data['items'] ) ) {
			foreach ( $data['items'] as $item ) {
				$wpdb->insert(
					$wpdb->prefix . 'pfp_quote_items',
					array(
						'quote_id'       => $quote_id,
						'product_id'     => $item['product_id'] ?? 0,
						'description'    => $item['description'] ?? '',
						'quantity'       => $item['quantity'] ?? 1,
						'unit_price'     => $item['unit_price'] ?? 0,
						'total_price'    => $item['total_price'] ?? 0,
						'specifications' => wp_json_encode( $item['specifications'] ?? array() ),
					)
				);
			}
		}

		// Log history.
		$this->log_history( $quote_id, '', 'nouveau', 'Devis créé.' );

		return $quote_id;
	}

	/**
	 * Update quote status.
	 *
	 * @param int    $quote_id   Quote ID.
	 * @param string $new_status New status.
	 * @param string $notes      Notes.
	 * @return bool
	 */
	public function update_status( $quote_id, $new_status, $notes = '' ) {
		global $wpdb;
		$table = $wpdb->prefix . 'pfp_quotes';

		$old_status = $wpdb->get_var(
			$wpdb->prepare( "SELECT status FROM {$table} WHERE id = %d", $quote_id )
		);

		$updated = $wpdb->update(
			$table,
			array( 'status' => $new_status ),
			array( 'id' => $quote_id ),
			array( '%s' ),
			array( '%d' )
		);

		if ( $updated ) {
			$this->log_history( $quote_id, $old_status, $new_status, $notes );
		}

		return (bool) $updated;
	}

	/**
	 * Convert a quote to a WooCommerce order.
	 *
	 * @param int $quote_id Quote ID.
	 * @return int|false Order ID or false.
	 */
	public function convert_to_order( $quote_id ) {
		$quote = $this->get_quote( $quote_id );
		if ( ! $quote || 'accepte' !== $quote['status'] ) {
			return false;
		}

		$order = wc_create_order();

		// Set customer.
		if ( $quote['customer_id'] ) {
			$order->set_customer_id( $quote['customer_id'] );
		}
		$order->set_billing_first_name( $quote['customer_name'] );
		$order->set_billing_email( $quote['customer_email'] );
		$order->set_billing_phone( $quote['customer_phone'] );

		// Add items.
		foreach ( $quote['items'] as $item ) {
			if ( $item['product_id'] ) {
				$product = wc_get_product( $item['product_id'] );
				if ( $product ) {
					$order->add_product( $product, $item['quantity'], array( 'total' => $item['total_price'] ) );
					continue;
				}
			}
			// Add as fee if no product.
			$fee = new WC_Order_Item_Fee();
			$fee->set_name( $item['description'] );
			$fee->set_total( $item['total_price'] );
			$order->add_item( $fee );
		}

		$order->calculate_totals();
		$order->add_order_note( sprintf( 'Commande créée depuis le devis #%s', $quote['quote_number'] ) );
		$order->save();

		// Update quote.
		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . 'pfp_quotes',
			array( 'converted_order_id' => $order->get_id() ),
			array( 'id' => $quote_id )
		);

		return $order->get_id();
	}

	/**
	 * Generate a unique quote number.
	 *
	 * @return string
	 */
	private function generate_quote_number() {
		$prefix = 'DEV-';
		$year   = gmdate( 'Y' );

		global $wpdb;
		$last_number = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT quote_number FROM {$wpdb->prefix}pfp_quotes WHERE quote_number LIKE %s ORDER BY id DESC LIMIT 1",
				$prefix . $year . '-%'
			)
		);

		if ( $last_number ) {
			$parts  = explode( '-', $last_number );
			$seq    = (int) end( $parts ) + 1;
		} else {
			$seq = 1;
		}

		return $prefix . $year . '-' . str_pad( $seq, 4, '0', STR_PAD_LEFT );
	}

	/**
	 * Log quote status change.
	 *
	 * @param int    $quote_id    Quote ID.
	 * @param string $from_status From status.
	 * @param string $to_status   To status.
	 * @param string $notes       Notes.
	 */
	private function log_history( $quote_id, $from_status, $to_status, $notes = '' ) {
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'pfp_quote_history',
			array(
				'quote_id'    => $quote_id,
				'from_status' => $from_status,
				'to_status'   => $to_status,
				'user_id'     => get_current_user_id(),
				'notes'       => $notes,
			)
		);
	}

	/**
	 * AJAX handler: submit quote request from frontend.
	 */
	public function ajax_submit_quote() {
		check_ajax_referer( 'pfp_quote_nonce', 'nonce' );

		$data = array(
			'customer_id'    => get_current_user_id(),
			'customer_name'  => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
			'customer_email' => sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ),
			'customer_phone' => sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) ),
			'notes'          => sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) ),
			'items'          => array(
				array(
					'description'    => sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) ),
					'quantity'       => absint( $_POST['quantity'] ?? 1 ),
					'specifications' => array(
						'product_type' => sanitize_text_field( wp_unslash( $_POST['product_type'] ?? '' ) ),
						'size'         => sanitize_text_field( wp_unslash( $_POST['size'] ?? '' ) ),
						'material'     => sanitize_text_field( wp_unslash( $_POST['material'] ?? '' ) ),
					),
				),
			),
		);

		if ( empty( $data['customer_name'] ) || empty( $data['customer_email'] ) ) {
			wp_send_json_error( array( 'message' => 'Veuillez remplir tous les champs obligatoires.' ) );
		}

		$quote_id = $this->create_quote( $data );

		if ( $quote_id ) {
			wp_send_json_success(
				array(
					'message'  => 'Votre demande de devis a été envoyée avec succès. Nous vous contacterons dans les plus brefs délais.',
					'quote_id' => $quote_id,
				)
			);
		} else {
			wp_send_json_error( array( 'message' => 'Une erreur est survenue. Veuillez réessayer.' ) );
		}
	}

	/**
	 * AJAX handler: update quote.
	 */
	public function ajax_update_quote() {
		check_ajax_referer( 'pfp_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'pfp_manage_quotes' ) ) {
			wp_send_json_error( array( 'message' => 'Accès refusé.' ) );
		}

		$quote_id   = isset( $_POST['quote_id'] ) ? absint( $_POST['quote_id'] ) : 0;
		$new_status = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';
		$notes      = isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '';

		if ( ! $quote_id || ! isset( $this->statuses[ $new_status ] ) ) {
			wp_send_json_error( array( 'message' => 'Paramètres invalides.' ) );
		}

		$this->update_status( $quote_id, $new_status, $notes );

		wp_send_json_success( array( 'message' => 'Devis mis à jour.' ) );
	}

	/**
	 * AJAX handler: convert quote to order.
	 */
	public function ajax_convert_to_order() {
		check_ajax_referer( 'pfp_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'pfp_manage_quotes' ) ) {
			wp_send_json_error( array( 'message' => 'Accès refusé.' ) );
		}

		$quote_id = isset( $_POST['quote_id'] ) ? absint( $_POST['quote_id'] ) : 0;
		if ( ! $quote_id ) {
			wp_send_json_error( array( 'message' => 'ID de devis invalide.' ) );
		}

		$order_id = $this->convert_to_order( $quote_id );

		if ( $order_id ) {
			wp_send_json_success(
				array(
					'message'  => 'Commande créée avec succès.',
					'order_id' => $order_id,
				)
			);
		} else {
			wp_send_json_error( array( 'message' => 'Impossible de convertir le devis.' ) );
		}
	}

	/**
	 * Get available statuses.
	 *
	 * @return array
	 */
	public function get_statuses() {
		return $this->statuses;
	}
}
