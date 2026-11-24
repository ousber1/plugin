<?php
/**
 * Activation, versioned migrations, capabilities.
 *
 * Data is preserved on deactivation and update. Only uninstall.php removes it,
 * and only when the administrator opted in.
 *
 * @package RelayStreamPortal
 */

defined( 'ABSPATH' ) || exit;

/**
 * Installer.
 */
class RSP_Installer {

	const CAPS = array(
		'rsp_view_admin'          => 'View portal administration',
		'rsp_manage_settings'     => 'Manage portal settings, branding, pages and API configuration',
		'rsp_manage_billing'      => 'Manage plans, orders, refunds, gifts and referral rewards',
		'rsp_manage_provisioning' => 'Manage streaming accounts, job queue and manual activations',
		'rsp_manage_support'      => 'Manage support tickets and announcements',
		'rsp_view_credentials'    => 'Reveal customer player credentials in the admin',
		'rsp_export_data'         => 'Export operational data',
	);

	/**
	 * Activation hook.
	 */
	public static function activate() {
		self::migrate();
		self::add_caps();
		RSP_Settings::seed_defaults();
		RSP_Jobs::schedule();
		RSP_Tickets::ensure_private_dir();
		RSP_Setup::install_pages(); // Dashboard, Login, Sign up, all portal pages and Checkout.
		update_option( 'rsp_activated_at', time(), false );
		flush_rewrite_rules();
	}

	/**
	 * Deactivation: unschedule cron only. Data is preserved.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'rsp_process_queue' );
		wp_clear_scheduled_hook( 'rsp_daily' );
		RSP_Setup::restore_checkout(); // Give WooCommerce its previous checkout page back.
	}

	/**
	 * Run migrations when the stored DB version is behind.
	 */
	public static function maybe_upgrade() {
		if ( (int) get_option( 'rsp_db_version', 0 ) < RSP_DB_VERSION ) {
			self::migrate();
			self::add_caps();
			RSP_Settings::seed_defaults();
		}
	}

