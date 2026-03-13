<?php
/**
 * Custom roles and capabilities for PrintFlow Pro.
 *
 * @package PrintFlowPro
 */

defined( 'ABSPATH' ) || exit;

class PFP_Roles {

	/**
	 * All PrintFlow Pro capabilities.
	 *
	 * @var array
	 */
	private $capabilities = array(
		'manage_printflow',
		'pfp_view_dashboard',
		'pfp_manage_products',
		'pfp_manage_pricing',
		'pfp_manage_files',
		'pfp_manage_quotes',
		'pfp_manage_orders',
		'pfp_manage_production',
		'pfp_manage_inventory',
		'pfp_manage_suppliers',
		'pfp_manage_distributors',
		'pfp_manage_finance',
		'pfp_manage_crm',
		'pfp_manage_delivery',
		'pfp_manage_notifications',
		'pfp_view_reports',
		'pfp_manage_settings',
	);

	/**
	 * Initialize roles on plugin load.
	 */
	public function init() {
		// Roles are created during activation, just ensure admin has caps.
		$this->ensure_admin_caps();
	}

	/**
	 * Create all custom roles.
	 */
	public function create_roles() {
		// Manager — almost everything except settings.
		add_role(
			'pfp_manager',
			__( 'PrintFlow Manager', 'printflow-pro' ),
			array(
				'read'                   => true,
				'edit_posts'             => false,
				'manage_printflow'       => true,
				'pfp_view_dashboard'     => true,
				'pfp_manage_products'    => true,
				'pfp_manage_pricing'     => true,
				'pfp_manage_files'       => true,
				'pfp_manage_quotes'      => true,
				'pfp_manage_orders'      => true,
				'pfp_manage_production'  => true,
				'pfp_manage_inventory'   => true,
				'pfp_manage_suppliers'   => true,
				'pfp_manage_distributors'=> true,
				'pfp_manage_finance'     => true,
				'pfp_manage_crm'         => true,
				'pfp_manage_delivery'    => true,
				'pfp_manage_notifications' => true,
				'pfp_view_reports'       => true,
			)
		);

		// Sales Agent.
		add_role(
			'pfp_sales_agent',
			__( 'PrintFlow Agent commercial', 'printflow-pro' ),
			array(
				'read'                => true,
				'edit_posts'          => false,
				'manage_printflow'    => true,
				'pfp_view_dashboard'  => true,
				'pfp_manage_quotes'   => true,
				'pfp_manage_orders'   => true,
				'pfp_manage_crm'      => true,
			)
		);

		// Designer.
		add_role(
			'pfp_designer',
			__( 'PrintFlow Designer', 'printflow-pro' ),
			array(
				'read'                  => true,
				'edit_posts'            => false,
				'upload_files'          => true,
				'manage_printflow'      => true,
				'pfp_view_dashboard'    => true,
				'pfp_manage_files'      => true,
				'pfp_manage_production' => true,
			)
		);

		// Production Staff.
		add_role(
			'pfp_production_staff',
			__( 'PrintFlow Production', 'printflow-pro' ),
			array(
				'read'                  => true,
				'edit_posts'            => false,
				'manage_printflow'      => true,
				'pfp_view_dashboard'    => true,
				'pfp_manage_production' => true,
			)
		);

		// Accountant.
		add_role(
			'pfp_accountant',
			__( 'PrintFlow Comptable', 'printflow-pro' ),
			array(
				'read'               => true,
				'edit_posts'         => false,
				'manage_printflow'   => true,
				'pfp_view_dashboard' => true,
				'pfp_manage_finance' => true,
				'pfp_view_reports'   => true,
			)
		);

		// Delivery Staff.
		add_role(
			'pfp_delivery_staff',
			__( 'PrintFlow Livreur', 'printflow-pro' ),
			array(
				'read'                => true,
				'edit_posts'          => false,
				'upload_files'        => true,
				'manage_printflow'    => true,
				'pfp_view_dashboard'  => true,
				'pfp_manage_delivery' => true,
			)
		);

		// Add all capabilities to administrator.
		$this->ensure_admin_caps();
	}

	/**
	 * Ensure administrator has all PrintFlow capabilities.
	 */
	private function ensure_admin_caps() {
		$admin = get_role( 'administrator' );
		if ( ! $admin ) {
			return;
		}
		foreach ( $this->capabilities as $cap ) {
			if ( ! $admin->has_cap( $cap ) ) {
				$admin->add_cap( $cap );
			}
		}
	}
}
