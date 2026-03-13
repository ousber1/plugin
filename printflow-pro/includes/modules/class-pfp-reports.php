<?php
/**
 * Reporting & Analytics module.
 *
 * @package PrintFlowPro
 */

defined( 'ABSPATH' ) || exit;

class PFP_Reports {

	public function init() {
		add_action( 'wp_ajax_pfp_get_report', array( $this, 'ajax_get_report' ) );
		add_action( 'wp_ajax_pfp_export_report', array( $this, 'ajax_export_report' ) );
	}

	/**
	 * Get sales report data.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @param string $group_by   Group by: day, week, month.
	 * @return array
	 */
	public function get_sales_report( $start_date, $end_date, $group_by = 'day' ) {
		global $wpdb;

		$date_format = '%Y-%m-%d';
		if ( 'month' === $group_by ) {
			$date_format = '%Y-%m';
		} elseif ( 'week' === $group_by ) {
			$date_format = '%Y-W%v';
		}

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE_FORMAT(date_created_gmt, %s) as period,
					COUNT(*) as order_count,
					COALESCE(SUM(total_amount), 0) as revenue
				FROM {$wpdb->prefix}wc_orders
				WHERE status IN ('wc-completed', 'wc-processing')
				AND date_created_gmt BETWEEN %s AND %s
				GROUP BY period
				ORDER BY period ASC",
				$date_format,
				$start_date,
				$end_date . ' 23:59:59'
			),
			ARRAY_A
		);

		return array(
			'data'    => $results,
			'summary' => array(
				'total_orders'  => array_sum( array_column( $results, 'order_count' ) ),
				'total_revenue' => array_sum( array_column( $results, 'revenue' ) ),
			),
		);
	}

	/**
	 * Get product performance report.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array
	 */
	public function get_product_report( $start_date, $end_date ) {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT oi.order_item_name as product_name,
					SUM(oim_qty.meta_value) as total_qty,
					SUM(oim_total.meta_value) as total_revenue
				FROM {$wpdb->prefix}woocommerce_order_items oi
				INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim_qty
					ON oi.order_item_id = oim_qty.order_item_id AND oim_qty.meta_key = '_qty'
				INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim_total
					ON oi.order_item_id = oim_total.order_item_id AND oim_total.meta_key = '_line_total'
				INNER JOIN {$wpdb->prefix}wc_orders o
					ON oi.order_id = o.id
				WHERE o.status IN ('wc-completed', 'wc-processing')
				AND o.date_created_gmt BETWEEN %s AND %s
				AND oi.order_item_type = 'line_item'
				GROUP BY oi.order_item_name
				ORDER BY total_revenue DESC
				LIMIT 50",
				$start_date,
				$end_date . ' 23:59:59'
			),
			ARRAY_A
		);
	}

	/**
	 * Get inventory report (material consumption).
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array
	 */
	public function get_inventory_report( $start_date, $end_date ) {
		global $wpdb;
		$prefix = $wpdb->prefix;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT m.name as material_name, m.code, m.unit,
					m.quantity as current_stock,
					m.min_alert_qty,
					COALESCE(SUM(CASE WHEN sm.type = 'in' THEN sm.quantity ELSE 0 END), 0) as total_in,
					COALESCE(SUM(CASE WHEN sm.type = 'out' THEN sm.quantity ELSE 0 END), 0) as total_out
				FROM {$prefix}pfp_materials m
				LEFT JOIN {$prefix}pfp_stock_movements sm
					ON m.id = sm.material_id AND sm.created_at BETWEEN %s AND %s
				WHERE m.status = 'active'
				GROUP BY m.id
				ORDER BY total_out DESC",
				$start_date,
				$end_date . ' 23:59:59'
			),
			ARRAY_A
		);
	}

	/**
	 * Get production efficiency report.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array
	 */
	public function get_production_report( $start_date, $end_date ) {
		global $wpdb;
		$prefix = $wpdb->prefix;

		$stats = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT status, COUNT(*) as count
				FROM {$prefix}pfp_production_jobs
				WHERE created_at BETWEEN %s AND %s
				GROUP BY status",
				$start_date,
				$end_date . ' 23:59:59'
			),
			ARRAY_A
		);

		$avg_time = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT AVG(TIMESTAMPDIFF(HOUR, started_at, completed_at))
				FROM {$prefix}pfp_production_jobs
				WHERE completed_at IS NOT NULL
				AND started_at IS NOT NULL
				AND created_at BETWEEN %s AND %s",
				$start_date,
				$end_date . ' 23:59:59'
			)
		);

		return array(
			'status_breakdown'     => $stats,
			'avg_production_hours' => round( (float) $avg_time, 1 ),
		);
	}

	/**
	 * Generate daily report (called by cron).
	 */
	public function generate_daily_report() {
		$today = gmdate( 'Y-m-d' );
		$sales = $this->get_sales_report( $today, $today );

		$admin_email = get_option( 'admin_email' );
		$subject     = sprintf( '[PrintFlow Pro] Rapport quotidien — %s', date_i18n( 'd/m/Y' ) );

		$body  = "Rapport quotidien PrintFlow Pro\n";
		$body .= "================================\n\n";
		$body .= sprintf( "Commandes: %d\n", $sales['summary']['total_orders'] );
		$body .= sprintf( "Chiffre d'affaires: %s MAD\n", number_format( $sales['summary']['total_revenue'], 2, ',', ' ' ) );

		wp_mail( $admin_email, $subject, $body );
	}

	/**
	 * Generate weekly report (called by cron).
	 */
	public function generate_weekly_report() {
		$end   = gmdate( 'Y-m-d' );
		$start = gmdate( 'Y-m-d', strtotime( '-7 days' ) );
		$sales = $this->get_sales_report( $start, $end );

		$admin_email = get_option( 'admin_email' );
		$subject     = sprintf( '[PrintFlow Pro] Rapport hebdomadaire — Semaine du %s', date_i18n( 'd/m/Y', strtotime( $start ) ) );

		$body  = "Rapport hebdomadaire PrintFlow Pro\n";
		$body .= "====================================\n\n";
		$body .= sprintf( "Période: %s — %s\n", date_i18n( 'd/m/Y', strtotime( $start ) ), date_i18n( 'd/m/Y' ) );
		$body .= sprintf( "Commandes: %d\n", $sales['summary']['total_orders'] );
		$body .= sprintf( "Chiffre d'affaires: %s MAD\n", number_format( $sales['summary']['total_revenue'], 2, ',', ' ' ) );

		wp_mail( $admin_email, $subject, $body );
	}

	/**
	 * Generate monthly report (called by cron).
	 */
	public function generate_monthly_report() {
		$start = gmdate( 'Y-m-01', strtotime( 'last month' ) );
		$end   = gmdate( 'Y-m-t', strtotime( 'last month' ) );
		$sales = $this->get_sales_report( $start, $end, 'month' );

		$finance = PrintFlow_Pro::instance()->get_module( 'finance' );
		$summary = $finance ? $finance->get_summary( $start, $end ) : array();

		$admin_email = get_option( 'admin_email' );
		$subject     = sprintf( '[PrintFlow Pro] Rapport mensuel — %s', date_i18n( 'F Y', strtotime( $start ) ) );

		$body  = "Rapport mensuel PrintFlow Pro\n";
		$body .= "===============================\n\n";
		$body .= sprintf( "Mois: %s\n\n", date_i18n( 'F Y', strtotime( $start ) ) );
		$body .= sprintf( "Commandes: %d\n", $sales['summary']['total_orders'] );
		$body .= sprintf( "Chiffre d'affaires: %s MAD\n", number_format( $sales['summary']['total_revenue'], 2, ',', ' ' ) );

		if ( ! empty( $summary ) ) {
			$body .= sprintf( "Dépenses: %s MAD\n", number_format( $summary['total_expenses'], 2, ',', ' ' ) );
			$body .= sprintf( "Profit: %s MAD\n", number_format( $summary['profit'], 2, ',', ' ' ) );
			$body .= sprintf( "Marge: %s%%\n", $summary['margin_percentage'] );
		}

		wp_mail( $admin_email, $subject, $body );

		// Re-schedule for next month.
		wp_schedule_single_event( strtotime( 'first day of next month 08:00:00' ), 'pfp_monthly_report' );
	}

	// AJAX handlers.

	public function ajax_get_report() {
		check_ajax_referer( 'pfp_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'pfp_view_reports' ) ) {
			wp_send_json_error( array( 'message' => 'Accès refusé.' ) );
		}

		$type  = sanitize_text_field( wp_unslash( $_POST['report_type'] ?? 'sales' ) );
		$start = sanitize_text_field( wp_unslash( $_POST['start_date'] ?? gmdate( 'Y-m-01' ) ) );
		$end   = sanitize_text_field( wp_unslash( $_POST['end_date'] ?? gmdate( 'Y-m-d' ) ) );

		$data = array();
		switch ( $type ) {
			case 'sales':
				$data = $this->get_sales_report( $start, $end );
				break;
			case 'products':
				$data = $this->get_product_report( $start, $end );
				break;
			case 'inventory':
				$data = $this->get_inventory_report( $start, $end );
				break;
			case 'production':
				$data = $this->get_production_report( $start, $end );
				break;
		}

		wp_send_json_success( $data );
	}

	public function ajax_export_report() {
		check_ajax_referer( 'pfp_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'pfp_view_reports' ) ) {
			wp_send_json_error( array( 'message' => 'Accès refusé.' ) );
		}

		// Export handled client-side from the report data.
		wp_send_json_success( array( 'message' => 'Utilisez les données du rapport pour l\'export.' ) );
	}
}
