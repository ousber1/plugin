<?php
/**
 * CSV exports of operational data. Credentials, gift codes and message bodies are never exported.
 *
 * @package RelayStreamPortal
 */

defined( 'ABSPATH' ) || exit;

/**
 * Export.
 */
class RSP_Export {

	const TYPES = array(
		'accounts'  => 'rsp_manage_provisioning',
		'jobs'      => 'rsp_manage_provisioning',
		'customers' => 'rsp_view_admin',
		'orders'    => 'rsp_manage_billing',
		'referrals' => 'rsp_manage_billing',
		'gifts'     => 'rsp_manage_billing',
		'tickets'   => 'rsp_manage_support',
		'audit'     => 'rsp_manage_settings',
	);

	/**
	 * Handle admin-post export.
	 */
	public static function handle() {
		$type = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : '';
		if ( ! isset( self::TYPES[ $type ] ) ) {
			wp_die( esc_html__( 'Unknown export.', 'relay-stream-portal' ), 400 );
		}
		check_admin_referer( 'rsp_export_' . $type );
		if ( ! current_user_can( 'rsp_export_data' ) || ! current_user_can( self::TYPES[ $type ] ) ) {
			wp_die( esc_html__( 'You do not have permission to export this data.', 'relay-stream-portal' ), 403 );
		}
		$rows = self::rows( $type );
		RSP_Audit::log( 'export', 'export', $type, '', array( 'rows' => count( $rows ) - 1 ) );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="rsp-' . $type . '-' . gmdate( 'Ymd-His' ) . '.csv"' );
		header( 'X-Content-Type-Options: nosniff' );
		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		foreach ( $rows as $r ) {
			fputcsv( $out, array_map( array( __CLASS__, 'cell' ), $r ) );
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}

	/**
	 * Neutralize spreadsheet formula injection.
	 *
	 * @param mixed $v Value.
	 * @return string
	 */
	public static function cell( $v ) {
		$v = (string) $v;
		return ( '' !== $v && in_array( $v[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) ? "'" . $v : $v;
	}

	/**
	 * Build rows (first row = header). Max 50k rows.
	 *
	 * @param string $type Type.
	 * @return array
	 */
	public static function rows( $type ) {
		global $wpdb;
		$limit = 50000;
		$email = static function ( $uid ) {
			$u = get_userdata( (int) $uid );
			return $u ? $u->user_email : '';
		};
		switch ( $type ) {
			case 'accounts':
				list( $w, $p ) = RSP_Admin_Pages::account_where();
				$sql  = 'SELECT a.* FROM ' . RSP_Installer::table( 'accounts' ) . " a WHERE $w ORDER BY a.id DESC LIMIT $limit";
				$data = $wpdb->get_results( $p ? $wpdb->prepare( $sql, $p ) : $sql ); // phpcs:ignore
				$rows = array( array( 'uuid', 'customer_email', 'nickname', 'status', 'plan', 'provider', 'type', 'provider_user_id', 'max_connections', 'activated_at', 'expires_at', 'created_at' ) );
				foreach ( (array) $data as $a ) {
					$rows[] = array( $a->uuid, $email( $a->user_id ), $a->nickname, $a->status, $a->plan_label, $a->provider, $a->provider_type, $a->provider_user_id, $a->max_connections, $a->activated_at, $a->expires_at, $a->created_at );
				}
				return $rows;
			case 'jobs':
				list( $w, $p ) = RSP_Admin_Pages::job_where();
				$sql  = 'SELECT * FROM ' . RSP_Installer::table( 'jobs' ) . " WHERE $w ORDER BY id DESC LIMIT $limit";
				$data = $wpdb->get_results( $p ? $wpdb->prepare( $sql, $p ) : $sql ); // phpcs:ignore
				$rows = array( array( 'id', 'type', 'state', 'step', 'account_id', 'order_id', 'attempts', 'review_reason', 'last_error', 'created_at', 'updated_at' ) );
				foreach ( (array) $data as $j ) {
					$rows[] = array( $j->id, $j->type, $j->state, $j->step, $j->account_id, $j->order_id, $j->attempts, $j->review_reason, RSP_Audit::redact_string( $j->last_error ), $j->created_at, $j->updated_at );
				}
				return $rows;
			case 'customers':
				$rows = array( array( 'user_id', 'email', 'display_name', 'registered', 'accounts', 'active_accounts', 'reward_balance_days', 'referral_code' ) );
				$ids  = $wpdb->get_col( 'SELECT DISTINCT user_id FROM ' . RSP_Installer::table( 'accounts' ) . " LIMIT $limit" ); // phpcs:ignore
				foreach ( $ids as $uid ) {
					$u = get_userdata( (int) $uid );
					if ( ! $u ) {
						continue;
					}
					$c      = $wpdb->get_row( $wpdb->prepare( 'SELECT COUNT(*) t, SUM(status=\'active\') a FROM ' . RSP_Installer::table( 'accounts' ) . ' WHERE user_id = %d', $uid ) ); // phpcs:ignore
					$rows[] = array( $u->ID, $u->user_email, $u->display_name, $u->user_registered, (int) $c->t, (int) $c->a, RSP_Referrals::balance( $u->ID ), get_user_meta( $u->ID, 'rsp_ref_code', true ) );
				}
				return $rows;
			case 'orders':
				$rows = array( array( 'order', 'date', 'customer_email', 'total', 'currency', 'payment_method', 'payment_status', 'provisioning_status', 'plans', 'review_flag' ) );
				if ( ! RSP_Plugin::has_woocommerce() ) {
					return $rows;
				}
				$args          = RSP_Admin_Pages::order_query_args();
				$args['limit'] = 5000;
				foreach ( wc_get_orders( $args ) as $o ) {
					$plans = array();
					foreach ( $o->get_items() as $it ) {
						$s = RSP_Woocommerce::snapshot( $it );
						if ( $s ) {
							$plans[] = $s['plan_name'] . ' (' . $s['purpose'] . ', ' . $s['months'] . 'm)';
						}
					}
					$rows[] = array( $o->get_order_number(), $o->get_date_created() ? $o->get_date_created()->date( 'c' ) : '', $o->get_billing_email(), $o->get_total(), $o->get_currency(), $o->get_payment_method_title(), $o->get_status(), RSP_Fulfillment::order_provisioning_status( $o ), implode( '; ', $plans ), $o->get_meta( '_rsp_needs_review' ) );
				}
				return $rows;
			case 'referrals':
				$data = $wpdb->get_results( 'SELECT * FROM ' . RSP_Installer::table( 'referrals' ) . " ORDER BY id DESC LIMIT $limit" ); // phpcs:ignore
				$rows = array( array( 'id', 'referrer_email', 'referee_email', 'status', 'order_id', 'reward_days', 'eligible_at', 'created_at' ) );
				foreach ( (array) $data as $r ) {
					$rows[] = array( $r->id, $email( $r->referrer_id ), $email( $r->referee_id ), $r->status, $r->order_id, $r->reward_days, $r->eligible_at, $r->created_at );
				}
				return $rows;
			case 'gifts':
				$data = $wpdb->get_results( 'SELECT * FROM ' . RSP_Installer::table( 'gifts' ) . " ORDER BY id DESC LIMIT $limit" ); // phpcs:ignore
				$rows = array( array( 'id', 'code_last4', 'purchaser_email', 'order_id', 'status', 'redeemed_by_email', 'redeemed_at', 'expires_at', 'created_at' ) );
				foreach ( (array) $data as $g ) {
					$rows[] = array( $g->id, $g->code_hint, $email( $g->purchaser_id ), $g->order_id, $g->status, $g->redeemed_by ? $email( $g->redeemed_by ) : '', $g->redeemed_at, $g->expires_at, $g->created_at );
				}
				return $rows;
			case 'tickets':
				list( $w, $p ) = RSP_Admin_Pages::ticket_where();
				$sql  = 'SELECT * FROM ' . RSP_Installer::table( 'tickets' ) . " WHERE $w ORDER BY id DESC LIMIT $limit";
				$data = $wpdb->get_results( $p ? $wpdb->prepare( $sql, $p ) : $sql ); // phpcs:ignore
				$rows = array( array( 'id', 'customer_email', 'subject', 'category', 'status', 'priority', 'assigned_to', 'created_at', 'updated_at' ) );
				foreach ( (array) $data as $t ) {
					$rows[] = array( $t->id, $email( $t->user_id ), $t->subject, $t->category, $t->status, $t->priority, $t->assigned_to ? $email( $t->assigned_to ) : '', $t->created_at, $t->updated_at );
				}
				return $rows;
			case 'audit':
				list( $w, $p ) = RSP_Admin_Pages::audit_where();
				$sql  = 'SELECT * FROM ' . RSP_Installer::table( 'audit' ) . " WHERE $w ORDER BY id DESC LIMIT $limit";
				$data = $wpdb->get_results( $p ? $wpdb->prepare( $sql, $p ) : $sql ); // phpcs:ignore
				$rows = array( array( 'id', 'created_at', 'actor_email', 'action', 'target_type', 'target_id', 'reason', 'context' ) );
				foreach ( (array) $data as $a ) {
					$rows[] = array( $a->id, $a->created_at, $a->actor_id ? $email( $a->actor_id ) : 'system', $a->action, $a->target_type, $a->target_id, $a->reason, $a->context );
				}
				return $rows;
		}
		return array();
	}
}
