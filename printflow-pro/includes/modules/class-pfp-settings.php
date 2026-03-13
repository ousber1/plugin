<?php
/**
 * Settings / Configuration module.
 *
 * @package PrintFlowPro
 */

defined( 'ABSPATH' ) || exit;

class PFP_Settings_Module {

	/**
	 * Default settings.
	 *
	 * @var array
	 */
	private $defaults = array(
		'pfp_business_name'          => '',
		'pfp_business_address'       => '',
		'pfp_business_phone'         => '',
		'pfp_business_email'         => '',
		'pfp_business_ice'           => '',
		'pfp_business_rc'            => '',
		'pfp_business_cnss'          => '',
		'pfp_business_logo'          => '',
		'pfp_currency'               => 'MAD',
		'pfp_tax_rate'               => 20,
		'pfp_default_margin'         => 30,
		'pfp_max_upload_size'        => 524288000,
		'pfp_allowed_file_types'     => 'pdf,ai,eps,psd,png,jpg,jpeg,tiff,tif',
		'pfp_default_lead_time'      => '3-5 jours',
		'pfp_design_service_fee'     => 150,
		'pfp_loyalty_points_ratio'   => 10,
		'pfp_remove_data_on_uninstall' => 'no',
	);

	public function init() {
		add_action( 'wp_ajax_pfp_save_settings', array( $this, 'ajax_save_settings' ) );
		add_action( 'wp_ajax_pfp_get_settings', array( $this, 'ajax_get_settings' ) );
		add_action( 'wp_ajax_pfp_toggle_module', array( $this, 'ajax_toggle_module' ) );
	}

	/**
	 * Get a setting value.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public function get( $key, $default = null ) {
		if ( null === $default && isset( $this->defaults[ $key ] ) ) {
			$default = $this->defaults[ $key ];
		}
		return get_option( $key, $default );
	}

	/**
	 * Set a setting value.
	 *
	 * @param string $key   Setting key.
	 * @param mixed  $value Value.
	 */
	public function set( $key, $value ) {
		update_option( $key, $value );
	}

	/**
	 * Get all settings.
	 *
	 * @return array
	 */
	public function get_all() {
		$settings = array();
		foreach ( $this->defaults as $key => $default ) {
			$settings[ $key ] = get_option( $key, $default );
		}

		$settings['active_modules'] = get_option( 'pfp_active_modules', array() );

		return $settings;
	}

	/**
	 * Get available modules and their status.
	 *
	 * @return array
	 */
	public function get_modules_config() {
		$active = get_option( 'pfp_active_modules', array() );
		$all_active = empty( $active );

		return array(
			array( 'key' => 'pricing',       'name' => 'Moteur de tarification',  'always_on' => false, 'active' => $all_active || in_array( 'pricing', $active, true ) ),
			array( 'key' => 'files',         'name' => 'Gestion des fichiers',    'always_on' => false, 'active' => $all_active || in_array( 'files', $active, true ) ),
			array( 'key' => 'quotes',        'name' => 'Gestion des devis',       'always_on' => false, 'active' => $all_active || in_array( 'quotes', $active, true ) ),
			array( 'key' => 'production',    'name' => 'Gestion de production',   'always_on' => false, 'active' => $all_active || in_array( 'production', $active, true ) ),
			array( 'key' => 'inventory',     'name' => 'Inventaire',              'always_on' => false, 'active' => $all_active || in_array( 'inventory', $active, true ) ),
			array( 'key' => 'suppliers',     'name' => 'Fournisseurs',            'always_on' => false, 'active' => $all_active || in_array( 'suppliers', $active, true ) ),
			array( 'key' => 'distributors',  'name' => 'Distributeurs',           'always_on' => false, 'active' => $all_active || in_array( 'distributors', $active, true ) ),
			array( 'key' => 'finance',       'name' => 'Finances',                'always_on' => false, 'active' => $all_active || in_array( 'finance', $active, true ) ),
			array( 'key' => 'crm',           'name' => 'CRM Clients',             'always_on' => false, 'active' => $all_active || in_array( 'crm', $active, true ) ),
			array( 'key' => 'delivery',      'name' => 'Livraisons',              'always_on' => false, 'active' => $all_active || in_array( 'delivery', $active, true ) ),
			array( 'key' => 'notifications', 'name' => 'Notifications',           'always_on' => false, 'active' => $all_active || in_array( 'notifications', $active, true ) ),
			array( 'key' => 'reports',       'name' => 'Rapports',                'always_on' => false, 'active' => $all_active || in_array( 'reports', $active, true ) ),
		);
	}

	// AJAX handlers.

	public function ajax_save_settings() {
		check_ajax_referer( 'pfp_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'pfp_manage_settings' ) ) {
			wp_send_json_error( array( 'message' => 'Accès refusé.' ) );
		}

		$settings = isset( $_POST['settings'] ) ? $_POST['settings'] : array();

		foreach ( $settings as $key => $value ) {
			if ( ! array_key_exists( $key, $this->defaults ) ) {
				continue;
			}
			$this->set( $key, sanitize_text_field( wp_unslash( $value ) ) );
		}

		wp_send_json_success( array( 'message' => 'Paramètres enregistrés.' ) );
	}

	public function ajax_get_settings() {
		check_ajax_referer( 'pfp_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'pfp_manage_settings' ) ) {
			wp_send_json_error( array( 'message' => 'Accès refusé.' ) );
		}
		wp_send_json_success( $this->get_all() );
	}

	public function ajax_toggle_module() {
		check_ajax_referer( 'pfp_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'pfp_manage_settings' ) ) {
			wp_send_json_error( array( 'message' => 'Accès refusé.' ) );
		}

		$module = sanitize_text_field( wp_unslash( $_POST['module'] ?? '' ) );
		$active = ! empty( $_POST['active'] );

		$modules = get_option( 'pfp_active_modules', array() );

		if ( $active && ! in_array( $module, $modules, true ) ) {
			$modules[] = $module;
		} elseif ( ! $active ) {
			$modules = array_diff( $modules, array( $module ) );
		}

		update_option( 'pfp_active_modules', array_values( $modules ) );

		wp_send_json_success( array( 'message' => 'Module mis à jour.' ) );
	}
}
