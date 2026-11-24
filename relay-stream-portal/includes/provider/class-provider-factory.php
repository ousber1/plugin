<?php
/**
 * Builds the configured provider.
 *
 * Live mode requires ALL of: api_mode=live, mapping verified by an administrator and a
 * MegaOTT API token (the base URL host must be allowlisted). Otherwise jobs use manual provisioning.
 *
 * @package RelayStreamPortal
 */

defined( 'ABSPATH' ) || exit;

/**
 * Factory.
 */
class RSP_Provider_Factory {

	/** @var callable|null Test transport override. */
	public static $transport = null;

	/**
	 * Whether live mode is fully enabled.
	 *
	 * @return bool
	 */
	public static function is_live() {
		$s = RSP_Settings::all();
		return 'live' === $s['api_mode'] && ! empty( $s['api_mapping_verified'] ) && '' !== (string) $s['megaott_token_enc'];
	}

	/**
	 * Active provider slug.
	 *
	 * @return string
	 */
	public static function active_slug() {
		return self::is_live() ? 'megaott' : 'manual';
	}

	/**
	 * The remote adapter (even when not live — for connection tests).
	 *
	 * @return RSP_Megaott_Provider
	 */
	public static function remote() {
		$s = RSP_Settings::all();
		return new RSP_Megaott_Provider(
			$s['megaott_base_url'],
			RSP_Crypto::decrypt( $s['megaott_token_enc'] ),
			preg_split( '/[\s,]+/', (string) $s['api_allowed_hosts'] ),
			(int) $s['api_timeout'],
			array(
				'forced_country'    => $s['megaott_forced_country'],
				'adult'             => $s['megaott_adult'],
				'whatsapp_telegram' => $s['megaott_contact'],
				'enable_vpn'        => $s['megaott_vpn'],
				'max_connections'   => $s['megaott_connections'],
			),
			self::$transport
		);
	}

	/**
	 * Provider used for jobs: MegaOTT when live, otherwise manual provisioning.
	 *
	 * @return RSP_Provider_Interface
	 */
	public static function get() {
		return self::is_live() ? self::remote() : new RSP_Manual_Provider();
	}
}
