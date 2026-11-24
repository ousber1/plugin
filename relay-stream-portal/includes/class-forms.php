<?php
/**
 * Front-end form handling (POST -> redirect -> GET). Every action checks a nonce AND
 * authorization/ownership server-side. Messages are passed back as whitelisted codes or
 * a short-lived server-side flash token, never as reflected input.
 *
 * Runs on wp_loaded so WooCommerce's cart/session are available.
 *
 * @package RelayStreamPortal
 */

defined( 'ABSPATH' ) || exit;

/**
 * Forms.
 */
class RSP_Forms {

	const PUBLIC_ACTIONS = array( 'login', 'register', 'lostpassword', 'resetpass' );
	const USER_ACTIONS   = array( 'profile', 'checkout', 'promo', 'referral_code', 'apply_days', 'redeem', 'ticket_new', 'ticket_reply', 'account_rename', 'gift_email', 'notifications_read' );

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'wp_loaded', array( __CLASS__, 'dispatch' ), 30 );
	}

	/**
	 * Hidden fields for a form.
	 *
	 * @param string $action Action.
	 * @return string
	 */
	public static function fields( $action ) {
		return '<input type="hidden" name="rsp_action" value="' . esc_attr( $action ) . '">' . wp_nonce_field( 'rsp_' . $action, '_rsp_nonce', false, false ) . '<input type="hidden" name="rsp_return" value="' . esc_attr( RSP_Portal::current_url() ) . '">';
	}

	/**
	 * Store a flash message and return its token.
	 *
	 * @param string $type success|error|info.
	 * @param string $message Message (server generated).
	 * @return string
	 */
	public static function flash( $type, $message ) {
		$token = bin2hex( random_bytes( 8 ) );
		set_transient( 'rsp_flash_' . $token, array( 'type' => $type, 'message' => wp_strip_all_tags( $message ), 'user' => get_current_user_id() ), 5 * MINUTE_IN_SECONDS );
		return $token;
	}

	/**
	 * Read the current flash or code message.
	 *
	 * @return array|null type, message
	 */
	public static function current_message() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_GET['rsp_flash'] ) ) {
			$token = preg_replace( '/[^a-f0-9]/', '', sanitize_text_field( wp_unslash( $_GET['rsp_flash'] ) ) );
			$f     = get_transient( 'rsp_flash_' . $token );
			if ( is_array( $f ) && (int) $f['user'] === get_current_user_id() ) {
				return $f;
			}
		}
		if ( ! empty( $_GET['rsp_msg'] ) ) {
			$codes = self::codes();
			$code  = sanitize_key( wp_unslash( $_GET['rsp_msg'] ) );
			if ( isset( $codes[ $code ] ) ) {
				return $codes[ $code ];
			}
		}
		// phpcs:enable
		return null;
	}

	/**
	 * Whitelisted message codes.
	 *
	 * @return array
	 */
	public static function codes() {
		return array(
			'verified'      => array( 'type' => 'success', 'message' => __( 'Email confirmed. You can sign in now.', 'relay-stream-portal' ) ),
			'verify_failed' => array( 'type' => 'error', 'message' => __( 'That confirmation link is invalid or expired.', 'relay-stream-portal' ) ),
			'verify_sent'   => array( 'type' => 'success', 'message' => __( 'Account created. Check your inbox to confirm your email address.', 'relay-stream-portal' ) ),
			'reset_sent'    => array( 'type' => 'success', 'message' => __( 'If an account exists for that address, a reset link is on its way.', 'relay-stream-portal' ) ),
			'reset_done'    => array( 'type' => 'success', 'message' => __( 'Password updated. Sign in with your new password.', 'relay-stream-portal' ) ),
			'logged_out'    => array( 'type' => 'info', 'message' => __( 'You have signed out.', 'relay-stream-portal' ) ),
			'saved'         => array( 'type' => 'success', 'message' => __( 'Saved.', 'relay-stream-portal' ) ),
			'expired'       => array( 'type' => 'error', 'message' => __( 'Your session expired. Please try again.', 'relay-stream-portal' ) ),
		);
	}

	/**
	 * Redirect back with a flash.
	 *
	 * @param string $type Type.
	 * @param string $message Message.
	 * @param string $url Optional URL.
	 */
	private static function back( $type, $message, $url = '' ) {
		$url = $url ? $url : self::return_url();
		wp_safe_redirect( add_query_arg( 'rsp_flash', self::flash( $type, $message ), remove_query_arg( array( 'rsp_flash', 'rsp_msg' ), $url ) ) );
		exit;
	}

	/**
	 * Return URL from the posted form, validated.
	 *
	 * @return string
	 */
	private static function return_url() {
		$r = isset( $_POST['rsp_return'] ) ? esc_url_raw( wp_unslash( $_POST['rsp_return'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		return wp_validate_redirect( $r, RSP_Settings::page_url( 'dashboard' ) );
	}

	/**
	 * Posted string.
	 *
	 * @param string $k Key.
	 * @return string
	 */
	private static function p( $k ) {
		return isset( $_POST[ $k ] ) ? sanitize_text_field( wp_unslash( $_POST[ $k ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	}

	/**
	 * Raw posted (passwords, textareas) — sanitized by the consumer.
	 *
	 * @param string $k Key.
	 * @return string
	 */
	private static function raw( $k ) {
		return isset( $_POST[ $k ] ) ? (string) wp_unslash( $_POST[ $k ] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
	}

	/**
	 * Dispatch.
	 */
	public static function dispatch() {
		if ( 'POST' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '' ) || empty( $_POST['rsp_action'] ) ) { // phpcs:ignore
			return;
		}
		$action = sanitize_key( wp_unslash( $_POST['rsp_action'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! in_array( $action, array_merge( self::PUBLIC_ACTIONS, self::USER_ACTIONS ), true ) ) {
			return;
		}
		$nonce = isset( $_POST['_rsp_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_rsp_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'rsp_' . $action ) ) {
			wp_safe_redirect( add_query_arg( 'rsp_msg', 'expired', self::return_url() ) );
			exit;
		}
		if ( in_array( $action, self::USER_ACTIONS, true ) && ! is_user_logged_in() ) {
			wp_safe_redirect( RSP_Settings::page_url( 'login', array( 'redirect_to' => rawurlencode( self::return_url() ) ) ) );
			exit;
		}
		nocache_headers();
		call_user_func( array( __CLASS__, 'do_' . $action ) );
	}

	/* ----- Public actions ----- */

	private static function do_login() {
		$user = RSP_Auth::login( self::p( 'log' ), self::raw( 'pwd' ), ! empty( $_POST['rememberme'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( is_wp_error( $user ) ) {
			self::back( 'error', $user->get_error_message() );
		}
		wp_safe_redirect( RSP_Auth::safe_redirect_target( self::raw( 'redirect_to' ) ) );
		exit;
	}

	private static function do_register() {
		$id = RSP_Auth::register(
			array(
				'email'         => self::p( 'email' ),
				'password'      => self::raw( 'password' ),
				'first_name'    => self::p( 'first_name' ),
				'referral_code' => self::p( 'rsp_referral_code' ),
			)
		);
		if ( is_wp_error( $id ) ) {
			self::back( 'error', $id->get_error_message() );
		}
		if ( RSP_Settings::get( 'email_verification' ) ) {
			wp_safe_redirect( RSP_Settings::page_url( 'login', array( 'rsp_msg' => 'verify_sent' ) ) );
			exit;
		}
		wp_set_current_user( $id );
		wp_set_auth_cookie( $id, true, is_ssl() );
		wp_safe_redirect( RSP_Auth::safe_redirect_target( self::raw( 'redirect_to' ) ) );
		exit;
	}

	private static function do_lostpassword() {
		$r = RSP_Auth::request_reset( self::p( 'user_login' ) );
		if ( is_wp_error( $r ) ) {
			self::back( 'error', $r->get_error_message() );
		}
		wp_safe_redirect( add_query_arg( 'rsp_msg', 'reset_sent', remove_query_arg( array( 'key', 'login' ), self::return_url() ) ) );
		exit;
	}

	private static function do_resetpass() {
		$r = RSP_Auth::complete_reset( self::p( 'key' ), self::p( 'login' ), self::raw( 'pass1' ) );
		if ( is_wp_error( $r ) ) {
			self::back( 'error', $r->get_error_message() );
		}
		wp_safe_redirect( RSP_Settings::page_url( 'login', array( 'rsp_msg' => 'reset_done' ) ) );
		exit;
	}

	/* ----- Customer actions ----- */

	private static function do_profile() {
		$r = RSP_Auth::update_profile(
			get_current_user_id(),
			array(
				'first_name'       => self::p( 'first_name' ),
				'last_name'        => self::p( 'last_name' ),
				'display_name'     => self::p( 'display_name' ),
				'email'            => self::p( 'email' ),
				'current_password' => self::raw( 'current_password' ),
				'new_password'     => self::raw( 'new_password' ),
				'email_opt_in'     => self::p( 'email_opt_in' ),
			)
		);
		is_wp_error( $r ) ? self::back( 'error', $r->get_error_message() ) : self::back( 'success', __( 'Profile saved.', 'relay-stream-portal' ) );
	}

	private static function do_checkout() {
		if ( ! RSP_Plugin::has_woocommerce() ) {
			self::back( 'error', __( 'Billing is not available right now.', 'relay-stream-portal' ) );
		}
		if ( ! RSP_Rate_Limiter::hit( 'checkout', 20, 10 * MINUTE_IN_SECONDS ) ) {
			self::back( 'error', __( 'Too many attempts. Please wait a few minutes.', 'relay-stream-portal' ) );
		}
		$purpose = self::p( 'purpose' );
		$account = self::p( 'account' );
		$target  = self::p( 'target' );
		if ( '' !== $target && 'gift' !== $purpose ) {
			$purpose = 'new' === $target ? 'new' : 'renew';
			$account = 'new' === $target ? '' : $target;
		}
		$url     = RSP_Woocommerce::start_checkout(
			get_current_user_id(),
			(int) self::p( 'plan' ),
			in_array( $purpose, array( 'new', 'renew', 'gift' ), true ) ? $purpose : 'new',
			array(
				'account'    => $account,
				'nickname'   => self::p( 'nickname' ),
				'gift_email' => self::p( 'gift_email' ),
				'promo'      => self::p( 'promo' ),
			)
		);
		if ( is_wp_error( $url ) ) {
			self::back( 'error', $url->get_error_message() );
		}
		wp_safe_redirect( $url );
		exit;
	}

	private static function do_promo() {
		if ( ! RSP_Plugin::has_woocommerce() ) {
			self::back( 'error', __( 'Billing is not available right now.', 'relay-stream-portal' ) );
		}
		$code = wc_format_coupon_code( self::p( 'promo' ) );
		if ( ! RSP_Rate_Limiter::hit( 'promo', 10, 10 * MINUTE_IN_SECONDS ) ) {
			self::back( 'error', __( 'Too many attempts. Please wait a few minutes.', 'relay-stream-portal' ) );
		}
		$coupon = $code ? new WC_Coupon( $code ) : null;
		if ( ! $coupon || ! $coupon->get_id() || ( $coupon->get_date_expires() && $coupon->get_date_expires()->getTimestamp() < time() ) ) {
			self::back( 'error', __( 'That promo code is not valid.', 'relay-stream-portal' ) );
		}
		if ( WC()->session ) {
			if ( ! WC()->session->has_session() ) {
				WC()->session->set_customer_session_cookie( true );
			}
			WC()->session->set( 'rsp_promo', $code );
		}
		self::back( 'success', __( 'Promo code saved. It will be applied at checkout.', 'relay-stream-portal' ) );
	}

	private static function do_referral_code() {
		if ( ! RSP_Rate_Limiter::hit( 'refcode', 5, HOUR_IN_SECONDS ) ) {
			self::back( 'error', __( 'Too many attempts. Please try again later.', 'relay-stream-portal' ) );
		}
		$r = RSP_Referrals::bind( get_current_user_id(), self::p( 'referral_code' ) );
		is_wp_error( $r ) ? self::back( 'error', $r->get_error_message() ) : self::back( 'success', __( 'Referral code applied. Thanks for joining through a friend!', 'relay-stream-portal' ) );
	}

	private static function do_apply_days() {
		$r = RSP_Referrals::apply_days( get_current_user_id(), self::p( 'account' ) );
		if ( is_wp_error( $r ) ) {
			self::back( 'error', $r->get_error_message() );
		}
		self::back( 'success', $r['auto'] ? __( 'Reward queued. Your account will be extended once the provider confirms it.', 'relay-stream-portal' ) : __( 'Request received. Our team will apply your reward days and confirm the new expiry.', 'relay-stream-portal' ) );
	}

	private static function do_redeem() {
		if ( ! RSP_Rate_Limiter::hit( 'redeem', 10, HOUR_IN_SECONDS ) ) {
			self::back( 'error', __( 'Too many attempts. Please try again later.', 'relay-stream-portal' ) );
		}
		$r = RSP_Gifts::redeem( self::p( 'code' ), get_current_user_id(), self::p( 'target' ) ? self::p( 'target' ) : 'new', self::p( 'nickname' ) );
		if ( is_wp_error( $r ) ) {
			self::back( 'error', $r->get_error_message() );
		}
		self::back( 'success', __( 'Gift redeemed! Track activation under My Accounts.', 'relay-stream-portal' ), RSP_Settings::page_url( 'accounts' ) );
	}

	private static function do_ticket_new() {
		if ( ! RSP_Rate_Limiter::hit( 'ticket', 10, HOUR_IN_SECONDS ) ) {
			self::back( 'error', __( 'Too many tickets. Please try again later.', 'relay-stream-portal' ) );
		}
		$id = RSP_Tickets::create(
			get_current_user_id(),
			array(
				'subject'      => self::p( 'subject' ),
				'category'     => self::p( 'category' ),
				'body'         => self::raw( 'body' ),
				'order_id'     => self::p( 'order_id' ),
				'account_uuid' => self::p( 'account' ),
			),
			isset( $_FILES['attachments'] ) ? $_FILES['attachments'] : array() // phpcs:ignore
		);
		if ( is_wp_error( $id ) ) {
			self::back( 'error', $id->get_error_message() );
		}
		self::back( 'success', __( 'Ticket created. We will reply here and by email.', 'relay-stream-portal' ), RSP_Settings::page_url( 'support', array( 'ticket' => $id ) ) );
	}

	private static function do_ticket_reply() {
		if ( ! RSP_Rate_Limiter::hit( 'reply', 30, HOUR_IN_SECONDS ) ) {
			self::back( 'error', __( 'Too many messages. Please try again later.', 'relay-stream-portal' ) );
		}
		$tid    = (int) self::p( 'ticket' );
		$ticket = RSP_Tickets::get( $tid );
		if ( ! $ticket || (int) $ticket->user_id !== get_current_user_id() ) {
			self::back( 'error', __( 'Ticket not found.', 'relay-stream-portal' ) );
		}
		$r = RSP_Tickets::reply( $tid, get_current_user_id(), self::raw( 'body' ), false, isset( $_FILES['attachments'] ) ? $_FILES['attachments'] : array() ); // phpcs:ignore
		is_wp_error( $r ) ? self::back( 'error', $r->get_error_message() ) : self::back( 'success', __( 'Reply sent.', 'relay-stream-portal' ) );
	}

	private static function do_account_rename() {
		$acc = RSP_Accounts::get_owned( self::p( 'account' ), get_current_user_id() );
		$nn  = substr( self::p( 'nickname' ), 0, 80 );
		if ( ! $acc || '' === $nn ) {
			self::back( 'error', __( 'Account not found.', 'relay-stream-portal' ) );
		}
		RSP_Accounts::update( $acc->id, array( 'nickname' => $nn ) );
		self::back( 'success', __( 'Account renamed.', 'relay-stream-portal' ) );
	}

	private static function do_gift_email() {
		if ( ! RSP_Rate_Limiter::hit( 'giftmail', 10, HOUR_IN_SECONDS ) ) {
			self::back( 'error', __( 'Too many attempts. Please try again later.', 'relay-stream-portal' ) );
		}
		$gift = RSP_Gifts::get( (int) self::p( 'gift' ) );
		if ( ! $gift || (int) $gift->purchaser_id !== get_current_user_id() ) {
			self::back( 'error', __( 'Gift not found.', 'relay-stream-portal' ) );
		}
		RSP_Gifts::send_email( $gift, self::p( 'email' ) ) ? self::back( 'success', __( 'Gift sent.', 'relay-stream-portal' ) ) : self::back( 'error', __( 'Could not send the gift. Check the address.', 'relay-stream-portal' ) );
	}

	private static function do_notifications_read() {
		RSP_Notifications::mark_all_read( get_current_user_id() );
		wp_safe_redirect( self::return_url() );
		exit;
	}
}
