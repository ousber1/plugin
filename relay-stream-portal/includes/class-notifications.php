<?php
/**
 * In-portal notifications and email templates. Emails never contain player credentials or API secrets.
 *
 * @package RelayStreamPortal
 */

defined( 'ABSPATH' ) || exit;

/**
 * Notifications.
 */
class RSP_Notifications {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'rsp_job_succeeded', array( __CLASS__, 'on_job_succeeded' ), 20, 2 );
		add_action( 'rsp_daily', array( __CLASS__, 'expiry_reminders' ) );
	}

	/**
	 * Table.
	 *
	 * @return string
	 */
	private static function t() {
		return RSP_Installer::table( 'notifications' );
	}

	/**
	 * Create a notification (deduplicated by key) and optionally email.
	 *
	 * @param int         $user_id User.
	 * @param string      $type Type.
	 * @param string      $title Title.
	 * @param string      $body Body.
	 * @param string      $link Link.
	 * @param string|null $dedupe Dedupe key.
	 * @param bool        $email Send email template of the same type.
	 * @param array       $vars Template vars.
	 * @return bool Whether created.
	 */
	public static function notify( $user_id, $type, $title, $body, $link = '', $dedupe = null, $email = true, array $vars = array() ) {
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				'INSERT IGNORE INTO ' . self::t() . ' (user_id, type, title, body, link, dedupe_key, created_at) VALUES (%d,%s,%s,%s,%s,' . ( $dedupe ? '%s' : 'NULL' ) . ',%s)', // phpcs:ignore
				array_merge(
					array( $user_id, $type, wp_strip_all_tags( $title ), wp_strip_all_tags( $body ), esc_url_raw( $link ) ),
					$dedupe ? array( substr( $dedupe, 0, 120 ) ) : array(),
					array( RSP_Installer::now() )
				)
			)
		);
		$created = 1 === (int) $wpdb->rows_affected;
		if ( $created && $email && RSP_Settings::get( 'email_notifications' ) ) {
			$user = get_userdata( $user_id );
			if ( $user && '0' !== get_user_meta( $user_id, 'rsp_email_opt_in', true ) ) {
				self::send_template( $user->user_email, $type, array_merge( array( 'name' => $user->display_name, 'link' => $link ), $vars ) );
			}
		}
		return $created;
	}

	/**
	 * Render and send a template.
	 *
	 * @param string $to Recipient.
	 * @param string $key Template key.
	 * @param array  $vars Variables.
	 * @return bool
	 */
	public static function send_template( $to, $key, array $vars ) {
		$templates = RSP_Settings::get( 'email_templates', array() );
		if ( empty( $templates[ $key ] ) ) {
			return false;
		}
		$vars    = array_merge( array( 'site' => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ), $vars );
		$replace = array();
		foreach ( $vars as $k => $v ) {
			$replace[ '{' . $k . '}' ] = wp_strip_all_tags( (string) $v );
		}
		$subject = strtr( $templates[ $key ]['subject'], $replace );
		$body    = strtr( $templates[ $key ]['body'], $replace );
		return wp_mail( $to, $subject, $body );
	}

	/**
	 * Unread + recent for a user.
	 *
	 * @param int $user_id User.
	 * @param int $limit Limit.
	 * @return object[]
	 */
	public static function recent( $user_id, $limit = 10 ) {
		global $wpdb;
		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT id, type, title, body, link, read_at, created_at FROM ' . self::t() . ' WHERE user_id = %d ORDER BY id DESC LIMIT %d', $user_id, $limit ) ); // phpcs:ignore
	}

	/**
	 * Unread count.
	 *
	 * @param int $user_id User.
	 * @return int
	 */
	public static function unread_count( $user_id ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::t() . ' WHERE user_id = %d AND read_at IS NULL', $user_id ) ); // phpcs:ignore
	}

	/**
	 * Mark all read (own notifications only).
	 *
	 * @param int $user_id User.
	 */
	public static function mark_all_read( $user_id ) {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . self::t() . ' SET read_at = %s WHERE user_id = %d AND read_at IS NULL', RSP_Installer::now(), $user_id ) ); // phpcs:ignore
	}

	/**
	 * Activation notice.
	 *
	 * @param object $job Job.
	 * @param object $account Account.
	 */
	public static function on_job_succeeded( $job, $account ) {
		if ( ! $account ) {
			return;
		}
		$expiry = $account->expires_at ? mysql2date( get_option( 'date_format' ), $account->expires_at ) : '';
		self::notify(
			(int) $account->user_id,
			'activation',
			'create' === $job->type ? __( 'Your account is active', 'relay-stream-portal' ) : __( 'Your account was extended', 'relay-stream-portal' ),
			/* translators: 1: account name 2: date */
			sprintf( __( '%1$s is active until %2$s.', 'relay-stream-portal' ), $account->nickname, $expiry ),
			RSP_Settings::page_url( 'accounts' ),
			'job_done:' . $job->id,
			true,
			array( 'account' => $account->nickname, 'expiry' => $expiry )
		);
	}

	/**
	 * Daily expiry reminders on configured offsets (deduplicated per account+expiry+offset).
	 */
	public static function expiry_reminders() {
		global $wpdb;
		$offsets = array_filter( array_map( 'intval', explode( ',', (string) RSP_Settings::get( 'reminder_days', '7,3,1' ) ) ) );
		foreach ( $offsets as $days ) {
			$from = gmdate( 'Y-m-d 00:00:00', time() + $days * DAY_IN_SECONDS );
			$to   = gmdate( 'Y-m-d 23:59:59', time() + $days * DAY_IN_SECONDS );
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . RSP_Installer::table( 'accounts' ) . " WHERE status = 'active' AND expires_at BETWEEN %s AND %s", $from, $to ) ); // phpcs:ignore
			foreach ( (array) $rows as $a ) {
				$expiry = mysql2date( get_option( 'date_format' ), $a->expires_at );
				self::notify(
					(int) $a->user_id,
					'expiry',
					/* translators: %d days */
					sprintf( _n( 'Expires in %d day', 'Expires in %d days', $days, 'relay-stream-portal' ), $days ),
					/* translators: 1: account 2: date */
					sprintf( __( '%1$s expires on %2$s. Plans never renew automatically.', 'relay-stream-portal' ), $a->nickname, $expiry ),
					RSP_Settings::page_url( 'plans', array( 'renew' => $a->uuid ) ),
					'expiry:' . $a->id . ':' . substr( $a->expires_at, 0, 10 ) . ':' . $days,
					true,
					array( 'account' => $a->nickname, 'expiry' => $expiry, 'days' => $days )
				);
			}
		}
	}
}
