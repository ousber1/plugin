<?php
/**
 * Dashboard module.
 *
 * @package PrintFlowPro
 */

defined( 'ABSPATH' ) || exit;

class PFP_Dashboard {

	/**
	 * Initialize the module.
	 */
	public function init() {
		add_action( 'wp_ajax_pfp_dashboard_stats', array( $this, 'ajax_get_stats' ) );
	}

	/**
	 * Get dashboard statistics.
	 *
	 * @return array
	 */
	public function get_stats() {
		return array(
			'orders_today'     => $this->get_orders_count_today(),
			'revenue_today'    => $this->get_revenue_today(),
			'revenue_month'    => $this->get_revenue_month(),
			'profit_month'     => $this->get_profit_month(),
			'pending_quotes'   => $this->get_pending_quotes_count(),
			'production_stats' => $this->get_production_stats(),
			'low_stock_count'  => $this->get_low_stock_count(),
			'top_products'     => $this->get_top_products(),
			'top_customers'    => $this->get_top_customers(),
			'recent_orders'    => $this->get_recent_orders(),
		);
	}

	/**
	 * AJAX handler for dashboard statistics.
	 */
	public function ajax_get_stats() {
		check_ajax_referer( 'pfp_dashboard_nonce', 'nonce' );

		if ( ! current_user_can( 'pfp_view_dashboard' ) ) {
			wp_send_json_error( array( 'message' => 'Accès refusé.' ) );
		}

		wp_send_json_success( $this->get_stats() );
	}

	/**
	 * Get today's order count.
	 *
	 * @return int
	 */
	private function get_orders_count_today() {
		$args = array(
			'date_created' => '>=' . gmdate( 'Y-m-d' ),
			'return'       => 'ids',
			'limit'        => -1,
		);
		$orders = wc_get_orders( $args );
		return count( $orders );
	}

	/**
	 * Get today's revenue.
	 *
	 * @return float
	 */
	private function get_revenue_today() {
		$args = array(
			'date_created' => '>=' . gmdate( 'Y-m-d' ),
			'status'       => array( 'wc-completed', 'wc-processing' ),
			'limit'        => -1,
		);
		$orders = wc_get_orders( $args );
		$total  = 0;
		foreach ( $orders as $order ) {
			$total += $order->get_total();
		}
		return $total;
	}

	/**
	 * Get this month's revenue.
	 *
	 * @return float
	 */
	private function get_revenue_month() {
		$args = array(
			'date_created' => '>=' . gmdate( 'Y-m-01' ),
			'status'       => array( 'wc-completed', 'wc-processing' ),
			'limit'        => -1,
		);
		$orders = wc_get_orders( $args );
		$total  = 0;
		foreach ( $orders as $order ) {
			$total += $order->get_total();
		}
		return $total;
	}

	/**
	 * Get this month's profit.
	 *
	 * @return float
	 */
	private function get_profit_month() {
		global $wpdb;
		$prefix = $wpdb->prefix;

		$month_start = gmdate( 'Y-m-01' );

		$income = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(amount), 0) FROM {$prefix}pfp_income WHERE received_at >= %s",
				$month_start
			)
		);

		$expenses = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(amount), 0) FROM {$prefix}pfp_expenses WHERE expense_date >= %s",
				$month_start
			)
		);

		return (float) $income - (float) $expenses;
	}

	/**
	 * Get pending quotes count.
	 *
	 * @return int
	 */
	private function get_pending_quotes_count() {
		global $wpdb;
		$prefix = $wpdb->prefix;
		return (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$prefix}pfp_quotes WHERE status IN ('nouveau', 'en_cours')"
		);
	}

	/**
	 * Get production statistics by status.
	 *
	 * @return array
	 */
	private function get_production_stats() {
		global $wpdb;
		$prefix = $wpdb->prefix;

		$results = $wpdb->get_results(
			"SELECT status, COUNT(*) as count FROM {$prefix}pfp_production_jobs GROUP BY status",
			ARRAY_A
		);

		$stats = array();
		foreach ( $results as $row ) {
			$stats[ $row['status'] ] = (int) $row['count'];
		}
		return $stats;
	}

	/**
	 * Get count of materials below minimum stock level.
	 *
	 * @return int
	 */
	private function get_low_stock_count() {
		global $wpdb;
		$prefix = $wpdb->prefix;
		return (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$prefix}pfp_materials WHERE quantity <= min_alert_qty AND status = 'active'"
		);
	}

	/**
	 * Get top 5 best-selling products this month.
	 *
	 * @return array
	 */
	private function get_top_products() {
		global $wpdb;

		$month_start = gmdate( 'Y-m-01' );

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT oi.order_item_name as name, SUM(oim.meta_value) as total_qty
				FROM {$wpdb->prefix}woocommerce_order_items oi
				INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id
				INNER JOIN {$wpdb->prefix}wc_orders o ON oi.order_id = o.id
				WHERE oim.meta_key = '_qty'
				AND o.date_created_gmt >= %s
				AND o.status IN ('wc-completed', 'wc-processing')
				AND oi.order_item_type = 'line_item'
				GROUP BY oi.order_item_name
				ORDER BY total_qty DESC
				LIMIT 5",
				$month_start
			),
			ARRAY_A
		);

		return $results ? $results : array();
	}

	/**
	 * Get top 5 customers by revenue.
	 *
	 * @return array
	 */
	private function get_top_customers() {
		global $wpdb;

		$results = $wpdb->get_results(
			"SELECT o.customer_id,
				COALESCE(u.display_name, o.billing_first_name) as name,
				SUM(o.total_amount) as total_spent,
				COUNT(o.id) as order_count
			FROM {$wpdb->prefix}wc_orders o
			LEFT JOIN {$wpdb->users} u ON o.customer_id = u.ID
			WHERE o.status IN ('wc-completed', 'wc-processing')
			GROUP BY o.customer_id
			ORDER BY total_spent DESC
			LIMIT 5",
			ARRAY_A
		);

		return $results ? $results : array();
	}

	/**
	 * Get 10 most recent orders.
	 *
	 * @return array
	 */
	private function get_recent_orders() {
		$orders = wc_get_orders(
			array(
				'limit'   => 10,
				'orderby' => 'date',
				'order'   => 'DESC',
			)
		);

		$result = array();
		foreach ( $orders as $order ) {
			$result[] = array(
				'id'     => $order->get_id(),
				'number' => $order->get_order_number(),
				'status' => $order->get_status(),
				'total'  => $order->get_total(),
				'date'   => $order->get_date_created() ? $order->get_date_created()->date( 'd/m/Y H:i' ) : '',
				'customer' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
			);
		}
		return $result;
	}
}
