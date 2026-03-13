<?php
/**
 * WooCommerce Checkout customizations for PrintFlow Pro.
 *
 * @package PrintFlowPro
 * @subpackage WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PFP_WC_Checkout
 *
 * Handles file uploads during checkout and custom checkout fields.
 */
class PFP_WC_Checkout {

	/**
	 * Initialize hooks.
	 */
	public function init() {
		add_action( 'woocommerce_before_order_notes', array( $this, 'add_checkout_fields' ) );
		add_action( 'woocommerce_checkout_process', array( $this, 'validate_checkout_fields' ) );
		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'save_checkout_fields' ) );
		add_action( 'woocommerce_after_cart_item_name', array( $this, 'display_file_upload_field' ), 10, 2 );
		add_action( 'woocommerce_order_status_changed', array( $this, 'handle_status_transition' ), 10, 4 );
	}

	/**
	 * Add custom fields to checkout form.
	 *
	 * @param \WC_Checkout $checkout Checkout instance.
	 */
	public function add_checkout_fields( $checkout ) {
		echo '<div id="pfp-checkout-fields"><h3>' . esc_html__( 'Informations pour l\'impression', 'printflow-pro' ) . '</h3>';

		woocommerce_form_field( 'pfp_project_name', array(
			'type'     => 'text',
			'class'    => array( 'form-row-wide' ),
			'label'    => __( 'Nom du projet', 'printflow-pro' ),
			'required' => false,
		), $checkout->get_value( 'pfp_project_name' ) );

		woocommerce_form_field( 'pfp_delivery_deadline', array(
			'type'     => 'date',
			'class'    => array( 'form-row-first' ),
			'label'    => __( 'Date de livraison souhaitée', 'printflow-pro' ),
			'required' => false,
		), $checkout->get_value( 'pfp_delivery_deadline' ) );

		woocommerce_form_field( 'pfp_special_instructions', array(
			'type'     => 'textarea',
			'class'    => array( 'form-row-wide' ),
			'label'    => __( 'Instructions spéciales pour l\'impression', 'printflow-pro' ),
			'required' => false,
		), $checkout->get_value( 'pfp_special_instructions' ) );

		echo '</div>';
	}

	/**
	 * Validate checkout fields.
	 */
	public function validate_checkout_fields() {
		$requires_file = false;

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			$product_id = $cart_item['product_id'];
			if ( 'yes' === get_post_meta( $product_id, '_pfp_requires_file', true ) ) {
				$requires_file = true;
				if ( empty( $cart_item['pfp_uploaded_file'] ) ) {
					wc_add_notice(
						sprintf(
							/* translators: %s: product name */
							__( 'Veuillez télécharger un fichier pour « %s ».', 'printflow-pro' ),
							$cart_item['data']->get_name()
						),
						'error'
					);
				}
			}
		}
	}

	/**
	 * Save checkout fields to order meta.
	 *
	 * @param int $order_id Order ID.
	 */
	public function save_checkout_fields( $order_id ) {
		$fields = array( 'pfp_project_name', 'pfp_delivery_deadline', 'pfp_special_instructions' );

		foreach ( $fields as $field ) {
			if ( ! empty( $_POST[ $field ] ) ) {
				update_post_meta( $order_id, '_' . $field, sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) );
			}
		}
	}

	/**
	 * Display file upload field for products that require files.
	 *
	 * @param array $cart_item Cart item data.
	 * @param string $cart_item_key Cart item key.
	 */
	public function display_file_upload_field( $cart_item, $cart_item_key ) {
		$product_id = $cart_item['product_id'];
		if ( 'yes' !== get_post_meta( $product_id, '_pfp_requires_file', true ) ) {
			return;
		}

		if ( 'yes' !== get_option( 'pfp_enable_file_upload', 'yes' ) ) {
			return;
		}

		$accepted = get_option( 'pfp_accepted_file_types', 'pdf,ai,eps,psd,jpg,png,tiff' );
		$accept   = implode( ',', array_map( function ( $ext ) {
			return '.' . trim( $ext );
		}, explode( ',', $accepted ) ) );

		printf(
			'<div class="pfp-file-upload" style="margin-top:8px;"><label>%s</label><input type="file" name="pfp_file_%s" accept="%s" /></div>',
			esc_html__( 'Télécharger votre fichier', 'printflow-pro' ),
			esc_attr( $cart_item_key ),
			esc_attr( $accept )
		);
	}

	/**
	 * Handle order status transitions for the printing workflow.
	 *
	 * @param int      $order_id   Order ID.
	 * @param string   $old_status Old status.
	 * @param string   $new_status New status.
	 * @param \WC_Order $order     Order object.
	 */
	public function handle_status_transition( $order_id, $old_status, $new_status, $order ) {
		if ( 'processing' === $new_status ) {
			$has_print_products = false;
			foreach ( $order->get_items() as $item ) {
				$product_id = $item->get_product_id();
				if ( 'yes' === get_post_meta( $product_id, '_pfp_requires_file', true ) ) {
					$has_print_products = true;
					break;
				}
			}

			if ( $has_print_products ) {
				$order->update_status( 'pfp-file-review', __( 'Commande en attente de révision des fichiers.', 'printflow-pro' ) );
			}
		}
	}
}
