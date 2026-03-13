<?php
/**
 * Custom WooCommerce Product Fields for PrintFlow Pro.
 *
 * @package PrintFlowPro
 * @subpackage WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PFP_WC_Product_Fields
 *
 * Adds printing-specific fields to WooCommerce product editor.
 */
class PFP_WC_Product_Fields {

	/**
	 * Initialize hooks.
	 */
	public function init() {
		add_action( 'woocommerce_product_data_panels', array( $this, 'render_product_data_panel' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_fields' ) );
		add_action( 'woocommerce_product_options_general_product_data', array( $this, 'add_general_fields' ) );
	}

	/**
	 * Render the PrintFlow product data panel.
	 */
	public function render_product_data_panel() {
		global $post;
		$product_id = $post->ID;
		?>
		<div id="printflow_product_data" class="panel woocommerce_options_panel">
			<div class="options_group">
				<h4 style="padding-left: 12px;"><?php esc_html_e( 'Options d\'impression', 'printflow-pro' ); ?></h4>

				<?php
				woocommerce_wp_select( array(
					'id'      => '_pfp_print_method',
					'label'   => __( 'Méthode d\'impression', 'printflow-pro' ),
					'options' => array(
						''          => __( 'Sélectionner...', 'printflow-pro' ),
						'offset'    => __( 'Offset', 'printflow-pro' ),
						'digital'   => __( 'Numérique', 'printflow-pro' ),
						'large'     => __( 'Grand format', 'printflow-pro' ),
						'screen'    => __( 'Sérigraphie', 'printflow-pro' ),
						'sublimation' => __( 'Sublimation', 'printflow-pro' ),
					),
					'value'   => get_post_meta( $product_id, '_pfp_print_method', true ),
				) );

				woocommerce_wp_text_input( array(
					'id'          => '_pfp_min_quantity',
					'label'       => __( 'Quantité minimale', 'printflow-pro' ),
					'type'        => 'number',
					'value'       => get_post_meta( $product_id, '_pfp_min_quantity', true ),
					'custom_attributes' => array( 'min' => '1', 'step' => '1' ),
				) );

				woocommerce_wp_text_input( array(
					'id'          => '_pfp_production_days',
					'label'       => __( 'Délai de production (jours)', 'printflow-pro' ),
					'type'        => 'number',
					'value'       => get_post_meta( $product_id, '_pfp_production_days', true ),
					'custom_attributes' => array( 'min' => '1', 'step' => '1' ),
				) );

				woocommerce_wp_checkbox( array(
					'id'    => '_pfp_requires_file',
					'label' => __( 'Fichier requis', 'printflow-pro' ),
					'description' => __( 'Le client doit télécharger un fichier pour ce produit.', 'printflow-pro' ),
					'value' => get_post_meta( $product_id, '_pfp_requires_file', true ),
				) );

				woocommerce_wp_select( array(
					'id'      => '_pfp_paper_type',
					'label'   => __( 'Type de papier par défaut', 'printflow-pro' ),
					'options' => array(
						''          => __( 'Sélectionner...', 'printflow-pro' ),
						'couche_mat'    => __( 'Couché mat', 'printflow-pro' ),
						'couche_bril'   => __( 'Couché brillant', 'printflow-pro' ),
						'offset'        => __( 'Offset', 'printflow-pro' ),
						'kraft'         => __( 'Kraft', 'printflow-pro' ),
						'vinyl'         => __( 'Vinyle', 'printflow-pro' ),
						'canvas'        => __( 'Canvas', 'printflow-pro' ),
					),
					'value'   => get_post_meta( $product_id, '_pfp_paper_type', true ),
				) );

				woocommerce_wp_text_input( array(
					'id'    => '_pfp_dimensions',
					'label' => __( 'Dimensions (LxH cm)', 'printflow-pro' ),
					'value' => get_post_meta( $product_id, '_pfp_dimensions', true ),
					'placeholder' => '21x29.7',
				) );
				?>
			</div>

			<div class="options_group">
				<h4 style="padding-left: 12px;"><?php esc_html_e( 'Options de finition', 'printflow-pro' ); ?></h4>

				<?php
				woocommerce_wp_checkbox( array(
					'id'    => '_pfp_finishing_lamination',
					'label' => __( 'Plastification', 'printflow-pro' ),
					'value' => get_post_meta( $product_id, '_pfp_finishing_lamination', true ),
				) );

				woocommerce_wp_checkbox( array(
					'id'    => '_pfp_finishing_uv',
					'label' => __( 'Vernis UV', 'printflow-pro' ),
					'value' => get_post_meta( $product_id, '_pfp_finishing_uv', true ),
				) );

				woocommerce_wp_checkbox( array(
					'id'    => '_pfp_finishing_folding',
					'label' => __( 'Pliage', 'printflow-pro' ),
					'value' => get_post_meta( $product_id, '_pfp_finishing_folding', true ),
				) );

				woocommerce_wp_checkbox( array(
					'id'    => '_pfp_finishing_binding',
					'label' => __( 'Reliure', 'printflow-pro' ),
					'value' => get_post_meta( $product_id, '_pfp_finishing_binding', true ),
				) );

				woocommerce_wp_checkbox( array(
					'id'    => '_pfp_finishing_cutting',
					'label' => __( 'Découpe', 'printflow-pro' ),
					'value' => get_post_meta( $product_id, '_pfp_finishing_cutting', true ),
				) );
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Save custom product fields.
	 *
	 * @param int $product_id Product ID.
	 */
	public function save_product_fields( $product_id ) {
		$fields = array(
			'_pfp_print_method',
			'_pfp_min_quantity',
			'_pfp_production_days',
			'_pfp_paper_type',
			'_pfp_dimensions',
		);

		foreach ( $fields as $field ) {
			if ( isset( $_POST[ $field ] ) ) {
				update_post_meta( $product_id, $field, sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) );
			}
		}

		$checkboxes = array(
			'_pfp_requires_file',
			'_pfp_finishing_lamination',
			'_pfp_finishing_uv',
			'_pfp_finishing_folding',
			'_pfp_finishing_binding',
			'_pfp_finishing_cutting',
		);

		foreach ( $checkboxes as $cb ) {
			$value = isset( $_POST[ $cb ] ) ? 'yes' : 'no';
			update_post_meta( $product_id, $cb, $value );
		}
	}

	/**
	 * Add general product data fields.
	 */
	public function add_general_fields() {
		echo '<div class="options_group show_if_pfp_print_product show_if_pfp_design_service show_if_pfp_print_bundle">';

		woocommerce_wp_text_input( array(
			'id'          => '_pfp_cost_price',
			'label'       => __( 'Prix de revient', 'printflow-pro' ) . ' (' . get_woocommerce_currency_symbol() . ')',
			'type'        => 'text',
			'data_type'   => 'price',
			'description' => __( 'Coût de production pour le calcul de la marge.', 'printflow-pro' ),
		) );

		echo '</div>';
	}
}
