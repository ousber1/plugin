<?php
/**
 * Internationalization handler.
 *
 * @package PrintFlowPro
 */

defined( 'ABSPATH' ) || exit;

class PFP_i18n {

	/**
	 * Load the plugin text domain.
	 */
	public function load_plugin_textdomain() {
		load_plugin_textdomain(
			'printflow-pro',
			false,
			dirname( PFP_PLUGIN_BASENAME ) . '/languages/'
		);
	}
}
