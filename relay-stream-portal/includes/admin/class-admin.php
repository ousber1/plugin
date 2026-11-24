<?php
/**
 * Admin framework: menu, routing, capability-gated actions, notices, exports.
 *
 * Every state-changing request goes through admin-post.php?action=rsp_admin with a per-action
 * nonce AND a capability check. Sensitive actions are written to the audit log (never secrets).
 *
 * @package RelayStreamPortal
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin.
 */
class RSP_Admin {

	/**
	 * Pages: slug => array(title, capability, callback).
	 *
	 * @return array
	 */
	public static function pages() {
		return array(
			'rsp'               => array( __( 'Overview', 'relay-stream-portal' ), 'rsp_view_admin', array( 'RSP_Admin_Pages', 'overview' ) ),
			'rsp-customers'     => array( __( 'Customers', 'relay-stream-portal' ), 'rsp_view_admin', array( 'RSP_Admin_Pages', 'customers' ) ),
			'rsp-accounts'      => array( __( 'Streaming Accounts', 'relay-stream-portal' ), 'rsp_manage_provisioning', array( 'RSP_Admin_Pages', 'accounts' ) ),
			'rsp-plans'         => array( __( 'Plans', 'relay-stream-portal' ), 'rsp_manage_billing', array( 'RSP_Admin_Pages', 'plans' ) ),
			'rsp-orders'        => array( __( 'Orders & Transactions', 'relay-stream-portal' ), 'rsp_manage_billing', array( 'RSP_Admin_Pages', 'orders' ) ),
			'rsp-api'           => array( __( 'API Settings & Job Queue', 'relay-stream-portal' ), 'rsp_manage_provisioning', array( 'RSP_Admin_Pages', 'api' ) ),
			'rsp-referrals'     => array( __( 'Referrals', 'relay-stream-portal' ), 'rsp_manage_billing', array( 'RSP_Admin_Pages', 'referrals' ) ),
			'rsp-gifts'         => array( __( 'Gifts', 'relay-stream-portal' ), 'rsp_manage_billing', array( 'RSP_Admin_Pages', 'gifts' ) ),
			'rsp-guides'        => array( __( 'Apps & Guides', 'relay-stream-portal' ), 'rsp_manage_settings', array( 'RSP_Admin_Pages', 'guides' ) ),
			'rsp-tickets'       => array( __( 'Tickets', 'relay-stream-portal' ), 'rsp_manage_support', array( 'RSP_Admin_Pages', 'tickets' ) ),
			'rsp-announcements' => array( __( 'Announcements', 'relay-stream-portal' ), 'rsp_manage_support', array( 'RSP_Admin_Pages', 'announcements' ) ),
			'rsp-notifications' => array( __( 'Notifications', 'relay-stream-portal' ), 'rsp_manage_settings', array( 'RSP_Admin_Pages', 'notifications' ) ),
			'rsp-pages'         => array( __( 'Elementor & Pages', 'relay-stream-portal' ), 'rsp_manage_settings', array( 'RSP_Admin_Pages', 'pages_setup' ) ),
			'rsp-branding'      => array( __( 'Branding', 'relay-stream-portal' ), 'rsp_manage_settings', array( 'RSP_Admin_Pages', 'branding' ) ),
			'rsp-audit'         => array( __( 'Audit Log', 'relay-stream-portal' ), 'rsp_manage_settings', array( 'RSP_Admin_Pages', 'audit' ) ),
		);
	}

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_rsp_admin', array( __CLASS__, 'handle' ) );
		add_action( 'admin_post_rsp_export', array( 'RSP_Export', 'handle' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( RSP_FILE ), array( __CLASS__, 'action_links' ) );
	}

