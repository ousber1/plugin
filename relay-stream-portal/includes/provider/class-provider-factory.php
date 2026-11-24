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
		return 'live' === $s['api_mode'] && ! empty( $s['api_mapping_verified'] ) && '' !== $s['api_key_enc'];
	}

	/**
	 * Active provider slug.
	 *
	 * @return string
	 */
	public static function active_slug() {
		return self::is_live() ? 'tvplus' : 'manual';
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
		$slug = $slug ? $slug : self::active_slug();
		if ( 'tvplus' === $slug && self::is_live() ) {
			return self::tvplus();
		}
		return new RSP_Manual_Provider();
	}
}
