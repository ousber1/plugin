<?php
/**
 * Admin action handlers. Called only after RSP_Admin::handle() verified nonce + capability.
 *
 * @package RelayStreamPortal
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin actions.
 */
class RSP_Admin_Actions {

	/**
	 * Dispatch.
	 *
	 * @param string $do Action.
	 */
	public static function dispatch( $do ) {
		call_user_func( array( __CLASS__, $do ) );
	}

	/**
	 * POST text.
	 *
	 * @param string $k Key.
	 * @return string
	 */
	private static function p( $k ) {
		return isset( $_POST[ $k ] ) ? sanitize_text_field( wp_unslash( $_POST[ $k ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	}

	/**
	 * POST checkbox.
	 *
	 * @param string $k Key.
	 * @return int
	 */
	private static function b( $k ) {
		return empty( $_POST[ $k ] ) ? 0 : 1; // phpcs:ignore WordPress.Security.NonceVerification
	}

	/**
	 * Required reason.
	 *
	 * @return string
	 */
	private static function reason() {
		$r = self::p( 'reason' );
		if ( '' === trim( $r ) ) {
			RSP_Admin::done( 'error', __( 'Please enter a reason. It is stored in the audit log.', 'relay-stream-portal' ) );
		}
		return $r;
	}

	// phpcs:disable WordPress.Security.NonceVerification.Missing

	/**
	 * Settings groups.
	 */
	public static function save_settings() {
		$group = self::p( 'group' );
		$v     = array();
		switch ( $group ) {
			case 'branding':
				foreach ( array( 'brand_name', 'brand_tagline', 'text_welcome', 'text_welcome_sub', 'floating_label', 'player_label' ) as $k ) {
					$v[ $k ] = self::p( $k );
				}
				$v['support_email'] = sanitize_email( self::p( 'support_email' ) );
				$v['support_url']   = esc_url_raw( self::p( 'support_url' ) );
				$v['player_url']    = RSP_Guides::is_public_url( esc_url_raw( self::p( 'player_url' ) ) ) ? esc_url_raw( self::p( 'player_url' ) ) : '';
				$v['logo_id']       = (int) self::p( 'logo_id' );
				foreach ( array( 'color_accent', 'color_accent_2', 'color_dark_bg', 'color_dark_surface', 'color_light_bg', 'color_light_surface' ) as $k ) {
					$c = sanitize_hex_color( self::p( $k ) );
					if ( $c ) {
						$v[ $k ] = $c;
					}
				}
				$v['theme_default'] = in_array( self::p( 'theme_default' ), array( 'auto', 'dark', 'light' ), true ) ? self::p( 'theme_default' ) : 'auto';
				$v['radius']        = max( 0, min( 32, (int) self::p( 'radius' ) ) );
				$v['language']      = in_array( self::p( 'language' ), array( '', 'en_US', 'ar' ), true ) ? self::p( 'language' ) : '';
				foreach ( array( 'allow_theme_toggle', 'floating_support', 'full_width', 'show_credentials', 'player_enabled', 'registration_open', 'email_verification', 'delete_on_uninstall' ) as $k ) {
					$v[ $k ] = self::b( $k );
				}
				$pos  = isset( $_POST['nav_pos'] ) ? array_map( 'intval', (array) wp_unslash( $_POST['nav_pos'] ) ) : array();
				$mods = RSP_Settings::modules();
				$pos  = array_intersect_key( $pos, $mods );
				asort( $pos );
				$v['nav_order']      = array_keys( $pos );
				$visible             = isset( $_POST['modules_visible'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['modules_visible'] ) ) : array();
				$v['modules_hidden'] = array_values( array_diff( array_keys( $mods ), $visible, array( 'dashboard' ) ) );
				break;
			case 'referrals':
				$v['referral_enabled']     = self::b( 'referral_enabled' );
				$v['referral_days']        = max( 0, (int) self::p( 'referral_days' ) );
				$v['referral_review_days'] = max( 0, (int) self::p( 'referral_review_days' ) );
				$v['referral_min_total']   = max( 0, (float) self::p( 'referral_min_total' ) );
				$v['referral_cookie_days'] = max( 1, (int) self::p( 'referral_cookie_days' ) );
				$v['referral_reversal']    = in_array( self::p( 'referral_reversal' ), array( 'keep', 'reverse_unapplied', 'review' ), true ) ? self::p( 'referral_reversal' ) : 'reverse_unapplied';
				break;
			case 'gifts':
				$v['gift_enabled']     = self::b( 'gift_enabled' );
				$v['gift_expiry_days'] = max( 1, (int) self::p( 'gift_expiry_days' ) );
				break;
			case 'support':
				$v['support_categories'] = sanitize_textarea_field( wp_unslash( isset( $_POST['support_categories'] ) ? $_POST['support_categories'] : '' ) ); // phpcs:ignore
				$v['attach_types']       = implode( ',', array_filter( array_map( 'sanitize_key', explode( ',', self::p( 'attach_types' ) ) ), static function ( $e ) { return ! in_array( $e, array( 'php', 'phtml', 'phar', 'exe', 'js', 'html', 'htm', 'svg', 'sh' ), true ); } ) );
				$v['attach_max_mb']      = max( 1, min( 50, (int) self::p( 'attach_max_mb' ) ) );
				break;
			case 'notifications':
				$v['email_notifications'] = self::b( 'email_notifications' );
				$v['reminder_days']       = implode( ',', array_filter( array_map( 'absint', explode( ',', self::p( 'reminder_days' ) ) ) ) );
				$tpl                      = array();
				foreach ( (array) ( isset( $_POST['tpl'] ) ? wp_unslash( $_POST['tpl'] ) : array() ) as $k => $t ) { // phpcs:ignore
					$tpl[ sanitize_key( $k ) ] = array( 'subject' => sanitize_text_field( $t['subject'] ), 'body' => sanitize_textarea_field( $t['body'] ) );
				}
				$v['email_templates'] = $tpl;
				break;
			default:
				RSP_Admin::done( 'error', __( 'Unknown settings group.', 'relay-stream-portal' ) );
		}
		RSP_Settings::update( $v );
		RSP_Audit::log( 'settings_saved', 'settings', $group, '', array( 'keys' => array_keys( $v ) ) );
		RSP_Admin::done( 'success', __( 'Settings saved.', 'relay-stream-portal' ) );
	}

	/**
	 * Billing content.
	 */
	public static function save_billing() {
		$guides = array();
		foreach ( (array) ( isset( $_POST['payment_guides'] ) ? wp_unslash( $_POST['payment_guides'] ) : array() ) as $g ) { // phpcs:ignore
			if ( ! empty( $g['title'] ) ) {
				$guides[] = array( 'title' => sanitize_text_field( $g['title'] ), 'url' => esc_url_raw( $g['url'] ), 'text' => sanitize_text_field( $g['text'] ) );
			}
		}
		RSP_Settings::update(
			array(
				'included_features'  => sanitize_textarea_field( wp_unslash( isset( $_POST['included_features'] ) ? $_POST['included_features'] : '' ) ), // phpcs:ignore
				'refund_policy_text' => sanitize_textarea_field( wp_unslash( isset( $_POST['refund_policy_text'] ) ? $_POST['refund_policy_text'] : '' ) ), // phpcs:ignore
				'payment_guides'     => $guides,
				'hide_stale_unpaid'  => self::b( 'hide_stale_unpaid' ),
				'stale_unpaid_hours' => max( 1, (int) self::p( 'stale_unpaid_hours' ) ),
				'max_accounts'       => max( 1, (int) self::p( 'max_accounts' ) ),
			)
		);
		RSP_Audit::log( 'settings_saved', 'settings', 'billing' );
		RSP_Admin::done( 'success', __( 'Saved.', 'relay-stream-portal' ) );
	}

	/**
	 * API settings.
	 */
	public static function save_api() {
		$s     = RSP_Settings::all();
		$url   = esc_url_raw( self::p( 'api_base_url' ), array( 'https' ) );
		$hosts = implode( ',', array_filter( array_map( static function ( $h ) { return preg_replace( '/[^a-z0-9.-]/', '', strtolower( trim( $h ) ) ); }, explode( ',', self::p( 'api_allowed_hosts' ) ) ) ) );
		$v     = array(
			'api_mode'                => 'live' === self::p( 'api_mode' ) ? 'live' : 'manual',
			'api_base_url'            => $url,
			'api_docs_url'            => esc_url_raw( self::p( 'api_docs_url' ) ),
			'api_allowed_hosts'       => $hosts,
			'api_timeout'             => max( 5, min( 60, (int) self::p( 'api_timeout' ) ) ),
			'api_note_prefix'         => substr( preg_replace( '/[^A-Za-z0-9_-]/', '', self::p( 'api_note_prefix' ) ), 0, 16 ),
			'api_mapping_verified'    => self::b( 'api_mapping_verified' ),
			'reward_month_conversion' => self::b( 'reward_month_conversion' ),
		);
		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( $url && ( ! $host || ! in_array( strtolower( $host ), explode( ',', $hosts ), true ) ) ) {
			RSP_Admin::done( 'error', __( 'The API URL host must be listed in Allowed API hosts.', 'relay-stream-portal' ) );
		}
		$key = isset( $_POST['api_key'] ) ? trim( (string) wp_unslash( $_POST['api_key'] ) ) : ''; // phpcs:ignore
		if ( self::b( 'api_key_clear' ) ) {
			$v['api_key_enc'] = '';
		} elseif ( '' !== $key ) {
			$v['api_key_enc'] = RSP_Crypto::encrypt( $key );
		}
		RSP_Settings::update( $v );
		$changes = array_keys( array_diff_assoc( array_map( 'strval', array_intersect_key( $v, $s ) ), array_map( 'strval', array_intersect_key( $s, $v ) ) ) );
		RSP_Audit::log( 'api_settings_saved', 'settings', 'api', '', array( 'changed' => array_values( array_diff( $changes, array( 'api_key_enc' ) ) ), 'key_changed' => isset( $v['api_key_enc'] ), 'live' => RSP_Provider_Factory::is_live() ) );
		RSP_Admin::done( 'success', RSP_Provider_Factory::is_live() ? __( 'Saved. Live API mode is active.', 'relay-stream-portal' ) : __( 'Saved. Provisioning stays manual until mode is Live, a key is saved and the mapping is verified.', 'relay-stream-portal' ) );
	}

	/**
	 * Test connection (read-only reseller_info).
	 */
	public static function test_connection() {
		$r = RSP_Provider_Factory::tvplus()->test_connection();
		set_transient( 'rsp_test_result_' . get_current_user_id(), array_merge( $r->to_log(), array( 'username_masked' => isset( $r->data['username'] ) ? $r->data['username'] : '' ) ), 120 );
		RSP_Audit::log( 'api_test_connection', 'settings', 'api', '', $r->to_log() );
		RSP_Admin::done( $r->ok() ? 'success' : 'error', $r->ok() ? __( 'Connection OK (reseller_info).', 'relay-stream-portal' ) : __( 'Connection failed: ', 'relay-stream-portal' ) . $r->error );
	}

	/**
	 * Load packages (bouquet).
	 */
	public static function load_packages() {
		$r = RSP_Provider_Factory::tvplus()->list_packages();
		if ( $r->ok() ) {
			update_option( 'rsp_packages_cache', $r->data['packages'], false );
		}
		RSP_Audit::log( 'api_load_packages', 'settings', 'api', '', $r->to_log() );
		RSP_Admin::done( $r->ok() ? 'success' : 'error', $r->ok() ? sprintf( /* translators: %d */ __( 'Loaded %d packages.', 'relay-stream-portal' ), count( $r->data['packages'] ) ) : $r->error );
	}

	/**
	 * Run queue.
	 */
	public static function run_queue() {
		$n = RSP_Jobs::process_queue( 20 );
		RSP_Admin::done( 'success', sprintf( /* translators: %d */ __( 'Processed %d job(s).', 'relay-stream-portal' ), $n ) );
	}

	/**
	 * Retry.
	 */
	public static function job_retry() {
		$r = RSP_Jobs::staff_retry( (int) self::p( 'job' ), self::reason(), true );
		if ( is_wp_error( $r ) ) {
			RSP_Admin::done( 'error', $r->get_error_message() );
		}
		RSP_Jobs::run( (int) self::p( 'job' ) );
		RSP_Admin::done( 'success', __( 'Job retried. Check its new state below.', 'relay-stream-portal' ) );
	}

	/**
	 * Reconcile.
	 */
	public static function job_reconcile() {
		$r = RSP_Jobs::staff_reconcile( (int) self::p( 'job' ), self::reason() );
		is_wp_error( $r ) ? RSP_Admin::done( 'error', $r->get_error_message() ) : RSP_Admin::done( 'success', __( 'Reconciled: the panel confirmed the new expiry.', 'relay-stream-portal' ) );
	}

	/**
	 * Manual completion.
	 */
	public static function job_complete() {
		$fields = array();
		foreach ( array( 'username', 'url', 'mac', 'server', 'expiry', 'max_connections', 'provider_user_id' ) as $k ) {
			$fields[ $k ] = self::p( $k );
		}
		$fields['password'] = isset( $_POST['password'] ) ? trim( (string) wp_unslash( $_POST['password'] ) ) : ''; // phpcs:ignore
		$fields['url']      = esc_url_raw( $fields['url'] );
		$fields['server']   = esc_url_raw( $fields['server'] );
		$r                  = RSP_Jobs::manual_complete( (int) self::p( 'job' ), $fields, self::reason() );
		is_wp_error( $r ) ? RSP_Admin::done( 'error', $r->get_error_message() ) : RSP_Admin::done( 'success', __( 'Job completed and the customer was notified.', 'relay-stream-portal' ), admin_url( 'admin.php?page=rsp-api' ) );
	}

	/**
	 * Cancel.
	 */
	public static function job_cancel() {
		RSP_Jobs::cancel( (int) self::p( 'job' ), self::reason() );
		RSP_Admin::done( 'success', __( 'Job cancelled.', 'relay-stream-portal' ) );
	}

	/**
	 * Save account.
	 */
	public static function account_save() {
		$a = RSP_Accounts::get_by_uuid( self::p( 'uuid' ) );
		if ( ! $a ) {
			RSP_Admin::done( 'error', __( 'Account not found.', 'relay-stream-portal' ) );
		}
		$reason = self::reason();
		$upd    = array(
			'nickname'         => substr( self::p( 'nickname' ), 0, 80 ),
			'plan_label'       => substr( self::p( 'plan_label' ), 0, 120 ),
			'provider_user_id' => self::p( 'provider_user_id' ) ? self::p( 'provider_user_id' ) : null,
			'max_connections'  => '' === self::p( 'max_connections' ) ? null : max( 0, (int) self::p( 'max_connections' ) ),
		);
		if ( in_array( self::p( 'status' ), RSP_Accounts::STATUSES, true ) ) {
			$upd['status'] = self::p( 'status' );
		}
		$exp = self::p( 'expiry' );
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $exp ) ) {
			$upd['expires_at'] = $exp . ' 23:59:59';
			if ( ! $a->activated_at && 'active' === ( isset( $upd['status'] ) ? $upd['status'] : $a->status ) ) {
				$upd['activated_at'] = RSP_Installer::now();
			}
		}
		$creds = array();
		foreach ( array( 'server', 'username', 'password', 'url', 'mac' ) as $k ) {
			$val = isset( $_POST[ 'cred_' . $k ] ) ? trim( (string) wp_unslash( $_POST[ 'cred_' . $k ] ) ) : ''; // phpcs:ignore
			if ( '' !== $val ) {
				$creds[ $k ] = in_array( $k, array( 'server', 'url' ), true ) ? esc_url_raw( $val ) : sanitize_text_field( $val );
			}
		}
		if ( $creds ) {
			RSP_Accounts::set_credentials( $a->id, array_merge( RSP_Accounts::credentials( $a ), $creds ) );
		}
		RSP_Accounts::update( $a->id, $upd );
		$changed = array();
		foreach ( $upd as $k => $val ) {
			if ( (string) $a->$k !== (string) $val ) {
				$changed[ $k ] = $val;
			}
		}
		RSP_Audit::log( 'account_override', 'account', $a->uuid, $reason, array( 'changed' => $changed, 'credentials_changed' => array_keys( $creds ) ) );
		RSP_Admin::done( 'success', __( 'Account saved.', 'relay-stream-portal' ) );
	}

	/**
	 * Manual account creation (already provisioned in the panel by staff).
	 */
	public static function account_create() {
		$uid = (int) self::p( 'user_id' );
		if ( ! get_userdata( $uid ) ) {
			RSP_Admin::done( 'error', __( 'Customer not found.', 'relay-stream-portal' ) );
		}
		$reason = self::reason();
		$acc    = RSP_Accounts::create( array( 'user_id' => $uid, 'nickname' => self::p( 'nickname' ), 'provider' => 'manual', 'provider_type' => 'mag' === self::p( 'provider_type' ) ? 'mag' : 'm3u', 'status' => 'pending_activation', 'plan_label' => self::p( 'plan_label' ) ) );
		$job    = RSP_Jobs::enqueue( 'manual:' . $acc->uuid, 'create', array( 'account_id' => (int) $acc->id, 'user_id' => $uid, 'payload' => array( 'months' => 0, 'plan_label' => self::p( 'plan_label' ), 'manual' => 1 ) ) );
		$fields = array();
		foreach ( array( 'username', 'url', 'mac', 'server', 'expiry', 'max_connections', 'provider_user_id' ) as $k ) {
			$fields[ $k ] = self::p( $k );
		}
		$fields['password'] = isset( $_POST['password'] ) ? trim( (string) wp_unslash( $_POST['password'] ) ) : ''; // phpcs:ignore
		$r                  = RSP_Jobs::manual_complete( (int) $job->id, $fields, $reason );
		is_wp_error( $r ) ? RSP_Admin::done( 'error', $r->get_error_message() ) : RSP_Admin::done( 'success', __( 'Account created and activated.', 'relay-stream-portal' ) );
	}

	/**
	 * Refresh from panel.
	 */
	public static function account_refresh() {
		$a = RSP_Accounts::get_by_uuid( self::p( 'uuid' ) );
		if ( ! $a || ! RSP_Provider_Factory::is_live() ) {
			RSP_Admin::done( 'error', __( 'Live mode is required.', 'relay-stream-portal' ) );
		}
		$r = RSP_Provider_Factory::get()->lookup_account( RSP_Accounts::credentials( $a ), $a->provider_type );
		RSP_Audit::log( 'account_refreshed', 'account', $a->uuid, '', $r->to_log() );
		if ( ! $r->ok() ) {
			RSP_Admin::done( 'error', $r->error );
		}
		$exp = $r->data['expire'] . ' 23:59:59';
		RSP_Accounts::update( $a->id, array( 'expires_at' => $exp, 'status' => strtotime( $exp . ' UTC' ) < time() ? 'expired' : ( '0' === $r->data['enabled'] ? 'suspended' : 'active' ) ) );
		RSP_Admin::done( 'success', sprintf( /* translators: %s date */ __( 'Panel reports expiry %s.', 'relay-stream-portal' ), $r->data['expire'] ) );
	}

	/**
	 * Staff-queued renewal (complimentary / goodwill).
	 */
	public static function account_renew() {
		$a      = RSP_Accounts::get_by_uuid( self::p( 'uuid' ) );
		$months = (int) self::p( 'months' );
		if ( ! $a || ! in_array( $months, array( 1, 3, 6, 12 ), true ) ) {
			RSP_Admin::done( 'error', __( 'Invalid request.', 'relay-stream-portal' ) );
		}
		$reason = self::reason();
		if ( RSP_Jobs::open_for_account( (int) $a->id ) ) {
			RSP_Admin::done( 'error', __( 'This account already has an open operation.', 'relay-stream-portal' ) );
		}
		$job = RSP_Jobs::enqueue( 'staff:' . wp_generate_uuid4(), 'renew', array( 'account_id' => (int) $a->id, 'user_id' => (int) $a->user_id, 'payload' => array( 'months' => $months, 'plan_label' => $a->plan_label, 'staff' => get_current_user_id() ) ) );
		RSP_Audit::log( 'staff_renewal_queued', 'account', $a->uuid, $reason, array( 'months' => $months, 'job' => $job->id ) );
		RSP_Admin::done( 'success', __( 'Renewal queued.', 'relay-stream-portal' ) );
	}

	/**
	 * Reveal credentials to staff (audited, shown once).
	 */
	public static function account_reveal() {
		$a = RSP_Accounts::get_by_uuid( self::p( 'uuid' ) );
		if ( ! $a ) {
			RSP_Admin::done( 'error', __( 'Account not found.', 'relay-stream-portal' ) );
		}
		$reason = self::reason();
		RSP_Audit::log( 'credentials_revealed', 'account', $a->uuid, $reason, array( 'by' => 'staff' ) );
		set_transient( 'rsp_reveal_' . get_current_user_id() . '_' . $a->id, 1, 60 );
		RSP_Admin::done( 'success', __( 'Credentials shown below for this page load only.', 'relay-stream-portal' ) );
	}

	/**
	 * Save plan display settings.
	 */
	public static function plans_save() {
		foreach ( (array) ( isset( $_POST['plan'] ) ? wp_unslash( $_POST['plan'] ) : array() ) as $row ) { // phpcs:ignore
			$p = wc_get_product( (int) $row['id'] );
			if ( ! $p || 'yes' !== $p->get_meta( '_rsp_is_plan' ) ) {
				continue;
			}
			$p->update_meta_data( '_rsp_available', empty( $row['available'] ) ? 'no' : 'yes' );
			$p->update_meta_data( '_rsp_featured', empty( $row['featured'] ) ? 'no' : 'yes' );
			$p->update_meta_data( '_rsp_order', (int) $row['order'] );
			$p->save();
		}
		RSP_Audit::log( 'plans_display_saved', 'product', 'bulk' );
		RSP_Admin::done( 'success', __( 'Plans saved.', 'relay-stream-portal' ) );
	}

	/**
	 * Demo plans.
	 */
	public static function demo_plans() {
		if ( ! RSP_Plugin::has_woocommerce() ) {
			RSP_Admin::done( 'error', __( 'WooCommerce is required.', 'relay-stream-portal' ) );
		}
		RSP_Woocommerce::create_demo_plans();
		RSP_Admin::done( 'success', __( 'Demo plans created. Review prices before going live.', 'relay-stream-portal' ) );
	}

	/**
	 * Referral staff actions.
	 */
	public static function referral_action() {
		global $wpdb;
		$id     = (int) self::p( 'id' );
		$op     = self::p( 'op' );
		$reason = self::reason();
		$t      = RSP_Installer::table( 'referrals' );
		$ref    = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE id = %d", $id ) ); // phpcs:ignore
		if ( ! $ref ) {
			RSP_Admin::done( 'error', __( 'Referral not found.', 'relay-stream-portal' ) );
		}
		if ( 'approve' === $op && 'pending' === $ref->status ) {
			$wpdb->update( $t, array( 'eligible_at' => RSP_Installer::now() ), array( 'id' => $id ) );
			RSP_Referrals::process_rewards();
		} elseif ( 'reject' === $op ) {
			$wpdb->update( $t, array( 'status' => 'rejected', 'updated_at' => RSP_Installer::now() ), array( 'id' => $id ) );
		} elseif ( 'keep' === $op && 'review' === $ref->status ) {
			$wpdb->update( $t, array( 'status' => 'rewarded', 'updated_at' => RSP_Installer::now() ), array( 'id' => $id ) );
		}
		RSP_Audit::log( 'referral_' . $op, 'referral', $id, $reason );
		RSP_Admin::done( 'success', __( 'Referral updated.', 'relay-stream-portal' ) );
	}

	/**
	 * Ledger adjustment.
	 */
	public static function ledger_adjust() {
		$uid  = (int) self::p( 'user_id' );
		$days = (int) self::p( 'days' );
		if ( ! $uid || 0 === $days ) {
			RSP_Admin::done( 'error', __( 'Enter a non-zero number of days.', 'relay-stream-portal' ) );
		}
		$reason = self::reason();
		RSP_Referrals::ledger_add( $uid, $days, 'adjustment', 'adjust:' . wp_generate_uuid4(), array( 'note' => $reason ) );
		RSP_Audit::log( 'ledger_adjusted', 'user', $uid, $reason, array( 'days' => $days ) );
		RSP_Admin::done( 'success', __( 'Ledger adjusted.', 'relay-stream-portal' ) );
	}

	/**
	 * Gift staff actions.
	 */
	public static function gift_action() {
		global $wpdb;
		$id     = (int) self::p( 'id' );
		$op     = self::p( 'op' );
		$reason = self::reason();
		$t      = RSP_Installer::table( 'gifts' );
		if ( 'revoke' === $op ) {
			$wpdb->query( $wpdb->prepare( "UPDATE $t SET status = 'revoked' WHERE id = %d AND status = 'issued'", $id ) ); // phpcs:ignore
		} elseif ( 'extend' === $op ) {
			$wpdb->query( $wpdb->prepare( "UPDATE $t SET expires_at = DATE_ADD(expires_at, INTERVAL 30 DAY) WHERE id = %d AND status = 'issued'", $id ) ); // phpcs:ignore
		} elseif ( 'resolve' === $op ) {
			$wpdb->query( $wpdb->prepare( "UPDATE $t SET status = 'redeemed' WHERE id = %d AND status = 'review'", $id ) ); // phpcs:ignore
		}
		RSP_Audit::log( 'gift_' . $op, 'gift', $id, $reason );
		RSP_Admin::done( 'success', __( 'Gift updated.', 'relay-stream-portal' ) );
	}

	/**
	 * Guides.
	 */
	public static function guides_save() {
		$in = isset( $_POST['g'] ) ? (array) wp_unslash( $_POST['g'] ) : array(); // phpcs:ignore
		update_option( 'rsp_guides', RSP_Guides::sanitize( $in ), false );
		RSP_Audit::log( 'guides_saved', 'settings', 'guides' );
		RSP_Admin::done( 'success', __( 'Guides saved. URLs that were not public http(s) links or contained credentials were removed.', 'relay-stream-portal' ) );
	}

	/**
	 * Staff ticket reply / internal note.
	 */
	public static function ticket_reply() {
		$tid = (int) self::p( 'ticket' );
		$r   = RSP_Tickets::reply( $tid, get_current_user_id(), isset( $_POST['body'] ) ? (string) wp_unslash( $_POST['body'] ) : '', self::b( 'internal' ) === 1, isset( $_FILES['attachments'] ) ? $_FILES['attachments'] : array() ); // phpcs:ignore
		is_wp_error( $r ) ? RSP_Admin::done( 'error', $r->get_error_message() ) : RSP_Admin::done( 'success', self::b( 'internal' ) ? __( 'Internal note added.', 'relay-stream-portal' ) : __( 'Reply sent.', 'relay-stream-portal' ) );
	}

	/**
	 * Ticket meta update.
	 */
	public static function ticket_update() {
		RSP_Tickets::staff_update( (int) self::p( 'ticket' ), array( 'status' => self::p( 'status' ), 'priority' => self::p( 'priority' ), 'assigned_to' => self::p( 'assigned_to' ) ) );
		RSP_Admin::done( 'success', __( 'Ticket updated.', 'relay-stream-portal' ) );
	}

	/**
	 * Announcements.
	 */
	public static function announcements_save() {
		$out = array();
		foreach ( (array) ( isset( $_POST['a'] ) ? wp_unslash( $_POST['a'] ) : array() ) as $a ) { // phpcs:ignore
			if ( empty( $a['title'] ) ) {
				continue;
			}
			$out[] = array(
				'id'          => ! empty( $a['id'] ) ? sanitize_key( $a['id'] ) : 'a' . strtolower( wp_generate_password( 8, false ) ),
				'title'       => sanitize_text_field( $a['title'] ),
				'body'        => sanitize_textarea_field( $a['body'] ),
				'tone'        => in_array( $a['tone'], array( 'info', 'success', 'warning' ), true ) ? $a['tone'] : 'info',
				'dismissible' => empty( $a['dismissible'] ) ? 0 : 1,
				'active'      => empty( $a['active'] ) ? 0 : 1,
				'starts'      => preg_match( '/^\d{4}-\d{2}-\d{2}$/', $a['starts'] ) ? $a['starts'] : '',
				'ends'        => preg_match( '/^\d{4}-\d{2}-\d{2}$/', $a['ends'] ) ? $a['ends'] : '',
			);
		}
		update_option( 'rsp_announcements', $out, false );
		RSP_Audit::log( 'announcements_saved', 'settings', 'announcements', '', array( 'count' => count( $out ) ) );
		RSP_Admin::done( 'success', __( 'Announcements saved.', 'relay-stream-portal' ) );
	}

	/**
	 * Create missing pages.
	 */
	public static function pages_create() {
		$r = RSP_Setup::create_pages( 'all' === self::p( 'mode' ), self::b( 'elementor' ) && did_action( 'elementor/loaded' ) );
		RSP_Admin::done( 'success', sprintf( /* translators: %d */ __( 'Created %d page(s).', 'relay-stream-portal' ), $r ) );
	}

	/**
	 * Map pages.
	 */
	public static function pages_map() {
		$map = array();
		foreach ( (array) ( isset( $_POST['pages'] ) ? wp_unslash( $_POST['pages'] ) : array() ) as $role => $id ) { // phpcs:ignore
			$role = sanitize_key( $role );
			if ( isset( RSP_Settings::page_roles()[ $role ] ) && (int) $id > 0 && 'page' === get_post_type( (int) $id ) ) {
				$map[ $role ] = (int) $id;
			}
		}
		update_option( 'rsp_pages', $map, true );
		RSP_Audit::log( 'pages_mapped', 'settings', 'pages', '', $map );
		RSP_Admin::done( 'success', __( 'Page mapping saved.', 'relay-stream-portal' ) );
	}

	/**
	 * Clear order review flag.
	 */
	public static function order_review_clear() {
		$o = wc_get_order( (int) self::p( 'order_id' ) );
		if ( $o ) {
			$reason = self::reason();
			$o->delete_meta_data( '_rsp_needs_review' );
			$o->save();
			$o->add_order_note( 'Relay Stream Portal: review completed — ' . $reason );
			RSP_Audit::log( 'order_reviewed', 'order', $o->get_id(), $reason );
		}
		RSP_Admin::done( 'success', __( 'Order marked as reviewed.', 'relay-stream-portal' ) );
	}
	// phpcs:enable
}