	/**
	 * Menu.
	 */
	public static function menu() {
		$pending = self::review_count();
		$bubble  = $pending ? ' <span class="awaiting-mod count-' . (int) $pending . '"><span class="pending-count">' . (int) $pending . '</span></span>' : '';
		add_menu_page( __( 'Relay Stream Portal', 'relay-stream-portal' ), __( 'Relay Stream', 'relay-stream-portal' ) . $bubble, 'rsp_view_admin', 'rsp', array( 'RSP_Admin_Pages', 'overview' ), 'dashicons-video-alt3', 56 );
		foreach ( self::pages() as $slug => $p ) {
			add_submenu_page( 'rsp', $p[0] . ' — Relay Stream Portal', $p[0], $p[1], $slug, $p[2] );
		}
	}

	/**
	 * Jobs needing review.
	 *
	 * @return int
	 */
	public static function review_count() {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . RSP_Installer::table( 'jobs' ) . " WHERE state IN ('needs_review','failed')" ); // phpcs:ignore
	}

	/**
	 * Plugin list link.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=rsp-pages' ) ) . '">' . esc_html__( 'Setup', 'relay-stream-portal' ) . '</a>' );
		return $links;
	}

	/**
	 * Assets.
	 *
	 * @param string $hook Hook.
	 */
	public static function assets( $hook ) {
		if ( false === strpos( (string) $hook, 'rsp' ) ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'rsp-admin', RSP_URL . 'assets/css/admin.css', array(), RSP_VERSION );
		wp_enqueue_script( 'rsp-admin', RSP_URL . 'assets/js/admin.js', array( 'jquery' ), RSP_VERSION, true );
	}

	/**
	 * Environment warnings.
	 */
	public static function notices() {
		$screen = get_current_screen();
		if ( ! $screen || false === strpos( (string) $screen->id, 'rsp' ) ) {
			return;
		}
		$flash = get_transient( 'rsp_admin_flash_' . get_current_user_id() );
		if ( $flash ) {
			delete_transient( 'rsp_admin_flash_' . get_current_user_id() );
			printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( 'error' === $flash['type'] ? 'error' : 'success' ), esc_html( $flash['message'] ) );
		}
		if ( ! RSP_Plugin::has_woocommerce() ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'WooCommerce is not active: plans, checkout, gifts and transactions are disabled. Accounts, guides and support still work.', 'relay-stream-portal' ) . '</p></div>';
		}
		if ( ! RSP_Crypto::has_dedicated_key() && current_user_can( 'rsp_manage_settings' ) ) {
			echo '<div class="notice notice-info"><p>' . wp_kses_post( __( 'Secrets are encrypted with a key derived from your WordPress salts. For a dedicated key, add <code>define( \'RSP_ENCRYPTION_KEY\', \'…base64 of 32 random bytes…\' );</code> to wp-config.php <strong>before</strong> saving the API key (changing the key later makes stored secrets unreadable).', 'relay-stream-portal' ) ) . '</p></div>';
		}
	}

	/**
	 * Set a flash message and redirect.
	 *
	 * @param string $type success|error.
	 * @param string $message Message.
	 * @param string $url Target.
	 */
	public static function done( $type, $message, $url = '' ) {
		set_transient( 'rsp_admin_flash_' . get_current_user_id(), array( 'type' => $type, 'message' => $message ), 60 );
		$url = $url ? $url : wp_get_referer();
		wp_safe_redirect( $url ? $url : admin_url( 'admin.php?page=rsp' ) );
		exit;
	}

	/**
	 * Form opening for an admin action.
	 *
	 * @param string $do Action.
	 * @param string $extra Extra attributes.
	 * @return string
	 */
	public static function form( $do, $extra = '' ) {
		return '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" ' . $extra . '><input type="hidden" name="action" value="rsp_admin"><input type="hidden" name="do" value="' . esc_attr( $do ) . '">' . wp_nonce_field( 'rsp_admin_' . $do, '_wpnonce', true, false );
	}

	/**
	 * Small inline action button form.
	 *
	 * @param string $do Action.
	 * @param array  $fields Hidden fields.
	 * @param string $label Label.
	 * @param string $class Button class.
	 * @param bool   $reason Ask for a reason.
	 * @return string
	 */
	public static function button( $do, array $fields, $label, $class = 'button', $reason = false ) {
		$h = self::form( $do, 'class="rsp-inline"' . ( $reason ? ' data-rsp-reason="1"' : '' ) );
		foreach ( $fields as $k => $v ) {
			$h .= '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">';
		}
		if ( $reason ) {
			$h .= '<input type="hidden" name="reason" value="">';
		}
		return $h . '<button type="submit" class="' . esc_attr( $class ) . '">' . esc_html( $label ) . '</button></form>';
	}

	/**
	 * Action capability map.
	 *
	 * @return array
	 */
	private static function action_caps() {
		return array(
			'save_settings'      => 'rsp_manage_settings',
			'save_api'           => 'rsp_manage_settings',
			'test_connection'    => 'rsp_manage_provisioning',
			'load_packages'      => 'rsp_manage_provisioning',
			'run_queue'          => 'rsp_manage_provisioning',
			'job_retry'          => 'rsp_manage_provisioning',
			'job_reconcile'      => 'rsp_manage_provisioning',
			'job_complete'       => 'rsp_manage_provisioning',
			'job_cancel'         => 'rsp_manage_provisioning',
			'account_save'       => 'rsp_manage_provisioning',
			'account_create'     => 'rsp_manage_provisioning',
			'account_refresh'    => 'rsp_manage_provisioning',
			'account_renew'      => 'rsp_manage_provisioning',
			'account_reveal'     => 'rsp_view_credentials',
			'account_suspend'    => 'rsp_manage_provisioning',
			'plans_save'         => 'rsp_manage_billing',
			'demo_plans'         => 'rsp_manage_billing',
			'referral_action'    => 'rsp_manage_billing',
			'ledger_adjust'      => 'rsp_manage_billing',
			'gift_action'        => 'rsp_manage_billing',
			'save_billing'       => 'rsp_manage_billing',
			'guides_save'        => 'rsp_manage_settings',
			'ticket_reply'       => 'rsp_manage_support',
			'ticket_update'      => 'rsp_manage_support',
			'announcements_save' => 'rsp_manage_support',
			'pages_create'       => 'rsp_manage_settings',
			'pages_map'          => 'rsp_manage_settings',
			'order_review_clear' => 'rsp_manage_billing',
		);
	}

	/**
	 * Dispatch admin-post actions.
	 */
	public static function handle() {
		$do   = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
		$caps = self::action_caps();
		if ( ! isset( $caps[ $do ] ) ) {
			wp_die( esc_html__( 'Unknown action.', 'relay-stream-portal' ), 400 );
		}
		check_admin_referer( 'rsp_admin_' . $do );
		if ( ! current_user_can( $caps[ $do ] ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'relay-stream-portal' ), 403 );
		}
		RSP_Admin_Actions::dispatch( $do );
	}

	/**
	 * Render a data table.
	 *
	 * @param array $columns key => label.
	 * @param array $rows Rows of key => trusted HTML.
	 * @param string $empty Empty text.
	 */
	public static function table( array $columns, array $rows, $empty = '' ) {
		echo '<table class="widefat striped rsp-table"><thead><tr>';
		foreach ( $columns as $label ) {
			echo '<th scope="col">' . esc_html( $label ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		if ( ! $rows ) {
			echo '<tr><td colspan="' . count( $columns ) . '">' . esc_html( $empty ? $empty : __( 'Nothing found.', 'relay-stream-portal' ) ) . '</td></tr>';
		}
		foreach ( $rows as $r ) {
			echo '<tr>';
			foreach ( array_keys( $columns ) as $k ) {
				echo '<td>' . ( isset( $r[ $k ] ) ? $r[ $k ] : '' ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput -- cells are escaped when built.
			}
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * Pagination links.
	 *
	 * @param int $total Total rows.
	 * @param int $per Per page.
	 */
	public static function pager( $total, $per ) {
		$pages = (int) ceil( $total / max( 1, $per ) );
		if ( $pages < 2 ) {
			return;
		}
		echo '<div class="tablenav"><div class="tablenav-pages"><span class="displaying-num">' . esc_html( sprintf( /* translators: %d items */ _n( '%d item', '%d items', $total, 'relay-stream-portal' ), $total ) ) . '</span> ';
		echo wp_kses_post( paginate_links( array( 'base' => add_query_arg( 'paged', '%#%' ), 'format' => '', 'current' => self::paged(), 'total' => $pages ) ) );
		echo '</div></div>';
	}

	/**
	 * Current page number.
	 *
	 * @return int
	 */
	public static function paged() {
		return isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification
	}

	/**
	 * GET param helper.
	 *
	 * @param string $k Key.
	 * @return string
	 */
	public static function q( $k ) {
		return isset( $_GET[ $k ] ) ? sanitize_text_field( wp_unslash( $_GET[ $k ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	}

	/**
	 * Search + filter bar.
	 *
	 * @param string $page Page slug.
	 * @param array  $filters name => options.
	 * @param string $export Export type or ''.
	 */
	public static function filters( $page, array $filters = array(), $export = '' ) {
		echo '<form method="get" class="rsp-filters"><input type="hidden" name="page" value="' . esc_attr( $page ) . '">';
		foreach ( $filters as $name => $opts ) {
			echo '<select name="' . esc_attr( $name ) . '"><option value="">' . esc_html__( 'All', 'relay-stream-portal' ) . '</option>';
			foreach ( $opts as $v => $l ) {
				echo '<option value="' . esc_attr( $v ) . '"' . selected( self::q( $name ), (string) $v, false ) . '>' . esc_html( $l ) . '</option>';
			}
			echo '</select> ';
		}
		echo '<input type="search" name="s" value="' . esc_attr( self::q( 's' ) ) . '" placeholder="' . esc_attr__( 'Search…', 'relay-stream-portal' ) . '"> ';
		submit_button( __( 'Filter', 'relay-stream-portal' ), 'secondary', '', false );
		echo '</form>';
		if ( $export && current_user_can( 'rsp_export_data' ) ) {
			$url = wp_nonce_url( add_query_arg( array_merge( array( 'action' => 'rsp_export', 'type' => $export ), array_filter( array_map( array( __CLASS__, 'q' ), array_combine( array_keys( $filters ), array_keys( $filters ) ) ) ), array( 's' => self::q( 's' ) ) ), admin_url( 'admin-post.php' ) ), 'rsp_export_' . $export );
			echo '<p><a class="button" href="' . esc_url( $url ) . '">' . esc_html__( 'Export CSV', 'relay-stream-portal' ) . '</a></p>';
		}
	}

	/**
	 * User link.
	 *
	 * @param int $uid User.
	 * @return string HTML.
	 */
	public static function user_link( $uid ) {
		$u = $uid ? get_userdata( (int) $uid ) : null;
		if ( ! $u ) {
			return '—';
		}
		return '<a href="' . esc_url( admin_url( 'admin.php?page=rsp-customers&user=' . (int) $uid ) ) . '">' . esc_html( $u->display_name ) . '</a><br><small>' . esc_html( $u->user_email ) . '</small>';
	}

	/**
	 * Status pill.
	 *
	 * @param string $s Status.
	 * @param string $label Label.
	 * @return string
	 */
	public static function pill( $s, $label ) {
		return '<span class="rsp-badge rsp-badge-' . esc_attr( sanitize_html_class( $s ) ) . '">' . esc_html( $label ) . '</span>';
	}

	/**
	 * Date.
	 *
	 * @param string|null $utc UTC.
	 * @return string
	 */
	public static function date( $utc ) {
		return $utc ? esc_html( get_date_from_gmt( $utc, 'Y-m-d H:i' ) ) : '—';
	}
}
