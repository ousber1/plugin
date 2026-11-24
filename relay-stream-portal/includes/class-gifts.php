<?php
/**
 * Gift subscriptions: issued after confirmed payment as random, expiring, single-use codes.
 * Codes are stored encrypted (for the purchaser to view) plus a keyed hash for lookup.
 * Redemption is a single conditional UPDATE, so concurrent requests cannot both succeed.
 *
 * @package RelayStreamPortal
 */

defined( 'ABSPATH' ) || exit;

/**
 * Gifts.
 */
class RSP_Gifts {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'rsp_daily', array( __CLASS__, 'expire_codes' ) );
	}

	/**
	 * Table.
	 *
	 * @return string
	 */
	private static function t() {
		return RSP_Installer::table( 'gifts' );
	}

	/**
	 * Format code for display: XXXX-XXXX-XXXX.
	 *
	 * @param string $code Code.
	 * @return string
	 */
	public static function format( $code ) {
		return implode( '-', str_split( $code, 4 ) );
	}

	/**
	 * Normalize user input.
	 *
	 * @param string $code Code.
	 * @return string
	 */
	public static function normalize( $code ) {
		return strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', (string) $code ) );
	}

	/**
	 * Issue a gift for an order item (idempotent per item).
	 *
	 * @param WC_Order              $order Order.
	 * @param WC_Order_Item_Product $item Item.
	 * @param array                 $snap Snapshot.
	 * @return object|null
	 */
	public static function issue( $order, $item, array $snap ) {
		global $wpdb;
		$existing = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::t() . ' WHERE order_item_id = %d', $item->get_id() ) ); // phpcs:ignore
		if ( $existing ) {
			return $existing;
		}
		$code    = RSP_Crypto::random_code( 12 );
		$expires = gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS * max( 1, (int) RSP_Settings::get( 'gift_expiry_days', 365 ) ) );
		$ok      = $wpdb->insert(
			self::t(),
			array(
				'code_hash'       => RSP_Crypto::lookup_hash( $code ),
				'code_enc'        => RSP_Crypto::encrypt( $code ),
				'code_hint'       => substr( $code, -4 ),
				'purchaser_id'    => (int) $order->get_customer_id(),
				'order_id'        => $order->get_id(),
				'order_item_id'   => $item->get_id(),
				'plan_snapshot'   => wp_json_encode( $snap ),
				'status'          => 'issued',
				'recipient_email' => isset( $snap['gift_email'] ) ? (string) $snap['gift_email'] : '',
				'expires_at'      => $expires,
				'created_at'      => RSP_Installer::now(),
			)
		);
		if ( ! $ok ) {
			return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::t() . ' WHERE order_item_id = %d', $item->get_id() ) ); // phpcs:ignore
		}
		$gift = self::get( (int) $wpdb->insert_id );
		RSP_Audit::log( 'gift_issued', 'gift', $gift->id, '', array( 'order_id' => $order->get_id(), 'hint' => $gift->code_hint ), 0 );
		if ( $order->get_customer_id() ) {
			RSP_Notifications::notify( (int) $order->get_customer_id(), 'gift', __( 'Your gift code is ready', 'relay-stream-portal' ), __( 'Copy it from Referrals & Gifts or send it by email.', 'relay-stream-portal' ), RSP_Settings::page_url( 'referrals', array( 'tab' => 'gifts' ) ), 'gift_issued:' . $gift->id, false );
		}
		if ( $gift->recipient_email ) {
			self::send_email( $gift, $gift->recipient_email );
		}
		return $gift;
	}

	/**
	 * Get by id.
	 *
	 * @param int $id Id.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::t() . ' WHERE id = %d', $id ) ); // phpcs:ignore
	}

	/**
	 * Gifts purchased by a user.
	 *
	 * @param int $user_id User.
	 * @return object[]
	 */
	public static function purchased_by( $user_id ) {
		global $wpdb;
		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::t() . ' WHERE purchaser_id = %d ORDER BY id DESC LIMIT 50', $user_id ) ); // phpcs:ignore
	}

	/**
	 * Reveal the code to its purchaser only.
	 *
	 * @param object $gift Gift.
	 * @param int    $user_id User.
	 * @return string
	 */
	public static function code_for_owner( $gift, $user_id ) {
		if ( ! $gift || (int) $gift->purchaser_id !== (int) $user_id || 'issued' !== $gift->status ) {
			return '';
		}
		return self::format( RSP_Crypto::decrypt( $gift->code_enc ) );
	}

	/**
	 * Email the code to a recipient.
	 *
	 * @param object $gift Gift.
	 * @param string $email Email.
	 * @return bool
	 */
	public static function send_email( $gift, $email ) {
		if ( ! is_email( $email ) || 'issued' !== $gift->status ) {
			return false;
		}
		$snap   = json_decode( $gift->plan_snapshot, true );
		$sender = get_userdata( (int) $gift->purchaser_id );
		$sent   = RSP_Notifications::send_template(
			$email,
			'gift',
			array(
				'sender' => $sender ? $sender->display_name : get_bloginfo( 'name' ),
				'plan'   => isset( $snap['plan_name'] ) ? $snap['plan_name'] : '',
				'code'   => self::format( RSP_Crypto::decrypt( $gift->code_enc ) ),
				'link'   => RSP_Settings::page_url( 'redeem' ),
				'expiry' => mysql2date( get_option( 'date_format' ), $gift->expires_at ),
			)
		);
		global $wpdb;
		$wpdb->update( self::t(), array( 'recipient_email' => $email ), array( 'id' => $gift->id ) );
		RSP_Audit::log( 'gift_emailed', 'gift', $gift->id, '', array( 'domain' => substr( strrchr( $email, '@' ), 1 ) ) );
		return $sent;
	}

	/**
	 * Redeem a code atomically.
	 *
	 * @param string      $code Code.
	 * @param int         $user_id Redeemer.
	 * @param string      $target 'new' or account uuid.
	 * @param string      $nickname Nickname for new accounts.
	 * @return array|WP_Error job + account.
	 */
	public static function redeem( $code, $user_id, $target, $nickname = '' ) {
		global $wpdb;
		$code = self::normalize( $code );
		if ( strlen( $code ) !== 12 ) {
			return new WP_Error( 'rsp_code', __( 'That code is not valid.', 'relay-stream-portal' ) );
		}
		$gift = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::t() . ' WHERE code_hash = %s', RSP_Crypto::lookup_hash( $code ) ) ); // phpcs:ignore
		if ( ! $gift || 'issued' !== $gift->status || strtotime( $gift->expires_at . ' UTC' ) < time() ) {
			return new WP_Error( 'rsp_code', __( 'That code is not valid, already used, or expired.', 'relay-stream-portal' ) );
		}
		$snap = json_decode( $gift->plan_snapshot, true );

		$account = null;
		if ( 'new' !== $target ) {
			$account = RSP_Accounts::get_owned( $target, $user_id );
			if ( ! $account || ! RSP_Accounts::is_renewable( $account ) || RSP_Jobs::open_for_account( (int) $account->id ) ) {
				return new WP_Error( 'rsp_account', __( 'Choose an active or expired account without pending operations.', 'relay-stream-portal' ) );
			}
			if ( ( isset( $snap['type'] ) ? $snap['type'] : 'm3u' ) !== $account->provider_type ) {
				return new WP_Error( 'rsp_account', __( 'This gift cannot be applied to that account type.', 'relay-stream-portal' ) );
			}
		} elseif ( RSP_Accounts::count_for_user( $user_id ) >= (int) RSP_Settings::get( 'max_accounts', 10 ) ) {
			return new WP_Error( 'rsp_limit', __( 'You reached the maximum number of accounts.', 'relay-stream-portal' ) );
		}

		// Atomic claim: only one request can flip issued -> redeemed.
		$claimed = $wpdb->query( $wpdb->prepare( 'UPDATE ' . self::t() . " SET status = 'redeemed', redeemed_by = %d, redeemed_at = %s WHERE id = %d AND status = 'issued' AND expires_at > %s", $user_id, RSP_Installer::now(), $gift->id, RSP_Installer::now() ) ); // phpcs:ignore
		if ( 1 !== (int) $claimed ) {
			return new WP_Error( 'rsp_code', __( 'That code is not valid, already used, or expired.', 'relay-stream-portal' ) );
		}

		if ( ! $account ) {
			$account = RSP_Accounts::create(
				array(
					'user_id'       => $user_id,
					'nickname'      => $nickname,
					'provider_type' => isset( $snap['type'] ) ? $snap['type'] : 'm3u',
					'status'        => 'pending_activation',
					'plan_label'    => isset( $snap['plan_name'] ) ? $snap['plan_name'] : '',
					'package_id'    => isset( $snap['package'] ) ? $snap['package'] : 'all',
				)
			);
		}
		$job = RSP_Jobs::enqueue(
			'gift:' . $gift->id,
			'new' === $target ? 'create' : 'renew',
			array(
				'account_id' => (int) $account->id,
				'user_id'    => $user_id,
				'order_id'   => (int) $gift->order_id,
				'payload'    => array(
					'months'     => (int) $snap['months'],
					'package'    => (string) $snap['package'],
					'type'       => (string) $snap['type'],
					'plan_label' => (string) $snap['plan_name'],
					'gift_id'    => (int) $gift->id,
				),
			)
		);
		$wpdb->update( self::t(), array( 'redeemed_account_id' => $account->id, 'redeemed_job_id' => $job ? $job->id : null ), array( 'id' => $gift->id ) );
		RSP_Audit::log( 'gift_redeemed', 'gift', $gift->id, '', array( 'account' => $account->uuid, 'job' => $job ? $job->id : 0 ), $user_id );
		if ( (int) $gift->purchaser_id && (int) $gift->purchaser_id !== (int) $user_id ) {
			RSP_Notifications::notify( (int) $gift->purchaser_id, 'gift', __( 'Your gift was redeemed', 'relay-stream-portal' ), __( 'The recipient redeemed the gift code you purchased.', 'relay-stream-portal' ), RSP_Settings::page_url( 'referrals', array( 'tab' => 'gifts' ) ), 'gift_redeemed:' . $gift->id );
		}
		return array( 'job' => $job, 'account' => RSP_Accounts::get( $account->id ) );
	}

	/**
	 * Order cancelled/refunded.
	 *
	 * @param WC_Order $order Order.
	 * @param string   $why Why.
	 */
	public static function on_order_unwound( $order, $why ) {
		global $wpdb;
		$gifts = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::t() . ' WHERE order_id = %d', $order->get_id() ) ); // phpcs:ignore
		foreach ( (array) $gifts as $g ) {
			if ( 'issued' === $g->status ) {
				$wpdb->query( $wpdb->prepare( 'UPDATE ' . self::t() . " SET status = 'revoked' WHERE id = %d AND status = 'issued'", $g->id ) ); // phpcs:ignore
				RSP_Audit::log( 'gift_revoked', 'gift', $g->id, 'Order ' . $why, array(), 0 );
			} elseif ( 'redeemed' === $g->status ) {
				$wpdb->update( self::t(), array( 'status' => 'review' ), array( 'id' => $g->id ) );
				RSP_Audit::log( 'gift_review', 'gift', $g->id, 'Order ' . $why . ' after redemption', array(), 0 );
			}
		}
	}

	/**
	 * Daily expiry sweep.
	 */
	public static function expire_codes() {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . self::t() . " SET status = 'expired' WHERE status = 'issued' AND expires_at < %s", RSP_Installer::now() ) ); // phpcs:ignore
	}

	/**
	 * Status label.
	 *
	 * @param string $s Status.
	 * @return string
	 */
	public static function status_label( $s ) {
		$l = array(
			'issued'   => __( 'Ready to redeem', 'relay-stream-portal' ),
			'redeemed' => __( 'Redeemed', 'relay-stream-portal' ),
			'expired'  => __( 'Expired', 'relay-stream-portal' ),
			'revoked'  => __( 'Revoked', 'relay-stream-portal' ),
			'review'   => __( 'Under review', 'relay-stream-portal' ),
		);
		return isset( $l[ $s ] ) ? $l[ $s ] : $s;
	}
}
