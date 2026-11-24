<?php
/**
 * Plugin bootstrap.
 *
 * @package RelayStreamPortal
 */

defined( 'ABSPATH' ) || exit;

/**
 * Main plugin singleton: wires every component.
 */
final class RSP_Plugin {

	/** @var RSP_Plugin|null */
	private static $instance = null;

	/**
	 * Singleton accessor.
	 *
	 * @return RSP_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->boot();
		}
		return self::$instance;
	}

	/**
	 * Whether WooCommerce billing is available.
	 *
	 * @return bool
	 */
	public static function has_woocommerce() {
		return class_exists( 'WooCommerce' ) && function_exists( 'wc_get_orders' );
	}

	/**
	 * Register hooks.
	 */
	private function boot() {
		RSP_Installer::maybe_upgrade();

		RSP_I18n::init();
		add_action( 'init', array( $this, 'load_textdomain' ), 1 );

		RSP_Jobs::init();
		RSP_Auth::init();
		RSP_Portal::init();
		RSP_Shortcodes::init();
		RSP_Rest::init();
		RSP_Forms::init();
		RSP_Referrals::init();
		RSP_Gifts::init();
		RSP_Tickets::init();
		RSP_Notifications::init();

		if ( self::has_woocommerce() ) {
			RSP_Woocommerce::init();
			RSP_Fulfillment::init();
		}

		add_action( 'elementor/init', array( 'RSP_Elementor', 'init' ) );

		if ( is_admin() ) {
			RSP_Admin::init();
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			RSP_Cli::register();
		}
	}

	/**
	 * Load translations.
	 */
	public function load_textdomain() {
		$domain = 'relay-stream-portal';
		$site   = determine_locale();
		$portal = RSP_I18n::locale();
		// Front end with a portal language different from the request locale: WordPress files
		// translations under the requested locale, so load the portal file under the current one.
		if ( ! is_admin() && $portal !== $site ) {
			unload_textdomain( $domain, true );
			if ( 0 === strpos( $portal, 'en' ) ) {
				return; // English source strings.
			}
			foreach ( array( WP_LANG_DIR . '/plugins/' . $domain . '-' . $portal . '.mo', RSP_DIR . 'languages/' . $domain . '-' . $portal . '.mo' ) as $file ) {
				if ( is_readable( $file ) && load_textdomain( $domain, $file, $site ) ) {
					return;
				}
			}
		}
		load_plugin_textdomain( $domain, false, dirname( plugin_basename( RSP_FILE ) ) . '/languages' );
	}
}
