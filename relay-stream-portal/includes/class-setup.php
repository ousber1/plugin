<?php
/**
 * Setup wizard: creates or maps portal pages.
 *
 * @package RelayStreamPortal
 */

defined( 'ABSPATH' ) || exit;

/**
 * Setup.
 */
class RSP_Setup {

	/**
	 * Installer entry point: create every missing portal page (Dashboard, Login, Sign up, Reset,
	 * module pages and Checkout) and use the portal Checkout for WooCommerce. Safe to call repeatedly.
	 *
	 * @return int Pages created.
	 */
	public static function install_pages() {
		$n = self::create_pages( true, false );
		self::apply_checkout();
		return $n;
	}

	/**
	 * Point WooCommerce's checkout at the portal Checkout page (previous page is remembered).
	 */
	public static function apply_checkout() {
		if ( ! RSP_Plugin::has_woocommerce() && ! function_exists( 'wc_get_page_id' ) ) {
			return;
		}
		$pages = (array) get_option( 'rsp_pages', array() );
		$ours  = ! empty( $pages['checkout'] ) ? (int) $pages['checkout'] : 0;
		if ( ! RSP_Settings::get( 'portal_checkout' ) || ! $ours || 'publish' !== get_post_status( $ours ) ) {
			return;
		}
		$current = (int) get_option( 'woocommerce_checkout_page_id' );
		if ( $current !== $ours ) {
			update_option( 'rsp_prev_checkout_page', $current, false );
			update_option( 'woocommerce_checkout_page_id', $ours );
			RSP_Audit::log( 'checkout_page_set', 'settings', 'checkout', '', array( 'from' => $current, 'to' => $ours ), get_current_user_id() );
		}
	}

	/**
	 * Restore the previous WooCommerce checkout page (deactivation or setting turned off).
	 */
	public static function restore_checkout() {
		$pages = (array) get_option( 'rsp_pages', array() );
		$prev  = (int) get_option( 'rsp_prev_checkout_page', 0 );
		if ( ! empty( $pages['checkout'] ) && (int) get_option( 'woocommerce_checkout_page_id' ) === (int) $pages['checkout'] && $prev && get_post( $prev ) ) {
			update_option( 'woocommerce_checkout_page_id', $prev );
		}
	}

	/**
	 * Create missing pages.
	 *
	 * @param bool $all_modules One page per module (otherwise one portal page + auth pages).
	 * @param bool $elementor Build the page with an Elementor widget instead of a shortcode.
	 * @return int Number of pages created.
	 */
	public static function create_pages( $all_modules, $elementor = false ) {
		$pages   = (array) get_option( 'rsp_pages', array() );
		$roles   = RSP_Settings::page_roles();
		$created = 0;
		$single  = array( 'login', 'register', 'reset', 'dashboard', 'redeem', 'checkout' );
		$widgets = array(
			'login'        => 'rsp-login',
			'register'     => 'rsp-register',
			'reset'        => 'rsp-password-reset',
		);
		foreach ( $roles as $role => $def ) {
			if ( ! $all_modules && ! in_array( $role, $single, true ) ) {
				continue;
			}
			if ( ! empty( $pages[ $role ] ) && get_post( (int) $pages[ $role ] ) && 'trash' !== get_post_status( (int) $pages[ $role ] ) ) {
				continue;
			}
			$id = wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_title'   => $def[0],
					'post_name'    => 'dashboard' === $role ? 'portal' : sanitize_title( 'portal-' . $role ),
					'post_content' => $def[1],
					'comment_status' => 'closed',
				),
				true
			);
			if ( is_wp_error( $id ) ) {
				continue;
			}
			if ( $elementor ) {
				$module = isset( $widgets[ $role ] ) ? $widgets[ $role ] : 'rsp-portal-shell';
				$data   = array(
					array(
						'id'       => substr( md5( 'rsp' . $id ), 0, 7 ),
						'elType'   => 'container',
						'settings' => array( 'content_width' => 'full', 'padding' => array( 'unit' => 'px', 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'isLinked' => true ) ),
						'elements' => array(
							array(
								'id'         => substr( md5( 'rspw' . $id ), 0, 7 ),
								'elType'     => 'widget',
								'widgetType' => $module,
								'settings'   => 'rsp-portal-shell' === $module ? array( 'rsp_module' => $role ) : array(),
								'elements'   => array(),
							),
						),
						'isInner'  => false,
					),
				);
				update_post_meta( $id, '_elementor_edit_mode', 'builder' );
				update_post_meta( $id, '_elementor_template_type', 'wp-page' );
				update_post_meta( $id, '_wp_page_template', 'elementor_canvas' === get_option( 'rsp_elementor_template' ) ? 'elementor_canvas' : 'elementor_header_footer' );
				update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
			}
			$pages[ $role ] = (int) $id;
			$created++;
		}
		update_option( 'rsp_pages', $pages, true );
		RSP_Audit::log( 'pages_created', 'settings', 'pages', '', array( 'created' => $created, 'elementor' => $elementor ) );
		return $created;
	}
}
