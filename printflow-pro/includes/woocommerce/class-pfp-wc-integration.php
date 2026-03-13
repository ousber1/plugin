<?php
/**
 * WooCommerce Integration for PrintFlow Pro.
 *
 * Handles core WooCommerce integration including HPOS compatibility,
 * custom product types, and product data tabs.
 *
 * @package PrintFlowPro
 * @subpackage WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PFP_WC_Integration
 *
 * Main WooCommerce integration class that bridges PrintFlow Pro
 * functionality with WooCommerce.
 */
class PFP_WC_Integration {

	/**
	 * Initialize hooks and filters for WooCommerce integration.
	 */
	public function init() {
		// Declare HPOS compatibility.
		add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compatibility' ) );

		// Filter product types to add PrintFlow types.
		add_filter( 'product_type_selector', array( $this, 'add_product_types' ) );

		// Add PrintFlow product data tab.
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'add_product_data_tab' ) );

		// Register custom order statuses support.
		add_action( 'init', array( $this, 'register_custom_order_statuses_support' ), 20 );

		// Add settings section in WooCommerce.
		add_filter( 'woocommerce_get_sections_advanced', array( $this, 'add_settings_section' ) );
		add_filter( 'woocommerce_get_settings_advanced', array( $this, 'get_settings' ), 10, 2 );

		// Enqueue admin scripts for WooCommerce product pages.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

		// Add PrintFlow column to WooCommerce orders list.
		add_filter( 'manage_edit-shop_order_columns', array( $this, 'add_order_columns' ) );
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'render_order_columns' ), 10, 2 );

		// HPOS-compatible order columns.
		add_filter( 'manage_woocommerce_page_wc-orders_columns', array( $this, 'add_order_columns' ) );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( $this, 'render_order_columns' ), 10, 2 );
	}

	/**
	 * Declare compatibility with WooCommerce High-Performance Order Storage (HPOS).
	 */
	public function declare_hpos_compatibility() {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				PFP_PLUGIN_FILE,
				true
			);
		}
	}

	/**
	 * Add PrintFlow product types to WooCommerce product type selector.
	 *
	 * @param array $types Existing product types.
	 * @return array Modified product types.
	 */
	public function add_product_types( $types ) {
		$types['pfp_print_product']  = __( 'Produit d\'impression', 'printflow-pro' );
		$types['pfp_design_service'] = __( 'Service de design', 'printflow-pro' );
		$types['pfp_print_bundle']   = __( 'Pack impression', 'printflow-pro' );

		return $types;
	}

	/**
	 * Add PrintFlow product data tab in the product editor.
	 *
	 * @param array $tabs Existing product data tabs.
	 * @return array Modified product data tabs.
	 */
	public function add_product_data_tab( $tabs ) {
		$tabs['printflow'] = array(
			'label'    => __( 'PrintFlow Pro', 'printflow-pro' ),
			'target'   => 'printflow_product_data',
			'class'    => array( 'show_if_simple', 'show_if_variable', 'show_if_pfp_print_product', 'show_if_pfp_design_service', 'show_if_pfp_print_bundle' ),
			'priority' => 65,
		);

		return $tabs;
	}

	/**
	 * Register support for custom order statuses in the WooCommerce workflow.
	 */
	public function register_custom_order_statuses_support() {
		/**
		 * Allow other plugins/themes to modify the supported custom statuses.
		 *
		 * @param array $statuses Array of custom status slugs.
		 */
		$custom_statuses = apply_filters(
			'pfp_supported_order_statuses',
			array(
				'wc-pfp-file-review',
				'wc-pfp-designing',
				'wc-pfp-printing',
				'wc-pfp-finishing',
				'wc-pfp-ready-delivery',
			)
		);

		// Store for reference by other components.
		update_option( 'pfp_custom_order_statuses', $custom_statuses, false );
	}

	/**
	 * Add PrintFlow settings section to WooCommerce Advanced settings.
	 *
	 * @param array $sections Existing sections.
	 * @return array Modified sections.
	 */
	public function add_settings_section( $sections ) {
		$sections['printflow'] = __( 'PrintFlow Pro', 'printflow-pro' );
		return $sections;
	}

	/**
	 * Get PrintFlow settings for WooCommerce settings page.
	 *
	 * @param array  $settings        Existing settings.
	 * @param string $current_section Current section slug.
	 * @return array Modified settings.
	 */
	public function get_settings( $settings, $current_section ) {
		if ( 'printflow' !== $current_section ) {
			return $settings;
		}

		return array(
			array(
				'title' => __( 'Paramètres PrintFlow Pro', 'printflow-pro' ),
				'type'  => 'title',
				'desc'  => __( 'Configurez l\'intégration de PrintFlow Pro avec WooCommerce.', 'printflow-pro' ),
				'id'    => 'pfp_wc_settings',
			),
			array(
				'title'   => __( 'Activer le téléchargement de fichiers', 'printflow-pro' ),
				'desc'    => __( 'Permettre aux clients de télécharger des fichiers lors de la commande.', 'printflow-pro' ),
				'id'      => 'pfp_enable_file_upload',
				'default' => 'yes',
				'type'    => 'checkbox',
			),
			array(
				'title'   => __( 'Formats de fichiers acceptés', 'printflow-pro' ),
				'desc'    => __( 'Extensions de fichiers séparées par des virgules.', 'printflow-pro' ),
				'id'      => 'pfp_accepted_file_types',
				'default' => 'pdf,ai,eps,psd,jpg,png,tiff',
				'type'    => 'text',
			),
			array(
				'title'   => __( 'Taille maximale de fichier (Mo)', 'printflow-pro' ),
				'desc'    => __( 'Taille maximale autorisée pour les fichiers téléchargés.', 'printflow-pro' ),
				'id'      => 'pfp_max_file_size',
				'default' => '50',
				'type'    => 'number',
				'custom_attributes' => array(
					'min'  => '1',
					'max'  => '500',
					'step' => '1',
				),
			),
			array(
				'title'   => __( 'Devise', 'printflow-pro' ),
				'desc'    => __( 'La devise est gérée par les paramètres WooCommerce (MAD recommandé).', 'printflow-pro' ),
				'id'      => 'pfp_currency_notice',
				'type'    => 'title',
			),
			array(
				'title'   => __( 'Délai de production par défaut (jours)', 'printflow-pro' ),
				'desc'    => __( 'Nombre de jours ouvrables pour la production.', 'printflow-pro' ),
				'id'      => 'pfp_default_lead_time',
				'default' => '3',
				'type'    => 'number',
				'custom_attributes' => array(
					'min'  => '1',
					'max'  => '90',
					'step' => '1',
				),
			),
			array(
				'type' => 'sectionend',
				'id'   => 'pfp_wc_settings',
			),
		);
	}

	/**
	 * Enqueue admin scripts for WooCommerce product edit pages.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 */
	public function enqueue_admin_scripts( $hook_suffix ) {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		if ( in_array( $screen->id, array( 'product', 'edit-product' ), true ) ) {
			wp_enqueue_style(
				'pfp-wc-product-admin',
				PFP_PLUGIN_URL . 'assets/css/admin/wc-product.css',
				array( 'woocommerce_admin_styles' ),
				PFP_VERSION
			);

			wp_enqueue_script(
				'pfp-wc-product-admin',
				PFP_PLUGIN_URL . 'assets/js/admin/wc-product.js',
				array( 'jquery', 'woocommerce_admin' ),
				PFP_VERSION,
				true
			);

			wp_localize_script(
				'pfp-wc-product-admin',
				'pfpWCProduct',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'pfp-wc-product' ),
					'i18n'    => array(
						'confirmDelete' => __( 'Êtes-vous sûr de vouloir supprimer cet élément ?', 'printflow-pro' ),
					),
				)
			);
		}
	}

	/**
	 * Add custom columns to the WooCommerce orders list table.
	 *
	 * @param array $columns Existing columns.
	 * @return array Modified columns.
	 */
	public function add_order_columns( $columns ) {
		$new_columns = array();

		foreach ( $columns as $key => $column ) {
			$new_columns[ $key ] = $column;

			if ( 'order_status' === $key ) {
				$new_columns['pfp_production_status'] = __( 'Statut production', 'printflow-pro' );
			}
		}

		return $new_columns;
	}

	/**
	 * Render content for custom order columns.
	 *
	 * @param string $column  Column identifier.
	 * @param int    $post_id Order post ID or order ID.
	 */
	public function render_order_columns( $column, $post_id ) {
		if ( 'pfp_production_status' !== $column ) {
			return;
		}

		$order = wc_get_order( $post_id );
		if ( ! $order ) {
			return;
		}

		$production_status = $order->get_meta( '_pfp_production_status' );
		if ( $production_status ) {
			printf(
				'<span class="pfp-production-badge pfp-status-%s">%s</span>',
				esc_attr( $production_status ),
				esc_html( $this->get_production_status_label( $production_status ) )
			);
		} else {
			echo '<span class="pfp-production-badge pfp-status-none">&mdash;</span>';
		}
	}

	/**
	 * Get human-readable label for a production status.
	 *
	 * @param string $status Production status key.
	 * @return string Translated status label.
	 */
	private function get_production_status_label( $status ) {
		$labels = array(
			'queued'     => __( 'En file d\'attente', 'printflow-pro' ),
			'in_review'  => __( 'En révision', 'printflow-pro' ),
			'designing'  => __( 'En design', 'printflow-pro' ),
			'printing'   => __( 'En impression', 'printflow-pro' ),
			'finishing'   => __( 'En finition', 'printflow-pro' ),
			'quality_check' => __( 'Contrôle qualité', 'printflow-pro' ),
			'ready'      => __( 'Prêt', 'printflow-pro' ),
		);

		return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
	}
}
