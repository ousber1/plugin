<?php
/**
 * WooCommerce Email customizations for PrintFlow Pro.
 *
 * @package PrintFlowPro
 * @subpackage WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PFP_WC_Emails
 *
 * Registers custom WooCommerce email notifications for print order workflow.
 */
class PFP_WC_Emails {

	/**
	 * Initialize hooks.
	 */
	public function init() {
		add_filter( 'woocommerce_email_classes', array( $this, 'register_emails' ) );
		add_filter( 'woocommerce_email_actions', array( $this, 'register_email_actions' ) );
		add_action( 'woocommerce_order_status_pfp-file-review', array( $this, 'trigger_file_review_email' ), 10, 2 );
		add_action( 'woocommerce_order_status_pfp-printing', array( $this, 'trigger_printing_email' ), 10, 2 );
		add_action( 'woocommerce_order_status_pfp-ready-delivery', array( $this, 'trigger_ready_delivery_email' ), 10, 2 );
	}

	/**
	 * Register custom email classes.
	 *
	 * @param array $email_classes Existing email classes.
	 * @return array
	 */
	public function register_emails( $email_classes ) {
		return $email_classes;
	}

	/**
	 * Register custom email action hooks.
	 *
	 * @param array $actions Existing email actions.
	 * @return array
	 */
	public function register_email_actions( $actions ) {
		$actions[] = 'woocommerce_order_status_pfp-file-review';
		$actions[] = 'woocommerce_order_status_pfp-designing';
		$actions[] = 'woocommerce_order_status_pfp-printing';
		$actions[] = 'woocommerce_order_status_pfp-finishing';
		$actions[] = 'woocommerce_order_status_pfp-ready-delivery';
		return $actions;
	}

	/**
	 * Send email when order moves to file review.
	 *
	 * @param int      $order_id Order ID.
	 * @param \WC_Order $order   Order object.
	 */
	public function trigger_file_review_email( $order_id, $order ) {
		if ( ! $order ) {
			$order = wc_get_order( $order_id );
		}
		if ( ! $order ) {
			return;
		}

		$this->send_status_notification( $order, 'file-review', array(
			'subject' => __( 'Vos fichiers sont en cours de vérification', 'printflow-pro' ),
			'heading' => __( 'Révision de vos fichiers', 'printflow-pro' ),
			'message' => __( 'Nous avons bien reçu votre commande et vos fichiers sont en cours de vérification par notre équipe.', 'printflow-pro' ),
		) );
	}

	/**
	 * Send email when order moves to printing.
	 *
	 * @param int      $order_id Order ID.
	 * @param \WC_Order $order   Order object.
	 */
	public function trigger_printing_email( $order_id, $order ) {
		if ( ! $order ) {
			$order = wc_get_order( $order_id );
		}
		if ( ! $order ) {
			return;
		}

		$this->send_status_notification( $order, 'printing', array(
			'subject' => __( 'Votre commande est en cours d\'impression', 'printflow-pro' ),
			'heading' => __( 'Impression en cours', 'printflow-pro' ),
			'message' => __( 'Votre commande est maintenant en cours d\'impression. Nous vous tiendrons informé de l\'avancement.', 'printflow-pro' ),
		) );
	}

	/**
	 * Send email when order is ready for delivery.
	 *
	 * @param int      $order_id Order ID.
	 * @param \WC_Order $order   Order object.
	 */
	public function trigger_ready_delivery_email( $order_id, $order ) {
		if ( ! $order ) {
			$order = wc_get_order( $order_id );
		}
		if ( ! $order ) {
			return;
		}

		$this->send_status_notification( $order, 'ready-delivery', array(
			'subject' => __( 'Votre commande est prête pour la livraison', 'printflow-pro' ),
			'heading' => __( 'Commande prête', 'printflow-pro' ),
			'message' => __( 'Votre commande est terminée et prête pour la livraison ou le retrait.', 'printflow-pro' ),
		) );
	}

	/**
	 * Send a status notification email using the order status changed template.
	 *
	 * @param \WC_Order $order   Order object.
	 * @param string    $status  Status slug.
	 * @param array     $args    Email arguments (subject, heading, message).
	 */
	private function send_status_notification( $order, $status, $args ) {
		$mailer  = WC()->mailer();
		$to      = $order->get_billing_email();
		$subject = $args['subject'];
		$content = wc_get_template_html(
			'emails/order-status-changed.php',
			array(
				'order'         => $order,
				'email_heading' => $args['heading'],
				'status_message' => $args['message'],
				'sent_to_admin' => false,
				'plain_text'    => false,
				'email'         => $mailer,
			),
			'',
			PFP_PLUGIN_DIR . 'templates/'
		);

		$headers = 'Content-Type: text/html; charset=UTF-8';
		$mailer->send( $to, $subject, $content, $headers );
	}
}
