<?php
/**
 * Plugin Name:       Relay Stream Portal
 * Plugin URI:        https://example.com/relay-stream-portal
 * Description:       Customer portal, management dashboard and provider integration for authorized streaming subscriptions. Works with WooCommerce for billing and Elementor Free (or shortcodes) for page building.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            Relay Stream Portal contributors
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       relay-stream-portal
 * Domain Path:       /languages
 * WC requires at least: 8.0
 * WC tested up to:   11.3
 * Elementor tested up to: 4.4
 *
 * @package RelayStreamPortal
 */

defined( 'ABSPATH' ) || exit;

define( 'RSP_VERSION', '1.0.0' );
define( 'RSP_DB_VERSION', 4 );
define( 'RSP_FILE', __FILE__ );
define( 'RSP_DIR', plugin_dir_path( __FILE__ ) );
define( 'RSP_URL', plugin_dir_url( __FILE__ ) );
define( 'RSP_SLUG', 'relay-stream-portal' );

/**
 * Class map autoloader. Classes are prefixed RSP_ and live in includes/.
 */
spl_autoload_register(
	static function ( $class ) {
		if ( 0 !== strpos( $class, 'RSP_' ) ) {
			return;
		}
		$name  = strtolower( str_replace( '_', '-', substr( $class, 4 ) ) );
		$paths = array(
			RSP_DIR . 'includes/class-' . $name . '.php',
			RSP_DIR . 'includes/provider/class-' . $name . '.php',
			RSP_DIR . 'includes/admin/class-' . $name . '.php',
			RSP_DIR . 'includes/elementor/class-' . $name . '.php',
		);
		foreach ( $paths as $path ) {
			if ( is_readable( $path ) ) {
				require_once $path;
				return;
			}
		}
	}
);

register_activation_hook( __FILE__, array( 'RSP_Installer', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'RSP_Installer', 'deactivate' ) );

// Declare WooCommerce HPOS and cart/checkout blocks compatibility.
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

add_action( 'plugins_loaded', array( 'RSP_Plugin', 'instance' ), 20 );

/**
 * Accessor.
 *
 * @return RSP_Plugin
 */
function rsp() {
	return RSP_Plugin::instance();
}
