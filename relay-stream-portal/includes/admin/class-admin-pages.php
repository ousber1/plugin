<?php
/**
 * Admin page renderers. All output is escaped; counts come from live data.
 *
 * @package RelayStreamPortal
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin pages.
 */
class RSP_Admin_Pages {

	const PER_PAGE = 25;

	/**
	 * Page header.
	 *
	 * @param string $title Title.
	 * @param string $intro Intro.
	 */
	private static function head( $title, $intro = '' ) {
		echo '<div class="wrap rsp-admin"><h1 class="wp-heading-inline">' . esc_html( $title ) . '</h1>';
		$mode = RSP_Provider_Factory::is_live() ? '<span class="rsp-badge rsp-badge-active">' . esc_html__( 'Live API', 'relay-stream-portal' ) . '</span>' : '<span class="rsp-badge rsp-badge-pending">' . esc_html__( 'Manual provisioning', 'relay-stream-portal' ) . '</span>';
		echo ' ' . $mode; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<hr class="wp-header-end">';
		if ( $intro ) {
			echo '<p class="description">' . esc_html( $intro ) . '</p>';
		}
	}

	/**
	 * Close wrap.
	 */
	private static function foot() {
		echo '</div>';
	}

	/**
	 * Count helper.
	 *
	 * @param string $table Table short name.
	 * @param string $where SQL where (static, no user input).
	 * @return int
	 */
	private static function count( $table, $where = '1=1' ) {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . RSP_Installer::table( $table ) . ' WHERE ' . $where ); // phpcs:ignore
	}

	/* ===================== Overview ===================== */

	/**
	 * Overview.
	 */
	public static function overview() {
		global $wpdb;
		self::head( __( 'Relay Stream Portal', 'relay-stream-portal' ) );
		$soon  = gmdate( 'Y-m-d H:i:s', time() + 7 * DAY_IN_SECONDS );
		$now   = RSP_Installer::now();
		$cards = array(
			array( __( 'Customers with accounts', 'relay-stream-portal' ), (int) $wpdb->get_var( 'SELECT COUNT(DISTINCT user_id) FROM ' . RSP_Installer::table( 'accounts' ) . " WHERE status <> 'cancelled'" ), 'rsp-customers' ), // phpcs:ignore
			array( __( 'Active accounts', 'relay-stream-portal' ), self::count( 'accounts', "status = 'active'" ), 'rsp-accounts&status=active' ),
			array( __( 'Expiring in 7 days', 'relay-stream-portal' ), (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . RSP_Installer::table( 'accounts' ) . " WHERE status = 'active' AND expires_at BETWEEN %s AND %s", $now, $soon ) ), 'rsp-accounts&status=active' ), // phpcs:ignore
			array( __( 'Awaiting activation', 'relay-stream-portal' ), self::count( 'jobs', "state IN ('pending','working')" ), 'rsp-api&state=pending' ),
			array( __( 'Jobs needing review', 'relay-stream-portal' ), self::count( 'jobs', "state = 'needs_review'" ), 'rsp-api&state=needs_review' ),
			array( __( 'Failed jobs', 'relay-stream-portal' ), self::count( 'jobs', "state = 'failed'" ), 'rsp-api&state=failed' ),
			array( __( 'Open tickets', 'relay-stream-portal' ), self::count( 'tickets', "status IN ('open','awaiting_staff')" ), 'rsp-tickets&status=awaiting_staff' ),
			array( __( 'Referrals in review period', 'relay-stream-portal' ), self::count( 'referrals', "status = 'pending'" ), 'rsp-referrals&status=pending' ),
			array( __( 'Unredeemed gifts', 'relay-stream-portal' ), self::count( 'gifts', "status = 'issued'" ), 'rsp-gifts&status=issued' ),
		);
		echo '<div class="rsp-cards">';
		foreach ( $cards as $c ) {
			echo '<a class="rsp-kpi" href="' . esc_url( admin_url( 'admin.php?page=' . $c[2] ) ) . '"><strong>' . (int) $c[1] . '</strong><span>' . esc_html( $c[0] ) . '</span></a>';
		}
		echo '</div>';

		echo '<div class="rsp-columns"><div class="rsp-panel"><h2>' . esc_html__( 'Setup checklist', 'relay-stream-portal' ) . '</h2><ul class="rsp-checklist">';
		$pages = (array) get_option( 'rsp_pages', array() );
		$items = array(
			array( ! empty( $pages['dashboard'] ), __( 'Portal pages created or mapped', 'relay-stream-portal' ), 'rsp-pages' ),
			array( RSP_Plugin::has_woocommerce(), __( 'WooCommerce active (billing)', 'relay-stream-portal' ), '' ),
			array( RSP_Plugin::has_woocommerce() && count( RSP_Woocommerce::plans() ) > 0, __( 'At least one plan available', 'relay-stream-portal' ), 'rsp-plans' ),
			array( RSP_Plugin::has_woocommerce() && count( RSP_Woocommerce::gateway_titles() ) > 0, __( 'A payment gateway enabled', 'relay-stream-portal' ), '' ),
			array( '' !== RSP_Settings::get( 'api_key_enc' ), __( 'Provider API key saved', 'relay-stream-portal' ), 'rsp-api' ),
			array( (bool) RSP_Settings::get( 'api_mapping_verified' ), __( 'API mapping verified against your panel', 'relay-stream-portal' ), 'rsp-api' ),
			array( RSP_Crypto::has_dedicated_key(), __( 'Dedicated encryption key in wp-config.php (recommended)', 'relay-stream-portal' ), '' ),
			array( did_action( 'elementor/loaded' ) > 0, __( 'Elementor active (optional — shortcodes work without it)', 'relay-stream-portal' ), 'rsp-pages' ),
		);
		foreach ( $items as $i ) {
			echo '<li class="' . ( $i[0] ? 'is-done' : 'is-todo' ) . '"><span class="dashicons ' . ( $i[0] ? 'dashicons-yes-alt' : 'dashicons-marker' ) . '"></span> ' . ( $i[2] ? '<a href="' . esc_url( admin_url( 'admin.php?page=' . $i[2] ) ) . '">' . esc_html( $i[1] ) . '</a>' : esc_html( $i[1] ) ) . '</li>';
		}
		echo '</ul></div><div class="rsp-panel"><h2>' . esc_html__( 'Recent activity', 'relay-stream-portal' ) . '</h2>';
		$rows = $wpdb->get_results( 'SELECT * FROM ' . RSP_Installer::table( 'audit' ) . ' ORDER BY id DESC LIMIT 10' ); // phpcs:ignore
		$out  = array();
		foreach ( (array) $rows as $r ) {
			$u     = $r->actor_id ? get_userdata( (int) $r->actor_id ) : null;
			$out[] = array( 'when' => RSP_Admin::date( $r->created_at ), 'who' => esc_html( $u ? $u->display_name : __( 'System', 'relay-stream-portal' ) ), 'what' => '<code>' . esc_html( $r->action ) . '</code> ' . esc_html( $r->target_type . ' ' . $r->target_id ) );
		}
		RSP_Admin::table( array( 'when' => __( 'When', 'relay-stream-portal' ), 'who' => __( 'Actor', 'relay-stream-portal' ), 'what' => __( 'Action', 'relay-stream-portal' ) ), $out );
		echo '</div></div>';
		self::foot();
	}

	/* ===================== Customers ===================== */

	/**
	 * Customers list / detail.
	 */
	public static function customers() {
		global $wpdb;
		$uid = (int) RSP_Admin::q( 'user' );
		if ( $uid ) {
			self::customer_detail( $uid );
			return;
		}
		self::head( __( 'Customers', 'relay-stream-portal' ), __( 'WordPress users who own streaming accounts, purchased plans or contacted support.', 'relay-stream-portal' ) );
		RSP_Admin::filters( 'rsp-customers', array(), 'customers' );
		$acc = RSP_Installer::table( 'accounts' );
		$s   = RSP_Admin::q( 's' );
		$args = array(
			'number'  => self::PER_PAGE,
			'paged'   => RSP_Admin::paged(),
			'orderby' => 'registered',
			'order'   => 'DESC',
			'role__in' => array_filter( array( get_role( 'customer' ) ? 'customer' : '', 'subscriber' ) ),
		);
		if ( $s ) {
			$args['search']         = '*' . $s . '*';
			$args['search_columns'] = array( 'user_login', 'user_email', 'display_name' );
		}
		$q    = new WP_User_Query( $args );
		$rows = array();
		foreach ( $q->get_results() as $u ) {
			$counts = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) total, SUM(status='active') active FROM $acc WHERE user_id = %d AND status <> 'cancelled'", $u->ID ) ); // phpcs:ignore
			$rows[] = array(
				'user'     => RSP_Admin::user_link( $u->ID ),
				'accounts' => (int) $counts->total . ' / ' . (int) $counts->active . ' ' . esc_html__( 'active', 'relay-stream-portal' ),
				'balance'  => (int) RSP_Referrals::balance( $u->ID ),
				'code'     => '<code>' . esc_html( (string) get_user_meta( $u->ID, 'rsp_ref_code', true ) ) . '</code>',
				'since'    => esc_html( mysql2date( 'Y-m-d', $u->user_registered ) ),
			);
		}
		RSP_Admin::table( array( 'user' => __( 'Customer', 'relay-stream-portal' ), 'accounts' => __( 'Accounts', 'relay-stream-portal' ), 'balance' => __( 'Reward days', 'relay-stream-portal' ), 'code' => __( 'Referral code', 'relay-stream-portal' ), 'since' => __( 'Registered', 'relay-stream-portal' ) ), $rows );
		RSP_Admin::pager( $q->get_total(), self::PER_PAGE );
		self::foot();
	}

	/**
	 * Customer detail.
	 *
	 * @param int $uid User.
	 */
	private static function customer_detail( $uid ) {
		global $wpdb;
		$u = get_userdata( $uid );
		if ( ! $u ) {
			wp_die( esc_html__( 'Customer not found.', 'relay-stream-portal' ) );
		}
		self::head( $u->display_name . ' <' . $u->user_email . '>' );
		echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=rsp-customers' ) ) . '">← ' . esc_html__( 'All customers', 'relay-stream-portal' ) . '</a> · <a href="' . esc_url( get_edit_user_link( $uid ) ) . '">' . esc_html__( 'WordPress profile', 'relay-stream-portal' ) . '</a></p>';

		echo '<h2>' . esc_html__( 'Streaming accounts', 'relay-stream-portal' ) . '</h2>';
		self::accounts_table( $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . RSP_Installer::table( 'accounts' ) . ' WHERE user_id = %d ORDER BY id DESC', $uid ) ) ); // phpcs:ignore
		if ( current_user_can( 'rsp_manage_provisioning' ) ) {
			echo '<details class="rsp-details"><summary class="button">' . esc_html__( 'Add a manually provisioned account', 'relay-stream-portal' ) . '</summary>';
			echo RSP_Admin::form( 'account_create' ) . '<input type="hidden" name="user_id" value="' . (int) $uid . '">'; // phpcs:ignore
			self::manual_fields( true );
			echo '<p><button class="button button-primary">' . esc_html__( 'Create account', 'relay-stream-portal' ) . '</button></p></form></details>';
		}

		if ( RSP_Plugin::has_woocommerce() ) {
			echo '<h2>' . esc_html__( 'Orders', 'relay-stream-portal' ) . '</h2>';
			$orders = wc_get_orders( array( 'customer_id' => $uid, 'limit' => 20 ) );
			$rows   = array();
			foreach ( $orders as $o ) {
				$p      = RSP_Fulfillment::order_provisioning_status( $o );
				$rows[] = array( 'order' => '<a href="' . esc_url( $o->get_edit_order_url() ) . '">#' . esc_html( $o->get_order_number() ) . '</a>', 'date' => esc_html( $o->get_date_created() ? $o->get_date_created()->date( 'Y-m-d' ) : '' ), 'total' => wp_kses_post( $o->get_formatted_order_total() ), 'pay' => esc_html( wc_get_order_status_name( $o->get_status() ) ), 'prov' => RSP_Admin::pill( $p, RSP_Fulfillment::status_label( $p ) ) );
			}
			RSP_Admin::table( array( 'order' => __( 'Order', 'relay-stream-portal' ), 'date' => __( 'Date', 'relay-stream-portal' ), 'total' => __( 'Total', 'relay-stream-portal' ), 'pay' => __( 'Payment', 'relay-stream-portal' ), 'prov' => __( 'Provisioning', 'relay-stream-portal' ) ), $rows );
		}

		echo '<h2>' . esc_html__( 'Reward ledger', 'relay-stream-portal' ) . ' <small>(' . esc_html( sprintf( /* translators: %d days */ __( 'balance %d days', 'relay-stream-portal' ), RSP_Referrals::balance( $uid ) ) ) . ')</small></h2>';
		$led  = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . RSP_Installer::table( 'ledger' ) . ' WHERE user_id = %d ORDER BY id DESC LIMIT 50', $uid ) ); // phpcs:ignore
		$rows = array();
		foreach ( (array) $led as $l ) {
			$rows[] = array( 'date' => RSP_Admin::date( $l->created_at ), 'delta' => ( $l->delta_days > 0 ? '+' : '' ) . (int) $l->delta_days, 'reason' => esc_html( $l->reason ), 'note' => esc_html( $l->note ) );
		}
		RSP_Admin::table( array( 'date' => __( 'Date', 'relay-stream-portal' ), 'delta' => __( 'Days', 'relay-stream-portal' ), 'reason' => __( 'Reason', 'relay-stream-portal' ), 'note' => __( 'Note', 'relay-stream-portal' ) ), $rows );
		if ( current_user_can( 'rsp_manage_billing' ) ) {
			echo RSP_Admin::form( 'ledger_adjust', 'class="rsp-row-form"' ) . '<input type="hidden" name="user_id" value="' . (int) $uid . '">'; // phpcs:ignore
			echo '<label>' . esc_html__( 'Adjust days', 'relay-stream-portal' ) . ' <input type="number" name="days" required step="1"></label> <label>' . esc_html__( 'Reason', 'relay-stream-portal' ) . ' <input type="text" name="reason" required class="regular-text"></label> <button class="button">' . esc_html__( 'Apply adjustment', 'relay-stream-portal' ) . '</button></form>';
		}

		echo '<h2>' . esc_html__( 'Tickets', 'relay-stream-portal' ) . '</h2>';
		$rows = array();
		foreach ( RSP_Tickets::for_user( $uid, 20 ) as $t ) {
			$rows[] = array( 'id' => '<a href="' . esc_url( admin_url( 'admin.php?page=rsp-tickets&ticket=' . (int) $t->id ) ) . '">#' . (int) $t->id . ' ' . esc_html( $t->subject ) . '</a>', 'status' => RSP_Admin::pill( $t->status, RSP_Tickets::status_label( $t->status ) ), 'updated' => RSP_Admin::date( $t->updated_at ) );
		}
		RSP_Admin::table( array( 'id' => __( 'Ticket', 'relay-stream-portal' ), 'status' => __( 'Status', 'relay-stream-portal' ), 'updated' => __( 'Updated', 'relay-stream-portal' ) ), $rows );
		self::foot();
	}

	/* ===================== Streaming accounts ===================== */

	/**
	 * Manual provisioning fields.
	 *
	 * @param bool $with_meta Include nickname/plan/type.
	 */
	private static function manual_fields( $with_meta = false ) {
		echo '<table class="form-table rsp-compact">';
		if ( $with_meta ) {
			echo '<tr><th>' . esc_html__( 'Nickname', 'relay-stream-portal' ) . '</th><td><input type="text" name="nickname" class="regular-text"></td></tr>';
			echo '<tr><th>' . esc_html__( 'Plan label', 'relay-stream-portal' ) . '</th><td><input type="text" name="plan_label" class="regular-text"></td></tr>';
			echo '<tr><th>' . esc_html__( 'Type', 'relay-stream-portal' ) . '</th><td><select name="provider_type"><option value="m3u">M3U / Xtream</option><option value="mag">MAG</option></select></td></tr>';
		}
		$f = array(
			'provider_user_id' => __( 'Provider user ID', 'relay-stream-portal' ),
			'server'           => __( 'Server URL', 'relay-stream-portal' ),
			'username'         => __( 'Username', 'relay-stream-portal' ),
			'password'         => __( 'Password', 'relay-stream-portal' ),
			'url'              => __( 'M3U URL', 'relay-stream-portal' ),
			'mac'              => __( 'MAC address', 'relay-stream-portal' ),
			'max_connections'  => __( 'Max connections', 'relay-stream-portal' ),
		);
		foreach ( $f as $k => $l ) {
			echo '<tr><th>' . esc_html( $l ) . '</th><td><input type="' . ( 'password' === $k ? 'password' : ( 'max_connections' === $k ? 'number' : 'text' ) ) . '" name="' . esc_attr( $k ) . '" class="regular-text" autocomplete="off"></td></tr>';
		}
		echo '<tr><th>' . esc_html__( 'Confirmed expiry', 'relay-stream-portal' ) . ' *</th><td><input type="date" name="expiry" required></td></tr>';
		echo '<tr><th>' . esc_html__( 'Reason (audit log)', 'relay-stream-portal' ) . ' *</th><td><input type="text" name="reason" required class="regular-text"></td></tr>';
		echo '</table>';
	}

	/**
	 * Accounts table.
	 *
	 * @param array $accounts Rows.
	 */
	private static function accounts_table( $accounts ) {
		$rows = array();
		foreach ( (array) $accounts as $a ) {
			$job    = RSP_Jobs::open_for_account( (int) $a->id );
			$rows[] = array(
				'account' => '<a href="' . esc_url( admin_url( 'admin.php?page=rsp-accounts&account=' . $a->uuid ) ) . '"><strong>' . esc_html( $a->nickname ) . '</strong></a><br><small><code>' . esc_html( $a->uuid ) . '</code></small>',
				'owner'   => RSP_Admin::user_link( $a->user_id ),
				'status'  => RSP_Admin::pill( $a->status, RSP_Accounts::status_label( $a->status ) ) . ( $job ? '<br><small>' . esc_html( sprintf( /* translators: 1: type 2: state */ __( 'Job: %1$s (%2$s)', 'relay-stream-portal' ), $job->type, RSP_Jobs::state_label( $job->state ) ) ) . '</small>' : '' ) . ( $a->review_reason ? '<br><small class="rsp-warn">' . esc_html( $a->review_reason ) . '</small>' : '' ),
				'plan'    => esc_html( $a->plan_label ) . '<br><small>' . esc_html( $a->provider . ' / ' . $a->provider_type . ( $a->provider_user_id ? ' #' . $a->provider_user_id : '' ) ) . '</small>',
				'expires' => RSP_Admin::date( $a->expires_at ),
			);
		}
		RSP_Admin::table( array( 'account' => __( 'Account', 'relay-stream-portal' ), 'owner' => __( 'Owner', 'relay-stream-portal' ), 'status' => __( 'Status', 'relay-stream-portal' ), 'plan' => __( 'Plan / provider', 'relay-stream-portal' ), 'expires' => __( 'Expires', 'relay-stream-portal' ) ), $rows );
	}

	/**
	 * Accounts page.
	 */
	public static function accounts() {
		global $wpdb;
		$uuid = RSP_Admin::q( 'account' );
		if ( $uuid ) {
			self::account_detail( $uuid );
			return;
		}
		self::head( __( 'Streaming Accounts', 'relay-stream-portal' ) );
		$statuses = array();
		foreach ( RSP_Accounts::STATUSES as $st ) {
			$statuses[ $st ] = RSP_Accounts::status_label( $st );
		}
		RSP_Admin::filters( 'rsp-accounts', array( 'status' => $statuses ), 'accounts' );
		list( $where, $params ) = self::account_where();
		$t     = RSP_Installer::table( 'accounts' );
		$total = (int) $wpdb->get_var( $params ? $wpdb->prepare( "SELECT COUNT(*) FROM $t a WHERE $where", $params ) : "SELECT COUNT(*) FROM $t a WHERE $where" ); // phpcs:ignore
		$sql   = "SELECT a.* FROM $t a WHERE $where ORDER BY a.id DESC LIMIT %d OFFSET %d";
		$rows  = $wpdb->get_results( $wpdb->prepare( $sql, array_merge( $params, array( self::PER_PAGE, ( RSP_Admin::paged() - 1 ) * self::PER_PAGE ) ) ) ); // phpcs:ignore
		self::accounts_table( $rows );
		RSP_Admin::pager( $total, self::PER_PAGE );
		self::foot();
	}

	/**
	 * WHERE for account filters (shared with export).
	 *
	 * @return array where, params
	 */
	public static function account_where() {
		global $wpdb;
		$where  = '1=1';
		$params = array();
		$st     = RSP_Admin::q( 'status' );
		if ( $st && in_array( $st, RSP_Accounts::STATUSES, true ) ) {
			$where   .= ' AND a.status = %s';
			$params[] = $st;
		}
		$s = RSP_Admin::q( 's' );
		if ( '' !== $s ) {
			$like     = '%' . $wpdb->esc_like( $s ) . '%';
			$where   .= ' AND (a.nickname LIKE %s OR a.uuid LIKE %s OR a.provider_user_id LIKE %s OR a.user_id IN (SELECT ID FROM ' . $wpdb->users . ' WHERE user_email LIKE %s OR display_name LIKE %s))';
			$params   = array_merge( $params, array( $like, $like, $like, $like, $like ) );
		}
		return array( $where, $params );
	}

	/**
	 * Account detail.
	 *
	 * @param string $uuid UUID.
	 */
	private static function account_detail( $uuid ) {
		global $wpdb;
		$a = RSP_Accounts::get_by_uuid( $uuid );
		if ( ! $a ) {
			wp_die( esc_html__( 'Account not found.', 'relay-stream-portal' ) );
		}
		self::head( $a->nickname );
		echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=rsp-accounts' ) ) . '">← ' . esc_html__( 'All accounts', 'relay-stream-portal' ) . '</a> · ' . esc_html__( 'Owner:', 'relay-stream-portal' ) . ' ' . RSP_Admin::user_link( $a->user_id ) . '</p>'; // phpcs:ignore
		$c = RSP_Accounts::credentials( $a );
		echo '<div class="rsp-columns"><div class="rsp-panel"><h2>' . esc_html__( 'Details', 'relay-stream-portal' ) . '</h2>';
		echo RSP_Admin::form( 'account_save' ) . '<input type="hidden" name="uuid" value="' . esc_attr( $a->uuid ) . '"><table class="form-table rsp-compact">'; // phpcs:ignore
		echo '<tr><th>' . esc_html__( 'Nickname', 'relay-stream-portal' ) . '</th><td><input type="text" name="nickname" value="' . esc_attr( $a->nickname ) . '" class="regular-text"></td></tr>';
		echo '<tr><th>' . esc_html__( 'Status', 'relay-stream-portal' ) . '</th><td><select name="status">';
		foreach ( RSP_Accounts::STATUSES as $st ) {
			echo '<option value="' . esc_attr( $st ) . '"' . selected( $a->status, $st, false ) . '>' . esc_html( RSP_Accounts::status_label( $st ) ) . '</option>';
		}
		echo '</select></td></tr>';
		echo '<tr><th>' . esc_html__( 'Plan label', 'relay-stream-portal' ) . '</th><td><input type="text" name="plan_label" value="' . esc_attr( $a->plan_label ) . '" class="regular-text"></td></tr>';
		echo '<tr><th>' . esc_html__( 'Expiry (YYYY-MM-DD)', 'relay-stream-portal' ) . '</th><td><input type="date" name="expiry" value="' . esc_attr( $a->expires_at ? substr( $a->expires_at, 0, 10 ) : '' ) . '"></td></tr>';
		echo '<tr><th>' . esc_html__( 'Max connections', 'relay-stream-portal' ) . '</th><td><input type="number" name="max_connections" min="0" value="' . esc_attr( null === $a->max_connections ? '' : (int) $a->max_connections ) . '"><p class="description">' . esc_html__( 'The provider API does not document device limits; record them here if known.', 'relay-stream-portal' ) . '</p></td></tr>';
		echo '<tr><th>' . esc_html__( 'Provider user ID', 'relay-stream-portal' ) . '</th><td><input type="text" name="provider_user_id" value="' . esc_attr( (string) $a->provider_user_id ) . '"></td></tr>';
		echo '<tr><th>' . esc_html__( 'Credentials', 'relay-stream-portal' ) . '</th><td>';
		foreach ( array( 'server', 'username', 'password', 'url', 'mac' ) as $k ) {
			echo '<p><label>' . esc_html( $k ) . ' <input type="' . ( 'password' === $k ? 'password' : 'text' ) . '" name="cred_' . esc_attr( $k ) . '" value="" placeholder="' . esc_attr( isset( $c[ $k ] ) ? RSP_Crypto::mask( $c[ $k ] ) : '' ) . '" autocomplete="off" class="regular-text"></label></p>';
		}
		echo '<p class="description">' . esc_html__( 'Leave blank to keep the stored value. Stored values are encrypted and shown masked.', 'relay-stream-portal' ) . '</p></td></tr>';
		echo '<tr><th>' . esc_html__( 'Reason (audit log)', 'relay-stream-portal' ) . ' *</th><td><input type="text" name="reason" required class="regular-text"></td></tr>';
		echo '</table><p><button class="button button-primary">' . esc_html__( 'Save account', 'relay-stream-portal' ) . '</button></p></form>';
		if ( current_user_can( 'rsp_view_credentials' ) && $c ) {
			echo RSP_Admin::button( 'account_reveal', array( 'uuid' => $a->uuid ), __( 'Reveal credentials (audited)', 'relay-stream-portal' ), 'button', true ); // phpcs:ignore
			$shown = get_transient( 'rsp_reveal_' . get_current_user_id() . '_' . $a->id );
			if ( $shown ) {
				delete_transient( 'rsp_reveal_' . get_current_user_id() . '_' . $a->id );
				echo '<pre class="rsp-secret">';
				foreach ( $c as $k => $v ) {
					echo esc_html( $k . ': ' . $v ) . "\n";
				}
				echo '</pre>';
			}
		}
		echo '</div><div class="rsp-panel"><h2>' . esc_html__( 'Provider actions', 'relay-stream-portal' ) . '</h2>';
		$caps = RSP_Provider_Factory::get()->capabilities();
		if ( RSP_Provider_Factory::is_live() && ! empty( $caps['lookup']['supported'] ) && $c ) {
			echo RSP_Admin::button( 'account_refresh', array( 'uuid' => $a->uuid ), __( 'Refresh status from panel', 'relay-stream-portal' ) ); // phpcs:ignore
		}
		echo RSP_Admin::form( 'account_renew', 'class="rsp-row-form"' ) . '<input type="hidden" name="uuid" value="' . esc_attr( $a->uuid ) . '">'; // phpcs:ignore
		echo '<label>' . esc_html__( 'Queue renewal', 'relay-stream-portal' ) . ' <select name="months"><option>1</option><option>3</option><option>6</option><option>12</option></select> ' . esc_html__( 'months', 'relay-stream-portal' ) . '</label> <input type="text" name="reason" required placeholder="' . esc_attr__( 'Reason', 'relay-stream-portal' ) . '"> <button class="button">' . esc_html__( 'Queue', 'relay-stream-portal' ) . '</button></form>';
		echo '<p class="description">' . esc_html__( 'Suspend/delete are not documented by the provider API. Use the status field to record a suspension done in the panel.', 'relay-stream-portal' ) . '</p>';
		echo '<h3>' . esc_html__( 'Operations', 'relay-stream-portal' ) . '</h3>';
		$jobs = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . RSP_Installer::table( 'jobs' ) . ' WHERE account_id = %d ORDER BY id DESC LIMIT 20', $a->id ) ); // phpcs:ignore
		self::jobs_table( $jobs );
		echo '</div></div>';
		self::foot();
	}

	/* ===================== Plans ===================== */

	/**
	 * Plans.
	 */
	public static function plans() {
		self::head( __( 'Plans', 'relay-stream-portal' ), __( 'Plans are WooCommerce products with the “Streaming plan” tab enabled. Orders keep an immutable snapshot of the plan and package at purchase time, so editing a plan never changes past orders.', 'relay-stream-portal' ) );
		if ( ! RSP_Plugin::has_woocommerce() ) {
			echo '<p>' . esc_html__( 'Activate WooCommerce to manage plans.', 'relay-stream-portal' ) . '</p>';
			self::foot();
			return;
		}
		echo '<p><a class="button button-primary" href="' . esc_url( admin_url( 'post-new.php?post_type=product' ) ) . '">' . esc_html__( 'Add plan product', 'relay-stream-portal' ) . '</a> ';
		echo RSP_Admin::button( 'demo_plans', array(), __( 'Create demo plans (1/3/6/12 months)', 'relay-stream-portal' ) ) . '</p>'; // phpcs:ignore
		$products = wc_get_products( array( 'limit' => 100, 'status' => array( 'publish', 'draft', 'private' ), 'meta_key' => '_rsp_is_plan', 'meta_value' => 'yes' ) ); // phpcs:ignore
		echo RSP_Admin::form( 'plans_save' ); // phpcs:ignore
		$rows = array();
		foreach ( $products as $p ) {
			$d      = RSP_Woocommerce::plan_data( $p );
			$rows[] = array(
				'name'    => '<a href="' . esc_url( get_edit_post_link( $p->get_id() ) ) . '"><strong>' . esc_html( $p->get_name() ) . '</strong></a> <small>(' . esc_html( $p->get_status() ) . ')</small>' . ( 'yes' === $p->get_meta( '_rsp_demo' ) ? ' <span class="rsp-badge">' . esc_html__( 'demo', 'relay-stream-portal' ) . '</span>' : '' ),
				'price'   => wp_kses_post( $p->get_price_html() ),
				'months'  => (int) $d['months'],
				'package' => '<code>' . esc_html( $d['package'] ) . '</code> / ' . esc_html( $d['type'] ),
				'avail'   => '<input type="hidden" name="plan[' . (int) $p->get_id() . '][id]" value="' . (int) $p->get_id() . '"><label><input type="checkbox" name="plan[' . (int) $p->get_id() . '][available]" value="1"' . checked( 'no' !== $p->get_meta( '_rsp_available' ), true, false ) . '> ' . esc_html__( 'Available', 'relay-stream-portal' ) . '</label>',
				'order'   => '<input type="number" class="small-text" name="plan[' . (int) $p->get_id() . '][order]" value="' . (int) $d['order'] . '">',
				'feat'    => '<input type="checkbox" name="plan[' . (int) $p->get_id() . '][featured]" value="1"' . checked( $d['featured'], true, false ) . '>',
			);
		}
		RSP_Admin::table( array( 'name' => __( 'Plan', 'relay-stream-portal' ), 'price' => __( 'Price', 'relay-stream-portal' ), 'months' => __( 'Months', 'relay-stream-portal' ), 'package' => __( 'Package / type', 'relay-stream-portal' ), 'avail' => __( 'Availability', 'relay-stream-portal' ), 'order' => __( 'Display order', 'relay-stream-portal' ), 'feat' => __( 'Featured', 'relay-stream-portal' ) ), $rows, __( 'No plan products yet.', 'relay-stream-portal' ) );
		if ( $rows ) {
			echo '<p><button class="button button-primary">' . esc_html__( 'Save plan display settings', 'relay-stream-portal' ) . '</button></p>';
		}
		echo '</form>';
		self::billing_settings();
		self::foot();
	}

	/**
	 * Billing display settings (plans page).
	 */
	private static function billing_settings() {
		$s = RSP_Settings::all();
		echo '<h2>' . esc_html__( 'Billing page content', 'relay-stream-portal' ) . '</h2>' . RSP_Admin::form( 'save_billing' ); // phpcs:ignore
		echo '<table class="form-table"><tr><th>' . esc_html__( 'What’s included (one per line)', 'relay-stream-portal' ) . '</th><td><textarea name="included_features" rows="5" class="large-text">' . esc_textarea( $s['included_features'] ) . '</textarea></td></tr>';
		echo '<tr><th>' . esc_html__( 'Refund policy note', 'relay-stream-portal' ) . '</th><td><textarea name="refund_policy_text" rows="3" class="large-text">' . esc_textarea( $s['refund_policy_text'] ) . '</textarea></td></tr>';
		echo '<tr><th>' . esc_html__( 'Payment guides', 'relay-stream-portal' ) . '</th><td><div class="rsp-repeater" data-rsp-repeater>';
		$guides = (array) $s['payment_guides'];
		$guides[] = array( 'title' => '', 'url' => '', 'text' => '' );
		foreach ( array_values( $guides ) as $i => $g ) {
			echo '<div class="rsp-rep-row"><input type="text" name="payment_guides[' . (int) $i . '][title]" placeholder="' . esc_attr__( 'Title', 'relay-stream-portal' ) . '" value="' . esc_attr( $g['title'] ) . '"> <input type="url" name="payment_guides[' . (int) $i . '][url]" placeholder="https://" value="' . esc_attr( $g['url'] ) . '"> <input type="text" class="regular-text" name="payment_guides[' . (int) $i . '][text]" placeholder="' . esc_attr__( 'Short text', 'relay-stream-portal' ) . '" value="' . esc_attr( $g['text'] ) . '"></div>';
		}
		echo '</div><p class="description">' . esc_html__( 'Empty rows are ignored. Only link to official, legitimate payment guides.', 'relay-stream-portal' ) . '</p></td></tr>';
		echo '<tr><th>' . esc_html__( 'Hide stale unpaid orders', 'relay-stream-portal' ) . '</th><td><label><input type="checkbox" name="hide_stale_unpaid" value="1"' . checked( $s['hide_stale_unpaid'], 1, false ) . '> ' . esc_html__( 'Hide pending/failed orders older than', 'relay-stream-portal' ) . '</label> <input type="number" class="small-text" name="stale_unpaid_hours" value="' . (int) $s['stale_unpaid_hours'] . '"> ' . esc_html__( 'hours by default in Transactions (customers can toggle).', 'relay-stream-portal' ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Max accounts per customer', 'relay-stream-portal' ) . '</th><td><input type="number" class="small-text" name="max_accounts" value="' . (int) $s['max_accounts'] . '"></td></tr>';
		echo '</table><p><button class="button button-primary">' . esc_html__( 'Save', 'relay-stream-portal' ) . '</button></p></form>';
	}

	/* ===================== Orders ===================== */

	/**
	 * Orders & transactions.
	 */
	public static function orders() {
		self::head( __( 'Orders & Transactions', 'relay-stream-portal' ), __( 'Payment status (WooCommerce) and provisioning status (this plugin) are tracked separately. Paid orders awaiting activation appear as Queued or Under review.', 'relay-stream-portal' ) );
		if ( ! RSP_Plugin::has_woocommerce() ) {
			self::foot();
			return;
		}
		$prov = array( 'awaiting' => RSP_Fulfillment::status_label( 'awaiting' ), 'pending' => RSP_Fulfillment::status_label( 'pending' ), 'needs_review' => RSP_Fulfillment::status_label( 'needs_review' ), 'failed' => RSP_Fulfillment::status_label( 'failed' ), 'succeeded' => RSP_Fulfillment::status_label( 'succeeded' ), 'gift_issued' => RSP_Fulfillment::status_label( 'gift_issued' ) );
		$pay  = array();
		foreach ( wc_get_order_statuses() as $k => $l ) {
			$pay[ substr( $k, 3 ) ] = $l;
		}
		RSP_Admin::filters( 'rsp-orders', array( 'pay' => $pay, 'prov' => $prov, 'flag' => array( 'review' => __( 'Flagged for review', 'relay-stream-portal' ) ) ), 'orders' );
		$args = self::order_query_args();
		$args['limit']    = self::PER_PAGE;
		$args['page']     = RSP_Admin::paged();
		$args['paginate'] = true;
		$res  = wc_get_orders( $args );
		$rows = array();
		foreach ( $res->orders as $o ) {
			$p     = RSP_Fulfillment::order_provisioning_status( $o );
			$items = array();
			foreach ( $o->get_items() as $it ) {
				$sn = RSP_Woocommerce::snapshot( $it );
				if ( $sn ) {
					$items[] = esc_html( $sn['plan_name'] . ' · ' . $sn['purpose'] . ' · ' . $sn['months'] . 'm · pack ' . $sn['package'] );
				}
			}
			$flag   = $o->get_meta( '_rsp_needs_review' );
			$rows[] = array(
				'order' => '<a href="' . esc_url( $o->get_edit_order_url() ) . '"><strong>#' . esc_html( $o->get_order_number() ) . '</strong></a><br><small>' . esc_html( $o->get_date_created() ? $o->get_date_created()->date( 'Y-m-d H:i' ) : '' ) . '</small>',
				'cust'  => RSP_Admin::user_link( $o->get_customer_id() ),
				'items' => implode( '<br>', $items ),
				'total' => wp_kses_post( $o->get_formatted_order_total() ) . '<br><small>' . esc_html( $o->get_payment_method_title() ) . '</small>',
				'pay'   => RSP_Admin::pill( $o->get_status(), wc_get_order_status_name( $o->get_status() ) ),
				'prov'  => RSP_Admin::pill( $p, RSP_Fulfillment::status_label( $p ) ) . ( $flag ? '<br><small class="rsp-warn">' . esc_html( $flag ) . '</small><br>' . RSP_Admin::button( 'order_review_clear', array( 'order_id' => $o->get_id() ), __( 'Mark reviewed', 'relay-stream-portal' ), 'button-link', true ) : '' ),
			);
		}
		RSP_Admin::table( array( 'order' => __( 'Order', 'relay-stream-portal' ), 'cust' => __( 'Customer', 'relay-stream-portal' ), 'items' => __( 'Plan snapshot', 'relay-stream-portal' ), 'total' => __( 'Amount', 'relay-stream-portal' ), 'pay' => __( 'Payment', 'relay-stream-portal' ), 'prov' => __( 'Provisioning', 'relay-stream-portal' ) ), $rows );
		RSP_Admin::pager( (int) $res->total, self::PER_PAGE );
		self::foot();
	}

	/**
	 * Order query args from filters (shared with export).
	 *
	 * @return array
	 */
	public static function order_query_args() {
		$meta = array( array( 'key' => '_rsp_has_plan', 'value' => 'yes' ) );
		$prov = RSP_Admin::q( 'prov' );
		if ( $prov && 'awaiting' !== $prov ) {
			$meta[] = array( 'key' => '_rsp_provisioning', 'value' => $prov );
		}
		if ( 'review' === RSP_Admin::q( 'flag' ) ) {
			$meta[] = array( 'key' => '_rsp_needs_review', 'compare' => 'EXISTS' );
		}
		$args = array( 'type' => 'shop_order', 'orderby' => 'date', 'order' => 'DESC' );
		$args[ RSP_Woocommerce::hpos() ? 'meta_query' : 'rsp_meta_query' ] = $meta; // phpcs:ignore WordPress.DB.SlowDBQuery
		$pay  = RSP_Admin::q( 'pay' );
		if ( 'awaiting' === $prov ) {
			$args['status'] = array( 'wc-pending', 'wc-on-hold' );
		} elseif ( $pay ) {
			$args['status'] = array( 'wc-' . sanitize_key( $pay ) );
		}
		$s = RSP_Admin::q( 's' );
		if ( '' !== $s ) {
			if ( ctype_digit( $s ) ) {
				$args['id'] = array( (int) $s );
			} else {
				$args['billing_email'] = sanitize_email( $s );
			}
		}
		return $args;
	}

	/* ===================== API & Jobs ===================== */

	/**
	 * Jobs table.
	 *
	 * @param array $jobs Jobs.
	 */
	public static function jobs_table( $jobs ) {
		$rows = array();
		foreach ( (array) $jobs as $j ) {
			$acc     = $j->account_id ? RSP_Accounts::get( (int) $j->account_id ) : null;
			$actions = array();
			if ( in_array( $j->state, array( 'failed', 'needs_review' ), true ) ) {
				if ( RSP_Provider_Factory::is_live() ) {
					$actions[] = RSP_Admin::button( 'job_retry', array( 'job' => $j->id ), __( 'Retry', 'relay-stream-portal' ), 'button button-small', true );
					if ( $acc && $acc->credentials ) {
						$actions[] = RSP_Admin::button( 'job_reconcile', array( 'job' => $j->id ), __( 'Reconcile', 'relay-stream-portal' ), 'button button-small', true );
					}
				}
				$actions[] = '<a class="button button-small" href="' . esc_url( admin_url( 'admin.php?page=rsp-api&job=' . (int) $j->id ) ) . '">' . esc_html__( 'Complete manually', 'relay-stream-portal' ) . '</a>';
			}
			if ( in_array( $j->state, array( 'pending', 'needs_review' ), true ) ) {
				$actions[] = RSP_Admin::button( 'job_cancel', array( 'job' => $j->id ), __( 'Cancel', 'relay-stream-portal' ), 'button-link rsp-danger', true );
			}
			$res    = json_decode( (string) $j->result, true );
			$rows[] = array(
				'id'      => '#' . (int) $j->id . '<br><small>' . esc_html( $j->type ) . '</small>',
				'state'   => RSP_Admin::pill( $j->state, RSP_Jobs::state_label( $j->state ) ) . '<br><small>' . esc_html( sprintf( /* translators: 1: step 2: attempts */ __( 'step %1$s · %2$d attempts', 'relay-stream-portal' ), $j->step, $j->attempts ) ) . '</small>',
				'account' => $acc ? '<a href="' . esc_url( admin_url( 'admin.php?page=rsp-accounts&account=' . $acc->uuid ) ) . '">' . esc_html( $acc->nickname ) . '</a>' : '—',
				'order'   => $j->order_id && RSP_Plugin::has_woocommerce() && ( $o = wc_get_order( (int) $j->order_id ) ) ? '<a href="' . esc_url( $o->get_edit_order_url() ) . '">#' . esc_html( $o->get_order_number() ) . '</a>' : '—', // phpcs:ignore
				'info'    => esc_html( $j->review_reason ? $j->review_reason : $j->last_error ) . ( ! empty( $res['confirmed_expiry'] ) ? '<br><small>' . esc_html( sprintf( /* translators: %s date */ __( 'Confirmed expiry %s', 'relay-stream-portal' ), $res['confirmed_expiry'] ) ) . '</small>' : '' ),
				'updated' => RSP_Admin::date( $j->updated_at ),
				'actions' => implode( ' ', $actions ),
			);
		}
		RSP_Admin::table( array( 'id' => __( 'Job', 'relay-stream-portal' ), 'state' => __( 'State', 'relay-stream-portal' ), 'account' => __( 'Account', 'relay-stream-portal' ), 'order' => __( 'Order', 'relay-stream-portal' ), 'info' => __( 'Details', 'relay-stream-portal' ), 'updated' => __( 'Updated', 'relay-stream-portal' ), 'actions' => __( 'Actions', 'relay-stream-portal' ) ), $rows, __( 'No operations.', 'relay-stream-portal' ) );
	}

	/**
	 * API settings + queue.
	 */
	public static function api() {
		global $wpdb;
		$job_id = (int) RSP_Admin::q( 'job' );
		if ( $job_id ) {
			self::manual_job( $job_id );
			return;
		}
		self::head( __( 'API Settings & Job Queue', 'relay-stream-portal' ) );
		$s     = RSP_Settings::all();
		$caps  = RSP_Provider_Factory::tvplus()->capabilities();
		$can_s = current_user_can( 'rsp_manage_settings' );

		echo '<div class="rsp-columns"><div class="rsp-panel"><h2>' . esc_html__( 'Provider connection (TVPlus Panel API)', 'relay-stream-portal' ) . '</h2>';
		if ( $can_s ) {
			echo RSP_Admin::form( 'save_api' ) . '<table class="form-table rsp-compact">'; // phpcs:ignore
			echo '<tr><th>' . esc_html__( 'Mode', 'relay-stream-portal' ) . '</th><td><select name="api_mode"><option value="manual"' . selected( $s['api_mode'], 'manual', false ) . '>' . esc_html__( 'Manual provisioning', 'relay-stream-portal' ) . '</option><option value="live"' . selected( $s['api_mode'], 'live', false ) . '>' . esc_html__( 'Live API', 'relay-stream-portal' ) . '</option></select><p class="description">' . esc_html__( 'Live mode is only used when the mapping is verified and an API key is saved.', 'relay-stream-portal' ) . '</p></td></tr>';
			echo '<tr><th>' . esc_html__( 'API endpoint URL', 'relay-stream-portal' ) . '</th><td><input type="url" name="api_base_url" class="regular-text code" value="' . esc_attr( $s['api_base_url'] ) . '"><p class="description">' . esc_html__( 'HTTPS only. The documentation lists https://tvpluspanel.net/api/api.php.', 'relay-stream-portal' ) . '</p></td></tr>';
			echo '<tr><th>' . esc_html__( 'Documentation URL (reference only)', 'relay-stream-portal' ) . '</th><td><input type="url" name="api_docs_url" class="regular-text code" value="' . esc_attr( $s['api_docs_url'] ) . '"></td></tr>';
			echo '<tr><th>' . esc_html__( 'Allowed API hosts', 'relay-stream-portal' ) . '</th><td><input type="text" name="api_allowed_hosts" class="regular-text code" value="' . esc_attr( $s['api_allowed_hosts'] ) . '"><p class="description">' . esc_html__( 'Comma separated. Requests to any other host are refused; redirects are never followed.', 'relay-stream-portal' ) . '</p></td></tr>';
			echo '<tr><th>' . esc_html__( 'API key', 'relay-stream-portal' ) . '</th><td><input type="password" name="api_key" class="regular-text" autocomplete="new-password" placeholder="' . esc_attr( $s['api_key_enc'] ? RSP_Crypto::mask( RSP_Crypto::decrypt( $s['api_key_enc'] ) ) : __( 'Not set', 'relay-stream-portal' ) ) . '"> <label><input type="checkbox" name="api_key_clear" value="1"> ' . esc_html__( 'Remove key', 'relay-stream-portal' ) . '</label><p class="description">' . esc_html__( 'Stored encrypted. Leave blank to keep the current key.', 'relay-stream-portal' ) . '</p></td></tr>';
			echo '<tr><th>' . esc_html__( 'Timeout (seconds)', 'relay-stream-portal' ) . '</th><td><input type="number" name="api_timeout" min="5" max="60" value="' . (int) $s['api_timeout'] . '"></td></tr>';
			echo '<tr><th>' . esc_html__( 'Panel note prefix', 'relay-stream-portal' ) . '</th><td><input type="text" name="api_note_prefix" value="' . esc_attr( $s['api_note_prefix'] ) . '"><p class="description">' . esc_html__( 'Sent as the documented “note” on create so staff can find accounts in the panel.', 'relay-stream-portal' ) . '</p></td></tr>';
			echo '<tr><th>' . esc_html__( 'Mapping verified', 'relay-stream-portal' ) . '</th><td><label><input type="checkbox" name="api_mapping_verified" value="1"' . checked( $s['api_mapping_verified'], 1, false ) . '> ' . esc_html__( 'I confirmed the operations below against my real panel (test connection, packages, one create + lookup on a test line).', 'relay-stream-portal' ) . '</label></td></tr>';
			echo '<tr><th>' . esc_html__( 'Reward month conversion', 'relay-stream-portal' ) . '</th><td><label><input type="checkbox" name="reward_month_conversion" value="1"' . checked( $s['reward_month_conversion'], 1, false ) . '> ' . esc_html__( 'Apply 30 reward days as a confirmed 1-month renewal (the API cannot add arbitrary days). Otherwise reward days go to staff review.', 'relay-stream-portal' ) . '</label></td></tr>';
			echo '</table><p><button class="button button-primary">' . esc_html__( 'Save API settings', 'relay-stream-portal' ) . '</button></p></form>';
		}
		echo '<p>' . RSP_Admin::button( 'test_connection', array(), __( 'Test connection', 'relay-stream-portal' ) ) . ' ' . RSP_Admin::button( 'load_packages', array(), __( 'Load packages from panel', 'relay-stream-portal' ) ) . '</p>'; // phpcs:ignore
		$test = get_transient( 'rsp_test_result_' . get_current_user_id() );
		if ( $test ) {
			delete_transient( 'rsp_test_result_' . get_current_user_id() );
			echo '<pre class="rsp-result">' . esc_html( wp_json_encode( $test, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ) . '</pre>';
		}
		$pk = get_option( 'rsp_packages_cache', array() );
		if ( $pk ) {
			echo '<p>' . esc_html( sprintf( /* translators: %d count */ __( '%d packages cached for the product “Provider package” field.', 'relay-stream-portal' ), count( $pk ) ) ) . '</p>';
		}
		echo '</div><div class="rsp-panel"><h2>' . esc_html__( 'Capabilities (from the documented API)', 'relay-stream-portal' ) . '</h2>';
		$rows = array();
		foreach ( $caps as $k => $c ) {
			$rows[] = array( 'cap' => '<code>' . esc_html( $k ) . '</code>', 'ok' => $c['supported'] ? '<span class="dashicons dashicons-yes" style="color:#16a34a"></span>' : '<span class="dashicons dashicons-no-alt" style="color:#dc2626"></span>', 'note' => esc_html( $c['note'] ) );
		}
		RSP_Admin::table( array( 'cap' => __( 'Operation', 'relay-stream-portal' ), 'ok' => __( 'Supported', 'relay-stream-portal' ), 'note' => __( 'Mapping / note', 'relay-stream-portal' ) ), $rows );
		echo '<p class="description">' . esc_html__( 'See docs/API-MAPPING.md in the plugin folder for endpoints, parameters and responses.', 'relay-stream-portal' ) . '</p></div></div>';

		echo '<h2>' . esc_html__( 'Operations queue', 'relay-stream-portal' ) . '</h2>';
		echo '<p>' . RSP_Admin::button( 'run_queue', array(), __( 'Run queue now', 'relay-stream-portal' ) ) . '</p>'; // phpcs:ignore
		$states = array();
		foreach ( RSP_Jobs::STATES as $st ) {
			$states[ $st ] = RSP_Jobs::state_label( $st ) . ' (' . self::count( 'jobs', "state = '" . esc_sql( $st ) . "'" ) . ')';
		}
		RSP_Admin::filters( 'rsp-api', array( 'state' => $states, 'type' => array( 'create' => 'create', 'renew' => 'renew', 'reward_extend' => 'reward_extend' ) ), 'jobs' );
		list( $where, $params ) = self::job_where();
		$t     = RSP_Installer::table( 'jobs' );
		$total = (int) $wpdb->get_var( $params ? $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE $where", $params ) : "SELECT COUNT(*) FROM $t WHERE $where" ); // phpcs:ignore
		$jobs  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE $where ORDER BY FIELD(state,'needs_review','failed','working','pending','succeeded'), id DESC LIMIT %d OFFSET %d", array_merge( $params, array( self::PER_PAGE, ( RSP_Admin::paged() - 1 ) * self::PER_PAGE ) ) ) ); // phpcs:ignore
		self::jobs_table( $jobs );
		RSP_Admin::pager( $total, self::PER_PAGE );
		self::foot();
	}

	/**
	 * Job filter WHERE.
	 *
	 * @return array
	 */
	public static function job_where() {
		$where  = '1=1';
		$params = array();
		if ( in_array( RSP_Admin::q( 'state' ), RSP_Jobs::STATES, true ) ) {
			$where   .= ' AND state = %s';
			$params[] = RSP_Admin::q( 'state' );
		}
		if ( in_array( RSP_Admin::q( 'type' ), array( 'create', 'renew', 'reward_extend' ), true ) ) {
			$where   .= ' AND type = %s';
			$params[] = RSP_Admin::q( 'type' );
		}
		if ( ctype_digit( RSP_Admin::q( 's' ) ) ) {
			$where   .= ' AND (id = %d OR order_id = %d)';
			$params[] = (int) RSP_Admin::q( 's' );
			$params[] = (int) RSP_Admin::q( 's' );
		}
		return array( $where, $params );
	}

	/**
	 * Manual completion form.
	 *
	 * @param int $id Job.
	 */
	private static function manual_job( $id ) {
		$j = RSP_Jobs::get( $id );
		if ( ! $j ) {
			wp_die( esc_html__( 'Job not found.', 'relay-stream-portal' ) );
		}
		$a = $j->account_id ? RSP_Accounts::get( (int) $j->account_id ) : null;
		$p = RSP_Jobs::payload( $j );
		/* translators: %d job */
		self::head( sprintf( __( 'Complete job #%d manually', 'relay-stream-portal' ), $id ) );
		echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=rsp-api' ) ) . '">← ' . esc_html__( 'Back to queue', 'relay-stream-portal' ) . '</a></p>';
		echo '<div class="rsp-panel"><p><strong>' . esc_html__( 'Operation:', 'relay-stream-portal' ) . '</strong> ' . esc_html( $j->type ) . ' · ' . esc_html( sprintf( /* translators: %d months */ __( '%d months', 'relay-stream-portal' ), isset( $p['months'] ) ? (int) $p['months'] : 0 ) ) . ( isset( $p['days'] ) ? ' · ' . esc_html( sprintf( /* translators: %d days */ __( '%d reward days', 'relay-stream-portal' ), (int) $p['days'] ) ) : '' ) . ' · ' . esc_html__( 'package', 'relay-stream-portal' ) . ' <code>' . esc_html( isset( $p['package'] ) ? $p['package'] : '—' ) . '</code></p>';
		if ( $a ) {
			echo '<p><strong>' . esc_html__( 'Account:', 'relay-stream-portal' ) . '</strong> ' . esc_html( $a->nickname ) . ' (' . esc_html( $a->uuid ) . ') · ' . esc_html__( 'current expiry', 'relay-stream-portal' ) . ' ' . RSP_Admin::date( $a->expires_at ) . ' · ' . RSP_Admin::user_link( $a->user_id ) . '</p>'; // phpcs:ignore
		}
		echo '<p class="description">' . esc_html__( 'Perform the operation in the provider panel first, then record the confirmed details. For renewals, leave credential fields blank to keep the stored ones.', 'relay-stream-portal' ) . '</p>';
		echo RSP_Admin::form( 'job_complete' ) . '<input type="hidden" name="job" value="' . (int) $id . '">'; // phpcs:ignore
		self::manual_fields( false );
		echo '<p><button class="button button-primary">' . esc_html__( 'Mark as completed', 'relay-stream-portal' ) . '</button></p></form></div>';
		self::foot();
	}

	/* ===================== Referrals ===================== */

	/**
	 * Referrals.
	 */
	public static function referrals() {
		global $wpdb;
		self::head( __( 'Referrals', 'relay-stream-portal' ) );
		$s = RSP_Settings::all();
		if ( current_user_can( 'rsp_manage_settings' ) ) {
			echo '<details class="rsp-details"' . ( RSP_Admin::q( 'status' ) ? '' : ' open' ) . '><summary class="button">' . esc_html__( 'Referral rules', 'relay-stream-portal' ) . '</summary>' . RSP_Admin::form( 'save_settings' ) . '<input type="hidden" name="group" value="referrals"><table class="form-table rsp-compact">'; // phpcs:ignore
			echo '<tr><th>' . esc_html__( 'Enabled', 'relay-stream-portal' ) . '</th><td><input type="checkbox" name="referral_enabled" value="1"' . checked( $s['referral_enabled'], 1, false ) . '></td></tr>';
			echo '<tr><th>' . esc_html__( 'Reward (days)', 'relay-stream-portal' ) . '</th><td><input type="number" name="referral_days" min="0" value="' . (int) $s['referral_days'] . '"></td></tr>';
			echo '<tr><th>' . esc_html__( 'Review period (days)', 'relay-stream-portal' ) . '</th><td><input type="number" name="referral_review_days" min="0" value="' . (int) $s['referral_review_days'] . '"><p class="description">' . esc_html__( 'Days after the friend’s first confirmed payment before the reward is credited. Refunds inside this window cancel the reward.', 'relay-stream-portal' ) . '</p></td></tr>';
			echo '<tr><th>' . esc_html__( 'Minimum order total', 'relay-stream-portal' ) . '</th><td><input type="number" step="0.01" name="referral_min_total" min="0" value="' . esc_attr( $s['referral_min_total'] ) . '"></td></tr>';
			echo '<tr><th>' . esc_html__( 'Link cookie lifetime (days)', 'relay-stream-portal' ) . '</th><td><input type="number" name="referral_cookie_days" min="1" value="' . (int) $s['referral_cookie_days'] . '"></td></tr>';
			echo '<tr><th>' . esc_html__( 'After a refund of a rewarded order', 'relay-stream-portal' ) . '</th><td><select name="referral_reversal">';
			foreach ( array( 'reverse_unapplied' => __( 'Remove the days if still unused, otherwise review', 'relay-stream-portal' ), 'review' => __( 'Always send to staff review', 'relay-stream-portal' ), 'keep' => __( 'Keep the reward', 'relay-stream-portal' ) ) as $k => $l ) {
				echo '<option value="' . esc_attr( $k ) . '"' . selected( $s['referral_reversal'], $k, false ) . '>' . esc_html( $l ) . '</option>';
			}
			echo '</select></td></tr></table><p><button class="button button-primary">' . esc_html__( 'Save rules', 'relay-stream-portal' ) . '</button></p></form></details>';
		}
		$st = array( 'invited' => RSP_Referrals::status_label( 'invited' ), 'pending' => RSP_Referrals::status_label( 'pending' ), 'rewarded' => RSP_Referrals::status_label( 'rewarded' ), 'reversed' => __( 'Reversed', 'relay-stream-portal' ), 'rejected' => __( 'Rejected', 'relay-stream-portal' ), 'review' => RSP_Referrals::status_label( 'review' ) );
		RSP_Admin::filters( 'rsp-referrals', array( 'status' => $st ), 'referrals' );
		$t      = RSP_Installer::table( 'referrals' );
		$where  = '1=1';
		$params = array();
		if ( isset( $st[ RSP_Admin::q( 'status' ) ] ) ) {
			$where   .= ' AND status = %s';
			$params[] = RSP_Admin::q( 'status' );
		}
		$total = (int) $wpdb->get_var( $params ? $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE $where", $params ) : "SELECT COUNT(*) FROM $t WHERE $where" ); // phpcs:ignore
		$list  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE $where ORDER BY id DESC LIMIT %d OFFSET %d", array_merge( $params, array( self::PER_PAGE, ( RSP_Admin::paged() - 1 ) * self::PER_PAGE ) ) ) ); // phpcs:ignore
		$rows  = array();
		foreach ( (array) $list as $r ) {
			$act = array();
			if ( 'pending' === $r->status ) {
				$act[] = RSP_Admin::button( 'referral_action', array( 'id' => $r->id, 'op' => 'approve' ), __( 'Credit now', 'relay-stream-portal' ), 'button button-small', true );
			}
			if ( in_array( $r->status, array( 'pending', 'invited', 'review' ), true ) ) {
				$act[] = RSP_Admin::button( 'referral_action', array( 'id' => $r->id, 'op' => 'reject' ), __( 'Reject', 'relay-stream-portal' ), 'button-link rsp-danger', true );
			}
			if ( 'review' === $r->status ) {
				$act[] = RSP_Admin::button( 'referral_action', array( 'id' => $r->id, 'op' => 'keep' ), __( 'Keep reward', 'relay-stream-portal' ), 'button button-small', true );
			}
			$rows[] = array(
				'ref'    => RSP_Admin::user_link( $r->referrer_id ),
				'friend' => RSP_Admin::user_link( $r->referee_id ),
				'status' => RSP_Admin::pill( $r->status, isset( $st[ $r->status ] ) ? $st[ $r->status ] : $r->status ) . ( $r->eligible_at && 'pending' === $r->status ? '<br><small>' . esc_html__( 'eligible', 'relay-stream-portal' ) . ' ' . RSP_Admin::date( $r->eligible_at ) . '</small>' : '' ),
				'order'  => $r->order_id ? '#' . (int) $r->order_id : '—',
				'days'   => (int) $r->reward_days,
				'date'   => RSP_Admin::date( $r->created_at ),
				'act'    => implode( ' ', $act ),
			);
		}
		RSP_Admin::table( array( 'ref' => __( 'Referrer', 'relay-stream-portal' ), 'friend' => __( 'Friend', 'relay-stream-portal' ), 'status' => __( 'Status', 'relay-stream-portal' ), 'order' => __( 'Order', 'relay-stream-portal' ), 'days' => __( 'Days', 'relay-stream-portal' ), 'date' => __( 'Created', 'relay-stream-portal' ), 'act' => __( 'Actions', 'relay-stream-portal' ) ), $rows );
		RSP_Admin::pager( $total, self::PER_PAGE );
		self::foot();
	}

	/* ===================== Gifts ===================== */

	/**
	 * Gifts.
	 */
	public static function gifts() {
		global $wpdb;
		self::head( __( 'Gifts', 'relay-stream-portal' ), __( 'Gift codes are never shown here — only the last four characters. Purchasers see full codes in their portal.', 'relay-stream-portal' ) );
		$s = RSP_Settings::all();
		if ( current_user_can( 'rsp_manage_settings' ) ) {
			echo RSP_Admin::form( 'save_settings', 'class="rsp-row-form"' ) . '<input type="hidden" name="group" value="gifts">'; // phpcs:ignore
			echo '<label><input type="checkbox" name="gift_enabled" value="1"' . checked( $s['gift_enabled'], 1, false ) . '> ' . esc_html__( 'Gift purchases enabled', 'relay-stream-portal' ) . '</label> &nbsp; <label>' . esc_html__( 'Codes expire after', 'relay-stream-portal' ) . ' <input type="number" class="small-text" name="gift_expiry_days" min="1" value="' . (int) $s['gift_expiry_days'] . '"> ' . esc_html__( 'days', 'relay-stream-portal' ) . '</label> <button class="button">' . esc_html__( 'Save', 'relay-stream-portal' ) . '</button></form>';
		}
		$st = array( 'issued' => RSP_Gifts::status_label( 'issued' ), 'redeemed' => RSP_Gifts::status_label( 'redeemed' ), 'expired' => RSP_Gifts::status_label( 'expired' ), 'revoked' => RSP_Gifts::status_label( 'revoked' ), 'review' => RSP_Gifts::status_label( 'review' ) );
		RSP_Admin::filters( 'rsp-gifts', array( 'status' => $st ), 'gifts' );
		$t      = RSP_Installer::table( 'gifts' );
		$where  = '1=1';
		$params = array();
		if ( isset( $st[ RSP_Admin::q( 'status' ) ] ) ) {
			$where   .= ' AND status = %s';
			$params[] = RSP_Admin::q( 'status' );
		}
		if ( '' !== RSP_Admin::q( 's' ) ) {
			$where   .= ' AND (code_hint = %s OR order_id = %d)';
			$params[] = strtoupper( substr( RSP_Admin::q( 's' ), -4 ) );
			$params[] = (int) RSP_Admin::q( 's' );
		}
		$total = (int) $wpdb->get_var( $params ? $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE $where", $params ) : "SELECT COUNT(*) FROM $t WHERE $where" ); // phpcs:ignore
		$list  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE $where ORDER BY id DESC LIMIT %d OFFSET %d", array_merge( $params, array( self::PER_PAGE, ( RSP_Admin::paged() - 1 ) * self::PER_PAGE ) ) ) ); // phpcs:ignore
		$rows  = array();
		foreach ( (array) $list as $g ) {
			$sn  = json_decode( $g->plan_snapshot, true );
			$act = array();
			if ( 'issued' === $g->status ) {
				$act[] = RSP_Admin::button( 'gift_action', array( 'id' => $g->id, 'op' => 'revoke' ), __( 'Revoke', 'relay-stream-portal' ), 'button-link rsp-danger', true );
				$act[] = RSP_Admin::button( 'gift_action', array( 'id' => $g->id, 'op' => 'extend' ), __( 'Extend 30 days', 'relay-stream-portal' ), 'button button-small', true );
			}
			if ( 'review' === $g->status ) {
				$act[] = RSP_Admin::button( 'gift_action', array( 'id' => $g->id, 'op' => 'resolve' ), __( 'Mark resolved', 'relay-stream-portal' ), 'button button-small', true );
			}
			$rows[] = array(
				'code'   => '••••-••••-' . esc_html( $g->code_hint ),
				'plan'   => esc_html( isset( $sn['plan_name'] ) ? $sn['plan_name'] : '' ),
				'buyer'  => RSP_Admin::user_link( $g->purchaser_id ),
				'status' => RSP_Admin::pill( $g->status, RSP_Gifts::status_label( $g->status ) ),
				'red'    => $g->redeemed_by ? RSP_Admin::user_link( $g->redeemed_by ) . '<br><small>' . RSP_Admin::date( $g->redeemed_at ) . '</small>' : '—',
				'exp'    => RSP_Admin::date( $g->expires_at ),
				'act'    => implode( ' ', $act ),
			);
		}
		RSP_Admin::table( array( 'code' => __( 'Code', 'relay-stream-portal' ), 'plan' => __( 'Plan', 'relay-stream-portal' ), 'buyer' => __( 'Purchaser', 'relay-stream-portal' ), 'status' => __( 'Status', 'relay-stream-portal' ), 'red' => __( 'Redeemed by', 'relay-stream-portal' ), 'exp' => __( 'Expires', 'relay-stream-portal' ), 'act' => __( 'Actions', 'relay-stream-portal' ) ), $rows );
		RSP_Admin::pager( $total, self::PER_PAGE );
		self::foot();
	}

	/* ===================== Guides ===================== */

	/**
	 * Apps & guides editor.
	 */
	public static function guides() {
		self::head( __( 'Apps & Guides', 'relay-stream-portal' ), __( 'Only link to official, approved installers and store pages. QR codes are generated from these public URLs only — never from account credentials.', 'relay-stream-portal' ) );
		$g = RSP_Guides::get();
		$r = wp_parse_args( $g['release'], array( 'enabled' => 0, 'version' => '', 'title' => '', 'subtitle' => '', 'compat' => '', 'qr_url' => '', 'stats' => array() ) );
		echo RSP_Admin::form( 'guides_save' ); // phpcs:ignore
		echo '<h2>' . esc_html__( 'Release banner', 'relay-stream-portal' ) . '</h2><table class="form-table rsp-compact">';
		echo '<tr><th>' . esc_html__( 'Show banner', 'relay-stream-portal' ) . '</th><td><input type="checkbox" name="g[release][enabled]" value="1"' . checked( $r['enabled'], 1, false ) . '></td></tr>';
		foreach ( array( 'version' => __( 'Version', 'relay-stream-portal' ), 'title' => __( 'Title', 'relay-stream-portal' ), 'compat' => __( 'Compatibility', 'relay-stream-portal' ), 'qr_url' => __( 'QR / install page URL (public)', 'relay-stream-portal' ) ) as $k => $l ) {
			echo '<tr><th>' . esc_html( $l ) . '</th><td><input type="text" class="large-text" name="g[release][' . esc_attr( $k ) . ']" value="' . esc_attr( $r[ $k ] ) . '"></td></tr>';
		}
		echo '<tr><th>' . esc_html__( 'Subtitle', 'relay-stream-portal' ) . '</th><td><textarea class="large-text" rows="2" name="g[release][subtitle]">' . esc_textarea( $r['subtitle'] ) . '</textarea></td></tr>';
		echo '<tr><th>' . esc_html__( 'Stats', 'relay-stream-portal' ) . '</th><td>';
		$stats   = array_values( (array) $r['stats'] );
		$stats[] = array( 'label' => '', 'value' => '' );
		foreach ( $stats as $i => $s ) {
			echo '<p><input type="text" name="g[release][stats][' . (int) $i . '][label]" placeholder="' . esc_attr__( 'Label', 'relay-stream-portal' ) . '" value="' . esc_attr( $s['label'] ) . '"> <input type="text" name="g[release][stats][' . (int) $i . '][value]" placeholder="' . esc_attr__( 'Value', 'relay-stream-portal' ) . '" value="' . esc_attr( $s['value'] ) . '"></p>';
		}
		echo '</td></tr><tr><th>' . esc_html__( 'App sign-in note', 'relay-stream-portal' ) . '</th><td><textarea class="large-text" rows="2" name="g[login_note]">' . esc_textarea( $g['login_note'] ) . '</textarea></td></tr></table>';

		echo '<h2>' . esc_html__( 'Devices', 'relay-stream-portal' ) . '</h2><p class="description">' . esc_html__( 'Clear a device label to remove it. Use the blank card to add a device.', 'relay-stream-portal' ) . '</p><div class="rsp-device-editor">';
		$devices   = array_values( $g['devices'] );
		$devices[] = array( 'id' => '', 'label' => '', 'icon' => 'box', 'version' => '', 'download_url' => '', 'store_url' => '', 'download_code' => '', 'show_qr' => 0, 'previous' => array(), 'steps' => array() );
		foreach ( $devices as $i => $d ) {
			$d    = wp_parse_args( $d, array( 'id' => '', 'label' => '', 'icon' => 'box', 'version' => '', 'download_url' => '', 'store_url' => '', 'download_code' => '', 'show_qr' => 0, 'previous' => array(), 'steps' => array() ) );
			$n    = 'g[devices][' . (int) $i . ']';
			$prev = array();
			foreach ( (array) $d['previous'] as $p ) {
				$prev[] = $p['version'] . ( $p['url'] ? '|' . $p['url'] : '' );
			}
			echo '<fieldset class="rsp-panel"><legend>' . esc_html( $d['label'] ? $d['label'] : __( 'New device', 'relay-stream-portal' ) ) . '</legend>';
			echo '<p><label>' . esc_html__( 'Label', 'relay-stream-portal' ) . ' <input type="text" name="' . esc_attr( $n ) . '[label]" value="' . esc_attr( $d['label'] ) . '"></label> <label>ID <input type="text" class="small-text" name="' . esc_attr( $n ) . '[id]" value="' . esc_attr( $d['id'] ) . '"></label> <label>' . esc_html__( 'Icon', 'relay-stream-portal' ) . ' <select name="' . esc_attr( $n ) . '[icon]">';
			foreach ( RSP_Guides::icons() as $ic ) {
				echo '<option' . selected( $d['icon'], $ic, false ) . '>' . esc_html( $ic ) . '</option>';
			}
			echo '</select></label> <label>' . esc_html__( 'Version', 'relay-stream-portal' ) . ' <input type="text" class="small-text" name="' . esc_attr( $n ) . '[version]" value="' . esc_attr( $d['version'] ) . '"></label></p>';
			echo '<p><label>' . esc_html__( 'Official download URL', 'relay-stream-portal' ) . ' <input type="url" class="regular-text" name="' . esc_attr( $n ) . '[download_url]" value="' . esc_attr( $d['download_url'] ) . '"></label> <label>' . esc_html__( 'Store URL', 'relay-stream-portal' ) . ' <input type="url" class="regular-text" name="' . esc_attr( $n ) . '[store_url]" value="' . esc_attr( $d['store_url'] ) . '"></label></p>';
			echo '<p><label>' . esc_html__( 'Downloader code', 'relay-stream-portal' ) . ' <input type="text" name="' . esc_attr( $n ) . '[download_code]" value="' . esc_attr( $d['download_code'] ) . '"></label> <label><input type="checkbox" name="' . esc_attr( $n ) . '[show_qr]" value="1"' . checked( $d['show_qr'], 1, false ) . '> ' . esc_html__( 'Show QR for the download/store URL', 'relay-stream-portal' ) . '</label></p>';
			echo '<p><label>' . esc_html__( 'Setup steps (one per line)', 'relay-stream-portal' ) . '<br><textarea class="large-text" rows="4" name="' . esc_attr( $n ) . '[steps_text]">' . esc_textarea( implode( "\n", (array) $d['steps'] ) ) . '</textarea></label></p>';
			echo '<p><label>' . esc_html__( 'Previous releases (version|URL per line)', 'relay-stream-portal' ) . '<br><textarea class="large-text" rows="2" name="' . esc_attr( $n ) . '[previous_text]">' . esc_textarea( implode( "\n", $prev ) ) . '</textarea></label></p></fieldset>';
		}
		echo '</div><h2>' . esc_html__( 'What’s new', 'relay-stream-portal' ) . '</h2>';
		$wn   = array_values( $g['whats_new'] );
		$wn[] = array( 'title' => '', 'text' => '' );
		foreach ( $wn as $i => $w ) {
			echo '<p><input type="text" name="g[whats_new][' . (int) $i . '][title]" placeholder="' . esc_attr__( 'Title', 'relay-stream-portal' ) . '" value="' . esc_attr( $w['title'] ) . '"> <input type="text" class="large-text" style="max-width:600px" name="g[whats_new][' . (int) $i . '][text]" placeholder="' . esc_attr__( 'Description', 'relay-stream-portal' ) . '" value="' . esc_attr( $w['text'] ) . '"></p>';
		}
		echo '<h2>' . esc_html__( 'Third-party players', 'relay-stream-portal' ) . '</h2><p><textarea class="large-text" rows="2" name="g[third_party_note]">' . esc_textarea( $g['third_party_note'] ) . '</textarea></p>';
		$tp   = array_values( $g['third_party'] );
		$tp[] = array( 'name' => '', 'status' => 'soon', 'url' => '', 'text' => '' );
		foreach ( $tp as $i => $t ) {
			echo '<p><input type="text" name="g[third_party][' . (int) $i . '][name]" placeholder="' . esc_attr__( 'Name', 'relay-stream-portal' ) . '" value="' . esc_attr( $t['name'] ) . '"> <select name="g[third_party][' . (int) $i . '][status]"><option value="soon"' . selected( $t['status'], 'soon', false ) . '>' . esc_html__( 'Coming soon', 'relay-stream-portal' ) . '</option><option value="ready"' . selected( $t['status'], 'ready', false ) . '>' . esc_html__( 'Guide ready', 'relay-stream-portal' ) . '</option></select> <input type="url" name="g[third_party][' . (int) $i . '][url]" placeholder="https://" value="' . esc_attr( $t['url'] ) . '"> <input type="text" class="regular-text" name="g[third_party][' . (int) $i . '][text]" placeholder="' . esc_attr__( 'Short note', 'relay-stream-portal' ) . '" value="' . esc_attr( $t['text'] ) . '"></p>';
		}
		echo '<p><button class="button button-primary">' . esc_html__( 'Save guides', 'relay-stream-portal' ) . '</button></p></form>';
		self::foot();
	}

	/* ===================== Tickets ===================== */

	/**
	 * Tickets.
	 */
	public static function tickets() {
		global $wpdb;
		$tid = (int) RSP_Admin::q( 'ticket' );
		if ( $tid ) {
			self::ticket_detail( $tid );
			return;
		}
		self::head( __( 'Tickets', 'relay-stream-portal' ) );
		$st = array();
		foreach ( RSP_Tickets::STATUSES as $s ) {
			$st[ $s ] = RSP_Tickets::status_label( $s );
		}
		$st['awaiting_customer'] = __( 'Awaiting customer', 'relay-stream-portal' );
		$agents = array( 'me' => __( 'Assigned to me', 'relay-stream-portal' ), 'none' => __( 'Unassigned', 'relay-stream-portal' ) );
		RSP_Admin::filters( 'rsp-tickets', array( 'status' => $st, 'category' => RSP_Settings::support_categories(), 'assigned' => $agents ), 'tickets' );
		list( $where, $params ) = self::ticket_where();
		$t     = RSP_Installer::table( 'tickets' );
		$total = (int) $wpdb->get_var( $params ? $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE $where", $params ) : "SELECT COUNT(*) FROM $t WHERE $where" ); // phpcs:ignore
		$list  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE $where ORDER BY FIELD(status,'awaiting_staff','open','awaiting_customer','resolved','closed'), updated_at DESC LIMIT %d OFFSET %d", array_merge( $params, array( self::PER_PAGE, ( RSP_Admin::paged() - 1 ) * self::PER_PAGE ) ) ) ); // phpcs:ignore
		$cats  = RSP_Settings::support_categories();
		$rows  = array();
		foreach ( (array) $list as $r ) {
			$ag     = $r->assigned_to ? get_userdata( (int) $r->assigned_to ) : null;
			$rows[] = array(
				'subj'   => '<a href="' . esc_url( admin_url( 'admin.php?page=rsp-tickets&ticket=' . (int) $r->id ) ) . '"><strong>#' . (int) $r->id . ' ' . esc_html( $r->subject ) . '</strong></a><br><small>' . esc_html( isset( $cats[ $r->category ] ) ? $cats[ $r->category ] : $r->category ) . ' · ' . esc_html( $r->priority ) . '</small>',
				'cust'   => RSP_Admin::user_link( $r->user_id ),
				'status' => RSP_Admin::pill( $r->status, isset( $st[ $r->status ] ) ? $st[ $r->status ] : $r->status ),
				'agent'  => esc_html( $ag ? $ag->display_name : '—' ),
				'upd'    => RSP_Admin::date( $r->updated_at ),
			);
		}
		RSP_Admin::table( array( 'subj' => __( 'Ticket', 'relay-stream-portal' ), 'cust' => __( 'Customer', 'relay-stream-portal' ), 'status' => __( 'Status', 'relay-stream-portal' ), 'agent' => __( 'Assigned', 'relay-stream-portal' ), 'upd' => __( 'Updated', 'relay-stream-portal' ) ), $rows );
		RSP_Admin::pager( $total, self::PER_PAGE );
		if ( current_user_can( 'rsp_manage_settings' ) ) {
			$s = RSP_Settings::all();
			echo '<h2>' . esc_html__( 'Support settings', 'relay-stream-portal' ) . '</h2>' . RSP_Admin::form( 'save_settings' ) . '<input type="hidden" name="group" value="support"><table class="form-table rsp-compact">'; // phpcs:ignore
			echo '<tr><th>' . esc_html__( 'Categories (slug|Label per line)', 'relay-stream-portal' ) . '</th><td><textarea name="support_categories" rows="5" class="large-text code">' . esc_textarea( $s['support_categories'] ) . '</textarea></td></tr>';
			echo '<tr><th>' . esc_html__( 'Attachment types', 'relay-stream-portal' ) . '</th><td><input type="text" name="attach_types" value="' . esc_attr( $s['attach_types'] ) . '" class="regular-text code"></td></tr>';
			echo '<tr><th>' . esc_html__( 'Max attachment size (MB)', 'relay-stream-portal' ) . '</th><td><input type="number" min="1" max="50" name="attach_max_mb" value="' . (int) $s['attach_max_mb'] . '"></td></tr>';
			echo '</table><p><button class="button button-primary">' . esc_html__( 'Save', 'relay-stream-portal' ) . '</button></p></form>';
		}
		self::foot();
	}

	/**
	 * Ticket WHERE.
	 *
	 * @return array
	 */
	public static function ticket_where() {
		global $wpdb;
		$where  = '1=1';
		$params = array();
		if ( in_array( RSP_Admin::q( 'status' ), RSP_Tickets::STATUSES, true ) ) {
			$where   .= ' AND status = %s';
			$params[] = RSP_Admin::q( 'status' );
		}
		if ( '' !== RSP_Admin::q( 'category' ) ) {
			$where   .= ' AND category = %s';
			$params[] = sanitize_key( RSP_Admin::q( 'category' ) );
		}
		if ( 'me' === RSP_Admin::q( 'assigned' ) ) {
			$where   .= ' AND assigned_to = %d';
			$params[] = get_current_user_id();
		} elseif ( 'none' === RSP_Admin::q( 'assigned' ) ) {
			$where .= ' AND assigned_to IS NULL';
		}
		if ( '' !== RSP_Admin::q( 's' ) ) {
			$where   .= ' AND (subject LIKE %s OR id = %d)';
			$params[] = '%' . $wpdb->esc_like( RSP_Admin::q( 's' ) ) . '%';
			$params[] = (int) RSP_Admin::q( 's' );
		}
		return array( $where, $params );
	}

	/**
	 * Ticket detail with internal notes.
	 *
	 * @param int $id Ticket.
	 */
	private static function ticket_detail( $id ) {
		$t = RSP_Tickets::get( $id );
		if ( ! $t ) {
			wp_die( esc_html__( 'Ticket not found.', 'relay-stream-portal' ) );
		}
		self::head( '#' . $id . ' ' . $t->subject );
		echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=rsp-tickets' ) ) . '">← ' . esc_html__( 'All tickets', 'relay-stream-portal' ) . '</a> · ' . RSP_Admin::user_link( $t->user_id ); // phpcs:ignore
		if ( $t->account_id ) {
			$a = RSP_Accounts::get( (int) $t->account_id );
			if ( $a ) {
				echo ' · <a href="' . esc_url( admin_url( 'admin.php?page=rsp-accounts&account=' . $a->uuid ) ) . '">' . esc_html( $a->nickname ) . '</a> ' . RSP_Admin::pill( $a->status, RSP_Accounts::status_label( $a->status ) ); // phpcs:ignore
			}
		}
		if ( $t->order_id && RSP_Plugin::has_woocommerce() && ( $o = wc_get_order( (int) $t->order_id ) ) ) { // phpcs:ignore
			echo ' · <a href="' . esc_url( $o->get_edit_order_url() ) . '">#' . esc_html( $o->get_order_number() ) . '</a>';
		}
		echo '</p><div class="rsp-columns"><div class="rsp-panel rsp-thread">';
		foreach ( RSP_Tickets::messages( $id, true ) as $m ) {
			$au = get_userdata( (int) $m->author_id );
			echo '<div class="rsp-msg' . ( $m->is_internal ? ' is-internal' : '' ) . ( (int) $m->author_id === (int) $t->user_id ? ' is-customer' : '' ) . '"><p><strong>' . esc_html( $au ? $au->display_name : '?' ) . '</strong> · ' . RSP_Admin::date( $m->created_at ) . ( $m->is_internal ? ' · <em>' . esc_html__( 'Internal note', 'relay-stream-portal' ) . '</em>' : '' ) . '</p>' . wp_kses_post( wpautop( esc_html( $m->body ) ) ); // phpcs:ignore
			foreach ( RSP_Tickets::attachments( (int) $m->id ) as $att ) {
				echo '<p><a href="' . esc_url( RSP_Tickets::download_url( (int) $att->id ) ) . '">📎 ' . esc_html( $att->original_name ) . '</a></p>';
			}
			echo '</div>';
		}
		echo RSP_Admin::form( 'ticket_reply', 'enctype="multipart/form-data"' ) . '<input type="hidden" name="ticket" value="' . (int) $id . '">'; // phpcs:ignore
		echo '<p><textarea name="body" rows="5" class="large-text" required></textarea></p><p><input type="file" name="attachments[]" multiple> <label><input type="checkbox" name="internal" value="1"> ' . esc_html__( 'Internal note (not visible to the customer)', 'relay-stream-portal' ) . '</label></p><p><button class="button button-primary">' . esc_html__( 'Send', 'relay-stream-portal' ) . '</button></p></form></div>';
		echo '<div class="rsp-panel">' . RSP_Admin::form( 'ticket_update' ) . '<input type="hidden" name="ticket" value="' . (int) $id . '"><p><label>' . esc_html__( 'Status', 'relay-stream-portal' ) . '<br><select name="status">'; // phpcs:ignore
		foreach ( RSP_Tickets::STATUSES as $s ) {
			echo '<option value="' . esc_attr( $s ) . '"' . selected( $t->status, $s, false ) . '>' . esc_html( RSP_Tickets::status_label( $s ) ) . '</option>';
		}
		echo '</select></label></p><p><label>' . esc_html__( 'Priority', 'relay-stream-portal' ) . '<br><select name="priority">';
		foreach ( array( 'low', 'normal', 'high' ) as $p ) {
			echo '<option' . selected( $t->priority, $p, false ) . '>' . esc_html( $p ) . '</option>';
		}
		echo '</select></label></p><p><label>' . esc_html__( 'Assigned to', 'relay-stream-portal' ) . '<br><select name="assigned_to"><option value="0">—</option>';
		foreach ( get_users( array( 'capability' => 'rsp_manage_support', 'number' => 100 ) ) as $agent ) {
			echo '<option value="' . (int) $agent->ID . '"' . selected( (int) $t->assigned_to, (int) $agent->ID, false ) . '>' . esc_html( $agent->display_name ) . '</option>';
		}
		echo '</select></label></p><p><button class="button">' . esc_html__( 'Update', 'relay-stream-portal' ) . '</button></p></form></div></div>';
		self::foot();
	}

	/* ===================== Announcements ===================== */

	/**
	 * Announcements editor.
	 */
	public static function announcements() {
		self::head( __( 'Announcements', 'relay-stream-portal' ), __( 'Shown on the portal overview and in the Announcements widget. Leave a title empty to delete a row.', 'relay-stream-portal' ) );
		$list   = array_values( (array) get_option( 'rsp_announcements', array() ) );
		$list[] = array( 'id' => '', 'title' => '', 'body' => '', 'tone' => 'info', 'dismissible' => 1, 'active' => 1, 'starts' => '', 'ends' => '' );
		echo RSP_Admin::form( 'announcements_save' ); // phpcs:ignore
		foreach ( $list as $i => $a ) {
			$n = 'a[' . (int) $i . ']';
			echo '<fieldset class="rsp-panel"><input type="hidden" name="' . esc_attr( $n ) . '[id]" value="' . esc_attr( $a['id'] ) . '">';
			echo '<p><input type="text" class="regular-text" name="' . esc_attr( $n ) . '[title]" placeholder="' . esc_attr__( 'Title', 'relay-stream-portal' ) . '" value="' . esc_attr( $a['title'] ) . '"> <select name="' . esc_attr( $n ) . '[tone]">';
			foreach ( array( 'info', 'success', 'warning' ) as $tone ) {
				echo '<option' . selected( $a['tone'], $tone, false ) . '>' . esc_html( $tone ) . '</option>';
			}
			echo '</select> <label><input type="checkbox" name="' . esc_attr( $n ) . '[active]" value="1"' . checked( ! empty( $a['active'] ), true, false ) . '> ' . esc_html__( 'Active', 'relay-stream-portal' ) . '</label> <label><input type="checkbox" name="' . esc_attr( $n ) . '[dismissible]" value="1"' . checked( ! empty( $a['dismissible'] ), true, false ) . '> ' . esc_html__( 'Dismissible', 'relay-stream-portal' ) . '</label></p>';
			echo '<p><textarea class="large-text" rows="2" name="' . esc_attr( $n ) . '[body]">' . esc_textarea( $a['body'] ) . '</textarea></p>';
			echo '<p><label>' . esc_html__( 'Starts', 'relay-stream-portal' ) . ' <input type="date" name="' . esc_attr( $n ) . '[starts]" value="' . esc_attr( $a['starts'] ) . '"></label> <label>' . esc_html__( 'Ends', 'relay-stream-portal' ) . ' <input type="date" name="' . esc_attr( $n ) . '[ends]" value="' . esc_attr( $a['ends'] ) . '"></label></p></fieldset>';
		}
		echo '<p><button class="button button-primary">' . esc_html__( 'Save announcements', 'relay-stream-portal' ) . '</button></p></form>';
		self::foot();
	}

	/* ===================== Notifications ===================== */

	/**
	 * Notifications + templates.
	 */
	public static function notifications() {
		self::head( __( 'Notifications', 'relay-stream-portal' ), __( 'Placeholders: {name} {site} {link} {account} {expiry} {days} {ticket} {subject} {sender} {plan} {code}. Emails never include player credentials.', 'relay-stream-portal' ) );
		$s = RSP_Settings::all();
		echo RSP_Admin::form( 'save_settings' ) . '<input type="hidden" name="group" value="notifications"><table class="form-table">'; // phpcs:ignore
		echo '<tr><th>' . esc_html__( 'Email notifications', 'relay-stream-portal' ) . '</th><td><label><input type="checkbox" name="email_notifications" value="1"' . checked( $s['email_notifications'], 1, false ) . '> ' . esc_html__( 'Send emails in addition to in-portal notifications (customers can opt out in their profile)', 'relay-stream-portal' ) . '</label></td></tr>';
		echo '<tr><th>' . esc_html__( 'Expiry reminders (days before)', 'relay-stream-portal' ) . '</th><td><input type="text" name="reminder_days" value="' . esc_attr( $s['reminder_days'] ) . '"> <p class="description">' . esc_html__( 'Comma separated, sent by the daily job.', 'relay-stream-portal' ) . '</p></td></tr>';
		$labels = array( 'ticket_reply' => __( 'Ticket reply', 'relay-stream-portal' ), 'activation' => __( 'Activation / extension', 'relay-stream-portal' ), 'expiry' => __( 'Expiry reminder', 'relay-stream-portal' ), 'gift' => __( 'Gift code (to recipient)', 'relay-stream-portal' ), 'referral' => __( 'Referral reward', 'relay-stream-portal' ), 'verify' => __( 'Email verification', 'relay-stream-portal' ) );
		foreach ( $s['email_templates'] as $k => $tpl ) {
			if ( ! isset( $labels[ $k ] ) ) {
				continue;
			}
			echo '<tr><th>' . esc_html( $labels[ $k ] ) . '</th><td><input type="text" class="large-text" name="tpl[' . esc_attr( $k ) . '][subject]" value="' . esc_attr( $tpl['subject'] ) . '"><textarea class="large-text" rows="5" name="tpl[' . esc_attr( $k ) . '][body]">' . esc_textarea( $tpl['body'] ) . '</textarea></td></tr>';
		}
		echo '</table><p><button class="button button-primary">' . esc_html__( 'Save notifications', 'relay-stream-portal' ) . '</button></p></form>';
		self::foot();
	}

	/* ===================== Pages & Elementor ===================== */

	/**
	 * Setup wizard + reference.
	 */
	public static function pages_setup() {
		self::head( __( 'Elementor & Pages', 'relay-stream-portal' ) );
		$pages = (array) get_option( 'rsp_pages', array() );
		echo '<h2>' . esc_html__( 'Step 1 — Create or map portal pages', 'relay-stream-portal' ) . '</h2>';
		echo '<p>' . esc_html__( 'Create missing pages with the matching shortcode, or map an existing page (for example one you designed in Elementor).', 'relay-stream-portal' ) . '</p>';
		echo RSP_Admin::form( 'pages_create', 'class="rsp-inline"' ) . '<label><input type="radio" name="mode" value="single" checked> ' . esc_html__( 'One portal page (navigation via ?rsp_view=…) + auth pages', 'relay-stream-portal' ) . '</label><br><label><input type="radio" name="mode" value="all"> ' . esc_html__( 'A separate page for every module', 'relay-stream-portal' ) . '</label><br><label><input type="checkbox" name="elementor" value="1"' . ( did_action( 'elementor/loaded' ) ? '' : ' disabled' ) . '> ' . esc_html__( 'Open new pages with Elementor (adds the matching widget instead of a shortcode)', 'relay-stream-portal' ) . '</label><p><button class="button button-primary">' . esc_html__( 'Create missing pages', 'relay-stream-portal' ) . '</button></p></form>'; // phpcs:ignore

		echo RSP_Admin::form( 'pages_map' ); // phpcs:ignore
		$rows = array();
		foreach ( RSP_Settings::page_roles() as $role => $def ) {
			$current = isset( $pages[ $role ] ) ? (int) $pages[ $role ] : 0;
			$rows[]  = array(
				'role' => esc_html( $def[0] ),
				'page' => wp_dropdown_pages( array( 'name' => 'pages[' . esc_attr( $role ) . ']', 'selected' => $current, 'show_option_none' => __( '— Not set —', 'relay-stream-portal' ), 'option_none_value' => 0, 'echo' => 0, 'post_status' => array( 'publish', 'draft', 'private' ) ) ),
				'code' => '<code>' . esc_html( $def[1] ) . '</code>',
				'view' => $current ? '<a href="' . esc_url( get_permalink( $current ) ) . '" target="_blank">' . esc_html__( 'View', 'relay-stream-portal' ) . '</a>' . ( did_action( 'elementor/loaded' ) ? ' · <a href="' . esc_url( admin_url( 'post.php?post=' . $current . '&action=elementor' ) ) . '">' . esc_html__( 'Edit with Elementor', 'relay-stream-portal' ) . '</a>' : '' ) : '—',
			);
		}
		RSP_Admin::table( array( 'role' => __( 'Portal page', 'relay-stream-portal' ), 'page' => __( 'WordPress page', 'relay-stream-portal' ), 'code' => __( 'Shortcode', 'relay-stream-portal' ), 'view' => '' ), $rows );
		echo '<p><button class="button button-primary">' . esc_html__( 'Save page mapping', 'relay-stream-portal' ) . '</button></p></form>';

		echo '<h2>' . esc_html__( 'Step 2 — Design with Elementor Free (optional)', 'relay-stream-portal' ) . '</h2><ol class="rsp-steps">';
		$steps = array(
			__( 'Open a mapped page with “Edit with Elementor”.', 'relay-stream-portal' ),
			__( 'Search the widget panel for “Relay” or open the “Relay Stream Portal” category.', 'relay-stream-portal' ),
			__( 'Drag “Portal Shell” onto a full-width section for the complete portal, or drop individual module widgets into your own layout.', 'relay-stream-portal' ),
			__( 'Use Content → labels, Layout → width/theme, and Style → colors, typography, radius and spacing. Leave a control empty to inherit the Branding settings.', 'relay-stream-portal' ),
			__( 'The editor shows sample data only. Publish and open the page while signed in as a customer to see live data.', 'relay-stream-portal' ),
			__( 'Prefer shortcodes? Elementor’s free Shortcode widget accepts every shortcode below; the output is identical.', 'relay-stream-portal' ),
		);
		foreach ( $steps as $s ) {
			echo '<li>' . esc_html( $s ) . '</li>';
		}
		echo '</ol><h2>' . esc_html__( 'Page → widget / shortcode reference', 'relay-stream-portal' ) . '</h2>';
		$rows = array();
		$defs = class_exists( 'RSP_Elementor' ) ? RSP_Elementor::definitions() : array();
		$by   = array();
		foreach ( $defs as $d ) {
			$by[ $d[2] ] = $d[1];
		}
		foreach ( RSP_Shortcodes::map() as $tag => $def ) {
			$rows[] = array( 'sc' => '<code>[' . esc_html( $tag ) . ']</code>', 'w' => esc_html( isset( $by[ $def[0] ] ) ? $by[ $def[0] ] : ( 'gifts' === $def[0] ? $by['referrals'] . ' (tab=gifts)' : '—' ) ), 'd' => esc_html( $def[1] ) );
		}
		RSP_Admin::table( array( 'sc' => __( 'Shortcode', 'relay-stream-portal' ), 'w' => __( 'Elementor widget', 'relay-stream-portal' ), 'd' => __( 'Description', 'relay-stream-portal' ) ), $rows );
		echo '<p class="description">' . esc_html__( 'Shortcode attributes: title="…" (heading), label="…" (button), class="…", tab="gifts" (referrals), device="firetv" (device guides), module="…" (portal shell).', 'relay-stream-portal' ) . '</p>';
		self::foot();
	}

	/* ===================== Branding ===================== */

	/**
	 * Branding + portal settings.
	 */
	public static function branding() {
		self::head( __( 'Branding', 'relay-stream-portal' ), __( 'These values are the defaults for every portal page, shortcode and Elementor widget. Elementor Style controls override them per widget.', 'relay-stream-portal' ) );
		$s = RSP_Settings::all();
		echo RSP_Admin::form( 'save_settings' ) . '<input type="hidden" name="group" value="branding"><table class="form-table">'; // phpcs:ignore
		$text = array( 'brand_name' => __( 'Brand name', 'relay-stream-portal' ), 'brand_tagline' => __( 'Tagline', 'relay-stream-portal' ), 'text_welcome' => __( 'Welcome heading ({name})', 'relay-stream-portal' ), 'text_welcome_sub' => __( 'Welcome subtitle', 'relay-stream-portal' ), 'support_email' => __( 'Support email', 'relay-stream-portal' ), 'support_url' => __( 'External support URL (optional)', 'relay-stream-portal' ), 'floating_label' => __( 'Floating button label', 'relay-stream-portal' ), 'player_url' => __( 'Authorized web player URL', 'relay-stream-portal' ), 'player_label' => __( 'Player button label', 'relay-stream-portal' ) );
		foreach ( $text as $k => $l ) {
			echo '<tr><th>' . esc_html( $l ) . '</th><td><input type="text" class="regular-text" name="' . esc_attr( $k ) . '" value="' . esc_attr( $s[ $k ] ) . '"></td></tr>';
		}
		$logo = $s['logo_id'] ? wp_get_attachment_image_url( (int) $s['logo_id'], 'medium' ) : '';
		echo '<tr><th>' . esc_html__( 'Logo', 'relay-stream-portal' ) . '</th><td><input type="hidden" name="logo_id" value="' . (int) $s['logo_id'] . '" data-rsp-media-id><img src="' . esc_url( $logo ) . '" style="max-height:48px;' . ( $logo ? '' : 'display:none' ) . '" data-rsp-media-preview alt=""> <button type="button" class="button" data-rsp-media>' . esc_html__( 'Choose logo', 'relay-stream-portal' ) . '</button> <button type="button" class="button-link" data-rsp-media-clear>' . esc_html__( 'Remove', 'relay-stream-portal' ) . '</button></td></tr>';
		$colors = array( 'color_accent' => __( 'Accent', 'relay-stream-portal' ), 'color_accent_2' => __( 'Secondary accent', 'relay-stream-portal' ), 'color_dark_bg' => __( 'Dark background', 'relay-stream-portal' ), 'color_dark_surface' => __( 'Dark cards', 'relay-stream-portal' ), 'color_light_bg' => __( 'Light background', 'relay-stream-portal' ), 'color_light_surface' => __( 'Light cards', 'relay-stream-portal' ) );
		echo '<tr><th>' . esc_html__( 'Colors', 'relay-stream-portal' ) . '</th><td class="rsp-color-grid">';
		foreach ( $colors as $k => $l ) {
			echo '<label><input type="color" name="' . esc_attr( $k ) . '" value="' . esc_attr( $s[ $k ] ) . '"> ' . esc_html( $l ) . '</label>';
		}
		echo '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Default theme', 'relay-stream-portal' ) . '</th><td><select name="theme_default">';
		foreach ( array( 'auto' => __( 'Follow device', 'relay-stream-portal' ), 'dark' => __( 'Dark', 'relay-stream-portal' ), 'light' => __( 'Light', 'relay-stream-portal' ) ) as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $s['theme_default'], $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select> <label><input type="checkbox" name="allow_theme_toggle" value="1"' . checked( $s['allow_theme_toggle'], 1, false ) . '> ' . esc_html__( 'Let customers switch theme', 'relay-stream-portal' ) . '</label></td></tr>';
		echo '<tr><th>' . esc_html__( 'Corner radius (px)', 'relay-stream-portal' ) . '</th><td><input type="number" min="0" max="32" name="radius" value="' . (int) $s['radius'] . '"></td></tr>';
		echo '<tr><th>' . esc_html__( 'Navigation order & visibility', 'relay-stream-portal' ) . '</th><td><table class="rsp-nav-table">';
		$order = array_values( array_unique( array_merge( (array) $s['nav_order'], array_keys( RSP_Settings::modules() ) ) ) );
		foreach ( $order as $i => $slug ) {
			$mods = RSP_Settings::modules();
			if ( ! isset( $mods[ $slug ] ) ) {
				continue;
			}
			echo '<tr><td><input type="number" class="small-text" name="nav_pos[' . esc_attr( $slug ) . ']" value="' . (int) ( $i + 1 ) . '"></td><td>' . esc_html( $mods[ $slug ] ) . '</td><td><label><input type="checkbox" name="modules_visible[]" value="' . esc_attr( $slug ) . '"' . checked( ! in_array( $slug, (array) $s['modules_hidden'], true ), true, false ) . '> ' . esc_html__( 'Visible', 'relay-stream-portal' ) . '</label></td></tr>';
		}
		echo '</table></td></tr>';
		echo '<tr><th>' . esc_html__( 'Portal language', 'relay-stream-portal' ) . '</th><td><select name="language"><option value="">' . esc_html__( 'Customer profile / site language', 'relay-stream-portal' ) . '</option><option value="en_US"' . selected( $s['language'], 'en_US', false ) . '>English</option><option value="ar"' . selected( $s['language'], 'ar', false ) . '>العربية (RTL)</option></select></td></tr>';
		$checks = array( 'floating_support' => __( 'Show floating support button', 'relay-stream-portal' ), 'full_width' => __( 'Full-width layout on portal pages', 'relay-stream-portal' ), 'show_credentials' => __( 'Let customers reveal player details', 'relay-stream-portal' ), 'player_enabled' => __( 'Enable “Start Watching” (requires an authorized player URL)', 'relay-stream-portal' ), 'registration_open' => __( 'Allow registration', 'relay-stream-portal' ), 'email_verification' => __( 'Require email verification for new registrations', 'relay-stream-portal' ), 'delete_on_uninstall' => __( 'Delete all plugin data when the plugin is deleted', 'relay-stream-portal' ) );
		foreach ( $checks as $k => $l ) {
			echo '<tr><th>' . esc_html( $l ) . '</th><td><input type="checkbox" name="' . esc_attr( $k ) . '" value="1"' . checked( $s[ $k ], 1, false ) . '></td></tr>';
		}
		echo '</table><p><button class="button button-primary">' . esc_html__( 'Save branding', 'relay-stream-portal' ) . '</button></p></form>';
		self::foot();
	}

	/* ===================== Audit ===================== */

	/**
	 * Audit log.
	 */
	public static function audit() {
		global $wpdb;
		self::head( __( 'Audit Log', 'relay-stream-portal' ), __( 'Sensitive actions with actor, time, target and reason. Secrets are never recorded.', 'relay-stream-portal' ) );
		$t       = RSP_Installer::table( 'audit' );
		$actions = $wpdb->get_col( "SELECT DISTINCT action FROM $t ORDER BY action" ); // phpcs:ignore
		RSP_Admin::filters( 'rsp-audit', array( 'act' => array_combine( $actions, $actions ) ), 'audit' );
		list( $where, $params ) = self::audit_where();
		$total = (int) $wpdb->get_var( $params ? $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE $where", $params ) : "SELECT COUNT(*) FROM $t WHERE $where" ); // phpcs:ignore
		$list  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE $where ORDER BY id DESC LIMIT %d OFFSET %d", array_merge( $params, array( 50, ( RSP_Admin::paged() - 1 ) * 50 ) ) ) ); // phpcs:ignore
		$rows  = array();
		foreach ( (array) $list as $r ) {
			$u      = $r->actor_id ? get_userdata( (int) $r->actor_id ) : null;
			$rows[] = array( 'when' => RSP_Admin::date( $r->created_at ), 'who' => esc_html( $u ? $u->display_name : __( 'System', 'relay-stream-portal' ) ), 'act' => '<code>' . esc_html( $r->action ) . '</code>', 'target' => esc_html( $r->target_type . ' ' . $r->target_id ), 'reason' => esc_html( $r->reason ), 'ctx' => '<small><code>' . esc_html( (string) $r->context ) . '</code></small>' );
		}
		RSP_Admin::table( array( 'when' => __( 'When', 'relay-stream-portal' ), 'who' => __( 'Actor', 'relay-stream-portal' ), 'act' => __( 'Action', 'relay-stream-portal' ), 'target' => __( 'Target', 'relay-stream-portal' ), 'reason' => __( 'Reason', 'relay-stream-portal' ), 'ctx' => __( 'Context', 'relay-stream-portal' ) ), $rows );
		RSP_Admin::pager( $total, 50 );
		self::foot();
	}

	/**
	 * Audit WHERE.
	 *
	 * @return array
	 */
	public static function audit_where() {
		global $wpdb;
		$where  = '1=1';
		$params = array();
		if ( '' !== RSP_Admin::q( 'act' ) ) {
			$where   .= ' AND action = %s';
			$params[] = sanitize_key( RSP_Admin::q( 'act' ) );
		}
		if ( '' !== RSP_Admin::q( 's' ) ) {
			$where   .= ' AND (target_id LIKE %s OR reason LIKE %s)';
			$like     = '%' . $wpdb->esc_like( RSP_Admin::q( 's' ) ) . '%';
			$params[] = $like;
			$params[] = $like;
		}
		return array( $where, $params );
	}
}
