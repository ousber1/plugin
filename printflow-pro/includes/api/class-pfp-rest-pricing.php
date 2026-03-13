<?php
/**
 * REST API Pricing endpoint for PrintFlow Pro.
 *
 * @package PrintFlowPro
 * @subpackage API
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PFP_REST_Pricing
 *
 * Provides pricing calculation endpoints for the frontend configurator.
 */
class PFP_REST_Pricing {

	/**
	 * Register routes.
	 */
	public function register_routes() {
		register_rest_route( PFP_REST_API::API_NAMESPACE, '/pricing/calculate', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'calculate_price' ),
			'permission_callback' => '__return_true',
			'args'                => array(
				'product_id' => array(
					'required'          => true,
					'type'              => 'integer',
					'sanitize_callback' => 'absint',
				),
				'quantity'   => array(
					'required'          => true,
					'type'              => 'integer',
					'sanitize_callback' => 'absint',
				),
				'options'    => array(
					'required' => false,
					'type'     => 'object',
					'default'  => array(),
				),
			),
		) );

		register_rest_route( PFP_REST_API::API_NAMESPACE, '/pricing/quantity-breaks/(?P<product_id>\d+)', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_quantity_breaks' ),
			'permission_callback' => '__return_true',
			'args'                => array(
				'product_id' => array(
					'required'          => true,
					'type'              => 'integer',
					'sanitize_callback' => 'absint',
				),
			),
		) );
	}

	/**
	 * Calculate price based on product options and quantity.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function calculate_price( $request ) {
		$product_id = $request->get_param( 'product_id' );
		$quantity   = $request->get_param( 'quantity' );
		$options    = $request->get_param( 'options' );

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return PFP_REST_API::error( 'invalid_product', __( 'Produit introuvable.', 'printflow-pro' ), 404 );
		}

		$base_price = (float) $product->get_price();
		$total      = $base_price * $quantity;

		// Apply finishing surcharges.
		$finishing_surcharges = array(
			'lamination' => 0.10,
			'uv'         => 0.15,
			'folding'    => 0.05,
			'binding'    => 0.20,
			'cutting'    => 0.08,
		);

		if ( ! empty( $options ) && is_array( $options ) ) {
			foreach ( $options as $option => $enabled ) {
				if ( $enabled && isset( $finishing_surcharges[ $option ] ) ) {
					$total += $total * $finishing_surcharges[ $option ];
				}
			}
		}

		// Apply quantity discount.
		$discount = $this->get_quantity_discount( $product_id, $quantity );
		$total    = $total * ( 1 - $discount );

		return rest_ensure_response( array(
			'product_id'   => $product_id,
			'quantity'     => $quantity,
			'base_price'   => $base_price,
			'unit_price'   => round( $total / $quantity, 2 ),
			'total'        => round( $total, 2 ),
			'discount'     => $discount,
			'currency'     => get_woocommerce_currency(),
		) );
	}

	/**
	 * Get quantity price breaks for a product.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_quantity_breaks( $request ) {
		$product_id = $request->get_param( 'product_id' );
		$product    = wc_get_product( $product_id );

		if ( ! $product ) {
			return PFP_REST_API::error( 'invalid_product', __( 'Produit introuvable.', 'printflow-pro' ), 404 );
		}

		$base_price = (float) $product->get_price();
		$breaks     = $this->get_default_breaks();

		$result = array();
		foreach ( $breaks as $qty => $discount ) {
			$result[] = array(
				'quantity'   => $qty,
				'discount'   => $discount * 100,
				'unit_price' => round( $base_price * ( 1 - $discount ), 2 ),
			);
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Get quantity discount rate.
	 *
	 * @param int $product_id Product ID.
	 * @param int $quantity   Quantity.
	 * @return float Discount as a decimal.
	 */
	private function get_quantity_discount( $product_id, $quantity ) {
		$breaks   = $this->get_default_breaks();
		$discount = 0;

		foreach ( $breaks as $min_qty => $rate ) {
			if ( $quantity >= $min_qty ) {
				$discount = $rate;
			}
		}

		return $discount;
	}

	/**
	 * Get default quantity break thresholds.
	 *
	 * @return array
	 */
	private function get_default_breaks() {
		return apply_filters( 'pfp_quantity_breaks', array(
			100  => 0.05,
			250  => 0.10,
			500  => 0.15,
			1000 => 0.20,
			5000 => 0.25,
		) );
	}
}