	/**
	 * Table name helper.
	 *
	 * @param string $name Short name.
	 * @return string
	 */
	public static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'rsp_' . $name;
	}

	/**
	 * Apply ordered migrations.
	 */
	public static function migrate() {
		$current = (int) get_option( 'rsp_db_version', 0 );
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// Migration 1-3 are idempotent dbDelta schemas; re-running converges the schema.
		if ( $current < RSP_DB_VERSION ) {
			foreach ( self::schema() as $sql ) {
				dbDelta( $sql );
			}
		}
		// v5: new visual defaults — replace previous defaults only where the admin never changed them.
		if ( $current > 0 && $current < 5 ) {
			$st  = (array) get_option( 'rsp_settings', array() );
			$old = array(
				'text_welcome'     => 'Welcome back, {name}',
				'text_welcome_sub' => 'Everything about your streaming accounts in one place.',
				'color_accent'     => '#14b8a6',
				'color_accent_2'   => '#a78bfa',
				'color_dark_bg'    => '#0b1016',
				'color_dark_surface' => '#121a23',
			);
			$new = RSP_Settings::defaults();
			foreach ( $old as $k => $v ) {
				if ( isset( $st[ $k ] ) && $st[ $k ] === $v ) {
					$st[ $k ] = $new[ $k ];
				}
			}
			if ( isset( $st['payment_guides'][0]['title'] ) && 'Paying by card' === $st['payment_guides'][0]['title'] ) {
				$st['payment_guides'] = $new['payment_guides'];
			}
			update_option( 'rsp_settings', $st, true );
		}
		// v6: MegaOTT only — drop TVPlus settings, allow megaott.net, create missing pages incl. Checkout.
		if ( $current > 0 && $current < 6 ) {
			$st = (array) get_option( 'rsp_settings', array() );
			unset( $st['api_key_enc'], $st['api_base_url'], $st['api_provider'] );
			$hosts = array_filter( array_map( 'trim', explode( ',', isset( $st['api_allowed_hosts'] ) ? (string) $st['api_allowed_hosts'] : '' ) ) );
			$hosts = array_values( array_diff( $hosts, array( 'tvpluspanel.net' ) ) );
			if ( ! in_array( 'megaott.net', $hosts, true ) ) {
				$hosts[] = 'megaott.net';
			}
			$st['api_allowed_hosts'] = implode( ',', $hosts );
			if ( isset( $st['api_docs_url'] ) && false !== strpos( (string) $st['api_docs_url'], 'tvplus' ) ) {
				$st['api_docs_url'] = 'https://megaott.net/docs/';
			}
			update_option( 'rsp_settings', $st, true );
			RSP_Settings::flush();
			add_action( 'init', array( 'RSP_Setup', 'install_pages' ), 20 );
		}
		// Record applied versions (v4 widened tickets.status to fit "awaiting_customer"; dbDelta alters the column).
		if ( $current < RSP_DB_VERSION ) {
			$log = (array) get_option( 'rsp_db_migrations', array() );
			for ( $v = max( 1, $current + 1 ); $v <= RSP_DB_VERSION; $v++ ) {
				$log[ $v ] = time();
			}
			update_option( 'rsp_db_migrations', $log, false );
		}
		update_option( 'rsp_db_version', RSP_DB_VERSION, true );
	}

	/**
	 * Full schema (dbDelta format).
	 *
	 * @return string[]
	 */
	public static function schema() {
		global $wpdb;
		$c = $wpdb->get_charset_collate();
		$t = array(
			'accounts'      => self::table( 'accounts' ),
			'jobs'          => self::table( 'jobs' ),
			'audit'         => self::table( 'audit' ),
			'referrals'     => self::table( 'referrals' ),
			'ledger'        => self::table( 'ledger' ),
			'gifts'         => self::table( 'gifts' ),
			'tickets'       => self::table( 'tickets' ),
			'messages'      => self::table( 'ticket_messages' ),
			'attachments'   => self::table( 'attachments' ),
			'notifications' => self::table( 'notifications' ),
		);

		return array(
			"CREATE TABLE {$t['accounts']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				uuid char(36) NOT NULL,
				user_id bigint(20) unsigned NOT NULL,
				nickname varchar(80) NOT NULL DEFAULT '',
				provider varchar(20) NOT NULL DEFAULT 'manual',
				provider_type varchar(20) NOT NULL DEFAULT 'm3u',
				provider_user_id varchar(64) DEFAULT NULL,
				credentials longtext NULL,
				status varchar(24) NOT NULL DEFAULT 'pending_payment',
				plan_label varchar(120) NOT NULL DEFAULT '',
				package_id varchar(32) NOT NULL DEFAULT '',
				max_connections smallint(5) unsigned DEFAULT NULL,
				activated_at datetime DEFAULT NULL,
				expires_at datetime DEFAULT NULL,
				source_order_id bigint(20) unsigned DEFAULT NULL,
				review_reason varchar(255) NOT NULL DEFAULT '',
				lock_token char(32) DEFAULT NULL,
				lock_expires int(10) unsigned DEFAULT NULL,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY uuid (uuid),
				KEY user_status (user_id,status),
				KEY expires_at (expires_at),
				KEY provider_user_id (provider_user_id)
			) $c;",
			"CREATE TABLE {$t['jobs']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				uuid char(36) NOT NULL,
				idempotency_key varchar(120) NOT NULL,
				type varchar(24) NOT NULL,
				state varchar(16) NOT NULL DEFAULT 'pending',
				step varchar(24) NOT NULL DEFAULT 'start',
				account_id bigint(20) unsigned DEFAULT NULL,
				user_id bigint(20) unsigned DEFAULT NULL,
				order_id bigint(20) unsigned DEFAULT NULL,
				order_item_id bigint(20) unsigned DEFAULT NULL,
				payload longtext NULL,
				result longtext NULL,
				pre_expiry datetime DEFAULT NULL,
				attempts smallint(5) unsigned NOT NULL DEFAULT 0,
				next_attempt_at datetime NOT NULL,
				last_error varchar(500) NOT NULL DEFAULT '',
				review_reason varchar(255) NOT NULL DEFAULT '',
				locked_by char(32) DEFAULT NULL,
				locked_at datetime DEFAULT NULL,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY idempotency_key (idempotency_key),
				UNIQUE KEY uuid (uuid),
				KEY state_next (state,next_attempt_at),
				KEY account_id (account_id),
				KEY order_id (order_id)
			) $c;",
			"CREATE TABLE {$t['audit']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				actor_id bigint(20) unsigned NOT NULL DEFAULT 0,
				action varchar(64) NOT NULL,
				target_type varchar(32) NOT NULL DEFAULT '',
				target_id varchar(64) NOT NULL DEFAULT '',
				reason varchar(500) NOT NULL DEFAULT '',
				context longtext NULL,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY action (action),
				KEY target (target_type,target_id),
				KEY created_at (created_at)
			) $c;",
			"CREATE TABLE {$t['referrals']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				referrer_id bigint(20) unsigned NOT NULL,
				referee_id bigint(20) unsigned NOT NULL,
				code varchar(32) NOT NULL,
				status varchar(16) NOT NULL DEFAULT 'invited',
				order_id bigint(20) unsigned DEFAULT NULL,
				reward_days int(11) NOT NULL DEFAULT 0,
				eligible_at datetime DEFAULT NULL,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY referee_id (referee_id),
				KEY referrer_status (referrer_id,status)
			) $c;",
			"CREATE TABLE {$t['ledger']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				user_id bigint(20) unsigned NOT NULL,
				delta_days int(11) NOT NULL,
				reason varchar(40) NOT NULL,
				source_key varchar(120) NOT NULL,
				referral_id bigint(20) unsigned DEFAULT NULL,
				account_id bigint(20) unsigned DEFAULT NULL,
				job_id bigint(20) unsigned DEFAULT NULL,
				note varchar(255) NOT NULL DEFAULT '',
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY source_key (source_key),
				KEY user_id (user_id)
			) $c;",
			"CREATE TABLE {$t['gifts']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				code_hash char(64) NOT NULL,
				code_enc text NOT NULL,
				code_hint varchar(8) NOT NULL DEFAULT '',
				purchaser_id bigint(20) unsigned NOT NULL,
				order_id bigint(20) unsigned NOT NULL,
				order_item_id bigint(20) unsigned NOT NULL,
				plan_snapshot longtext NOT NULL,
				status varchar(16) NOT NULL DEFAULT 'issued',
				recipient_email varchar(190) NOT NULL DEFAULT '',
				redeemed_by bigint(20) unsigned DEFAULT NULL,
				redeemed_account_id bigint(20) unsigned DEFAULT NULL,
				redeemed_job_id bigint(20) unsigned DEFAULT NULL,
				redeemed_at datetime DEFAULT NULL,
				expires_at datetime NOT NULL,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY code_hash (code_hash),
				UNIQUE KEY order_item_id (order_item_id),
				KEY purchaser_id (purchaser_id),
				KEY status (status)
			) $c;",
			"CREATE TABLE {$t['tickets']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				user_id bigint(20) unsigned NOT NULL,
				subject varchar(190) NOT NULL,
				category varchar(40) NOT NULL DEFAULT 'general',
				status varchar(24) NOT NULL DEFAULT 'open',
				priority varchar(10) NOT NULL DEFAULT 'normal',
				order_id bigint(20) unsigned DEFAULT NULL,
				account_id bigint(20) unsigned DEFAULT NULL,
				assigned_to bigint(20) unsigned DEFAULT NULL,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY user_id (user_id),
				KEY status (status),
				KEY assigned_to (assigned_to)
			) $c;",
			"CREATE TABLE {$t['messages']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				ticket_id bigint(20) unsigned NOT NULL,
				author_id bigint(20) unsigned NOT NULL,
				body longtext NOT NULL,
				is_internal tinyint(1) NOT NULL DEFAULT 0,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY ticket_id (ticket_id)
			) $c;",
			"CREATE TABLE {$t['attachments']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				ticket_id bigint(20) unsigned NOT NULL,
				message_id bigint(20) unsigned NOT NULL,
				user_id bigint(20) unsigned NOT NULL,
				stored_name varchar(80) NOT NULL,
				original_name varchar(190) NOT NULL,
				mime varchar(100) NOT NULL,
				size int(10) unsigned NOT NULL,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY ticket_id (ticket_id)
			) $c;",
			"CREATE TABLE {$t['notifications']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				user_id bigint(20) unsigned NOT NULL,
				type varchar(40) NOT NULL,
				title varchar(190) NOT NULL,
				body text NOT NULL,
				link varchar(500) NOT NULL DEFAULT '',
				dedupe_key varchar(120) DEFAULT NULL,
				read_at datetime DEFAULT NULL,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY dedupe_key (dedupe_key),
				KEY user_unread (user_id,read_at)
			) $c;",
		);
	}

	/**
	 * Grant capabilities to administrators and create staff roles.
	 */
	public static function add_caps() {
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			foreach ( array_keys( self::CAPS ) as $cap ) {
				$admin->add_cap( $cap );
			}
		}
		// Role names are stored untranslated (WordPress translates them on display).
		$roles = array(
			'rsp_support_agent'   => array( 'Portal Support Agent', array( 'rsp_view_admin', 'rsp_manage_support' ) ),
			'rsp_fulfillment'     => array( 'Portal Fulfillment Staff', array( 'rsp_view_admin', 'rsp_manage_provisioning', 'rsp_view_credentials' ) ),
			'rsp_billing_manager' => array( 'Portal Billing Manager', array( 'rsp_view_admin', 'rsp_manage_billing', 'rsp_export_data' ) ),
		);
		foreach ( $roles as $role => $def ) {
			$caps = array( 'read' => true );
			foreach ( $def[1] as $cap ) {
				$caps[ $cap ] = true;
			}
			if ( ! get_role( $role ) ) {
				add_role( $role, $def[0], $caps );
			}
		}
	}

	/**
	 * Current UTC datetime string.
	 *
	 * @return string
	 */
	public static function now() {
		return gmdate( 'Y-m-d H:i:s' );
	}

	/**
	 * RFC 4122 v4 UUID.
	 *
	 * @return string
	 */
	public static function uuid() {
		return wp_generate_uuid4();
	}
}
