<?php
/**
 * Builds the configured provider.
 *
 * Live mode requires ALL of: api_mode=live, mapping verified by an administrator,
 * an API key, and a base URL whose host is allowlisted. Otherwise the manual provider is used.
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
		$secret = 'megaott' === $s['api_provider'] ? $s['megaott_token_enc'] : $s['api_key_enc'];
		return 'live' === $s['api_mode'] && ! empty( $s['api_mapping_verified'] ) && '' !== $secret;
	}

	/**
	 * Active provider slug.
	 *
	 * @return string
	 */
	public static function active_slug() {
		return self::is_live() ? self::configured_slug() : 'manual';
	}

	/**
	 * Selected provider (tvplus|megaott), regardless of mode.
	 *
	 * @return string
	 */
	public static function configured_slug() {
		return 'megaott' === RSP_Settings::get( 'api_provider' ) ? 'megaott' : 'tvplus';
	}

	/**
	 * The configured remote adapter (even when not live — for connection tests).
	 *
	 * @return RSP_Provider_Interface
	 */
	public static function remote() {
		return 'megaott' === self::configured_slug() ? self::megaott() : self::tvplus();
	}

	/**
	 * MegaOTT adapter from settings.
	 *
	 * @return RSP_Megaott_Provider
	 */
	public static function megaott() {
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
	 * TVPlus adapter built from settings (even when not live, for connection tests).
	 *
	 * @return RSP_Tvplus_Provider
	 */
	public static function tvplus() {
		$s = RSP_Settings::all();
		return new RSP_Tvplus_Provider(
			$s['api_base_url'],
			RSP_Crypto::decrypt( $s['api_key_enc'] ),
			preg_split( '/[\s,]+/', (string) $s['api_allowed_hosts'] ),
			(int) $s['api_timeout'],
			self::$transport
		);
	}

	/**
	 * Provider for a given account/provider slug.
	 *
	 * @param string|null $slug Slug.
	 * @return RSP_Provider_Interface
	 */
	public static function get( $slug = null ) {
		if ( self::is_live() ) {
			return self::remote();
		}
		return new RSP_Manual_Provider();
	}
}
