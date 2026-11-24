<?php
/**
 * Referral codes, conversion tracking and the reward-days ledger.
 *
 * Flow: invited -> pending (first eligible confirmed payment, review window starts)
 *       -> rewarded (ledger credit, once, keyed by referral id) | reversed | rejected | review.
 * Earned days live in a ledger. Applying them to an account requires provider support:
 * TVPlus only renews whole months, so by default applications go to staff review. When
 * "month conversion" is enabled, each 30-day block is applied as a confirmed 1-month renewal.
 *
 * @package RelayStreamPortal
 */

defined( 'ABSPATH' ) || exit;

/**
 * Referrals.
 */
class RSP_Referrals {

	const COOKIE = 'rsp_ref';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'capture_cookie' ) );
		add_action( 'user_register', array( __CLASS__, 'on_register' ), 20 );
		add_action( 'woocommerce_payment_complete', array( __CLASS__, 'on_paid' ), 30 );
		add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'on_paid' ), 30 );
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'on_paid' ), 30 );
		add_action( 'rsp_daily', array( __CLASS__, 'process_rewards' ) );
		add_action( 'rsp_process_queue', array( __CLASS__, 'process_rewards' ), 20 );
		add_action( 'rsp_job_cancelled', array( __CLASS__, 'refund_reserved_days' ) );
	}

	/**
	 * Table helpers.
	 *
	 * @param string $n Name.
	 * @return string
	 */
	private static function t( $n = 'referrals' ) {
		return RSP_Installer::table( $n );
	}

	/**
	 * Referral code for a user (created lazily, unique).
	 *
	 * @param int $user_id User.
	 * @return string
	 */
	public static function code_for( $user_id ) {
		$code = (string) get_user_meta( $user_id, 'rsp_ref_code', true );
		if ( $code ) {
			return $code;
		}
		for ( $i = 0; $i < 5; $i++ ) {
			$candidate = 'R' . RSP_Crypto::random_code( 9 );
			if ( ! self::user_by_code( $candidate ) ) {
				update_user_meta( $user_id, 'rsp_ref_code', $candidate );
				return $candidate;
			}
		}
		return '';
	}

	/**
	 * Lookup referrer by code.
	 *
	 * @param string $code Code.
	 * @return int User id or 0.
	 */
	public static function user_by_code( $code ) {
		$code = strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', (string) $code ) );
		if ( '' === $code ) {
			return 0;
		}
		$users = get_users( array( 'meta_key' => 'rsp_ref_code', 'meta_value' => $code, 'number' => 1, 'fields' => 'ID' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		return $users ? (int) $users[0] : 0;
	}

	/**
	 * Share link.
	 *
	 * @param int $user_id User.
	 * @return string
	 */
	public static function link_for( $user_id ) {
		return add_query_arg( 'rsp_ref', self::code_for( $user_id ), RSP_Settings::page_url( 'register' ) );
	}

	/**
	 * Remember ?rsp_ref= for later registration.
	 */
	public static function capture_cookie() {
		if ( empty( $_GET['rsp_ref'] ) || ! RSP_Settings::get( 'referral_enabled' ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$code = strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', sanitize_text_field( wp_unslash( $_GET['rsp_ref'] ) ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( strlen( $code ) >= 6 && strlen( $code ) <= 16 && ! headers_sent() ) {
			setcookie( self::COOKIE, $code, array( 'expires' => time() + DAY_IN_SECONDS * (int) RSP_Settings::get( 'referral_cookie_days', 30 ), 'path' => COOKIEPATH ? COOKIEPATH : '/', 'domain' => COOKIE_DOMAIN, 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax' ) );
			$_COOKIE[ self::COOKIE ] = $code;
		}
	}

	/**
	 * Bind on registration.
	 *
	 * @param int $user_id User.
	 */
	public static function on_register( $user_id ) {
		$code = isset( $_COOKIE[ self::COOKIE ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) ) : '';
		if ( isset( $_POST['rsp_referral_code'] ) && '' !== $_POST['rsp_referral_code'] ) { // phpcs:ignore WordPress.Security.NonceVerification
			$code = sanitize_text_field( wp_unslash( $_POST['rsp_referral_code'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		}
		if ( $code ) {
			self::bind( $user_id, $code );
		}
	}

	/**
	 * Link a referee to a referrer.
	 *
	 * @param int    $referee_id Referee.
	 * @param string $code Code.
	 * @return true|WP_Error
	 */
	public static function bind( $referee_id, $code ) {
		global $wpdb;
		if ( ! RSP_Settings::get( 'referral_enabled' ) ) {
			return new WP_Error( 'rsp_ref', __( 'Referrals are not active.', 'relay-stream-portal' ) );
		}
		$referrer_id = self::user_by_code( $code );
		if ( ! $referrer_id ) {
			return new WP_Error( 'rsp_ref', __( 'That referral code was not found.', 'relay-stream-portal' ) );
		}
		if ( (int) $referrer_id === (int) $referee_id ) {
			return new WP_Error( 'rsp_ref', __( 'You cannot use your own referral code.', 'relay-stream-portal' ) );
		}
		$a = get_userdata( $referrer_id );
		$b = get_userdata( $referee_id );
		if ( $a && $b && self::same_email( $a->user_email, $b->user_email ) ) {
			return new WP_Error( 'rsp_ref', __( 'You cannot use your own referral code.', 'relay-stream-portal' ) );
		}
		if ( self::has_paid_orders( $referee_id ) ) {
			return new WP_Error( 'rsp_ref', __( 'Referral codes can only be used before your first purchase.', 'relay-stream-portal' ) );
		}
		$now = RSP_Installer::now();
		$wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO ' . self::t() . ' (referrer_id, referee_id, code, status, created_at, updated_at) VALUES (%d,%d,%s,%s,%s,%s)', $referrer_id, $referee_id, strtoupper( $code ), 'invited', $now, $now ) ); // phpcs:ignore
		if ( 1 !== (int) $wpdb->rows_affected ) {
			return new WP_Error( 'rsp_ref', __( 'A referral is already linked to your account.', 'relay-stream-portal' ) );
		}
		RSP_Audit::log( 'referral_bound', 'user', $referee_id, '', array( 'referrer' => $referrer_id ), $referee_id );
		return true;
	}

	/**
	 * Compare emails ignoring case and +tags / gmail dots.
	 *
	 * @param string $x Email.
	 * @param string $y Email.
	 * @return bool
	 */
	public static function same_email( $x, $y ) {
		$n = static function ( $e ) {
			$e = strtolower( trim( $e ) );
			if ( false === strpos( $e, '@' ) ) {
				return $e;
			}
			list( $local, $domain ) = explode( '@', $e, 2 );
			$local                  = preg_replace( '/\+.*$/', '', $local );
			if ( in_array( $domain, array( 'gmail.com', 'googlemail.com' ), true ) ) {
				$local  = str_replace( '.', '', $local );
				$domain = 'gmail.com';
			}
			return $local . '@' . $domain;
		};
		return $n( $x ) === $n( $y );
	}

	/**
	 * Paid order check.
	 *
	 * @param int $user_id User.
	 * @return bool
	 */
	private static function has_paid_orders( $user_id ) {
		if ( ! RSP_Plugin::has_woocommerce() ) {
			return false;
		}
		$ids = wc_get_orders( array( 'customer_id' => $user_id, 'status' => wc_get_is_paid_statuses(), 'limit' => 1, 'return' => 'ids' ) );
		return ! empty( $ids );
	}

	/**
	 * First eligible confirmed payment starts the review window.
	 *
	 * @param int $order_id Order.
	 */
	public static function on_paid( $order_id ) {
		global $wpdb;
		$order = wc_get_order( $order_id );
		if ( ! $order || ! $order->is_paid() || ! $order->get_customer_id() || ! RSP_Settings::get( 'referral_enabled' ) ) {
			return;
		}
		$eligible = false;
		foreach ( $order->get_items() as $item ) {
			if ( RSP_Woocommerce::snapshot( $item ) ) {
				$eligible = true;
			}
		}
		if ( ! $eligible || (float) $order->get_total() < (float) RSP_Settings::get( 'referral_min_total', 0 ) ) {
			return;
		}
		$ref = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::t() . " WHERE referee_id = %d AND status = 'invited'", $order->get_customer_id() ) ); // phpcs:ignore
		if ( ! $ref ) {
			return;
		}
		$referrer = get_userdata( (int) $ref->referrer_id );
		if ( $referrer && self::same_email( $referrer->user_email, $order->get_billing_email() ) ) {
			$wpdb->update( self::t(), array( 'status' => 'rejected', 'order_id' => $order_id, 'updated_at' => RSP_Installer::now() ), array( 'id' => $ref->id ) );
			RSP_Audit::log( 'referral_rejected', 'referral', $ref->id, 'Billing email matches referrer', array(), 0 );
			return;
		}
		$eligible_at = gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS * max( 0, (int) RSP_Settings::get( 'referral_review_days', 7 ) ) );
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . self::t() . " SET status = 'pending', order_id = %d, eligible_at = %s, updated_at = %s WHERE id = %d AND status = 'invited'", $order_id, $eligible_at, RSP_Installer::now(), $ref->id ) ); // phpcs:ignore
	}

	/**
	 * Credit rewards whose review window passed (exactly once per referral).
	 */
	public static function process_rewards() {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::t() . " WHERE status = 'pending' AND eligible_at <= %s LIMIT 50", RSP_Installer::now() ) ); // phpcs:ignore
		foreach ( (array) $rows as $ref ) {
			$order = RSP_Plugin::has_woocommerce() ? wc_get_order( (int) $ref->order_id ) : null;
			if ( ! $order || ! $order->is_paid() || (float) $order->get_total_refunded() > 0 ) {
				$wpdb->update( self::t(), array( 'status' => 'reversed', 'updated_at' => RSP_Installer::now() ), array( 'id' => $ref->id ) );
				RSP_Audit::log( 'referral_reversed', 'referral', $ref->id, 'Order not eligible at end of review window', array(), 0 );
				continue;
			}
			$days = max( 0, (int) RSP_Settings::get( 'referral_days', 30 ) );
			// Claim the row first so only one worker credits it.
			$claimed = $wpdb->query( $wpdb->prepare( 'UPDATE ' . self::t() . " SET status = 'rewarded', reward_days = %d, updated_at = %s WHERE id = %d AND status = 'pending'", $days, RSP_Installer::now(), $ref->id ) ); // phpcs:ignore
			if ( 1 !== (int) $claimed ) {
				continue;
			}
			self::ledger_add( (int) $ref->referrer_id, $days, 'referral', 'referral:' . $ref->id, array( 'referral_id' => (int) $ref->id ) );
			RSP_Audit::log( 'referral_rewarded', 'referral', $ref->id, '', array( 'days' => $days ), 0 );
			RSP_Notifications::notify( (int) $ref->referrer_id, 'referral', __( 'You earned free days', 'relay-stream-portal' ), sprintf( /* translators: %d days */ __( '%d days were added to your reward balance.', 'relay-stream-portal' ), $days ), RSP_Settings::page_url( 'referrals' ), 'referral_reward:' . $ref->id, true, array( 'days' => $days ) );
		}
	}

	/**
	 * Add a ledger entry; the unique source key prevents duplicates.
	 *
	 * @param int    $user_id User.
	 * @param int    $days Delta.
	 * @param string $reason Reason.
	 * @param string $source_key Unique key.
	 * @param array  $extra referral_id, account_id, job_id, note.
	 * @return int|false Insert id or false when duplicate.
	 */
	public static function ledger_add( $user_id, $days, $reason, $source_key, array $extra = array() ) {
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				'INSERT IGNORE INTO ' . self::t( 'ledger' ) . ' (user_id, delta_days, reason, source_key, referral_id, account_id, job_id, note, created_at) VALUES (%d,%d,%s,%s,%d,%d,%d,%s,%s)', // phpcs:ignore
				$user_id,
				$days,
				$reason,
				$source_key,
				isset( $extra['referral_id'] ) ? $extra['referral_id'] : 0,
				isset( $extra['account_id'] ) ? $extra['account_id'] : 0,
				isset( $extra['job_id'] ) ? $extra['job_id'] : 0,
				isset( $extra['note'] ) ? substr( $extra['note'], 0, 255 ) : '',
				RSP_Installer::now()
			)
		);
		return 1 === (int) $wpdb->rows_affected ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Balance in days.
	 *
	 * @param int $user_id User.
	 * @return int
	 */
	public static function balance( $user_id ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(SUM(delta_days),0) FROM ' . self::t( 'ledger' ) . ' WHERE user_id = %d', $user_id ) ); // phpcs:ignore
	}

	/**
	 * Stats for the portal.
	 *
	 * @param int $user_id User.
	 * @return array
	 */
	public static function stats( $user_id ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT status, COUNT(*) c FROM ' . self::t() . ' WHERE referrer_id = %d GROUP BY status', $user_id ), OBJECT_K ); // phpcs:ignore
		$c    = static function ( $s ) use ( $rows ) {
			return isset( $rows[ $s ] ) ? (int) $rows[ $s ]->c : 0;
		};
		$earned = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(SUM(delta_days),0) FROM ' . self::t( 'ledger' ) . " WHERE user_id = %d AND reason = 'referral'", $user_id ) ); // phpcs:ignore
		return array(
			'invited'   => array_sum( array_map( 'intval', wp_list_pluck( $rows, 'c' ) ) ),
			'converted' => $c( 'rewarded' ) + $c( 'pending' ),
			'pending'   => $c( 'pending' ) + $c( 'invited' ),
			'earned'    => $earned,
			'balance'   => self::balance( $user_id ),
		);
	}

	/**
	 * History without exposing invitee private data.
	 *
	 * @param int $user_id Referrer.
	 * @return array
	 */
	public static function history( $user_id ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT id, referee_id, status, reward_days, created_at FROM ' . self::t() . ' WHERE referrer_id = %d ORDER BY id DESC LIMIT 50', $user_id ) ); // phpcs:ignore
		$out  = array();
		foreach ( (array) $rows as $r ) {
			$u     = get_userdata( (int) $r->referee_id );
			$name  = $u ? ( $u->first_name ? $u->first_name : $u->display_name ) : '';
			$out[] = array(
				'label'  => $name ? mb_substr( $name, 0, 1 ) . '•••' : __( 'Friend', 'relay-stream-portal' ),
				'status' => $r->status,
				'days'   => (int) $r->reward_days,
				'date'   => $r->created_at,
			);
		}
		return $out;
	}

	/**
	 * Status label.
	 *
	 * @param string $s Status.
	 * @return string
	 */
	public static function status_label( $s ) {
		$l = array(
			'invited'  => __( 'Signed up', 'relay-stream-portal' ),
			'pending'  => __( 'Paid — in review period', 'relay-stream-portal' ),
			'rewarded' => __( 'Reward earned', 'relay-stream-portal' ),
			'reversed' => __( 'Not eligible', 'relay-stream-portal' ),
			'rejected' => __( 'Not eligible', 'relay-stream-portal' ),
			'review'   => __( 'Under review', 'relay-stream-portal' ),
		);
		return isset( $l[ $s ] ) ? $l[ $s ] : $s;
	}

	/**
	 * Customer applies earned days to an account.
	 *
	 * @param int    $user_id User.
	 * @param string $account_uuid Account.
	 * @return array|WP_Error
	 */
	public static function apply_days( $user_id, $account_uuid ) {
		global $wpdb;
		$acc = RSP_Accounts::get_owned( $account_uuid, $user_id );
		if ( ! $acc || ! RSP_Accounts::is_renewable( $acc ) || RSP_Jobs::open_for_account( (int) $acc->id ) ) {
			return new WP_Error( 'rsp_account', __( 'Choose an active or expired account without pending operations.', 'relay-stream-portal' ) );
		}
		$lock = 'rsp_ledger_' . (int) $user_id;
		if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 10)', $lock ) ) ) {
			return new WP_Error( 'rsp_busy', __( 'Please try again in a moment.', 'relay-stream-portal' ) );
		}
		try {
			$balance = self::balance( $user_id );
			$convert = RSP_Settings::get( 'reward_month_conversion' ) && RSP_Provider_Factory::is_live();
			$days    = $convert ? 30 : $balance;
			if ( $balance < 1 || ( $convert && $balance < 30 ) ) {
				return new WP_Error( 'rsp_balance', $convert ? __( 'You need at least 30 days to apply a reward.', 'relay-stream-portal' ) : __( 'You have no reward days to apply.', 'relay-stream-portal' ) );
			}
			$apply_id = wp_generate_uuid4();
			$ledger   = self::ledger_add( $user_id, -$days, 'applied', 'apply:' . $apply_id, array( 'account_id' => (int) $acc->id, 'note' => 'Reserved for account ' . $acc->uuid ) );
			$job      = RSP_Jobs::enqueue(
				'reward:' . $apply_id,
				'reward_extend',
				array(
					'account_id' => (int) $acc->id,
					'user_id'    => $user_id,
					'payload'    => array( 'months' => 1, 'days' => $days, 'ledger_id' => $ledger, 'type' => $acc->provider_type ),
				)
			);
			if ( $job && ! $convert && 'needs_review' !== $job->state ) {
				/* translators: %d days */
				RSP_Jobs::to_review( $job, sprintf( 'Apply %d reward days. The provider cannot extend by arbitrary days; extend manually and record the new expiry.', $days ), false );
			}
			RSP_Audit::log( 'reward_apply_requested', 'account', $acc->uuid, '', array( 'days' => $days, 'job' => $job ? $job->id : 0 ), $user_id );
			return array( 'job' => $job, 'days' => $days, 'auto' => $convert );
		} finally {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
		}
	}

	/**
	 * Return reserved days if a reward job is cancelled.
	 *
	 * @param object $job Job.
	 */
	public static function refund_reserved_days( $job ) {
		if ( ! $job || 'reward_extend' !== $job->type ) {
			return;
		}
		$p = RSP_Jobs::payload( $job );
		self::ledger_add( (int) $job->user_id, (int) $p['days'], 'refund', 'refund:' . $job->id, array( 'job_id' => (int) $job->id, 'note' => 'Reward job cancelled' ) );
	}

	/**
	 * Order unwound (refund/cancel).
	 *
	 * @param WC_Order $order Order.
	 * @param string   $why Why.
	 */
	public static function on_order_unwound( $order, $why ) {
		global $wpdb;
		$ref = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::t() . ' WHERE order_id = %d', $order->get_id() ) ); // phpcs:ignore
		if ( ! $ref ) {
			return;
		}
		if ( 'pending' === $ref->status ) {
			$wpdb->update( self::t(), array( 'status' => 'reversed', 'updated_at' => RSP_Installer::now() ), array( 'id' => $ref->id ) );
			RSP_Audit::log( 'referral_reversed', 'referral', $ref->id, 'Order ' . $why . ' during review window', array(), 0 );
			return;
		}
		if ( 'rewarded' !== $ref->status ) {
			return;
		}
		$policy = RSP_Settings::get( 'referral_reversal', 'reverse_unapplied' );
		if ( 'keep' === $policy ) {
			RSP_Audit::log( 'referral_kept', 'referral', $ref->id, 'Order ' . $why . '; policy keeps rewards', array(), 0 );
			return;
		}
		if ( 'reverse_unapplied' === $policy && self::balance( (int) $ref->referrer_id ) >= (int) $ref->reward_days ) {
			self::ledger_add( (int) $ref->referrer_id, -(int) $ref->reward_days, 'reversal', 'reversal:' . $ref->id, array( 'referral_id' => (int) $ref->id ) );
			$wpdb->update( self::t(), array( 'status' => 'reversed', 'updated_at' => RSP_Installer::now() ), array( 'id' => $ref->id ) );
			RSP_Audit::log( 'referral_reversed', 'referral', $ref->id, 'Order ' . $why . ' after reward', array(), 0 );
			return;
		}
		$wpdb->update( self::t(), array( 'status' => 'review', 'updated_at' => RSP_Installer::now() ), array( 'id' => $ref->id ) );
		RSP_Audit::log( 'referral_review', 'referral', $ref->id, 'Order ' . $why . ' after reward days were used', array(), 0 );
	}
}
