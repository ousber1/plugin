<?php
/**
 * Dynamic Pricing Engine module.
 *
 * Calculates print product prices based on multiple configurable parameters.
 *
 * @package PrintFlowPro
 */

defined( 'ABSPATH' ) || exit;

class PFP_Pricing_Engine {

	/**
	 * Initialize the module.
	 */
	public function init() {
		// Frontend price calculation.
		add_filter( 'woocommerce_product_get_price', array( $this, 'maybe_adjust_price' ), 10, 2 );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'calculate_cart_item_prices' ), 20 );

		// AJAX price calculator.
		add_action( 'wp_ajax_pfp_calculate_price', array( $this, 'ajax_calculate_price' ) );
		add_action( 'wp_ajax_nopriv_pfp_calculate_price', array( $this, 'ajax_calculate_price' ) );

		// Admin pricing management.
		add_action( 'wp_ajax_pfp_save_pricing_rule', array( $this, 'ajax_save_pricing_rule' ) );
		add_action( 'wp_ajax_pfp_delete_pricing_rule', array( $this, 'ajax_delete_pricing_rule' ) );
		add_action( 'wp_ajax_pfp_save_pricing_modifier', array( $this, 'ajax_save_pricing_modifier' ) );
	}

	/**
	 * Calculate price based on selected options.
	 *
	 * @param int   $product_id Product ID.
	 * @param array $options    Selected options.
	 * @return float Calculated price.
	 */
	public function calculate_price( $product_id, $options = array() ) {
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return 0;
		}

		$base_price = (float) $product->get_meta( '_pfp_base_material_cost' );
		if ( $base_price <= 0 ) {
			$base_price = (float) $product->get_regular_price();
		}

		$quantity    = isset( $options['quantity'] ) ? max( 1, (int) $options['quantity'] ) : 1;
		$size        = $options['size'] ?? '';
		$material    = $options['material'] ?? '';
		$sides       = $options['sides'] ?? 'recto';
		$finishing    = $options['finishing'] ?? 'sans';
		$lamination  = $options['lamination'] ?? 'sans';
		$urgency     = $options['urgency'] ?? 'standard';
		$design      = ! empty( $options['design_service'] );

		// Apply size multiplier.
		$size_multiplier = $this->get_modifier_value( 'size', $size );

		// Apply sides multiplier.
		$sides_multiplier = $this->get_modifier_value( 'sides', $sides );

		// Calculate unit cost.
		$unit_cost = $base_price * $size_multiplier * $sides_multiplier;

		// Add finishing cost per unit.
		$finishing_cost = $this->get_modifier_value( 'finishing', $finishing, 'fixed' );
		$unit_cost += $finishing_cost;

		// Add lamination cost per unit.
		$lamination_cost = $this->get_modifier_value( 'lamination', $lamination, 'fixed' );
		$unit_cost += $lamination_cost;

		// Subtotal before discounts.
		$subtotal = $unit_cost * $quantity;

		// Apply quantity tier discount.
		$discount = $this->get_quantity_discount( $product_id, $quantity );
		$subtotal = $subtotal * ( 1 - $discount / 100 );

		// Apply urgency multiplier.
		$urgency_multiplier = $this->get_modifier_value( 'urgency', $urgency );
		$subtotal = $subtotal * $urgency_multiplier;

		// Add design service fee.
		$design_fee = 0;
		if ( $design ) {
			$design_fee = (float) $product->get_meta( '_pfp_design_fee' );
			if ( $design_fee <= 0 ) {
				$design_fee = 150; // Default 150 MAD.
			}
		}

		$total = $subtotal + $design_fee;

		// Apply margin.
		$margin = $this->get_margin( $product_id );
		$total  = $total * ( 1 + $margin / 100 );

		return round( $total, 2 );
	}

	/**
	 * Get modifier value from database.
	 *
	 * @param string $type          Modifier type.
	 * @param string $option_value  Option value.
	 * @param string $modifier_type Expected modifier type (multiplier or fixed).
	 * @return float
	 */
	private function get_modifier_value( $type, $option_value, $modifier_type = 'multiplier' ) {
		if ( empty( $option_value ) || 'sans' === $option_value ) {
			return 'multiplier' === $modifier_type ? 1.0 : 0.0;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'pfp_pricing_modifiers';

		$value = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT modifier_value FROM {$table} WHERE type = %s AND option_value = %s AND modifier_type = %s LIMIT 1",
				$type,
				$option_value,
				$modifier_type
			)
		);

		if ( null !== $value ) {
			return (float) $value;
		}

		return 'multiplier' === $modifier_type ? 1.0 : 0.0;
	}

	/**
	 * Get quantity discount percentage.
	 *
	 * @param int $product_id Product ID.
	 * @param int $quantity   Quantity.
	 * @return float Discount percentage.
	 */
	private function get_quantity_discount( $product_id, $quantity ) {
		global $wpdb;
		$prefix = $wpdb->prefix;

		// First try product-specific rules, then category rules, then global.
		$product    = wc_get_product( $product_id );
		$categories = $product ? $product->get_category_ids() : array();

		// Check for applicable pricing rules.
		$discount = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT pt.discount_percentage
				FROM {$prefix}pfp_pricing_tiers pt
				INNER JOIN {$prefix}pfp_pricing_rules pr ON pt.rule_id = pr.id
				WHERE pr.rule_type = 'quantity_discount'
				AND pr.status = 'active'
				AND pt.min_qty <= %d AND pt.max_qty >= %d
				ORDER BY pr.priority ASC
				LIMIT 1",
				$quantity,
				$quantity
			)
		);

		return $discount ? (float) $discount : 0;
	}

	/**
	 * Get margin percentage for a product.
	 *
	 * @param int $product_id Product ID.
	 * @return float Margin percentage.
	 */
	private function get_margin( $product_id ) {
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return 0;
		}

		// Product-specific margin.
		$margin = $product->get_meta( '_pfp_margin_percentage' );
		if ( '' !== $margin && false !== $margin ) {
			return (float) $margin;
		}

		// Global margin.
		return (float) get_option( 'pfp_default_margin', 30 );
	}

	/**
	 * Maybe adjust product price for PrintFlow products.
	 *
	 * @param string     $price   Product price.
	 * @param WC_Product $product Product object.
	 * @return string
	 */
	public function maybe_adjust_price( $price, $product ) {
		if ( 'yes' !== $product->get_meta( '_pfp_is_printflow_product' ) ) {
			return $price;
		}
		// On catalog pages, show base price. Dynamic pricing shown via JS on product page.
		return $price;
	}

	/**
	 * Calculate cart item prices with print options.
	 *
	 * @param WC_Cart $cart Cart object.
	 */
	public function calculate_cart_item_prices( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
			$product = $cart_item['data'];
			if ( 'yes' !== $product->get_meta( '_pfp_is_printflow_product' ) ) {
				continue;
			}

			$options = isset( $cart_item['pfp_options'] ) ? $cart_item['pfp_options'] : array();
			if ( empty( $options ) ) {
				continue;
			}

			$options['quantity'] = $cart_item['quantity'];
			$calculated_price    = $this->calculate_price( $product->get_id(), $options );

			if ( $calculated_price > 0 ) {
				// Set per-unit price.
				$unit_price = $calculated_price / $cart_item['quantity'];
				$cart_item['data']->set_price( $unit_price );
			}
		}
	}

	/**
	 * AJAX handler: calculate price.
	 */
	public function ajax_calculate_price() {
		check_ajax_referer( 'pfp_pricing_nonce', 'nonce' );

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		if ( ! $product_id ) {
			wp_send_json_error( array( 'message' => 'Produit invalide.' ) );
		}

		$options = array(
			'quantity'       => isset( $_POST['quantity'] ) ? absint( $_POST['quantity'] ) : 1,
			'size'           => isset( $_POST['size'] ) ? sanitize_text_field( wp_unslash( $_POST['size'] ) ) : '',
			'material'       => isset( $_POST['material'] ) ? sanitize_text_field( wp_unslash( $_POST['material'] ) ) : '',
			'sides'          => isset( $_POST['sides'] ) ? sanitize_text_field( wp_unslash( $_POST['sides'] ) ) : 'recto',
			'finishing'      => isset( $_POST['finishing'] ) ? sanitize_text_field( wp_unslash( $_POST['finishing'] ) ) : 'sans',
			'lamination'     => isset( $_POST['lamination'] ) ? sanitize_text_field( wp_unslash( $_POST['lamination'] ) ) : 'sans',
			'urgency'        => isset( $_POST['urgency'] ) ? sanitize_text_field( wp_unslash( $_POST['urgency'] ) ) : 'standard',
			'design_service' => ! empty( $_POST['design_service'] ),
		);

		$price = $this->calculate_price( $product_id, $options );

		wp_send_json_success(
			array(
				'price'           => $price,
				'formatted_price' => number_format( $price, 2, ',', ' ' ) . ' MAD',
				'unit_price'      => $options['quantity'] > 0 ? round( $price / $options['quantity'], 2 ) : $price,
			)
		);
	}

	/**
	 * AJAX handler: save pricing rule.
	 */
	public function ajax_save_pricing_rule() {
		check_ajax_referer( 'pfp_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'pfp_manage_pricing' ) ) {
			wp_send_json_error( array( 'message' => 'Accès refusé.' ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'pfp_pricing_rules';

		$data = array(
			'name'             => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
			'product_category' => sanitize_text_field( wp_unslash( $_POST['product_category'] ?? '' ) ),
			'rule_type'        => sanitize_text_field( wp_unslash( $_POST['rule_type'] ?? '' ) ),
			'conditions'       => wp_json_encode( $_POST['conditions'] ?? array() ),
			'multiplier'       => floatval( $_POST['multiplier'] ?? 1 ),
			'fixed_amount'     => floatval( $_POST['fixed_amount'] ?? 0 ),
			'priority'         => intval( $_POST['priority'] ?? 10 ),
			'status'           => sanitize_text_field( wp_unslash( $_POST['status'] ?? 'active' ) ),
		);

		$rule_id = isset( $_POST['rule_id'] ) ? absint( $_POST['rule_id'] ) : 0;

		if ( $rule_id ) {
			$wpdb->update( $table, $data, array( 'id' => $rule_id ) );
		} else {
			$wpdb->insert( $table, $data );
			$rule_id = $wpdb->insert_id;
		}

		wp_send_json_success( array( 'rule_id' => $rule_id, 'message' => 'Règle enregistrée.' ) );
	}

	/**
	 * AJAX handler: delete pricing rule.
	 */
	public function ajax_delete_pricing_rule() {
		check_ajax_referer( 'pfp_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'pfp_manage_pricing' ) ) {
			wp_send_json_error( array( 'message' => 'Accès refusé.' ) );
		}

		$rule_id = isset( $_POST['rule_id'] ) ? absint( $_POST['rule_id'] ) : 0;
		if ( ! $rule_id ) {
			wp_send_json_error( array( 'message' => 'ID de règle invalide.' ) );
		}

		global $wpdb;
		$prefix = $wpdb->prefix;

		$wpdb->delete( "{$prefix}pfp_pricing_tiers", array( 'rule_id' => $rule_id ) );
		$wpdb->delete( "{$prefix}pfp_pricing_rules", array( 'id' => $rule_id ) );

		wp_send_json_success( array( 'message' => 'Règle supprimée.' ) );
	}

	/**
	 * AJAX handler: save pricing modifier.
	 */
	public function ajax_save_pricing_modifier() {
		check_ajax_referer( 'pfp_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'pfp_manage_pricing' ) ) {
			wp_send_json_error( array( 'message' => 'Accès refusé.' ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'pfp_pricing_modifiers';

		$data = array(
			'name'           => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
			'type'           => sanitize_text_field( wp_unslash( $_POST['type'] ?? '' ) ),
			'option_value'   => sanitize_text_field( wp_unslash( $_POST['option_value'] ?? '' ) ),
			'modifier_type'  => sanitize_text_field( wp_unslash( $_POST['modifier_type'] ?? 'multiplier' ) ),
			'modifier_value' => floatval( $_POST['modifier_value'] ?? 1 ),
		);

		$modifier_id = isset( $_POST['modifier_id'] ) ? absint( $_POST['modifier_id'] ) : 0;

		if ( $modifier_id ) {
			$wpdb->update( $table, $data, array( 'id' => $modifier_id ) );
		} else {
			$wpdb->insert( $table, $data );
			$modifier_id = $wpdb->insert_id;
		}

		wp_send_json_success( array( 'modifier_id' => $modifier_id, 'message' => 'Modificateur enregistré.' ) );
	}

	/**
	 * Get all pricing rules.
	 *
	 * @return array
	 */
	public function get_pricing_rules() {
		global $wpdb;
		return $wpdb->get_results(
			"SELECT * FROM {$wpdb->prefix}pfp_pricing_rules ORDER BY priority ASC",
			ARRAY_A
		);
	}

	/**
	 * Get all pricing modifiers.
	 *
	 * @param string $type Optional filter by type.
	 * @return array
	 */
	public function get_pricing_modifiers( $type = '' ) {
		global $wpdb;
		$table = $wpdb->prefix . 'pfp_pricing_modifiers';

		if ( $type ) {
			return $wpdb->get_results(
				$wpdb->prepare( "SELECT * FROM {$table} WHERE type = %s ORDER BY name", $type ),
				ARRAY_A
			);
		}

		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY type, name", ARRAY_A );
	}
}
