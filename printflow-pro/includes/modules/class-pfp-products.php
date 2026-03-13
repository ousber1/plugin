<?php
/**
 * Product Management module.
 *
 * Handles automatic product creation and print-specific product management.
 *
 * @package PrintFlowPro
 */

defined( 'ABSPATH' ) || exit;

class PFP_Products {

	/**
	 * Initialize the module.
	 */
	public function init() {
		add_action( 'wp_ajax_pfp_generate_products', array( $this, 'ajax_generate_products' ) );
		add_action( 'wp_ajax_pfp_get_product_catalog', array( $this, 'ajax_get_catalog' ) );
	}

	/**
	 * Get the predefined product catalog.
	 *
	 * @return array
	 */
	public function get_product_catalog() {
		$catalog_file = PFP_PLUGIN_DIR . 'includes/data/products-catalog.json';
		if ( ! file_exists( $catalog_file ) ) {
			return array();
		}
		$json = file_get_contents( $catalog_file );
		return json_decode( $json, true ) ?: array();
	}

	/**
	 * Generate products from catalog.
	 *
	 * @param array $product_keys Product keys to generate. Empty for all.
	 * @return array Results with created product IDs.
	 */
	public function generate_products( $product_keys = array() ) {
		$catalog = $this->get_product_catalog();
		$results = array();

		foreach ( $catalog as $key => $product_data ) {
			if ( ! empty( $product_keys ) && ! in_array( $key, $product_keys, true ) ) {
				continue;
			}

			$product_id = $this->create_product( $product_data );
			if ( $product_id ) {
				$results[ $key ] = $product_id;
			}
		}

		return $results;
	}

	/**
	 * Create a single WooCommerce product from catalog data.
	 *
	 * @param array $data Product data from catalog.
	 * @return int|false Product ID or false on failure.
	 */
	public function create_product( $data ) {
		// Check if product with same SKU already exists.
		$existing = wc_get_product_id_by_sku( $data['sku'] );
		if ( $existing ) {
			return $existing;
		}

		// Ensure category exists.
		$category_id = $this->ensure_category( $data['category'] );

		// Create appropriate product type.
		if ( ! empty( $data['attributes'] ) ) {
			$product = new WC_Product_Variable();
		} else {
			$product = new WC_Product_Simple();
		}

		$product->set_name( $data['name'] );
		$product->set_short_description( $data['short_description'] ?? '' );
		$product->set_description( $data['long_description'] ?? '' );
		$product->set_sku( $data['sku'] );
		$product->set_regular_price( $data['base_price'] ?? '' );
		$product->set_category_ids( array( $category_id ) );
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'visible' );
		$product->set_manage_stock( false );
		$product->set_virtual( false );

		// Set print-specific meta.
		$product->update_meta_data( '_pfp_product_type', $data['product_type'] ?? '' );
		$product->update_meta_data( '_pfp_file_upload_required', $data['file_upload'] ?? 'yes' );
		$product->update_meta_data( '_pfp_design_service', $data['design_service'] ?? 'yes' );
		$product->update_meta_data( '_pfp_design_fee', $data['design_fee'] ?? 150 );
		$product->update_meta_data( '_pfp_lead_time', $data['lead_time'] ?? '3-5 jours' );
		$product->update_meta_data( '_pfp_is_printflow_product', 'yes' );

		$product_id = $product->save();

		// Create attributes and variations for variable products.
		if ( $product instanceof WC_Product_Variable && ! empty( $data['attributes'] ) ) {
			$this->create_product_attributes( $product, $data['attributes'] );
			$this->create_product_variations( $product, $data );
		}

		// Map materials if provided.
		if ( ! empty( $data['material_mapping'] ) ) {
			$this->map_materials( $product_id, $data['material_mapping'] );
		}

		return $product_id;
	}

	/**
	 * Ensure a product category exists and return its ID.
	 *
	 * @param string $category_name Category name.
	 * @return int Term ID.
	 */
	private function ensure_category( $category_name ) {
		$term = get_term_by( 'name', $category_name, 'product_cat' );
		if ( $term ) {
			return $term->term_id;
		}

		$result = wp_insert_term( $category_name, 'product_cat' );
		if ( is_wp_error( $result ) ) {
			return 0;
		}
		return $result['term_id'];
	}

	/**
	 * Create product attributes.
	 *
	 * @param WC_Product_Variable $product Product object.
	 * @param array               $attributes Attributes data.
	 */
	private function create_product_attributes( $product, $attributes ) {
		$product_attributes = array();
		$position           = 0;

		foreach ( $attributes as $attr_name => $attr_options ) {
			$attribute = new WC_Product_Attribute();
			$attribute->set_name( $attr_name );
			$attribute->set_options( $attr_options );
			$attribute->set_position( $position );
			$attribute->set_visible( true );
			$attribute->set_variation( true );

			$product_attributes[] = $attribute;
			$position++;
		}

		$product->set_attributes( $product_attributes );
		$product->save();
	}

	/**
	 * Create product variations from first attribute options.
	 *
	 * @param WC_Product_Variable $product Product.
	 * @param array               $data    Product data.
	 */
	private function create_product_variations( $product, $data ) {
		if ( empty( $data['attributes'] ) ) {
			return;
		}

		$attributes = $data['attributes'];
		$first_attr = array_key_first( $attributes );
		$base_price = $data['base_price'] ?? 0;

		// Create one variation per option of the first attribute.
		foreach ( $attributes[ $first_attr ] as $index => $option ) {
			$variation = new WC_Product_Variation();
			$variation->set_parent_id( $product->get_id() );
			$variation->set_attributes( array( sanitize_title( $first_attr ) => $option ) );

			$price_modifier = 1 + ( $index * 0.15 );
			$variation->set_regular_price( round( $base_price * $price_modifier, 2 ) );
			$variation->set_status( 'publish' );
			$variation->save();
		}
	}

	/**
	 * Map materials to a product.
	 *
	 * @param int   $product_id Product ID.
	 * @param array $mappings   Material mapping data.
	 */
	private function map_materials( $product_id, $mappings ) {
		global $wpdb;
		$table = $wpdb->prefix . 'pfp_material_product_map';

		foreach ( $mappings as $mapping ) {
			// Try to find material by code.
			$material_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}pfp_materials WHERE code = %s",
					$mapping['material_code']
				)
			);

			if ( $material_id ) {
				$wpdb->insert(
					$table,
					array(
						'material_id'      => $material_id,
						'product_id'       => $product_id,
						'quantity_per_unit' => $mapping['quantity_per_unit'] ?? 1,
						'unit'             => $mapping['unit'] ?? 'pièce',
					),
					array( '%d', '%d', '%f', '%s' )
				);
			}
		}
	}

	/**
	 * AJAX handler: generate products.
	 */
	public function ajax_generate_products() {
		check_ajax_referer( 'pfp_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'pfp_manage_products' ) ) {
			wp_send_json_error( array( 'message' => 'Accès refusé.' ) );
		}

		$product_keys = isset( $_POST['products'] ) ? array_map( 'sanitize_text_field', $_POST['products'] ) : array();
		$results      = $this->generate_products( $product_keys );

		wp_send_json_success(
			array(
				'message'  => sprintf( '%d produits créés avec succès.', count( $results ) ),
				'products' => $results,
			)
		);
	}

	/**
	 * AJAX handler: get product catalog.
	 */
	public function ajax_get_catalog() {
		check_ajax_referer( 'pfp_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'pfp_manage_products' ) ) {
			wp_send_json_error( array( 'message' => 'Accès refusé.' ) );
		}

		wp_send_json_success( $this->get_product_catalog() );
	}
}
