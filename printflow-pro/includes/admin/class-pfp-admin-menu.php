<?php
/**
 * Admin menu registration.
 *
 * @package PrintFlowPro
 */

defined( 'ABSPATH' ) || exit;

class PFP_Admin_Menu {

	public function init() {
		add_action( 'admin_menu', array( $this, 'register_menus' ) );
	}

	/**
	 * Register all admin menu pages.
	 */
	public function register_menus() {
		// Main menu.
		add_menu_page(
			__( 'PrintFlow Pro', 'printflow-pro' ),
			__( 'PrintFlow Pro', 'printflow-pro' ),
			'manage_printflow',
			'printflow-pro',
			array( $this, 'render_dashboard' ),
			'dashicons-printer',
			25
		);

		// Dashboard (same as main).
		add_submenu_page(
			'printflow-pro',
			__( 'Tableau de bord', 'printflow-pro' ),
			__( 'Tableau de bord', 'printflow-pro' ),
			'pfp_view_dashboard',
			'printflow-pro',
			array( $this, 'render_dashboard' )
		);

		// Products.
		add_submenu_page(
			'printflow-pro',
			__( 'Produits', 'printflow-pro' ),
			__( 'Produits', 'printflow-pro' ),
			'pfp_manage_products',
			'printflow-pro-products',
			array( $this, 'render_products' )
		);

		// Pricing.
		add_submenu_page(
			'printflow-pro',
			__( 'Tarification', 'printflow-pro' ),
			__( 'Tarification', 'printflow-pro' ),
			'pfp_manage_pricing',
			'printflow-pro-pricing',
			array( $this, 'render_pricing' )
		);

		// Files.
		add_submenu_page(
			'printflow-pro',
			__( 'Fichiers', 'printflow-pro' ),
			__( 'Fichiers', 'printflow-pro' ),
			'pfp_manage_files',
			'printflow-pro-files',
			array( $this, 'render_files' )
		);

		// Quotes.
		add_submenu_page(
			'printflow-pro',
			__( 'Devis', 'printflow-pro' ),
			__( 'Devis', 'printflow-pro' ),
			'pfp_manage_quotes',
			'printflow-pro-quotes',
			array( $this, 'render_quotes' )
		);

		// Orders.
		add_submenu_page(
			'printflow-pro',
			__( 'Commandes', 'printflow-pro' ),
			__( 'Commandes', 'printflow-pro' ),
			'pfp_manage_orders',
			'printflow-pro-orders',
			array( $this, 'render_orders' )
		);

		// Production.
		add_submenu_page(
			'printflow-pro',
			__( 'Production', 'printflow-pro' ),
			__( 'Production', 'printflow-pro' ),
			'pfp_manage_production',
			'printflow-pro-production',
			array( $this, 'render_production' )
		);

		// Inventory.
		add_submenu_page(
			'printflow-pro',
			__( 'Inventaire', 'printflow-pro' ),
			__( 'Inventaire', 'printflow-pro' ),
			'pfp_manage_inventory',
			'printflow-pro-inventory',
			array( $this, 'render_inventory' )
		);

		// Suppliers.
		add_submenu_page(
			'printflow-pro',
			__( 'Fournisseurs', 'printflow-pro' ),
			__( 'Fournisseurs', 'printflow-pro' ),
			'pfp_manage_suppliers',
			'printflow-pro-suppliers',
			array( $this, 'render_suppliers' )
		);

		// Distributors.
		add_submenu_page(
			'printflow-pro',
			__( 'Distributeurs', 'printflow-pro' ),
			__( 'Distributeurs', 'printflow-pro' ),
			'pfp_manage_distributors',
			'printflow-pro-distributors',
			array( $this, 'render_distributors' )
		);

		// Finance.
		add_submenu_page(
			'printflow-pro',
			__( 'Finances', 'printflow-pro' ),
			__( 'Finances', 'printflow-pro' ),
			'pfp_manage_finance',
			'printflow-pro-finance',
			array( $this, 'render_finance' )
		);

		// CRM.
		add_submenu_page(
			'printflow-pro',
			__( 'Clients', 'printflow-pro' ),
			__( 'Clients', 'printflow-pro' ),
			'pfp_manage_crm',
			'printflow-pro-crm',
			array( $this, 'render_crm' )
		);

		// Delivery.
		add_submenu_page(
			'printflow-pro',
			__( 'Livraisons', 'printflow-pro' ),
			__( 'Livraisons', 'printflow-pro' ),
			'pfp_manage_delivery',
			'printflow-pro-delivery',
			array( $this, 'render_delivery' )
		);

		// Notifications.
		add_submenu_page(
			'printflow-pro',
			__( 'Notifications', 'printflow-pro' ),
			__( 'Notifications', 'printflow-pro' ),
			'pfp_manage_notifications',
			'printflow-pro-notifications',
			array( $this, 'render_notifications' )
		);

		// Reports.
		add_submenu_page(
			'printflow-pro',
			__( 'Rapports', 'printflow-pro' ),
			__( 'Rapports', 'printflow-pro' ),
			'pfp_view_reports',
			'printflow-pro-reports',
			array( $this, 'render_reports' )
		);

		// Settings.
		add_submenu_page(
			'printflow-pro',
			__( 'Réglages', 'printflow-pro' ),
			__( 'Réglages', 'printflow-pro' ),
			'pfp_manage_settings',
			'printflow-pro-settings',
			array( $this, 'render_settings' )
		);

		// Setup wizard (hidden from menu).
		add_submenu_page(
			null,
			__( 'Assistant de configuration', 'printflow-pro' ),
			'',
			'manage_options',
			'printflow-pro-setup',
			array( $this, 'render_setup_wizard' )
		);
	}

