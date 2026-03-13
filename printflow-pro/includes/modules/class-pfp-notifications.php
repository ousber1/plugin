<?php
/**
 * Notifications module.
 *
 * @package PrintFlowPro
 */

defined( 'ABSPATH' ) || exit;

class PFP_Notifications {

	public function init() {
		// Listen for events that trigger notifications.
		add_action( 'pfp_order_status_changed', array( $this, 'on_order_status_changed' ), 10, 4 );
		add_action( 'pfp_low_stock_alert', array( $this, 'on_low_stock' ) );

		add_action( 'wp_ajax_pfp_save_notification_template', array( $this, 'ajax_save_template' ) );
		add_action( 'wp_ajax_pfp_get_notification_log', array( $this, 'ajax_get_log' ) );
	}

	/**
	 * Send a notification using a template.
	 *
	 * @param string $event_type Event type.
	 * @param string $recipient  Recipient email.
	 * @param array  $variables  Template variables.
	 * @return bool
	 */
	public function send_notification( $event_type, $recipient, $variables = array() ) {
		global $wpdb;
		$template = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}pfp_notification_templates WHERE event_type = %s AND is_active = 1 AND channel = 'email'",
				$event_type
			),
			ARRAY_A
		);

		if ( ! $template ) {
			return false;
		}

		$subject = $this->replace_variables( $template['subject'], $variables );
		$body    = $this->replace_variables( $template['body'], $variables );

		// Add business name if not set.
		$business_name = get_option( 'pfp_business_name', get_bloginfo( 'name' ) );
		$variables['business_name'] = $business_name;
		$subject = $this->replace_variables( $subject, $variables );
		$body    = $this->replace_variables( $body, $variables );

		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
		$sent    = wp_mail( $recipient, $subject, $body, $headers );

		// Log.
		$wpdb->insert(
			$wpdb->prefix . 'pfp_notification_log',
			array(
				'template_id'   => $template['id'],
				'recipient'     => $recipient,
				'channel'       => 'email',
				'status'        => $sent ? 'sent' : 'failed',
				'error_message' => $sent ? '' : 'wp_mail returned false',
			)
		);

		return $sent;
	}

	/**
	 * Replace template variables.
	 *
	 * @param string $text      Template text.
	 * @param array  $variables Variables.
	 * @return string
	 */
	private function replace_variables( $text, $variables ) {
		foreach ( $variables as $key => $value ) {
			$text = str_replace( '{' . $key . '}', $value, $text );
		}
		return $text;
	}

	/**
	 * Handle order status change notifications.
	 *
	 * @param int      $order_id   Order ID.
	 * @param string   $old_status Old status.
	 * @param string   $new_status New status.
	 * @param WC_Order $order      Order object.
	 */
	public function on_order_status_changed( $order_id, $old_status, $new_status, $order ) {
		$event_map = array(
			'processing' => 'order_confirmed',
			'pfp-ready-delivery' => 'order_ready',
			'completed'  => 'order_delivered',
		);

		if ( ! isset( $event_map[ $new_status ] ) ) {
			return;
		}

		$variables = array(
			'customer_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
			'order_number'  => $order->get_order_number(),
			'order_total'   => $order->get_formatted_order_total(),
		);

		$this->send_notification(
			$event_map[ $new_status ],
			$order->get_billing_email(),
			$variables
		);
	}

	/**
	 * Handle low stock alerts.
	 *
	 * @param array $material Material data.
	 */
	public function on_low_stock( $material ) {
		$admin_email = get_option( 'admin_email' );

		$variables = array(
			'material_name' => $material['name'],
			'material_code' => $material['code'],
			'current_qty'   => $material['quantity'],
			'min_qty'       => $material['min_alert_qty'],
			'unit'          => $material['unit'],
		);

		$this->send_notification( 'low_stock_alert', $admin_email, $variables );
	}

	/**
	 * Get notification templates.
	 *
	 * @return array
	 */
	public function get_templates() {
		global $wpdb;
		return $wpdb->get_results(
			"SELECT * FROM {$wpdb->prefix}pfp_notification_templates ORDER BY event_type",
			ARRAY_A
		);
	}

	// AJAX handlers.

	public function ajax_save_template() {
		check_ajax_referer( 'pfp_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'pfp_manage_notifications' ) ) {
			wp_send_json_error( array( 'message' => 'Accès refusé.' ) );
		}

		$id   = absint( $_POST['id'] ?? 0 );
		$data = array(
			'event_type' => sanitize_text_field( wp_unslash( $_POST['event_type'] ?? '' ) ),
			'channel'    => sanitize_text_field( wp_unslash( $_POST['channel'] ?? 'email' ) ),
			'subject'    => sanitize_text_field( wp_unslash( $_POST['subject'] ?? '' ) ),
			'body'       => sanitize_textarea_field( wp_unslash( $_POST['body'] ?? '' ) ),
			'is_active'  => ! empty( $_POST['is_active'] ) ? 1 : 0,
		);

		global $wpdb;
		if ( $id ) {
			$wpdb->update( $wpdb->prefix . 'pfp_notification_templates', $data, array( 'id' => $id ) );
		} else {
			$wpdb->insert( $wpdb->prefix . 'pfp_notification_templates', $data );
		}

		wp_send_json_success( array( 'message' => 'Modèle enregistré.' ) );
	}

	public function ajax_get_log() {
		check_ajax_referer( 'pfp_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'pfp_manage_notifications' ) ) {
			wp_send_json_error( array( 'message' => 'Accès refusé.' ) );
		}

		global $wpdb;
		$log = $wpdb->get_results(
			"SELECT * FROM {$wpdb->prefix}pfp_notification_log ORDER BY sent_at DESC LIMIT 100",
			ARRAY_A
		);
		wp_send_json_success( $log );
	}
}
