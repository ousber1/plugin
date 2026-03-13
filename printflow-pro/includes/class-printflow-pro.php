<?php
/**
 * Main PrintFlow Pro plugin class.
 *
 * @package PrintFlowPro
 */

defined( 'ABSPATH' ) || exit;

require_once PFP_PLUGIN_DIR . 'includes/traits/trait-pfp-singleton.php';

/**
 * Class PrintFlow_Pro
 *
 * The main plugin orchestrator that loads modules, registers hooks, and
 * initializes all plugin functionality.
 */
class PrintFlow_Pro {

	use PFP_Singleton;

	/**
	 * Loaded module instances.
	 *
	 * @var array
	 */
	private $modules = array();

	/**
	 * Private constructor for singleton pattern.
	 */
	private function __construct() {}

	/**
	 * Initialize the plugin.
	 */
	public function init() {
		$this->load_dependencies();
		$this->set_locale();
		$this->register_roles();
		$this->load_modules();

		if ( is_admin() ) {
			$this->init_admin();
		}

		$this->init_frontend();
		$this->init_woocommerce();
		$this->init_api();
		$this->register_cron_events();
	}

	/**
	 * Load required files.
	 */
	private function load_dependencies() {
		$includes = PFP_PLUGIN_DIR . 'includes/';

		require_once $includes . 'class-pfp-i18n.php';
		require_once $includes . 'class-pfp-roles.php';
		require_once $includes . 'class-pfp-installer.php';

		// Modules.
		require_once $includes . 'modules/class-pfp-dashboard.php';
		require_once $includes . 'modules/class-pfp-products.php';
		require_once $includes . 'modules/class-pfp-pricing-engine.php';
		require_once $includes . 'modules/class-pfp-file-manager.php';
		require_once $includes . 'modules/class-pfp-quotes.php';
		require_once $includes . 'modules/class-pfp-orders.php';
		require_once $includes . 'modules/class-pfp-production.php';
		require_once $includes . 'modules/class-pfp-inventory.php';
		require_once $includes . 'modules/class-pfp-suppliers.php';
		require_once $includes . 'modules/class-pfp-distributors.php';
		require_once $includes . 'modules/class-pfp-finance.php';
		require_once $includes . 'modules/class-pfp-crm.php';
		require_once $includes . 'modules/class-pfp-delivery.php';
		require_once $includes . 'modules/class-pfp-notifications.php';
		require_once $includes . 'modules/class-pfp-reports.php';
		require_once $includes . 'modules/class-pfp-settings.php';

		// Admin.
		require_once $includes . 'admin/class-pfp-admin.php';
		require_once $includes . 'admin/class-pfp-admin-menu.php';

		// Frontend.
		require_once $includes . 'frontend/class-pfp-frontend.php';
		require_once $includes . 'frontend/class-pfp-shortcodes.php';

		// WooCommerce integration.
		require_once $includes . 'woocommerce/class-pfp-wc-integration.php';
		require_once $includes . 'woocommerce/class-pfp-wc-order-statuses.php';
		require_once $includes . 'woocommerce/class-pfp-wc-product-fields.php';
		require_once $includes . 'woocommerce/class-pfp-wc-checkout.php';
		require_once $includes . 'woocommerce/class-pfp-wc-emails.php';

		// REST API.
		require_once $includes . 'api/class-pfp-rest-api.php';
		require_once $includes . 'api/class-pfp-rest-pricing.php';
		require_once $includes . 'api/class-pfp-rest-production.php';
		require_once $includes . 'api/class-pfp-rest-inventory.php';
	}

	/**
	 * Set up localization.
	 */
	private function set_locale() {
		$i18n = new PFP_i18n();
		add_action( 'init', array( $i18n, 'load_plugin_textdomain' ) );
	}

	/**
	 * Register custom roles and capabilities.
	 */
	private function register_roles() {
		$roles = new PFP_Roles();
		$roles->init();
	}

