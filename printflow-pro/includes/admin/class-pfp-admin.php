<?php
/**
 * Admin hooks and asset management.
 *
 * @package PrintFlowPro
 */

defined( 'ABSPATH' ) || exit;

class PFP_Admin {

	public function init() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_init', array( $this, 'maybe_redirect_to_wizard' ) );
		add_filter( 'plugin_action_links_' . PFP_PLUGIN_BASENAME, array( $this, 'add_plugin_links' ) );
	}

	/**
	 * Enqueue admin CSS and JS.
	 *
	 * @param string $hook Current admin page.
	 */
	public function enqueue_assets( $hook ) {
		// Only load on PrintFlow pages.
		if ( strpos( $hook, 'printflow' ) === false && strpos( $hook, 'pfp' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'pfp-admin',
			PFP_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			PFP_VERSION
		);

		wp_enqueue_script(
			'pfp-admin',
			PFP_PLUGIN_URL . 'assets/js/admin/dashboard.js',
			array( 'jquery' ),
			PFP_VERSION,
			true
		);

		wp_localize_script(
			'pfp-admin',
			'pfp_admin',
			array(
				'ajax_url'    => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'pfp_admin_nonce' ),
				'rest_url'    => rest_url( 'printflow-pro/v1/' ),
				'rest_nonce'  => wp_create_nonce( 'wp_rest' ),
				'currency'    => get_option( 'pfp_currency', 'MAD' ),
				'plugin_url'  => PFP_PLUGIN_URL,
			)
		);

		// Chart.js for reports.
		if ( strpos( $hook, 'rapports' ) !== false || strpos( $hook, 'dashboard' ) !== false || strpos( $hook, 'printflow-pro' ) !== false ) {
			wp_enqueue_script(
				'chartjs',
				'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
				array(),
				'4.4.0',
				true
			);
		}
	}

	/**
	 * Redirect to setup wizard on first activation.
	 */
	public function maybe_redirect_to_wizard() {
		if ( ! get_transient( 'pfp_activation_redirect' ) ) {
			return;
		}
		delete_transient( 'pfp_activation_redirect' );

		if ( wp_doing_ajax() || is_network_admin() ) {
			return;
		}

		// Only redirect if setup hasn't been completed.
		if ( get_option( 'pfp_setup_complete', false ) ) {
			return;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=printflow-pro-setup' ) );
		exit;
	}

	/**
	 * Add quick links to the plugins list page.
	 *
	 * @param array $links Existing links.
	 * @return array
	 */
	public function add_plugin_links( $links ) {
		$custom_links = array(
			'<a href="' . admin_url( 'admin.php?page=printflow-pro' ) . '">' . __( 'Tableau de bord', 'printflow-pro' ) . '</a>',
			'<a href="' . admin_url( 'admin.php?page=printflow-pro-settings' ) . '">' . __( 'Réglages', 'printflow-pro' ) . '</a>',
		);
		return array_merge( $custom_links, $links );
	}
}
