<?php
/**
 * Plugin Name: PrintFlow Pro
 * Plugin URI: https://printflow-pro.com
 * Description: Gestion complète de votre imprimerie — du devis à la livraison. A complete printing business management platform built on WooCommerce.
 * Version: 1.0.0
 * Author: PrintFlow Pro
 * Author URI: https://printflow-pro.com
 * Text Domain: printflow-pro
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 * WC tested up to: 9.0
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package PrintFlowPro
 */

defined( 'ABSPATH' ) || exit;

// Plugin constants.
define( 'PFP_VERSION', '1.0.0' );
define( 'PFP_PLUGIN_FILE', __FILE__ );
define( 'PFP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PFP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'PFP_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'PFP_DB_VERSION', '1.0.0' );

/**
 * Check if WooCommerce is active before initializing the plugin.
 */
function pfp_check_woocommerce() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'pfp_woocommerce_missing_notice' );
		return false;
	}
	return true;
}

/**
 * Display admin notice if WooCommerce is not active.
 */
function pfp_woocommerce_missing_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<strong>PrintFlow Pro</strong> nécessite WooCommerce pour fonctionner.
			Veuillez installer et activer <a href="<?php echo esc_url( admin_url( 'plugin-install.php?s=woocommerce&tab=search&type=term' ) ); ?>">WooCommerce</a>.
		</p>
	</div>
	<?php
}

/**
 * Plugin activation hook.
 */
function pfp_activate() {
	require_once PFP_PLUGIN_DIR . 'includes/class-pfp-activator.php';
	PFP_Activator::activate();
}
register_activation_hook( __FILE__, 'pfp_activate' );

/**
 * Plugin deactivation hook.
 */
function pfp_deactivate() {
	require_once PFP_PLUGIN_DIR . 'includes/class-pfp-deactivator.php';
	PFP_Deactivator::deactivate();
}
register_deactivation_hook( __FILE__, 'pfp_deactivate' );

/**
 * Declare HPOS compatibility.
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

/**
 * Initialize the plugin.
 */
function pfp_init() {
	if ( ! pfp_check_woocommerce() ) {
		return;
	}

	require_once PFP_PLUGIN_DIR . 'includes/class-printflow-pro.php';

	$plugin = PrintFlow_Pro::instance();
	$plugin->init();
}
add_action( 'plugins_loaded', 'pfp_init', 20 );