	/**
	 * Load and initialize all plugin modules.
	 */
	private function load_modules() {
		$module_classes = array(
			'dashboard'     => 'PFP_Dashboard',
			'products'      => 'PFP_Products',
			'pricing'       => 'PFP_Pricing_Engine',
			'files'         => 'PFP_File_Manager',
			'quotes'        => 'PFP_Quotes',
			'orders'        => 'PFP_Orders',
			'production'    => 'PFP_Production',
			'inventory'     => 'PFP_Inventory',
			'suppliers'     => 'PFP_Suppliers',
			'distributors'  => 'PFP_Distributors',
			'finance'       => 'PFP_Finance',
			'crm'           => 'PFP_CRM',
			'delivery'      => 'PFP_Delivery',
			'notifications' => 'PFP_Notifications',
			'reports'       => 'PFP_Reports',
			'settings'      => 'PFP_Settings_Module',
		);

		foreach ( $module_classes as $key => $class ) {
			if ( $this->is_module_active( $key ) && class_exists( $class ) ) {
				$this->modules[ $key ] = new $class();
				$this->modules[ $key ]->init();
			}
		}
	}

	/**
	 * Check if a module is active.
	 *
	 * @param string $module_key Module identifier.
	 * @return bool
	 */
	public function is_module_active( $module_key ) {
		$always_active = array( 'dashboard', 'orders', 'settings', 'products' );
		if ( in_array( $module_key, $always_active, true ) ) {
			return true;
		}
		$active_modules = get_option( 'pfp_active_modules', array() );
		if ( empty( $active_modules ) ) {
			return true; // All active by default.
		}
		return in_array( $module_key, $active_modules, true );
	}

	/**
	 * Get a loaded module instance.
	 *
	 * @param string $key Module key.
	 * @return object|null
	 */
	public function get_module( $key ) {
		return isset( $this->modules[ $key ] ) ? $this->modules[ $key ] : null;
	}

	/**
	 * Initialize admin functionality.
	 */
	private function init_admin() {
		$admin = new PFP_Admin();
		$admin->init();

		$menu = new PFP_Admin_Menu();
		$menu->init();
	}

	/**
	 * Initialize frontend functionality.
	 */
	private function init_frontend() {
		$frontend = new PFP_Frontend();
		$frontend->init();

		$shortcodes = new PFP_Shortcodes();
		$shortcodes->init();
	}

	/**
	 * Initialize WooCommerce integration.
	 */
	private function init_woocommerce() {
		$wc_integration = new PFP_WC_Integration();
		$wc_integration->init();

		$wc_statuses = new PFP_WC_Order_Statuses();
		$wc_statuses->init();

		$wc_fields = new PFP_WC_Product_Fields();
		$wc_fields->init();

		$wc_checkout = new PFP_WC_Checkout();
		$wc_checkout->init();

		$wc_emails = new PFP_WC_Emails();
		$wc_emails->init();
	}

	/**
	 * Initialize REST API.
	 */
	private function init_api() {
		$api = new PFP_REST_API();
		$api->init();
	}

	/**
	 * Register cron events for scheduled tasks.
	 */
	private function register_cron_events() {
		add_action( 'pfp_hourly_stock_check', array( $this, 'run_stock_check' ) );
		add_action( 'pfp_daily_report', array( $this, 'run_daily_report' ) );
		add_action( 'pfp_weekly_report', array( $this, 'run_weekly_report' ) );
		add_action( 'pfp_monthly_report', array( $this, 'run_monthly_report' ) );
	}

	/**
	 * Run hourly stock level check.
	 */
	public function run_stock_check() {
		$inventory = $this->get_module( 'inventory' );
		if ( $inventory ) {
			$inventory->check_low_stock_alerts();
		}
	}

	/**
	 * Run daily report generation.
	 */
	public function run_daily_report() {
		$reports = $this->get_module( 'reports' );
		if ( $reports ) {
			$reports->generate_daily_report();
		}
	}

	/**
	 * Run weekly report generation.
	 */
	public function run_weekly_report() {
		$reports = $this->get_module( 'reports' );
		if ( $reports ) {
			$reports->generate_weekly_report();
		}
	}

	/**
	 * Run monthly report generation.
	 */
	public function run_monthly_report() {
		$reports = $this->get_module( 'reports' );
		if ( $reports ) {
			$reports->generate_monthly_report();
		}
	}
}
