<?php
/**
 * WooCommerce integration: plan products, portal checkout handoff, snapshots.
 *
 * Uses only WooCommerce CRUD/order APIs so it works with HPOS and legacy storage.
 *
 * @package RelayStreamPortal
 */

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce glue.
 */
class RSP_Woocommerce {

	const SNAPSHOT_KEY = '_rsp_snapshot';

	/**
	 * Hooks.
	 */
	public static function init() {
		// Product admin.
		add_filter( 'woocommerce_product_data_tabs', array( __CLASS__, 'product_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( __CLASS__, 'product_panel' ) );
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'save_product' ) );
		add_action( 'woocommerce_product_after_variable_attributes', array( __CLASS__, 'variation_fields' ), 10, 3 );
		add_action( 'woocommerce_save_product_variation', array( __CLASS__, 'save_variation' ), 10, 2 );

		// Cart / checkout.
		add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'cart_item_display' ), 10, 2 );
		add_action( 'woocommerce_check_cart_items', array( __CLASS__, 'validate_cart' ) );
		add_filter( 'woocommerce_add_to_cart_validation', array( __CLASS__, 'block_direct_add' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'snapshot_line_item' ), 10, 4 );
		add_filter( 'woocommerce_order_item_display_meta_key', array( __CLASS__, 'meta_label' ), 10, 2 );
		add_filter( 'woocommerce_hidden_order_itemmeta', array( __CLASS__, 'hide_meta' ) );
		add_filter( 'woocommerce_checkout_registration_required', array( __CLASS__, 'require_account' ) );

		add_filter( 'woocommerce_checkout_fields', array( __CLASS__, 'minimal_fields' ), 50 );
		add_filter( 'woocommerce_cart_needs_shipping_address', array( __CLASS__, 'no_shipping_for_plans' ) );

		// Portal staff roles need wp-admin even without edit_posts.
		add_filter( 'woocommerce_prevent_admin_access', array( __CLASS__, 'allow_staff_admin' ) );
		add_filter( 'woocommerce_disable_admin_bar', array( __CLASS__, 'allow_staff_admin' ) );
		// Legacy (posts) order storage: translate our meta filter into WP_Query args.
		add_filter( 'woocommerce_order_data_store_cpt_get_orders_query', array( __CLASS__, 'legacy_meta_query' ), 10, 2 );
	}

	/**
	 * Whether the cart contains only portal plans.
	 *
	 * @return bool
	 */
	public static function cart_only_plans() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->is_empty() ) {
			return false;
		}
		foreach ( WC()->cart->get_cart() as $item ) {
			if ( empty( $item['rsp_purpose'] ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Streaming plans are digital: ask only for name, email and (optional) phone.
	 * Disable in Branding if a payment gateway needs the billing address.
	 *
	 * @param array $fields Fields.
	 * @return array
	 */
	public static function minimal_fields( $fields ) {
		if ( ! RSP_Settings::get( 'minimal_billing' ) || ! self::cart_only_plans() ) {
			return $fields;
		}
		foreach ( array( 'billing_company', 'billing_address_1', 'billing_address_2', 'billing_city', 'billing_postcode', 'billing_state', 'billing_country' ) as $k ) {
			unset( $fields['billing'][ $k ] );
		}
		if ( isset( $fields['billing']['billing_phone'] ) ) {
			$fields['billing']['billing_phone']['required'] = false;
		}
		foreach ( array( 'billing_first_name' => 'form-row-first', 'billing_last_name' => 'form-row-last' ) as $k => $cls ) {
			if ( isset( $fields['billing'][ $k ] ) ) {
				$fields['billing'][ $k ]['class'] = array( $cls );
			}
		}
		$fields['shipping'] = array();
		unset( $fields['order']['order_comments'] );
		return $fields;
	}

	/**
	 * No shipping address for streaming plans.
	 *
	 * @param bool $needs Needs.
	 * @return bool
	 */
	public static function no_shipping_for_plans( $needs ) {
		return self::cart_only_plans() ? false : $needs;
	}

	/**
	 * Let portal staff into wp-admin.
	 *
	 * @param bool $prevent Prevent.
	 * @return bool
	 */
	public static function allow_staff_admin( $prevent ) {
		return current_user_can( 'rsp_view_admin' ) ? false : $prevent;
	}

	/**
	 * Whether HPOS is the authoritative order store.
	 *
	 * @return bool
	 */
	public static function hpos() {
		return class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
	}

	/**
	 * Map rsp_meta_query onto the legacy CPT order query.
	 *
	 * @param array $query WP_Query args.
	 * @param array $vars Order query vars.
	 * @return array
	 */
	public static function legacy_meta_query( $query, $vars ) {
		if ( ! empty( $vars['rsp_meta_query'] ) ) {
			$query['meta_query'] = array_merge( isset( $query['meta_query'] ) ? (array) $query['meta_query'] : array(), $vars['rsp_meta_query'] ); // phpcs:ignore WordPress.DB.SlowDBQuery
		}
		return $query;
	}

	/**
	 * Is the product (or variation) a portal plan?
	 *
	 * @param WC_Product|false $product Product.
	 * @return bool
	 */
	public static function is_plan( $product ) {
		if ( ! $product ) {
			return false;
		}
		$parent = $product->get_parent_id() ? wc_get_product( $product->get_parent_id() ) : $product;
		return $parent && 'yes' === $parent->get_meta( '_rsp_is_plan' );
	}

	/**
	 * Plan definition for a product or variation (variation values override parent).
	 *
	 * @param WC_Product $product Product.
	 * @return array
	 */
	public static function plan_data( $product ) {
		$parent = $product->get_parent_id() ? wc_get_product( $product->get_parent_id() ) : $product;
		$get    = static function ( $key, $default = '' ) use ( $product, $parent ) {
			$v = $product->get_meta( $key );
			if ( '' === $v || null === $v ) {
				$v = $parent->get_meta( $key );
			}
			return ( '' === $v || null === $v ) ? $default : $v;
		};
		$months   = max( 1, (int) $get( '_rsp_months', 1 ) );
		$price    = (float) wc_get_price_to_display( $product );
		$baseline = (float) $get( '_rsp_baseline_monthly', 0 );
		$monthly  = $months > 0 ? $price / $months : $price;
		$savings  = ( $baseline > 0 && $months > 1 && $monthly < $baseline ) ? (int) round( ( 1 - $monthly / $baseline ) * 100 ) : 0;
		return array(
			'product_id'   => $product->get_parent_id() ? $product->get_parent_id() : $product->get_id(),
			'variation_id' => $product->get_parent_id() ? $product->get_id() : 0,
			'id'           => $product->get_id(),
			'name'         => $product->get_name(),
			'months'       => $months,
			'package'      => (string) $get( '_rsp_package', '' ),
			'template'     => (string) $get( '_rsp_template', '' ),
			'connections'  => (int) $get( '_rsp_connections', 0 ),
			'type'         => 'mag' === $get( '_rsp_type', 'm3u' ) ? 'mag' : 'm3u',
			'featured'     => 'yes' === $get( '_rsp_featured', 'no' ),
			'badge'        => (string) $get( '_rsp_badge', '' ),
			'order'        => (int) $get( '_rsp_order', 0 ),
			'features'     => array_values( array_filter( array_map( 'trim', preg_split( '/\r?\n/', (string) $get( '_rsp_features', '' ) ) ) ) ),
			'price'        => $price,
			'price_html'   => wc_price( $price ),
			'monthly'      => $monthly,
			'monthly_html' => wc_price( $monthly ),
			'baseline'     => $baseline,
			'savings'      => $savings,
			'giftable'     => 'no' !== $get( '_rsp_giftable', 'yes' ),
			'purchasable'  => $product->is_purchasable() && $product->is_in_stock(),
			'currency'     => get_woocommerce_currency(),
		);
	}

	/**
	 * Available plans sorted by display order.
	 *
	 * @param bool $gift_only Only giftable plans.
	 * @return array[]
	 */
	public static function plans( $gift_only = false ) {
		$products = wc_get_products(
			array(
				'status'     => 'publish',
				'limit'      => 50,
				'meta_key'   => '_rsp_is_plan', // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value' => 'yes', // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		);
		$out = array();
		foreach ( $products as $p ) {
			if ( 'no' === $p->get_meta( '_rsp_available' ) ) {
				continue;
			}
			$items = $p->is_type( 'variable' ) ? array_filter( array_map( 'wc_get_product', $p->get_children() ) ) : array( $p );
			foreach ( $items as $item ) {
				$d = self::plan_data( $item );
				if ( ! $d['purchasable'] || ( $gift_only && ! $d['giftable'] ) ) {
					continue;
				}
				$out[] = $d;
			}
		}
		usort(
			$out,
			static function ( $a, $b ) {
				return $a['order'] === $b['order'] ? $a['months'] - $b['months'] : $a['order'] - $b['order'];
			}
		);
		return $out;
	}

	/**
	 * Server-side handoff from the portal to WooCommerce checkout.
	 * Revalidates ownership and eligibility; never trusts posted account data.
	 *
	 * @param int    $user_id User.
	 * @param int    $product_id Product or variation id.
	 * @param string $purpose new|renew|gift.
	 * @param array  $extra account (uuid), nickname, gift_email, promo.
	 * @return string|WP_Error Checkout URL.
	 */
	public static function start_checkout( $user_id, $product_id, $purpose, array $extra ) {
		if ( ! $user_id ) {
			return new WP_Error( 'rsp_login', __( 'Please sign in first.', 'relay-stream-portal' ) );
		}
		$product = wc_get_product( $product_id );
		if ( ! $product || ! self::is_plan( $product ) || ! $product->is_purchasable() ) {
			return new WP_Error( 'rsp_plan', __( 'That plan is not available.', 'relay-stream-portal' ) );
		}
		$parent = $product->get_parent_id() ? wc_get_product( $product->get_parent_id() ) : $product;
		if ( 'no' === $parent->get_meta( '_rsp_available' ) || 'publish' !== $parent->get_status() || $product->is_type( 'variable' ) ) {
			return new WP_Error( 'rsp_plan', __( 'That plan is not available.', 'relay-stream-portal' ) );
		}
		$data = array( 'rsp_purpose' => $purpose );
		if ( 'renew' === $purpose ) {
			$acc = RSP_Accounts::get_owned( isset( $extra['account'] ) ? (string) $extra['account'] : '', $user_id );
			if ( ! $acc || ! RSP_Accounts::is_renewable( $acc ) ) {
				return new WP_Error( 'rsp_account', __( 'That account cannot be renewed right now.', 'relay-stream-portal' ) );
			}
			$open = RSP_Jobs::open_for_account( (int) $acc->id );
			if ( $open ) {
				return new WP_Error( 'rsp_account', __( 'This account already has an operation in progress.', 'relay-stream-portal' ) );
			}
			$data['rsp_account'] = $acc->uuid;
		} elseif ( 'new' === $purpose ) {
			if ( RSP_Accounts::count_for_user( $user_id ) >= (int) RSP_Settings::get( 'max_accounts', 10 ) ) {
				return new WP_Error( 'rsp_limit', __( 'You reached the maximum number of accounts.', 'relay-stream-portal' ) );
			}
			$data['rsp_nickname'] = isset( $extra['nickname'] ) ? substr( sanitize_text_field( $extra['nickname'] ), 0, 80 ) : '';
		} elseif ( 'gift' === $purpose ) {
			if ( ! RSP_Settings::get( 'gift_enabled' ) || 'no' === $parent->get_meta( '_rsp_giftable' ) ) {
				return new WP_Error( 'rsp_gift', __( 'Gifts are not available for this plan.', 'relay-stream-portal' ) );
			}
			$email = isset( $extra['gift_email'] ) ? sanitize_email( $extra['gift_email'] ) : '';
			if ( $email && ! is_email( $email ) ) {
				return new WP_Error( 'rsp_gift', __( 'Enter a valid recipient email or leave it empty.', 'relay-stream-portal' ) );
			}
			$data['rsp_gift_email'] = $email;
		} else {
			return new WP_Error( 'rsp_purpose', __( 'Invalid request.', 'relay-stream-portal' ) );
		}

		if ( ! WC()->cart ) {
			wc_load_cart();
		}
		// One portal plan per checkout keeps fulfillment unambiguous.
		foreach ( WC()->cart->get_cart() as $key => $item ) {
			if ( isset( $item['rsp_purpose'] ) ) {
				WC()->cart->remove_cart_item( $key );
			}
		}
		$data['rsp_token'] = wp_generate_password( 12, false );
		self::$adding      = true;
		$added             = WC()->cart->add_to_cart( $product->get_parent_id() ? $product->get_parent_id() : $product->get_id(), 1, $product->get_parent_id() ? $product->get_id() : 0, $product->get_parent_id() ? $product->get_attributes() : array(), $data );
		self::$adding = false;
		if ( ! $added ) {
			return new WP_Error( 'rsp_cart', __( 'Could not start checkout. Please try again.', 'relay-stream-portal' ) );
		}
		$promo = isset( $extra['promo'] ) ? wc_format_coupon_code( $extra['promo'] ) : '';
		if ( '' === $promo && WC()->session ) {
			$promo = (string) WC()->session->get( 'rsp_promo', '' );
		}
		if ( '' !== $promo && ! WC()->cart->has_discount( $promo ) ) {
			WC()->cart->apply_coupon( $promo );
		}
		return wc_get_checkout_url();
	}

	/**
	 * Portal plans may only enter the cart through start_checkout (which sets rsp_purpose).
	 *
	 * @param bool $valid Valid.
	 * @param int  $product_id Product.
	 * @return bool
	 */
	public static function block_direct_add( $valid, $product_id ) {
		if ( $valid && ! self::$adding && self::is_plan( wc_get_product( $product_id ) ) ) {
			wc_add_notice( __( 'Choose this plan from your customer portal.', 'relay-stream-portal' ), 'notice' );
			return false;
		}
		return $valid;
	}

	/** @var bool */
	public static $adding = false;

	/**
	 * Revalidate cart lines (ownership may have changed since adding).
	 */
	public static function validate_cart() {
		if ( ! WC()->cart ) {
			return;
		}
		foreach ( WC()->cart->get_cart() as $key => $item ) {
			if ( empty( $item['rsp_purpose'] ) ) {
				continue;
			}
			if ( ! is_user_logged_in() ) {
				wc_add_notice( __( 'Please sign in to purchase a streaming plan.', 'relay-stream-portal' ), 'error' );
				return;
			}
			if ( 'renew' === $item['rsp_purpose'] ) {
				$acc = RSP_Accounts::get_owned( $item['rsp_account'], get_current_user_id() );
				if ( ! $acc || ! RSP_Accounts::is_renewable( $acc ) ) {
					WC()->cart->remove_cart_item( $key );
					wc_add_notice( __( 'The account selected for renewal is no longer available and was removed from your cart.', 'relay-stream-portal' ), 'error' );
				}
			}
		}
	}

	/**
	 * Show purpose in cart.
	 *
	 * @param array $data Data.
	 * @param array $item Cart item.
	 * @return array
	 */
	public static function cart_item_display( $data, $item ) {
		if ( empty( $item['rsp_purpose'] ) ) {
			return $data;
		}
		if ( 'renew' === $item['rsp_purpose'] ) {
			$acc    = RSP_Accounts::get_owned( $item['rsp_account'], get_current_user_id() );
			$data[] = array( 'key' => __( 'Renews', 'relay-stream-portal' ), 'value' => $acc ? esc_html( $acc->nickname ) : '—' );
		} elseif ( 'gift' === $item['rsp_purpose'] ) {
			$data[] = array( 'key' => __( 'Gift', 'relay-stream-portal' ), 'value' => ! empty( $item['rsp_gift_email'] ) ? esc_html( $item['rsp_gift_email'] ) : __( 'Code shown in your portal', 'relay-stream-portal' ) );
		} else {
			$data[] = array( 'key' => __( 'New account', 'relay-stream-portal' ), 'value' => ! empty( $item['rsp_nickname'] ) ? esc_html( $item['rsp_nickname'] ) : __( 'Yes', 'relay-stream-portal' ) );
		}
		$data[] = array( 'key' => __( 'Renewal', 'relay-stream-portal' ), 'value' => __( 'One-time payment, no auto-renewal', 'relay-stream-portal' ) );
		return $data;
	}

	/**
	 * Store an immutable snapshot of plan + package mapping on the order item.
	 *
	 * @param WC_Order_Item_Product $item Item.
	 * @param string                $cart_key Key.
	 * @param array                 $values Cart values.
	 * @param WC_Order              $order Order.
	 */
	public static function snapshot_line_item( $item, $cart_key, $values, $order ) {
		if ( empty( $values['rsp_purpose'] ) ) {
			return;
		}
		$product = $item->get_product();
		if ( ! $product || ! self::is_plan( $product ) ) {
			return;
		}
		if ( $item->get_meta( self::SNAPSHOT_KEY ) ) {
			return; // Never overwrite an existing snapshot.
		}
		$plan     = self::plan_data( $product );
		$snapshot = array(
			'v'            => 1,
			'purpose'      => in_array( $values['rsp_purpose'], array( 'new', 'renew', 'gift' ), true ) ? $values['rsp_purpose'] : 'new',
			'product_id'   => $plan['product_id'],
			'variation_id' => $plan['variation_id'],
			'plan_name'    => $plan['name'],
			'months'       => $plan['months'],
			'package'      => $plan['package'],
			'template'     => $plan['template'],
			'connections'  => $plan['connections'],
			'type'         => $plan['type'],
			'unit_price'   => $plan['price'],
			'currency'     => $order->get_currency() ? $order->get_currency() : get_woocommerce_currency(),
			'account_uuid' => 'renew' === $values['rsp_purpose'] && ! empty( $values['rsp_account'] ) ? $values['rsp_account'] : '',
			'nickname'     => isset( $values['rsp_nickname'] ) ? $values['rsp_nickname'] : '',
			'gift_email'   => isset( $values['rsp_gift_email'] ) ? $values['rsp_gift_email'] : '',
			'provider'     => RSP_Provider_Factory::active_slug(),
			'captured_at'  => RSP_Installer::now(),
		);
		$item->add_meta_data( self::SNAPSHOT_KEY, wp_json_encode( $snapshot ), true );
		$item->add_meta_data( '_rsp_purpose', $snapshot['purpose'], true );
		$order->update_meta_data( '_rsp_has_plan', 'yes' );
	}

	/**
	 * Read a snapshot from an item.
	 *
	 * @param WC_Order_Item $item Item.
	 * @return array|null
	 */
	public static function snapshot( $item ) {
		$raw = $item->get_meta( self::SNAPSHOT_KEY );
		$s   = $raw ? json_decode( $raw, true ) : null;
		return is_array( $s ) ? $s : null;
	}

	/**
	 * Hide internal meta in order screens.
	 *
	 * @param array $hidden Keys.
	 * @return array
	 */
	public static function hide_meta( $hidden ) {
		return array_merge( $hidden, array( self::SNAPSHOT_KEY, '_rsp_account_uuid' ) );
	}

	/**
	 * Labels for visible meta.
	 *
	 * @param string $key Key.
	 * @param object $meta Meta.
	 * @return string
	 */
	public static function meta_label( $key, $meta ) {
		return '_rsp_purpose' === $key ? __( 'Portal purpose', 'relay-stream-portal' ) : $key;
	}

	/**
	 * Plans require a WordPress account at checkout.
	 *
	 * @param bool $required Required.
	 * @return bool
	 */
	public static function require_account( $required ) {
		if ( WC()->cart ) {
			foreach ( WC()->cart->get_cart() as $item ) {
				if ( ! empty( $item['rsp_purpose'] ) ) {
					return true;
				}
			}
		}
		return $required;
	}

	/**
	 * Customer orders (HPOS-safe).
	 *
	 * @param int    $user_id User.
	 * @param array  $args status, page, per_page, hide_stale.
	 * @return array orders, total, pages.
	 */
	public static function customer_orders( $user_id, array $args = array() ) {
		$per   = isset( $args['per_page'] ) ? max( 1, min( 50, (int) $args['per_page'] ) ) : 10;
		$page  = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;
		$query = array(
			'customer_id' => (int) $user_id,
			'limit'       => $per,
			'page'        => $page,
			'paginate'    => true,
			'orderby'     => 'date',
			'order'       => 'DESC',
			'type'        => 'shop_order',
		);
		if ( ! empty( $args['status'] ) && 'all' !== $args['status'] ) {
			$query['status'] = array( 'wc-' . sanitize_key( $args['status'] ) );
		}
		if ( ! empty( $args['hide_stale'] ) ) {
			$statuses = array_diff( array_keys( wc_get_order_statuses() ), array( 'wc-cancelled', 'wc-checkout-draft' ) );
			if ( empty( $query['status'] ) ) {
				$query['status'] = array_values( $statuses );
			}
			$hours = max( 1, (int) RSP_Settings::get( 'stale_unpaid_hours', 48 ) );
			// Unpaid orders older than the payment window are hidden after fetch (gateway-agnostic).
			$query['limit']    = 200;
			$query['page']     = 1;
			$result            = wc_get_orders( $query );
			$orders            = array_values(
				array_filter(
					$result->orders,
					static function ( $o ) use ( $hours ) {
						return ! ( in_array( $o->get_status(), array( 'pending', 'failed' ), true ) && $o->get_date_created() && $o->get_date_created()->getTimestamp() < time() - $hours * HOUR_IN_SECONDS );
					}
				)
			);
			$total = count( $orders );
			return array(
				'orders' => array_slice( $orders, ( $page - 1 ) * $per, $per ),
				'total'  => $total,
				'pages'  => max( 1, (int) ceil( $total / $per ) ),
			);
		}
		$result = wc_get_orders( $query );
		return array( 'orders' => $result->orders, 'total' => (int) $result->total, 'pages' => max( 1, (int) $result->max_num_pages ) );
	}

	/**
	 * Available gateway titles.
	 *
	 * @return string[]
	 */
	public static function gateway_titles() {
		$out = array();
		if ( function_exists( 'WC' ) && WC()->payment_gateways() ) {
			foreach ( WC()->payment_gateways()->payment_gateways() as $gw ) {
				if ( 'yes' === $gw->enabled ) {
					$out[ $gw->id ] = wp_strip_all_tags( $gw->get_title() );
				}
			}
		}
		return $out;
	}

	/* ---------- Product admin ---------- */

	/**
	 * Tab.
	 *
	 * @param array $tabs Tabs.
	 * @return array
	 */
	public static function product_tab( $tabs ) {
		$tabs['rsp_plan'] = array(
			'label'    => __( 'Streaming plan', 'relay-stream-portal' ),
			'target'   => 'rsp_plan_data',
			'class'    => array(),
			'priority' => 21,
		);
		return $tabs;
	}

	/**
	 * Cached provider packages.
	 *
	 * @return array
	 */
	public static function package_options() {
		$cache = get_option( 'rsp_packages_cache', array() );
		return array( '' => __( '— Choose a MegaOTT package —', 'relay-stream-portal' ) ) + ( is_array( $cache ) ? $cache : array() );
	}

	/**
	 * Panel.
	 */
	public static function product_panel() {
		global $product_object;
		echo '<div id="rsp_plan_data" class="panel woocommerce_options_panel hidden">';
		echo '<div class="options_group">';
		woocommerce_wp_checkbox( array( 'id' => '_rsp_is_plan', 'label' => __( 'Portal plan', 'relay-stream-portal' ), 'description' => __( 'Sell this product as a streaming plan in the customer portal.', 'relay-stream-portal' ), 'value' => $product_object->get_meta( '_rsp_is_plan' ) ) );
		woocommerce_wp_checkbox( array( 'id' => '_rsp_available', 'label' => __( 'Available', 'relay-stream-portal' ), 'value' => $product_object->get_meta( '_rsp_available' ) ? $product_object->get_meta( '_rsp_available' ) : 'yes', 'cbvalue' => 'yes' ) );
		woocommerce_wp_select( array( 'id' => '_rsp_months', 'label' => __( 'Duration (months)', 'relay-stream-portal' ), 'options' => array( '1' => '1', '3' => '3', '6' => '6', '12' => '12' ), 'value' => $product_object->get_meta( '_rsp_months' ), 'desc_tip' => true, 'description' => __( 'The MegaOTT package defines the real period; keep this equal to it (used for display and monthly price).', 'relay-stream-portal' ) ) );
		woocommerce_wp_select( array( 'id' => '_rsp_package', 'label' => __( 'Provider package', 'relay-stream-portal' ), 'options' => self::package_options() + ( $product_object->get_meta( '_rsp_package' ) ? array( $product_object->get_meta( '_rsp_package' ) => $product_object->get_meta( '_rsp_package' ) ) : array() ), 'value' => $product_object->get_meta( '_rsp_package' ), 'desc_tip' => true, 'description' => __( 'Load packages in Relay Stream → API Settings first.', 'relay-stream-portal' ) ) );
		woocommerce_wp_text_input( array( 'id' => '_rsp_template', 'label' => __( 'Bouquet template ID (MegaOTT, optional)', 'relay-stream-portal' ), 'value' => $product_object->get_meta( '_rsp_template' ), 'desc_tip' => true, 'description' => __( 'Must be a template valid for the package.', 'relay-stream-portal' ) ) );
		woocommerce_wp_text_input( array( 'id' => '_rsp_connections', 'label' => __( 'Connections (MegaOTT)', 'relay-stream-portal' ), 'type' => 'number', 'value' => $product_object->get_meta( '_rsp_connections' ), 'desc_tip' => true, 'description' => __( 'Simultaneous connections for new lines (0 = provider default). Bounded by the package maximum.', 'relay-stream-portal' ) ) );
		woocommerce_wp_select( array( 'id' => '_rsp_type', 'label' => __( 'Account type', 'relay-stream-portal' ), 'options' => array( 'm3u' => 'M3U / Xtream', 'mag' => 'MAG (MAC address)' ), 'value' => $product_object->get_meta( '_rsp_type' ) ) );
		echo '</div><div class="options_group">';
		woocommerce_wp_checkbox( array( 'id' => '_rsp_featured', 'label' => __( 'Featured', 'relay-stream-portal' ), 'value' => $product_object->get_meta( '_rsp_featured' ) ) );
		woocommerce_wp_text_input( array( 'id' => '_rsp_badge', 'label' => __( 'Badge text', 'relay-stream-portal' ), 'value' => $product_object->get_meta( '_rsp_badge' ), 'placeholder' => __( 'Best value', 'relay-stream-portal' ) ) );
		woocommerce_wp_text_input( array( 'id' => '_rsp_order', 'label' => __( 'Display order', 'relay-stream-portal' ), 'type' => 'number', 'value' => $product_object->get_meta( '_rsp_order' ) ) );
		woocommerce_wp_text_input( array( 'id' => '_rsp_baseline_monthly', 'label' => __( 'Savings baseline (per month)', 'relay-stream-portal' ), 'data_type' => 'price', 'value' => $product_object->get_meta( '_rsp_baseline_monthly' ), 'desc_tip' => true, 'description' => __( 'Savings are shown only when this is set and higher than the monthly equivalent.', 'relay-stream-portal' ) ) );
		woocommerce_wp_textarea_input( array( 'id' => '_rsp_features', 'label' => __( 'Features (one per line)', 'relay-stream-portal' ), 'value' => $product_object->get_meta( '_rsp_features' ) ) );
		woocommerce_wp_checkbox( array( 'id' => '_rsp_giftable', 'label' => __( 'Can be gifted', 'relay-stream-portal' ), 'value' => $product_object->get_meta( '_rsp_giftable' ) ? $product_object->get_meta( '_rsp_giftable' ) : 'yes', 'cbvalue' => 'yes' ) );
		echo '</div></div>';
	}

	/**
	 * Save product fields (WooCommerce verifies its own nonce before this action).
	 *
	 * @param WC_Product $product Product.
	 */
	public static function save_product( $product ) {
		if ( ! current_user_can( 'edit_product', $product->get_id() ) ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$product->update_meta_data( '_rsp_is_plan', isset( $_POST['_rsp_is_plan'] ) ? 'yes' : 'no' );
		$product->update_meta_data( '_rsp_available', isset( $_POST['_rsp_available'] ) ? 'yes' : 'no' );
		$product->update_meta_data( '_rsp_featured', isset( $_POST['_rsp_featured'] ) ? 'yes' : 'no' );
		$product->update_meta_data( '_rsp_giftable', isset( $_POST['_rsp_giftable'] ) ? 'yes' : 'no' );
		$months = isset( $_POST['_rsp_months'] ) ? (int) $_POST['_rsp_months'] : 1;
		$product->update_meta_data( '_rsp_months', in_array( $months, array( 1, 3, 6, 12 ), true ) ? $months : 1 );
		$product->update_meta_data( '_rsp_package', isset( $_POST['_rsp_package'] ) ? preg_replace( '/[^0-9]/', '', wp_unslash( $_POST['_rsp_package'] ) ) : '' );
		$product->update_meta_data( '_rsp_template', isset( $_POST['_rsp_template'] ) ? preg_replace( '/[^0-9]/', '', wp_unslash( $_POST['_rsp_template'] ) ) : '' );
		$product->update_meta_data( '_rsp_connections', isset( $_POST['_rsp_connections'] ) ? max( 0, (int) $_POST['_rsp_connections'] ) : 0 );
		$product->update_meta_data( '_rsp_type', isset( $_POST['_rsp_type'] ) && 'mag' === $_POST['_rsp_type'] ? 'mag' : 'm3u' );
		$product->update_meta_data( '_rsp_badge', isset( $_POST['_rsp_badge'] ) ? sanitize_text_field( wp_unslash( $_POST['_rsp_badge'] ) ) : '' );
		$product->update_meta_data( '_rsp_order', isset( $_POST['_rsp_order'] ) ? (int) $_POST['_rsp_order'] : 0 );
		$product->update_meta_data( '_rsp_baseline_monthly', isset( $_POST['_rsp_baseline_monthly'] ) ? wc_format_decimal( wp_unslash( $_POST['_rsp_baseline_monthly'] ) ) : '' );
		$product->update_meta_data( '_rsp_features', isset( $_POST['_rsp_features'] ) ? sanitize_textarea_field( wp_unslash( $_POST['_rsp_features'] ) ) : '' );
		// phpcs:enable
	}

	/**
	 * Variation fields.
	 *
	 * @param int     $loop Index.
	 * @param array   $data Data.
	 * @param WP_Post $variation Variation post.
	 */
	public static function variation_fields( $loop, $data, $variation ) {
		$v = wc_get_product( $variation->ID );
		woocommerce_wp_select( array( 'id' => "_rsp_months_{$loop}", 'name' => "_rsp_months[{$loop}]", 'label' => __( 'Plan duration (months)', 'relay-stream-portal' ), 'options' => array( '' => __( 'Inherit', 'relay-stream-portal' ), '1' => '1', '3' => '3', '6' => '6', '12' => '12' ), 'value' => $v->get_meta( '_rsp_months' ), 'wrapper_class' => 'form-row form-row-first' ) );
		woocommerce_wp_text_input( array( 'id' => "_rsp_package_{$loop}", 'name' => "_rsp_package[{$loop}]", 'label' => __( 'Provider package', 'relay-stream-portal' ), 'value' => $v->get_meta( '_rsp_package' ), 'placeholder' => __( 'Inherit', 'relay-stream-portal' ), 'wrapper_class' => 'form-row form-row-last' ) );
	}

	/**
	 * Save variation fields.
	 *
	 * @param int $variation_id Variation.
	 * @param int $i Index.
	 */
	public static function save_variation( $variation_id, $i ) {
		if ( ! current_user_can( 'edit_product', $variation_id ) ) {
			return;
		}
		check_ajax_referer( 'save-variations', 'security' );
		$v = wc_get_product( $variation_id );
		$m = isset( $_POST['_rsp_months'][ $i ] ) ? (int) $_POST['_rsp_months'][ $i ] : 0;
		$v->update_meta_data( '_rsp_months', in_array( $m, array( 1, 3, 6, 12 ), true ) ? $m : '' );
		$v->update_meta_data( '_rsp_package', isset( $_POST['_rsp_package'][ $i ] ) ? preg_replace( '/[^0-9]/', '', wp_unslash( $_POST['_rsp_package'][ $i ] ) ) : '' );
		$v->save();
	}

	/**
	 * Create demo plans (1/3/6/12 months) for evaluation. Prices are placeholders.
	 *
	 * @return int[] Product ids.
	 */
	public static function create_demo_plans() {
		$defs = array(
			array( 'Relay Monthly', 1, '13.00', 10, '', 'no' ),
			array( 'Relay Quarter', 3, '36.00', 20, '', 'no' ),
			array( 'Relay Half-Year', 6, '66.00', 30, 'Popular', 'yes' ),
			array( 'Relay Annual', 12, '120.00', 40, 'Best value', 'no' ),
		);
		$ids  = array();
		foreach ( $defs as $d ) {
			$p = new WC_Product_Simple();
			$p->set_name( $d[0] );
			$p->set_status( 'publish' );
			$p->set_catalog_visibility( 'hidden' );
			$p->set_virtual( true );
			$p->set_sold_individually( true );
			$p->set_regular_price( $d[2] );
			$p->update_meta_data( '_rsp_is_plan', 'yes' );
			$p->update_meta_data( '_rsp_available', 'yes' );
			$p->update_meta_data( '_rsp_months', $d[1] );
			$p->update_meta_data( '_rsp_package', '' ); // Map to a MegaOTT package ID before going live.
			$p->update_meta_data( '_rsp_type', 'm3u' );
			$p->update_meta_data( '_rsp_order', $d[3] );
			$p->update_meta_data( '_rsp_badge', $d[4] );
			$p->update_meta_data( '_rsp_featured', $d[5] );
			$p->update_meta_data( '_rsp_baseline_monthly', '13.00' );
			$p->update_meta_data( '_rsp_giftable', 'yes' );
			$p->update_meta_data( '_rsp_features', "Full authorized catalog\nWorks on every supported device\nEmail and ticket support" );
			$p->update_meta_data( '_rsp_demo', 'yes' );
			$ids[] = $p->save();
		}
		RSP_Audit::log( 'demo_plans_created', 'product', implode( ',', $ids ) );
		return $ids;
	}
}
