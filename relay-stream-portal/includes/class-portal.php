<?php
/**
 * Portal renderer shared by shortcodes and Elementor widgets.
 *
 * One renderer, one view model:
 *   - real data for logged-in customers (ownership enforced in every query),
 *   - synthetic RSP_Preview data inside the Elementor editor.
 *
 * @package RelayStreamPortal
 */

defined( 'ABSPATH' ) || exit;

/**
 * Portal.
 */
class RSP_Portal {

	/** @var bool Whether portal output was rendered on this request. */
	private static $rendered = false;
	/** @var int Instance counter for unique ids. */
	private static $instance = 0;
	/** @var bool Whether a full shell (with its own chat button) rendered. */
	private static $shell = false;
	/** @var array|null Cached view model per request. */
	private static $vm = null;

	const PUBLIC_MODULES = array( 'login', 'register', 'reset' );

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ), 5 );
		add_action( 'template_redirect', array( __CLASS__, 'portal_page_headers' ), 1 );
		add_action( 'wp_footer', array( __CLASS__, 'floating_support' ), 50 );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
	}

	/**
	 * Register CSS/JS (enqueued on demand when something renders).
	 */
	public static function register_assets() {
		wp_register_style( 'rsp-portal', RSP_URL . 'assets/css/portal.css', array(), RSP_VERSION );
		wp_register_script( 'rsp-qrcode', RSP_URL . 'assets/vendor/qrcode-generator.js', array(), '1.4.4', true );
		wp_register_script( 'rsp-portal', RSP_URL . 'assets/js/portal.js', array( 'rsp-qrcode' ), RSP_VERSION, true );
		wp_localize_script(
			'rsp-portal',
			'RSP',
			array(
				'rest'  => esc_url_raw( rest_url( RSP_Rest::NS ) ),
				'nonce' => is_user_logged_in() ? wp_create_nonce( 'wp_rest' ) : '',
				'i18n'  => array(
					'copied'   => __( 'Copied', 'relay-stream-portal' ),
					'copy'     => __( 'Copy', 'relay-stream-portal' ),
					'reveal'   => __( 'Show', 'relay-stream-portal' ),
					'hide'     => __( 'Hide', 'relay-stream-portal' ),
					'error'    => __( 'Something went wrong. Please try again.', 'relay-stream-portal' ),
					'light'    => __( 'Switch to light theme', 'relay-stream-portal' ),
					'dark'     => __( 'Switch to dark theme', 'relay-stream-portal' ),
				),
			)
		);
	}

	/**
	 * Enqueue assets + inline brand variables.
	 */
	public static function enqueue() {
		if ( ! wp_style_is( 'rsp-portal', 'registered' ) ) {
			self::register_assets();
		}
		wp_enqueue_style( 'rsp-portal' );
		wp_enqueue_script( 'rsp-portal' );
		static $done = false;
		if ( ! $done ) {
			wp_add_inline_style( 'rsp-portal', self::brand_css() );
			$done = true;
		}
	}

	/**
	 * Brand CSS variables from settings (Elementor widget controls override these per instance).
	 *
	 * @return string
	 */
	public static function brand_css() {
		$s   = RSP_Settings::all();
		$hex = static function ( $v, $d ) {
			$v = sanitize_hex_color( $v );
			return $v ? $v : $d;
		};
		return sprintf(
			'body.rsp-app-body{background:%4$s}.rsp-root{--rsp-accent:%1$s;--rsp-accent-2:%2$s;--rsp-radius:%3$dpx;--rsp-gold:%8$s}.rsp-root[data-rsp-theme=dark]{--rsp-bg:%4$s;--rsp-surface:%5$s}.rsp-root[data-rsp-theme=light]{--rsp-bg:%6$s;--rsp-surface:%7$s}',
			$hex( $s['color_accent'], '#14b8a6' ),
			$hex( $s['color_accent_2'], '#a78bfa' ),
			max( 0, min( 32, (int) $s['radius'] ) ),
			$hex( $s['color_dark_bg'], '#0b1016' ),
			$hex( $s['color_dark_surface'], '#121a23' ),
			$hex( $s['color_light_bg'], '#f5f7f8' ),
			$hex( $s['color_light_surface'], '#ffffff' ),
			$hex( isset( $s['color_gold'] ) ? $s['color_gold'] : '', '#e7b85b' )
		);
	}

	/**
	 * Is this an Elementor editor / preview request?
	 *
	 * @return bool
	 */
	public static function is_preview() {
		if ( apply_filters( 'rsp_force_preview', false ) ) {
			return true;
		}
		if ( ! class_exists( '\Elementor\Plugin' ) || ! isset( \Elementor\Plugin::$instance ) ) {
			return false;
		}
		$el = \Elementor\Plugin::$instance;
		if ( ( isset( $el->editor ) && $el->editor->is_edit_mode() ) || ( isset( $el->preview ) && $el->preview->is_preview_mode() ) ) {
			return current_user_can( 'edit_posts' );
		}
		return wp_doing_ajax() && isset( $_REQUEST['action'] ) && 'elementor_ajax' === $_REQUEST['action'] && current_user_can( 'edit_posts' ); // phpcs:ignore WordPress.Security.NonceVerification
	}

	/**
	 * Current URL (for return targets).
	 *
	 * @return string
	 */
	public static function current_url() {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		return remove_query_arg( array( 'rsp_flash', 'rsp_msg', '_wpnonce' ), home_url( $uri ) );
	}

	/**
	 * Base URL of the current page without portal query args.
	 *
	 * @return string
	 */
	public static function current_base_url() {
		$id = get_queried_object_id();
		return $id ? get_permalink( $id ) : home_url( '/' );
	}

	/**
	 * Mark portal pages as private/uncacheable before output.
	 */
	public static function portal_page_headers() {
		$id    = get_queried_object_id();
		$pages = array_map( 'intval', (array) get_option( 'rsp_pages', array() ) );
		$post  = $id ? get_post( $id ) : null;
		$is    = $id && ( in_array( $id, $pages, true ) || ( $post && false !== strpos( (string) $post->post_content, '[relay_' ) ) || ( $post && false !== strpos( (string) get_post_meta( $id, '_elementor_data', true ), 'rsp-' ) ) );
		if ( $is ) {
			if ( ! defined( 'DONOTCACHEPAGE' ) ) {
				define( 'DONOTCACHEPAGE', true );
			}
			nocache_headers();
			header( 'X-Robots-Tag: noindex, nofollow', true );
			header( 'Referrer-Policy: same-origin', true );
		}
	}

	/**
	 * Body class.
	 *
	 * @param array $c Classes.
	 * @return array
	 */
	public static function body_class( $c ) {
		$pages = array_map( 'intval', (array) get_option( 'rsp_pages', array() ) );
		if ( in_array( get_queried_object_id(), $pages, true ) ) {
			$c[] = 'rsp-portal-page';
			if ( RSP_Settings::get( 'full_width' ) ) {
				$c[] = 'rsp-portal-full';
			}
		}
		return $c;
	}

	/**
	 * Theme attribute value.
	 *
	 * @return string
	 */
	private static function theme_attr() {
		$t = RSP_Settings::get( 'theme_default', 'auto' );
		return in_array( $t, array( 'dark', 'light' ), true ) ? $t : 'auto';
	}

	/**
	 * View model for the current user (cached per request).
	 *
	 * @param array $args Args (page/status filters for transactions).
	 * @return array
	 */
	public static function view_model( array $args = array() ) {
		if ( self::is_preview() ) {
			return RSP_Preview::view_model();
		}
		if ( null !== self::$vm && empty( $args ) ) {
			return self::$vm;
		}
		$uid  = get_current_user_id();
		$user = wp_get_current_user();
		$name = $user->first_name ? $user->first_name : $user->display_name;
		$vm   = array(
			'preview'        => false,
			'user'           => array(
				'id'       => $uid,
				'name'     => $user->display_name,
				'first'    => $name,
				'email'    => $user->user_email,
				'initials' => strtoupper( mb_substr( $name, 0, 1 ) . mb_substr( (string) $user->last_name, 0, 1 ) ),
			),
			'accounts'       => array(),
			'pending_orders' => array(),
			'orders'         => array( 'rows' => array(), 'pages' => 1, 'total' => 0, 'page' => 1 ),
			'order_count'    => 0,
			'plans'          => array(),
			'gateways'       => array(),
			'billing'        => RSP_Plugin::has_woocommerce(),
			'player'         => array(
				'enabled' => RSP_Settings::get( 'player_enabled' ) && RSP_Guides::is_public_url( RSP_Settings::get( 'player_url' ) ),
				'url'     => RSP_Settings::get( 'player_url' ),
				'label'   => RSP_Settings::get( 'player_label' ),
			),
		);

		foreach ( RSP_Accounts::for_user( $uid ) as $a ) {
			$job                = RSP_Jobs::open_for_account( (int) $a->id );
			$row                = RSP_Accounts::to_public( $a );
			$row['days_left']   = $a->expires_at ? max( 0, (int) ceil( ( strtotime( $a->expires_at . ' UTC' ) - time() ) / DAY_IN_SECONDS ) ) : null;
			$row['job']         = $job ? array( 'state' => $job->state, 'label' => RSP_Jobs::state_label( $job->state ) ) : null;
			$row['renewable']   = RSP_Accounts::is_renewable( $a ) && ! $job;
			$row['review_reason'] = 'review' === $a->status ? __( 'Our team is checking this activation and will update it shortly.', 'relay-stream-portal' ) : '';
			$vm['accounts'][]   = $row;
		}

		if ( $vm['billing'] ) {
			$page = isset( $args['page'] ) ? (int) $args['page'] : 1;
			$res  = RSP_Woocommerce::customer_orders(
				$uid,
				array(
					'page'       => $page,
					'per_page'   => isset( $args['per_page'] ) ? (int) $args['per_page'] : 10,
					'status'     => isset( $args['status'] ) ? $args['status'] : '',
					'hide_stale' => isset( $args['hide_stale'] ) ? $args['hide_stale'] : (bool) RSP_Settings::get( 'hide_stale_unpaid' ),
				)
			);
			$vm['orders']      = array( 'rows' => array_map( array( __CLASS__, 'order_row' ), $res['orders'] ), 'pages' => $res['pages'], 'total' => $res['total'], 'page' => $page );
			$vm['order_count'] = (int) $res['total'];
			$vm['plans']       = RSP_Woocommerce::plans();
			$vm['gateways']    = RSP_Woocommerce::gateway_titles();
			// Unpaid "new account" orders appear as "awaiting payment" cards in My Accounts.
			$unpaid = wc_get_orders( array( 'customer_id' => $uid, 'status' => array( 'pending', 'on-hold' ), 'limit' => 5, 'date_created' => '>' . ( time() - DAY_IN_SECONDS * 3 ) ) );
			foreach ( $unpaid as $o ) {
				foreach ( $o->get_items() as $item ) {
					$s = RSP_Woocommerce::snapshot( $item );
					if ( $s && 'new' === $s['purpose'] ) {
						$vm['pending_orders'][] = array( 'number' => $o->get_order_number(), 'plan' => $s['plan_name'], 'pay_url' => $o->needs_payment() ? $o->get_checkout_payment_url() : '', 'date' => $o->get_date_created() ? $o->get_date_created()->date( 'Y-m-d H:i:s' ) : '' );
					}
				}
			}
		}

		$vm['referral'] = array(
			'code'         => RSP_Referrals::code_for( $uid ),
			'link'         => RSP_Referrals::link_for( $uid ),
			'stats'        => RSP_Referrals::stats( $uid ),
			'history'      => RSP_Referrals::history( $uid ),
			'has_referrer' => (bool) self::has_referrer( $uid ),
		);
		$vm['gifts'] = array();
		foreach ( RSP_Gifts::purchased_by( $uid ) as $g ) {
			$snap          = json_decode( $g->plan_snapshot, true );
			$vm['gifts'][] = array(
				'id'         => (int) $g->id,
				'plan'       => isset( $snap['plan_name'] ) ? $snap['plan_name'] : '',
				'status'     => $g->status,
				'code'       => RSP_Gifts::code_for_owner( $g, $uid ),
				'hint'       => $g->code_hint,
				'expires_at' => $g->expires_at,
				'recipient'  => $g->recipient_email,
			);
		}
		$vm['tickets'] = array();
		foreach ( RSP_Tickets::for_user( $uid, 20 ) as $t ) {
			$vm['tickets'][] = array( 'id' => (int) $t->id, 'subject' => $t->subject, 'status' => $t->status, 'status_label' => RSP_Tickets::status_label( $t->status ), 'category' => $t->category, 'updated_at' => $t->updated_at );
		}
		$vm['notifications'] = array_map( 'get_object_vars', RSP_Notifications::recent( $uid, 8 ) );
		$vm['unread']        = RSP_Notifications::unread_count( $uid );
		$vm['announcements'] = self::announcements( $uid );

		if ( empty( $args ) ) {
			self::$vm = $vm;
		}
		return $vm;
	}

	/**
	 * Whether a referral is linked to the user.
	 *
	 * @param int $uid User.
	 * @return bool
	 */
	private static function has_referrer( $uid ) {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . RSP_Installer::table( 'referrals' ) . ' WHERE referee_id = %d', $uid ) ); // phpcs:ignore
	}

	/**
	 * Map an order to a row.
	 *
	 * @param WC_Order $o Order.
	 * @return array
	 */
	public static function order_row( $o ) {
		$prov = RSP_Fulfillment::order_provisioning_status( $o );
		return array(
			'id'           => $o->get_id(),
			'number'       => $o->get_order_number(),
			'date'         => $o->get_date_created() ? $o->get_date_created()->date( 'Y-m-d H:i:s' ) : '',
			'total'        => $o->get_formatted_order_total(),
			'method'       => $o->get_payment_method_title(),
			'status'       => $o->get_status(),
			'status_label' => wc_get_order_status_name( $o->get_status() ),
			'prov'         => $prov,
			'prov_label'   => RSP_Fulfillment::status_label( $prov ),
			'view_url'     => $o->get_view_order_url(),
			'pay_url'      => $o->needs_payment() ? $o->get_checkout_payment_url() : '',
		);
	}

	/**
	 * Active announcements not dismissed by the user.
	 *
	 * @param int $uid User.
	 * @return array
	 */
	public static function announcements( $uid ) {
		$dismissed = (array) get_user_meta( $uid, 'rsp_dismissed', true );
		$out       = array();
		foreach ( (array) get_option( 'rsp_announcements', array() ) as $a ) {
			if ( empty( $a['active'] ) || in_array( $a['id'], $dismissed, true ) ) {
				continue;
			}
			if ( ! empty( $a['starts'] ) && strtotime( $a['starts'] ) > time() ) {
				continue;
			}
			if ( ! empty( $a['ends'] ) && strtotime( $a['ends'] . ' 23:59:59' ) < time() ) {
				continue;
			}
			$out[] = $a;
		}
		return $out;
	}

	/**
	 * Render a template file with variables.
	 *
	 * @param string $name Relative template (e.g. modules/dashboard).
	 * @param array  $vars Vars.
	 * @return string
	 */
	public static function template( $name, array $vars = array() ) {
		$name = preg_replace( '/[^a-z0-9_\/-]/', '', $name );
		$file = apply_filters( 'rsp_template_path', RSP_DIR . 'templates/' . $name . '.php', $name );
		if ( ! is_readable( $file ) ) {
			return '';
		}
		ob_start();
		extract( $vars, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract
		include $file;
		return (string) ob_get_clean();
	}

	/**
	 * Wrapper attributes shared by shell and module embeds.
	 *
	 * @param array  $args Args.
	 * @param string $extra Extra classes.
	 * @return string
	 */
	private static function root_open( array $args, $extra ) {
		self::$instance++;
		$classes = array( 'rsp-root', $extra );
		if ( ! empty( $args['class'] ) ) {
			$classes[] = sanitize_html_class( $args['class'] );
		}
		if ( ! empty( $args['fullbleed'] ) ) {
			$classes[] = 'rsp-fullbleed';
		}
		$dir    = RSP_I18n::is_rtl() ? 'rtl' : 'ltr';
		$theme  = ! empty( $args['theme'] ) && in_array( $args['theme'], array( 'dark', 'light', 'auto' ), true ) ? $args['theme'] : self::theme_attr();
		$toggle = RSP_Settings::get( 'allow_theme_toggle' ) && empty( $args['theme'] );
		return '<div id="rsp-' . (int) self::$instance . '" class="' . esc_attr( implode( ' ', array_filter( $classes ) ) ) . '" data-rsp-theme="' . esc_attr( $theme ) . '" data-rsp-toggle="' . ( $toggle ? '1' : '0' ) . '" dir="' . esc_attr( $dir ) . '">';
	}

	/**
	 * Resolve the requested module.
	 *
	 * @param string $default Default.
	 * @return string
	 */
	public static function requested_module( $default ) {
		$mods = RSP_Settings::nav_modules();
		$v    = isset( $_GET['rsp_view'] ) ? sanitize_key( wp_unslash( $_GET['rsp_view'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( $v && isset( $mods[ $v ] ) ) {
			return $v;
		}
		return isset( $mods[ $default ] ) || in_array( $default, array( 'redeem', 'profile', 'checkout' ), true ) ? $default : 'dashboard';
	}

	/**
	 * Full portal shell (header + navigation + module).
	 *
	 * @param array $args module, labels, hide_nav.
	 * @return string
	 */
	public static function render_shell( array $args = array() ) {
		self::enqueue();
		self::$rendered = true;
		self::$shell    = true;
		$module         = self::requested_module( isset( $args['module'] ) ? $args['module'] : 'dashboard' );
		$preview        = self::is_preview();
		if ( ! $preview && ! is_user_logged_in() ) {
			return self::root_open( $args, 'rsp-shell rsp-guest' ) . self::render_auth( 'login', $args, false ) . '</div>';
		}
		$vm = self::view_model( self::transaction_args( $module ) );
		if ( 'topbar' === RSP_Settings::get( 'layout' ) ) {
			$html  = self::root_open( $args, 'rsp-shell rsp-layout-topbar' );
			$html .= self::template( 'partials/header', array( 'vm' => $vm, 'module' => $module, 'args' => $args ) );
			$html .= '<main class="rsp-main" id="rsp-main-' . (int) self::$instance . '" tabindex="-1">';
			$html .= self::flash_html() . self::module_html( $module, $vm, $args );
			$html .= '</main></div>';
			return $html;
		}
		$content = self::flash_html() . self::module_html( $module, $vm, $args );
		$html    = self::root_open( $args, 'rsp-shell rsp-layout-sidebar' . ( RSP_Settings::get( 'app_mode' ) && '' !== RSP_App::page_role() ? ' rsp-is-app' : '' ) );
		$html   .= self::template( 'partials/app-shell', array( 'vm' => $vm, 'module' => $module, 'args' => $args, 'content' => $content ) );
		$html   .= '</div>';
		return $html;
	}

	/**
	 * A single module embedded in an existing page.
	 *
	 * @param string $module Module.
	 * @param array  $args Args.
	 * @return string
	 */
	public static function render_module( $module, array $args = array() ) {
		self::enqueue();
		self::$rendered = true;
		$preview        = self::is_preview();
		if ( in_array( $module, self::PUBLIC_MODULES, true ) ) {
			return self::root_open( $args, 'rsp-embed rsp-auth-embed' ) . self::render_auth( $module, $args, $preview ) . '</div>';
		}
		if ( ! $preview && ! is_user_logged_in() ) {
			if ( in_array( $module, array( 'plan_cards', 'device_guides', 'support_button', 'announcements' ), true ) ) {
				$vm = array( 'preview' => false, 'guest' => true, 'plans' => RSP_Plugin::has_woocommerce() ? RSP_Woocommerce::plans() : array(), 'announcements' => array(), 'billing' => RSP_Plugin::has_woocommerce() );
				return self::root_open( $args, 'rsp-embed' ) . self::module_html( $module, $vm, $args ) . '</div>';
			}
			return self::root_open( $args, 'rsp-embed rsp-guest' ) . self::render_auth( 'login', $args, false ) . '</div>';
		}
		$vm = self::view_model( self::transaction_args( $module ) );
		return self::root_open( $args, 'rsp-embed' ) . self::flash_html() . self::module_html( $module, $vm, $args ) . '</div>';
	}

	/**
	 * Transactions filters from the query string.
	 *
	 * @param string $module Module.
	 * @return array
	 */
	private static function transaction_args( $module ) {
		if ( 'transactions' !== $module ) {
			return array();
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$statuses = RSP_Plugin::has_woocommerce() ? array_map( static function ( $s ) { return substr( $s, 3 ); }, array_keys( wc_get_order_statuses() ) ) : array();
		$status   = isset( $_GET['rsp_status'] ) ? sanitize_key( wp_unslash( $_GET['rsp_status'] ) ) : '';
		return array(
			'page'       => isset( $_GET['rsp_page'] ) ? max( 1, (int) $_GET['rsp_page'] ) : 1,
			'status'     => in_array( $status, $statuses, true ) ? $status : '',
			'hide_stale' => isset( $_GET['rsp_hide'] ) ? '1' === $_GET['rsp_hide'] : (bool) RSP_Settings::get( 'hide_stale_unpaid' ),
		);
		// phpcs:enable
	}

	/**
	 * Module body.
	 *
	 * @param string $module Module.
	 * @param array  $vm View model.
	 * @param array  $args Args.
	 * @return string
	 */
	public static function module_html( $module, array $vm, array $args ) {
		$map = array(
			'dashboard'            => 'modules/dashboard',
			'accounts'             => 'modules/accounts',
			'apps'                 => 'modules/apps',
			'plans'                => 'modules/plans',
			'transactions'         => 'modules/transactions',
			'referrals'            => 'modules/referrals',
			'gifts'                => 'modules/referrals',
			'redeem'               => 'modules/redeem',
			'support'              => 'modules/support',
			'checkout'             => 'modules/checkout',
			'profile'              => 'modules/profile',
			'plan_cards'           => 'partials/plan-cards',
			'subscription_summary' => 'partials/subscription-summary',
			'announcements'        => 'partials/announcements',
			'device_guides'        => 'partials/device-guides',
			'support_button'       => 'partials/support-button',
		);
		if ( ! isset( $map[ $module ] ) ) {
			return '';
		}
		if ( 'gifts' === $module ) {
			$args['tab'] = 'gifts';
		}
		if ( in_array( $module, array( 'plans', 'transactions', 'plan_cards', 'checkout' ), true ) && empty( $vm['billing'] ) ) {
			return '<div class="rsp-empty">' . esc_html__( 'Billing is unavailable because WooCommerce is not active.', 'relay-stream-portal' ) . '</div>';
		}
		return self::template( $map[ $module ], array( 'vm' => $vm, 'args' => $args, 'module' => $module ) );
	}

	/**
	 * Auth forms.
	 *
	 * @param string $which login|register|reset.
	 * @param array  $args Args.
	 * @param bool   $preview Preview.
	 * @return string
	 */
	public static function render_auth( $which, array $args, $preview ) {
		self::enqueue();
		if ( ! $preview && is_user_logged_in() && 'reset' !== $which ) {
			$u = wp_get_current_user();
			return '<div class="rsp-card rsp-auth-card"><p>' . sprintf( /* translators: %s name */ esc_html__( 'You are signed in as %s.', 'relay-stream-portal' ), esc_html( $u->display_name ) ) . '</p><p><a class="rsp-btn rsp-btn-primary" href="' . esc_url( RSP_Settings::page_url( 'dashboard' ) ) . '">' . esc_html__( 'Open your portal', 'relay-stream-portal' ) . '</a></p></div>';
		}
		return self::flash_html() . self::template( 'auth/' . $which, array( 'args' => $args, 'preview' => $preview ) );
	}

	/**
	 * Flash message markup.
	 *
	 * @return string
	 */
	public static function flash_html() {
		static $shown = false;
		$m = RSP_Forms::current_message();
		if ( ! $m || $shown ) {
			return '';
		}
		$shown = true;
		$icon  = 'error' === $m['type'] ? 'alert' : ( 'success' === $m['type'] ? 'check' : 'info' );
		return '<div class="rsp-flash rsp-flash-' . esc_attr( $m['type'] ) . '" role="' . ( 'error' === $m['type'] ? 'alert' : 'status' ) . '">' . RSP_Icons::get( $icon ) . '<span>' . esc_html( $m['message'] ) . '</span></div>';
	}

	/**
	 * Floating support button on portal pages.
	 */
	public static function floating_support() {
		if ( ! self::$rendered || ! RSP_Settings::get( 'floating_support' ) || ! is_user_logged_in() || ( 'sidebar' === RSP_Settings::get( 'layout' ) && self::$shell ) ) {
			return;
		}
		echo self::template( 'partials/floating-support', array() ); // phpcs:ignore WordPress.Security.EscapeOutput
	}

	/**
	 * Format a UTC datetime for display.
	 *
	 * @param string|null $utc UTC datetime.
	 * @return string
	 */
	public static function date( $utc ) {
		if ( ! $utc ) {
			return '—';
		}
		return esc_html( date_i18n( get_option( 'date_format' ), strtotime( $utc . ' UTC' ) ) );
	}

	/**
	 * Price split into symbol, whole and decimals ("$" "24" ".99") using WooCommerce currency settings.
	 *
	 * @param float $amount Amount.
	 * @return string HTML.
	 */
	public static function price_html( $amount ) {
		$dec    = function_exists( 'wc_get_price_decimals' ) ? wc_get_price_decimals() : 2;
		$dsep   = function_exists( 'wc_get_price_decimal_separator' ) ? wc_get_price_decimal_separator() : '.';
		$tsep   = function_exists( 'wc_get_price_thousand_separator' ) ? wc_get_price_thousand_separator() : ',';
		$sym    = function_exists( 'get_woocommerce_currency_symbol' ) ? html_entity_decode( get_woocommerce_currency_symbol() ) : '$';
		$pos    = get_option( 'woocommerce_currency_pos', 'left' );
		$whole  = number_format( floor( (float) $amount ), 0, '', $tsep );
		$cents  = $dec > 0 ? $dsep . substr( number_format( (float) $amount, $dec, '.', '' ), -$dec ) : '';
		$symbol = '<span class="rsp-price-sym">' . esc_html( $sym ) . '</span>';
		$body   = '<span class="rsp-price-whole">' . esc_html( $whole ) . '</span><span class="rsp-price-cents">' . esc_html( $cents ) . '</span>';
		return '<span class="rsp-price" dir="ltr">' . ( in_array( $pos, array( 'right', 'right_space' ), true ) ? $body . $symbol : $symbol . $body ) . '</span>';
	}

	/**
	 * Light inline markup for admin-authored guide text: **bold** only, everything else escaped.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public static function rich( $text ) {
		return preg_replace( '/\*\*(.+?)\*\*/u', '<strong>$1</strong>', esc_html( (string) $text ) );
	}

	/**
	 * Status pill.
	 *
	 * @param string $status Status key.
	 * @param string $label Label.
	 * @return string
	 */
	public static function pill( $status, $label ) {
		$tone = array(
			'active' => 'ok', 'succeeded' => 'ok', 'completed' => 'ok', 'rewarded' => 'ok', 'issued' => 'ok', 'resolved' => 'ok', 'gift_issued' => 'ok',
			'expired' => 'bad', 'failed' => 'bad', 'cancelled' => 'bad', 'suspended' => 'bad', 'refunded' => 'bad', 'revoked' => 'bad', 'reversed' => 'bad', 'rejected' => 'bad',
			'review' => 'warn', 'needs_review' => 'warn', 'pending' => 'warn', 'awaiting' => 'warn', 'awaiting_customer' => 'warn', 'on-hold' => 'warn', 'pending_payment' => 'warn',
		);
		$t = isset( $tone[ $status ] ) ? $tone[ $status ] : 'info';
		return '<span class="rsp-pill rsp-pill-' . esc_attr( $t ) . '">' . esc_html( $label ) . '</span>';
	}

	/**
	 * Label override helper (Elementor widget labels or shortcode attributes).
	 *
	 * @param array  $args Args.
	 * @param string $key Key.
	 * @param string $default Default.
	 * @return string
	 */
	public static function label( array $args, $key, $default ) {
		return isset( $args['labels'][ $key ] ) && '' !== trim( (string) $args['labels'][ $key ] ) ? (string) $args['labels'][ $key ] : $default;
	}

	/**
	 * Disabled attribute for preview (forms do not submit in the editor).
	 *
	 * @param array $vm View model.
	 * @return string
	 */
	public static function inert( array $vm ) {
		return ! empty( $vm['preview'] ) ? ' disabled aria-disabled="true"' : '';
	}
}
