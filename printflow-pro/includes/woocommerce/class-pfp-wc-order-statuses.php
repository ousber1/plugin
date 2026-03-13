<?php
/**
 * Custom WooCommerce Order Statuses for PrintFlow Pro.
 *
 * @package PrintFlowPro
 * @subpackage WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PFP_WC_Order_Statuses
 *
 * Registers and manages custom order statuses for the printing workflow.
 */
class PFP_WC_Order_Statuses {

	/**
	 * Initialize hooks.
	 */
	public function init() {
		add_action( 'init', array( $this, 'register_order_statuses' ) );
		add_filter( 'wc_order_statuses', array( $this, 'add_order_statuses' ) );
		add_filter( 'woocommerce_valid_order_statuses_for_payment', array( $this, 'valid_statuses_for_payment' ) );
		add_filter( 'woocommerce_valid_order_statuses_for_cancel', array( $this, 'valid_statuses_for_cancel' ) );
		add_action( 'admin_head', array( $this, 'order_status_styles' ) );
	}

	/**
	 * Get custom order statuses.
	 *
	 * @return array
	 */
	public function get_custom_statuses() {
		return array(
			'wc-pfp-file-review'    => array(
				'label'       => _x( 'Révision fichier', 'Order status', 'printflow-pro' ),
				'label_count' => _n_noop(
					'Révision fichier <span class="count">(%s)</span>',
					'Révision fichier <span class="count">(%s)</span>',
					'printflow-pro'
				),
			),
			'wc-pfp-designing'      => array(
				'label'       => _x( 'En design', 'Order status', 'printflow-pro' ),
				'label_count' => _n_noop(
					'En design <span class="count">(%s)</span>',
					'En design <span class="count">(%s)</span>',
					'printflow-pro'
				),
			),
			'wc-pfp-printing'       => array(
				'label'       => _x( 'En impression', 'Order status', 'printflow-pro' ),
				'label_count' => _n_noop(
					'En impression <span class="count">(%s)</span>',
					'En impression <span class="count">(%s)</span>',
					'printflow-pro'
				),
			),
			'wc-pfp-finishing'      => array(
				'label'       => _x( 'En finition', 'Order status', 'printflow-pro' ),
				'label_count' => _n_noop(
					'En finition <span class="count">(%s)</span>',
					'En finition <span class="count">(%s)</span>',
					'printflow-pro'
				),
			),
			'wc-pfp-ready-delivery' => array(
				'label'       => _x( 'Prêt à livrer', 'Order status', 'printflow-pro' ),
				'label_count' => _n_noop(
					'Prêt à livrer <span class="count">(%s)</span>',
					'Prêt à livrer <span class="count">(%s)</span>',
					'printflow-pro'
				),
			),
		);
	}

	/**
	 * Register custom order statuses with WordPress.
	 */
	public function register_order_statuses() {
		foreach ( $this->get_custom_statuses() as $status => $args ) {
			register_post_status( $status, array(
				'label'                     => $args['label'],
				'public'                    => true,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				'label_count'               => $args['label_count'],
			) );
		}
	}

	/**
	 * Add custom statuses to WooCommerce order statuses list.
	 *
	 * @param array $statuses Existing order statuses.
	 * @return array
	 */
	public function add_order_statuses( $statuses ) {
		$custom = $this->get_custom_statuses();
		foreach ( $custom as $status => $args ) {
			$statuses[ $status ] = $args['label'];
		}
		return $statuses;
	}

	/**
	 * Allow payment for certain custom statuses.
	 *
	 * @param array $statuses Valid statuses for payment.
	 * @return array
	 */
	public function valid_statuses_for_payment( $statuses ) {
		$statuses[] = 'pfp-file-review';
		return $statuses;
	}

	/**
	 * Allow cancellation from certain custom statuses.
	 *
	 * @param array $statuses Valid statuses for cancel.
	 * @return array
	 */
	public function valid_statuses_for_cancel( $statuses ) {
		$statuses[] = 'pfp-file-review';
		$statuses[] = 'pfp-designing';
		return $statuses;
	}

	/**
	 * Output CSS for custom order status badges in admin.
	 */
	public function order_status_styles() {
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->id, array( 'edit-shop_order', 'woocommerce_page_wc-orders' ), true ) ) {
			return;
		}
		?>
		<style>
			.order-status.status-pfp-file-review { background: #f8dda7; color: #94660c; }
			.order-status.status-pfp-designing { background: #c8d7e1; color: #2e4453; }
			.order-status.status-pfp-printing { background: #c6e1c6; color: #5b841b; }
			.order-status.status-pfp-finishing { background: #d7cad2; color: #6b3a5d; }
			.order-status.status-pfp-ready-delivery { background: #c6e1c6; color: #2e7d32; }
		</style>
		<?php
	}
}
