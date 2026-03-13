<?php
/**
 * Financial Management module (Income, Expenses, Invoicing, Profit).
 *
 * @package PrintFlowPro
 */

defined( 'ABSPATH' ) || exit;

class PFP_Finance {

	public function init() {
		add_action( 'wp_ajax_pfp_save_expense', array( $this, 'ajax_save_expense' ) );
		add_action( 'wp_ajax_pfp_save_income', array( $this, 'ajax_save_income' ) );
		add_action( 'wp_ajax_pfp_create_invoice', array( $this, 'ajax_create_invoice' ) );
		add_action( 'wp_ajax_pfp_record_payment', array( $this, 'ajax_record_payment' ) );
		add_action( 'wp_ajax_pfp_get_financial_summary', array( $this, 'ajax_get_summary' ) );
		add_action( 'wp_ajax_pfp_export_finances', array( $this, 'ajax_export' ) );
	}

	/**
	 * Get financial summary for a date range.
	 *
	 * @param string $start_date Start date (Y-m-d).
	 * @param string $end_date   End date (Y-m-d).
	 * @return array
	 */
	public function get_summary( $start_date, $end_date ) {
		global $wpdb;
		$prefix = $wpdb->prefix;

		$total_income = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(amount), 0) FROM {$prefix}pfp_income WHERE received_at BETWEEN %s AND %s",
				$start_date,
				$end_date . ' 23:59:59'
			)
		);

		$total_expenses = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(amount), 0) FROM {$prefix}pfp_expenses WHERE expense_date BETWEEN %s AND %s",
				$start_date,
				$end_date
			)
		);

		$expenses_by_category = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ec.name as category, COALESCE(SUM(e.amount), 0) as total
				FROM {$prefix}pfp_expenses e
				LEFT JOIN {$prefix}pfp_expense_categories ec ON e.category_id = ec.id
				WHERE e.expense_date BETWEEN %s AND %s
				GROUP BY e.category_id
				ORDER BY total DESC",
				$start_date,
				$end_date
			),
			ARRAY_A
		);

		$invoices_paid = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(total_amount), 0) FROM {$prefix}pfp_invoices WHERE status = 'paid' AND paid_at BETWEEN %s AND %s",
				$start_date,
				$end_date . ' 23:59:59'
			)
		);

		$invoices_outstanding = (float) $wpdb->get_var(
			"SELECT COALESCE(SUM(total_amount), 0) FROM {$prefix}pfp_invoices WHERE status IN ('sent', 'overdue')"
		);

		$profit = $total_income - $total_expenses;
		$margin = $total_income > 0 ? round( ( $profit / $total_income ) * 100, 1 ) : 0;

		return array(
			'total_income'          => $total_income,
			'total_expenses'        => $total_expenses,
			'profit'                => $profit,
			'margin_percentage'     => $margin,
			'expenses_by_category'  => $expenses_by_category,
			'invoices_paid'         => $invoices_paid,
			'invoices_outstanding'  => $invoices_outstanding,
			'start_date'            => $start_date,
			'end_date'              => $end_date,
		);
	}

	/**
	 * Get profit per order.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	public function get_order_profit( $order_id ) {
		global $wpdb;
		$prefix = $wpdb->prefix;

		$income = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(amount), 0) FROM {$prefix}pfp_income WHERE order_id = %d",
				$order_id
			)
		);

		// Calculate COGS from material consumption.
		$order = wc_get_order( $order_id );
		$cogs  = 0;

		if ( $order ) {
			foreach ( $order->get_items() as $item ) {
				$product_id = $item->get_product_id();
				$qty        = $item->get_quantity();

				$mappings = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT mpm.quantity_per_unit, m.purchase_cost
						FROM {$prefix}pfp_material_product_map mpm
						INNER JOIN {$prefix}pfp_materials m ON mpm.material_id = m.id
						WHERE mpm.product_id = %d",
						$product_id
					),
					ARRAY_A
				);

				foreach ( $mappings as $map ) {
					$cogs += $map['quantity_per_unit'] * $qty * $map['purchase_cost'];
				}
			}
		}

		$profit = $income - $cogs;

		return array(
			'order_id' => $order_id,
			'income'   => $income,
			'cogs'     => $cogs,
			'profit'   => $profit,
			'margin'   => $income > 0 ? round( ( $profit / $income ) * 100, 1 ) : 0,
		);
	}

	/**
	 * Record an expense.
	 *
	 * @param array $data Expense data.
	 * @return int Expense ID.
	 */
	public function record_expense( $data ) {
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'pfp_expenses',
			array(
				'category_id'    => $data['category_id'] ?? 0,
				'amount'         => $data['amount'],
				'description'    => $data['description'],
				'payment_method' => $data['payment_method'] ?? 'cash',
				'reference'      => $data['reference'] ?? '',
				'receipt_file'   => $data['receipt_file'] ?? '',
				'expense_date'   => $data['expense_date'] ?? current_time( 'Y-m-d' ),
			)
		);
		return $wpdb->insert_id;
	}

	/**
	 * Record income manually.
	 *
	 * @param array $data Income data.
	 * @return int Income ID.
	 */
	public function record_income( $data ) {
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'pfp_income',
			array(
				'order_id'       => $data['order_id'] ?? 0,
				'amount'         => $data['amount'],
				'payment_method' => $data['payment_method'] ?? 'cash',
				'reference'      => $data['reference'] ?? '',
				'category'       => $data['category'] ?? 'other',
				'notes'          => $data['notes'] ?? '',
				'received_at'    => $data['received_at'] ?? current_time( 'mysql' ),
			)
		);
		return $wpdb->insert_id;
	}

	/**
	 * Create an invoice from an order.
	 *
	 * @param int $order_id Order ID.
	 * @return int Invoice ID.
	 */
	public function create_invoice( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return 0;
		}

		global $wpdb;
		$prefix = $wpdb->prefix;

		// Check if invoice already exists.
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$prefix}pfp_invoices WHERE order_id = %d",
				$order_id
			)
		);
		if ( $exists ) {
			return $exists;
		}

		$invoice_number = $this->generate_invoice_number();
		$tax_rate       = floatval( get_option( 'pfp_tax_rate', 20 ) );
		$total          = $order->get_total();
		$tax_amount     = $total * ( $tax_rate / ( 100 + $tax_rate ) );

		$wpdb->insert(
			"{$prefix}pfp_invoices",
			array(
				'order_id'       => $order_id,
				'customer_id'    => $order->get_customer_id(),
				'invoice_number' => $invoice_number,
				'total_amount'   => $total,
				'tax_amount'     => round( $tax_amount, 2 ),
				'status'         => 'draft',
				'due_date'       => gmdate( 'Y-m-d', strtotime( '+30 days' ) ),
			)
		);

		$invoice_id = $wpdb->insert_id;

		// Add items.
		foreach ( $order->get_items() as $item ) {
			$wpdb->insert(
				"{$prefix}pfp_invoice_items",
				array(
					'invoice_id'  => $invoice_id,
					'description' => $item->get_name(),
					'quantity'    => $item->get_quantity(),
					'unit_price'  => $item->get_total() / max( 1, $item->get_quantity() ),
					'total_price' => $item->get_total(),
				)
			);
		}

		return $invoice_id;
	}

	/**
	 * Generate a unique invoice number.
	 *
	 * @return string
	 */
	private function generate_invoice_number() {
		$prefix = 'FAC-';
		$year   = gmdate( 'Y' );

		global $wpdb;
		$last = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT invoice_number FROM {$wpdb->prefix}pfp_invoices WHERE invoice_number LIKE %s ORDER BY id DESC LIMIT 1",
				$prefix . $year . '-%'
			)
		);

		$seq = 1;
		if ( $last ) {
			$parts = explode( '-', $last );
			$seq   = (int) end( $parts ) + 1;
		}

		return $prefix . $year . '-' . str_pad( $seq, 5, '0', STR_PAD_LEFT );
	}

	/**
	 * Get expense categories.
	 *
	 * @return array
	 */
	public function get_expense_categories() {
		global $wpdb;
		return $wpdb->get_results(
			"SELECT * FROM {$wpdb->prefix}pfp_expense_categories ORDER BY name",
			ARRAY_A
		);
	}

	/**
	 * Get expenses with optional filtering.
	 *
	 * @param array $args Query args.
	 * @return array
	 */
	public function get_expenses( $args = array() ) {
		global $wpdb;
		$prefix = $wpdb->prefix;
		$where  = '1=1';
		$params = array();

		if ( ! empty( $args['start_date'] ) ) {
			$where .= ' AND e.expense_date >= %s';
			$params[] = $args['start_date'];
		}
		if ( ! empty( $args['end_date'] ) ) {
			$where .= ' AND e.expense_date <= %s';
			$params[] = $args['end_date'];
		}
		if ( ! empty( $args['category_id'] ) ) {
			$where .= ' AND e.category_id = %d';
			$params[] = $args['category_id'];
		}

		$limit  = isset( $args['limit'] ) ? absint( $args['limit'] ) : 50;
		$offset = isset( $args['offset'] ) ? absint( $args['offset'] ) : 0;

		$sql = "SELECT e.*, ec.name as category_name
				FROM {$prefix}pfp_expenses e
				LEFT JOIN {$prefix}pfp_expense_categories ec ON e.category_id = ec.id
				WHERE {$where}
				ORDER BY e.expense_date DESC
				LIMIT %d OFFSET %d";

		$params[] = $limit;
		$params[] = $offset;

		return $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
	}

	/**
	 * Get income records.
	 *
	 * @param array $args Query args.
	 * @return array
	 */
	public function get_income( $args = array() ) {
		global $wpdb;
		$prefix = $wpdb->prefix;
		$where  = '1=1';
		$params = array();

		if ( ! empty( $args['start_date'] ) ) {
			$where .= ' AND received_at >= %s';
			$params[] = $args['start_date'];
		}
		if ( ! empty( $args['end_date'] ) ) {
			$where .= ' AND received_at <= %s';
			$params[] = $args['end_date'] . ' 23:59:59';
		}

		$limit  = isset( $args['limit'] ) ? absint( $args['limit'] ) : 50;
		$offset = isset( $args['offset'] ) ? absint( $args['offset'] ) : 0;

		$sql = "SELECT * FROM {$prefix}pfp_income WHERE {$where} ORDER BY received_at DESC LIMIT %d OFFSET %d";
		$params[] = $limit;
		$params[] = $offset;

		return $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
	}

	// AJAX handlers.

	public function ajax_save_expense() {
		check_ajax_referer( 'pfp_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'pfp_manage_finance' ) ) {
			wp_send_json_error( array( 'message' => 'Accès refusé.' ) );
		}

		$data = array(
			'category_id'    => absint( $_POST['category_id'] ?? 0 ),
			'amount'         => floatval( $_POST['amount'] ?? 0 ),
			'description'    => sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) ),
			'payment_method' => sanitize_text_field( wp_unslash( $_POST['payment_method'] ?? 'cash' ) ),
			'reference'      => sanitize_text_field( wp_unslash( $_POST['reference'] ?? '' ) ),
			'expense_date'   => sanitize_text_field( wp_unslash( $_POST['expense_date'] ?? '' ) ),
		);

		if ( $data['amount'] <= 0 || empty( $data['description'] ) ) {
			wp_send_json_error( array( 'message' => 'Montant et description sont obligatoires.' ) );
		}

		$id = $this->record_expense( $data );
		wp_send_json_success( array( 'id' => $id, 'message' => 'Dépense enregistrée.' ) );
	}

	public function ajax_save_income() {
		check_ajax_referer( 'pfp_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'pfp_manage_finance' ) ) {
			wp_send_json_error( array( 'message' => 'Accès refusé.' ) );
		}

		$data = array(
			'amount'         => floatval( $_POST['amount'] ?? 0 ),
			'payment_method' => sanitize_text_field( wp_unslash( $_POST['payment_method'] ?? 'cash' ) ),
			'reference'      => sanitize_text_field( wp_unslash( $_POST['reference'] ?? '' ) ),
			'category'       => sanitize_text_field( wp_unslash( $_POST['category'] ?? 'other' ) ),
			'notes'          => sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) ),
			'received_at'    => sanitize_text_field( wp_unslash( $_POST['received_at'] ?? current_time( 'mysql' ) ) ),
		);

		if ( $data['amount'] <= 0 ) {
			wp_send_json_error( array( 'message' => 'Le montant doit être positif.' ) );
		}

		$id = $this->record_income( $data );
		wp_send_json_success( array( 'id' => $id, 'message' => 'Revenu enregistré.' ) );
	}

	public function ajax_create_invoice() {
		check_ajax_referer( 'pfp_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'pfp_manage_finance' ) ) {
			wp_send_json_error( array( 'message' => 'Accès refusé.' ) );
		}

		$order_id   = absint( $_POST['order_id'] ?? 0 );
		$invoice_id = $this->create_invoice( $order_id );

		if ( $invoice_id ) {
			wp_send_json_success( array( 'invoice_id' => $invoice_id, 'message' => 'Facture créée.' ) );
		} else {
			wp_send_json_error( array( 'message' => 'Impossible de créer la facture.' ) );
		}
	}

	public function ajax_record_payment() {
		check_ajax_referer( 'pfp_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'pfp_manage_finance' ) ) {
			wp_send_json_error( array( 'message' => 'Accès refusé.' ) );
		}

		$invoice_id = absint( $_POST['invoice_id'] ?? 0 );
		$amount     = floatval( $_POST['amount'] ?? 0 );
		$method     = sanitize_text_field( wp_unslash( $_POST['method'] ?? 'cash' ) );

		if ( ! $invoice_id || $amount <= 0 ) {
			wp_send_json_error( array( 'message' => 'Paramètres invalides.' ) );
		}

		global $wpdb;
		$prefix = $wpdb->prefix;

		$wpdb->insert(
			"{$prefix}pfp_payments",
			array(
				'invoice_id' => $invoice_id,
				'amount'     => $amount,
				'method'     => $method,
				'reference'  => sanitize_text_field( wp_unslash( $_POST['reference'] ?? '' ) ),
				'paid_at'    => current_time( 'mysql' ),
			)
		);

		// Check if invoice is fully paid.
		$invoice_total = (float) $wpdb->get_var(
			$wpdb->prepare( "SELECT total_amount FROM {$prefix}pfp_invoices WHERE id = %d", $invoice_id )
		);
		$total_paid = (float) $wpdb->get_var(
			$wpdb->prepare( "SELECT COALESCE(SUM(amount), 0) FROM {$prefix}pfp_payments WHERE invoice_id = %d", $invoice_id )
		);

		if ( $total_paid >= $invoice_total ) {
			$wpdb->update(
				"{$prefix}pfp_invoices",
				array( 'status' => 'paid', 'paid_at' => current_time( 'mysql' ) ),
				array( 'id' => $invoice_id )
			);
		}

		wp_send_json_success( array( 'message' => 'Paiement enregistré.' ) );
	}

	public function ajax_get_summary() {
		check_ajax_referer( 'pfp_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'pfp_manage_finance' ) ) {
			wp_send_json_error( array( 'message' => 'Accès refusé.' ) );
		}

		$start = sanitize_text_field( wp_unslash( $_POST['start_date'] ?? gmdate( 'Y-m-01' ) ) );
		$end   = sanitize_text_field( wp_unslash( $_POST['end_date'] ?? gmdate( 'Y-m-d' ) ) );

		wp_send_json_success( $this->get_summary( $start, $end ) );
	}

	public function ajax_export() {
		check_ajax_referer( 'pfp_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'pfp_manage_finance' ) ) {
			wp_send_json_error( array( 'message' => 'Accès refusé.' ) );
		}

		$type  = sanitize_text_field( wp_unslash( $_POST['type'] ?? 'summary' ) );
		$start = sanitize_text_field( wp_unslash( $_POST['start_date'] ?? gmdate( 'Y-m-01' ) ) );
		$end   = sanitize_text_field( wp_unslash( $_POST['end_date'] ?? gmdate( 'Y-m-d' ) ) );

		$data = array();
		if ( 'expenses' === $type ) {
			$data = $this->get_expenses( array( 'start_date' => $start, 'end_date' => $end, 'limit' => 1000 ) );
		} elseif ( 'income' === $type ) {
			$data = $this->get_income( array( 'start_date' => $start, 'end_date' => $end, 'limit' => 1000 ) );
		} else {
			$data = $this->get_summary( $start, $end );
		}

		wp_send_json_success( array( 'data' => $data, 'type' => $type ) );
	}
}
