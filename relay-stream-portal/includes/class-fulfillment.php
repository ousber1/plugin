<?php
/**
 * Order -> provisioning bridge.
 *
 * Activation is triggered ONLY by WooCommerce's confirmed-paid state
 * (payment_complete / processing / completed with $order->is_paid()).
 * Thank-you pages and gateway redirects never trigger fulfillment.
 *
 * Refund / cancellation policy:
 *  - Cancelled, failed or fully refunded BEFORE activation: open jobs are cancelled; unredeemed gifts revoked.
 *  - Fully refunded AFTER activation: the provider documents no suspend/shorten operation, so the account
 *    is flagged for staff review (staff may suspend manually and record it).
 *  - Partial refunds: no automatic change; the order is flagged for staff review.
 *  - Gifts: unredeemed -> revoked; redeemed -> staff review.
 *  - Referral rewards: handled by RSP_Referrals according to the configured reversal policy.
 *
 * @package RelayStreamPortal
 */

defined( 'ABSPATH' ) || exit;

/**
 * Fulfillment.
 */
class RSP_Fulfillment {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'woocommerce_payment_complete', array( __CLASS__, 'on_paid' ), 20 );
		add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'on_paid' ), 20 );
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'on_paid' ), 20 );
		add_action( 'woocommerce_order_status_cancelled', array( __CLASS__, 'on_cancelled' ), 20 );
		add_action( 'woocommerce_order_status_failed', array( __CLASS__, 'on_cancelled' ), 20 );
		add_action( 'woocommerce_order_status_refunded', array( __CLASS__, 'on_full_refund' ), 20 );
		add_action( 'woocommerce_order_partially_refunded', array( __CLASS__, 'on_partial_refund' ), 20, 2 );
		add_action( 'rsp_job_succeeded', array( __CLASS__, 'sync_order_status' ), 5 );
		add_action( 'rsp_job_needs_review', array( __CLASS__, 'sync_order_status' ), 5 );
	}

	/**
	 * Provisioning status label.
	 *
	 * @param string $s Status.
	 * @return string
	 */
	public static function status_label( $s ) {
		$l = array(
			'none'         => __( 'Not applicable', 'relay-stream-portal' ),
			'awaiting'     => __( 'Awaiting payment', 'relay-stream-portal' ),
			'pending'      => __( 'Queued', 'relay-stream-portal' ),
			'working'      => __( 'Activating', 'relay-stream-portal' ),
			'succeeded'    => __( 'Activated', 'relay-stream-portal' ),
			'failed'       => __( 'Failed', 'relay-stream-portal' ),
			'needs_review' => __( 'Under review', 'relay-stream-portal' ),
			'gift_issued'  => __( 'Gift code issued', 'relay-stream-portal' ),
			'cancelled'    => __( 'Cancelled', 'relay-stream-portal' ),
		);
		return isset( $l[ $s ] ) ? $l[ $s ] : $s;
	}

	/**
	 * Provisioning status for an order (separate from payment status).
	 *
	 * @param WC_Order $order Order.
	 * @return string
	 */
	public static function order_provisioning_status( $order ) {
		$has_plan = false;
		$all_gift = true;
		foreach ( $order->get_items() as $item ) {
			$s = RSP_Woocommerce::snapshot( $item );
			if ( $s ) {
				$has_plan = true;
				if ( 'gift' !== $s['purpose'] ) {
					$all_gift = false;
				}
			}
		}
		if ( ! $has_plan ) {
			return 'none';
		}
		if ( in_array( $order->get_status(), array( 'cancelled', 'refunded', 'failed' ), true ) ) {
			$js = RSP_Jobs::order_status( $order->get_id() );
			return 'succeeded' === $js ? 'needs_review' : 'cancelled';
		}
		if ( ! $order->is_paid() ) {
			return 'awaiting';
		}
		if ( $all_gift ) {
			return 'gift_issued';
		}
		$js = RSP_Jobs::order_status( $order->get_id() );
		return 'none' === $js ? 'pending' : $js;
	}

	/**
	 * Confirmed payment.
	 *
	 * @param int $order_id Order.
	 */
	public static function on_paid( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || ! $order->is_paid() || 'shop_order' !== $order->get_type() ) {
			return;
		}
		foreach ( $order->get_items() as $item_id => $item ) {
			$snap = RSP_Woocommerce::snapshot( $item );
			if ( ! $snap ) {
				continue;
			}
			self::fulfill_item( $order, $item, $snap );
		}
		self::sync_order_status( null, $order_id );
	}

	/**
	 * Fulfil one item under a MySQL named lock so concurrent payment events serialize.
	 *
	 * @param WC_Order              $order Order.
	 * @param WC_Order_Item_Product $item Item.
	 * @param array                 $snap Snapshot.
	 */
	public static function fulfill_item( $order, $item, array $snap ) {
		global $wpdb;
		$item_id = (int) $item->get_id();
		$lock    = 'rsp_item_' . $item_id;
		if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 15)', $lock ) ) ) {
			return; // Another request is handling this item; the job key keeps it unique anyway.
		}
		try {
			$user_id = (int) $order->get_customer_id();
			$key     = 'item:' . $item_id;

			if ( 'gift' === $snap['purpose'] ) {
				RSP_Gifts::issue( $order, $item, $snap );
				return;
			}
			if ( RSP_Jobs::get_by_key( $key ) ) {
				return; // Duplicate payment event.
			}
			if ( ! $user_id ) {
				RSP_Audit::log( 'order_needs_review', 'order', $order->get_id(), 'Plan purchased without a customer account.', array(), 0 );
				$order->update_meta_data( '_rsp_needs_review', 'Plan purchased as guest; link to a customer manually.' );
				$order->save();
				return;
			}
			$payload = array(
				'months'     => (int) $snap['months'],
				'package'    => (string) $snap['package'],
				'type'       => (string) $snap['type'],
				'plan_label' => (string) $snap['plan_name'],
				'order_id'   => $order->get_id(),
			);

			if ( 'renew' === $snap['purpose'] ) {
				// Revalidate ownership against the order's customer at fulfillment time.
				$acc = RSP_Accounts::get_owned( (string) $snap['account_uuid'], $user_id );
				$job = RSP_Jobs::enqueue(
					$key,
					'renew',
					array(
						'account_id'    => $acc ? (int) $acc->id : 0,
						'user_id'       => $user_id,
						'order_id'      => $order->get_id(),
						'order_item_id' => $item_id,
						'payload'       => $payload,
					)
				);
				if ( ! $acc && $job ) {
					RSP_Jobs::to_review( $job, 'Renewal target account is not owned by the paying customer.' );
				}
			} else {
				$uuid = wc_get_order_item_meta( $item_id, '_rsp_account_uuid', true );
				$acc  = $uuid ? RSP_Accounts::get_by_uuid( $uuid ) : null;
				if ( ! $acc ) {
					$acc = RSP_Accounts::create(
						array(
							'user_id'         => $user_id,
							'nickname'        => isset( $snap['nickname'] ) ? $snap['nickname'] : '',
							'provider_type'   => $snap['type'],
							'status'          => 'pending_activation',
							'plan_label'      => $snap['plan_name'],
							'package_id'      => $snap['package'],
							'source_order_id' => $order->get_id(),
						)
					);
					wc_update_order_item_meta( $item_id, '_rsp_account_uuid', $acc->uuid );
				}
				RSP_Jobs::enqueue(
					$key,
					'create',
					array(
						'account_id'    => (int) $acc->id,
						'user_id'       => $user_id,
						'order_id'      => $order->get_id(),
						'order_item_id' => $item_id,
						'payload'       => $payload,
					)
				);
			}
			$order->add_order_note( __( 'Relay Stream Portal: provisioning queued after confirmed payment.', 'relay-stream-portal' ) );
		} finally {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
		}
	}

	/**
	 * Keep an order meta mirror of provisioning status for admin lists.
	 *
	 * @param object|null $job Job.
	 * @param int|null    $order_id Order.
	 */
	public static function sync_order_status( $job = null, $order_id = null ) {
		$order_id = $order_id ? $order_id : ( $job && ! empty( $job->order_id ) ? (int) $job->order_id : 0 );
		$order    = $order_id ? wc_get_order( $order_id ) : null;
		if ( $order ) {
			$order->update_meta_data( '_rsp_provisioning', self::order_provisioning_status( $order ) );
			$order->save_meta_data();
		}
	}

	/**
	 * Cancelled / failed.
	 *
	 * @param int $order_id Order.
	 */
	public static function on_cancelled( $order_id ) {
		self::unwind( $order_id, 'cancelled' );
	}

	/**
	 * Full refund.
	 *
	 * @param int $order_id Order.
	 */
	public static function on_full_refund( $order_id ) {
		self::unwind( $order_id, 'refunded' );
	}

	/**
	 * Partial refund: flag for review, change nothing automatically.
	 *
	 * @param int $order_id Order.
	 * @param int $refund_id Refund.
	 */
	public static function on_partial_refund( $order_id, $refund_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || ! self::has_plan_items( $order ) ) {
			return;
		}
		$order->update_meta_data( '_rsp_needs_review', __( 'Partial refund issued; decide whether to adjust the account.', 'relay-stream-portal' ) );
		$order->save();
		$order->add_order_note( __( 'Relay Stream Portal: partial refund flagged for staff review. No automatic change was made.', 'relay-stream-portal' ) );
		RSP_Audit::log( 'partial_refund_review', 'order', $order_id, '', array( 'refund_id' => $refund_id ), 0 );
	}

	/**
	 * Has portal items.
	 *
	 * @param WC_Order $order Order.
	 * @return bool
	 */
	private static function has_plan_items( $order ) {
		foreach ( $order->get_items() as $item ) {
			if ( RSP_Woocommerce::snapshot( $item ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Unwind fulfillment for a cancelled/refunded order.
	 *
	 * @param int    $order_id Order.
	 * @param string $why cancelled|refunded.
	 */
	private static function unwind( $order_id, $why ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || ! self::has_plan_items( $order ) ) {
			return;
		}
		$review = array();
		foreach ( RSP_Jobs::for_order( $order_id ) as $job ) {
			if ( in_array( $job->state, array( 'pending', 'needs_review', 'failed' ), true ) ) {
				if ( 'create' === $job->type && 'needs_review' === $job->state && 'send' === $job->step ) {
					$review[] = 'Job #' . $job->id . ' may have created a remote account before the ' . $why . '.';
					continue;
				}
				RSP_Jobs::cancel( $job->id, 'Order ' . $why, 0 );
				if ( $job->account_id && 'create' === $job->type ) {
					$acc = RSP_Accounts::get( $job->account_id );
					if ( $acc && empty( $acc->credentials ) ) {
						RSP_Accounts::update( $acc->id, array( 'status' => 'cancelled' ) );
					}
				}
			} elseif ( 'working' === $job->state ) {
				$review[] = 'Job #' . $job->id . ' was running when the order was ' . $why . '.';
			} elseif ( 'succeeded' === $job->state && 'refunded' === $why ) {
				$review[] = 'Account was already activated by job #' . $job->id . '. The provider API has no suspend operation; adjust manually if required.';
				if ( $job->account_id ) {
					RSP_Accounts::update( $job->account_id, array( 'review_reason' => 'Order #' . $order_id . ' refunded after activation.' ) );
				}
			}
		}
		RSP_Gifts::on_order_unwound( $order, $why );
		RSP_Referrals::on_order_unwound( $order, $why );
		if ( $review ) {
			$order->update_meta_data( '_rsp_needs_review', implode( ' ', $review ) );
			$order->save();
			$order->add_order_note( 'Relay Stream Portal: ' . implode( ' ', $review ) );
		}
		RSP_Audit::log( 'order_unwound', 'order', $order_id, $why, array( 'review' => count( $review ) ), 0 );
		self::sync_order_status( null, $order_id );
	}
}
