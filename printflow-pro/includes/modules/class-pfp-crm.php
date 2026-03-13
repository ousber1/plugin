<?php
/**
 * CRM / Customer Management module.
 *
 * @package PrintFlowPro
 */

defined( 'ABSPATH' ) || exit;

class PFP_CRM {

	public function init() {
		add_action( 'wp_ajax_pfp_get_customer_profile', array( $this, 'ajax_get_profile' ) );
		add_action( 'wp_ajax_pfp_add_customer_note', array( $this, 'ajax_add_note' ) );
		add_action( 'wp_ajax_pfp_get_top_customers', array( $this, 'ajax_get_top_customers' ) );

		// Award loyalty points on order completion.
		add_action( 'woocommerce_order_status_completed', array( $this, 'award_loyalty_points' ) );
	}

	/**
	 * Get customer profile with aggregated data.
	 *
	 * @param int $customer_id Customer (user) ID.
	 * @return array
	 */
	public function get_customer_profile( $customer_id ) {
		$customer = new WC_Customer( $customer_id );
		if ( ! $customer->get_id() ) {
			return array();
		}

		global $wpdb;
		$prefix = $wpdb->prefix;

		// Order stats.
		$order_stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) as order_count, COALESCE(SUM(total_amount), 0) as total_spent
				FROM {$prefix}wc_orders
				WHERE customer_id = %d AND status IN ('wc-completed', 'wc-processing')",
				$customer_id
			),
			ARRAY_A
		);

		// Recent orders.
		$recent_orders = wc_get_orders( array(
			'customer_id' => $customer_id,
			'limit'       => 10,
			'orderby'     => 'date',
			'order'       => 'DESC',
		) );

		$orders_data = array();
		foreach ( $recent_orders as $order ) {
			$orders_data[] = array(
				'id'     => $order->get_id(),
				'number' => $order->get_order_number(),
				'date'   => $order->get_date_created() ? $order->get_date_created()->date( 'd/m/Y' ) : '',
				'total'  => $order->get_total(),
				'status' => $order->get_status(),
			);
		}

		// Loyalty points.
		$points_earned = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(points), 0) FROM {$prefix}pfp_loyalty_points WHERE customer_id = %d AND type = 'earned'",
				$customer_id
			)
		);
		$points_redeemed = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(ABS(points)), 0) FROM {$prefix}pfp_loyalty_points WHERE customer_id = %d AND type = 'redeemed'",
				$customer_id
			)
		);

		// Customer notes.
		$notes = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT cn.*, u.display_name as author
				FROM {$prefix}pfp_customer_notes cn
				LEFT JOIN {$wpdb->users} u ON cn.user_id = u.ID
				WHERE cn.customer_id = %d
				ORDER BY cn.created_at DESC
				LIMIT 20",
				$customer_id
			),
			ARRAY_A
		);

		// Determine customer segment.
		$total_spent   = (float) ( $order_stats['total_spent'] ?? 0 );
		$order_count   = (int) ( $order_stats['order_count'] ?? 0 );
		$segment       = $this->determine_segment( $total_spent, $order_count, $customer_id );

		return array(
			'id'              => $customer_id,
			'first_name'      => $customer->get_first_name(),
			'last_name'       => $customer->get_last_name(),
			'email'           => $customer->get_email(),
			'phone'           => $customer->get_billing_phone(),
			'city'            => $customer->get_billing_city(),
			'company'         => $customer->get_billing_company(),
			'date_created'    => $customer->get_date_created() ? $customer->get_date_created()->date( 'd/m/Y' ) : '',
			'order_count'     => $order_count,
			'total_spent'     => $total_spent,
			'recent_orders'   => $orders_data,
			'loyalty_balance' => $points_earned - $points_redeemed,
			'points_earned'   => $points_earned,
			'points_redeemed' => $points_redeemed,
			'notes'           => $notes,
			'segment'         => $segment,
		);
	}

	/**
	 * Determine customer segment.
	 *
	 * @param float $total_spent Total spent.
	 * @param int   $order_count Order count.
	 * @param int   $customer_id Customer ID.
	 * @return string
	 */
	private function determine_segment( $total_spent, $order_count, $customer_id ) {
		if ( $total_spent >= 10000 || $order_count >= 20 ) {
			return 'VIP';
		}
		if ( $total_spent >= 3000 || $order_count >= 5 ) {
			return 'Fidèle';
		}
		if ( $order_count >= 1 ) {
			// Check if last order was more than 6 months ago.
			$last_order = wc_get_orders( array(
				'customer_id' => $customer_id,
				'limit'       => 1,
				'orderby'     => 'date',
				'order'       => 'DESC',
			) );
			if ( ! empty( $last_order ) ) {
				$last_date = $last_order[0]->get_date_created();
				if ( $last_date && $last_date->getTimestamp() < strtotime( '-6 months' ) ) {
					return 'Inactif';
				}
			}
			return 'Actif';
		}
		return 'Nouveau';
	}

	/**
	 * Award loyalty points for a completed order.
	 *
	 * @param int $order_id Order ID.
	 */
	public function award_loyalty_points( $order_id ) {
		$order       = wc_get_order( $order_id );
		$customer_id = $order->get_customer_id();

		if ( ! $customer_id ) {
			return;
		}

		// 1 point per 10 MAD spent.
		$points = (int) floor( $order->get_total() / 10 );

		if ( $points <= 0 ) {
			return;
		}

		global $wpdb;

		// Check if points already awarded for this order.
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}pfp_loyalty_points WHERE customer_id = %d AND reference = %s",
				$customer_id,
				'order_' . $order_id
			)
		);

		if ( $exists ) {
			return;
		}

		$wpdb->insert(
			$wpdb->prefix . 'pfp_loyalty_points',
			array(
				'customer_id' => $customer_id,
				'points'      => $points,
				'type'        => 'earned',
				'reference'   => 'order_' . $order_id,
			)
		);
	}

	/**
	 * Get all customers with CRM data.
	 *
	 * @param array $args Query args.
	 * @return array
	 */
	public function get_customers( $args = array() ) {
		$query_args = array(
			'role__in' => array( 'customer', 'subscriber' ),
			'orderby'  => 'registered',
			'order'    => 'DESC',
			'number'   => $args['limit'] ?? 20,
			'offset'   => $args['offset'] ?? 0,
		);

		if ( ! empty( $args['search'] ) ) {
			$query_args['search']         = '*' . $args['search'] . '*';
			$query_args['search_columns'] = array( 'user_login', 'user_email', 'display_name' );
		}

		$users   = get_users( $query_args );
		$results = array();

		foreach ( $users as $user ) {
			$customer = new WC_Customer( $user->ID );
			$results[] = array(
				'id'         => $user->ID,
				'name'       => $customer->get_first_name() . ' ' . $customer->get_last_name(),
				'email'      => $customer->get_email(),
				'phone'      => $customer->get_billing_phone(),
				'city'       => $customer->get_billing_city(),
				'registered' => $user->user_registered,
			);
		}

		return $results;
	}

	// AJAX handlers.

	public function ajax_get_profile() {
		check_ajax_referer( 'pfp_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'pfp_manage_crm' ) ) {
			wp_send_json_error( array( 'message' => 'Accès refusé.' ) );
		}

		$customer_id = absint( $_POST['customer_id'] ?? 0 );
		if ( ! $customer_id ) {
			wp_send_json_error( array( 'message' => 'ID client invalide.' ) );
		}

		wp_send_json_success( $this->get_customer_profile( $customer_id ) );
	}

	public function ajax_add_note() {
		check_ajax_referer( 'pfp_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'pfp_manage_crm' ) ) {
			wp_send_json_error( array( 'message' => 'Accès refusé.' ) );
		}

		$customer_id = absint( $_POST['customer_id'] ?? 0 );
		$note        = sanitize_textarea_field( wp_unslash( $_POST['note'] ?? '' ) );
		$type        = sanitize_text_field( wp_unslash( $_POST['type'] ?? 'note' ) );

		if ( ! $customer_id || empty( $note ) ) {
			wp_send_json_error( array( 'message' => 'Paramètres invalides.' ) );
		}

		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'pfp_customer_notes',
			array(
				'customer_id' => $customer_id,
				'user_id'     => get_current_user_id(),
				'note'        => $note,
				'type'        => $type,
			)
		);

		wp_send_json_success( array( 'message' => 'Note ajoutée.' ) );
	}

	public function ajax_get_top_customers() {
		check_ajax_referer( 'pfp_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'pfp_manage_crm' ) ) {
			wp_send_json_error( array( 'message' => 'Accès refusé.' ) );
		}

		global $wpdb;
		$results = $wpdb->get_results(
			"SELECT o.customer_id, u.display_name as name, u.user_email as email,
				COUNT(o.id) as order_count, SUM(o.total_amount) as total_spent
			FROM {$wpdb->prefix}wc_orders o
			INNER JOIN {$wpdb->users} u ON o.customer_id = u.ID
			WHERE o.status IN ('wc-completed', 'wc-processing')
			AND o.customer_id > 0
			GROUP BY o.customer_id
			ORDER BY total_spent DESC
			LIMIT 20",
			ARRAY_A
		);

		wp_send_json_success( $results );
	}
}