	// Render methods load the corresponding admin view template.

	public function render_dashboard() {
		include PFP_PLUGIN_DIR . 'includes/admin/views/dashboard/index.php';
	}

	public function render_products() {
		include PFP_PLUGIN_DIR . 'includes/admin/views/products/index.php';
	}

	public function render_pricing() {
		include PFP_PLUGIN_DIR . 'includes/admin/views/pricing/index.php';
	}

	public function render_files() {
		include PFP_PLUGIN_DIR . 'includes/admin/views/dashboard/index.php';
	}

	public function render_quotes() {
		include PFP_PLUGIN_DIR . 'includes/admin/views/quotes/index.php';
	}

	public function render_orders() {
		include PFP_PLUGIN_DIR . 'includes/admin/views/orders/index.php';
	}

	public function render_production() {
		include PFP_PLUGIN_DIR . 'includes/admin/views/production/index.php';
	}

	public function render_inventory() {
		include PFP_PLUGIN_DIR . 'includes/admin/views/inventory/index.php';
	}

	public function render_suppliers() {
		include PFP_PLUGIN_DIR . 'includes/admin/views/suppliers/index.php';
	}

	public function render_distributors() {
		include PFP_PLUGIN_DIR . 'includes/admin/views/distributors/index.php';
	}

	public function render_finance() {
		include PFP_PLUGIN_DIR . 'includes/admin/views/finance/index.php';
	}

	public function render_crm() {
		include PFP_PLUGIN_DIR . 'includes/admin/views/crm/index.php';
	}

	public function render_delivery() {
		include PFP_PLUGIN_DIR . 'includes/admin/views/delivery/index.php';
	}

	public function render_notifications() {
		include PFP_PLUGIN_DIR . 'includes/admin/views/notifications/index.php';
	}

	public function render_reports() {
		include PFP_PLUGIN_DIR . 'includes/admin/views/reports/index.php';
	}

	public function render_settings() {
		include PFP_PLUGIN_DIR . 'includes/admin/views/settings/index.php';
	}

	public function render_setup_wizard() {
		include PFP_PLUGIN_DIR . 'includes/admin/views/setup-wizard/index.php';
	}
}
