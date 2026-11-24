<?php
/**
 * Portal language and direction.
 *
 * Front end: the portal uses the Branding "language" setting when set, otherwise the signed-in
 * user's profile language, otherwise the site language. The admin follows WordPress' own rules.
 *
 * @package RelayStreamPortal
 */

defined( 'ABSPATH' ) || exit;

/**
 * I18n.
 */
class RSP_I18n {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_filter( 'plugin_locale', array( __CLASS__, 'plugin_locale' ), 10, 2 );
	}

	/**
	 * Portal locale.
	 *
	 * @return string
	 */
	public static function locale() {
		$set = (string) RSP_Settings::get( 'language', '' );
		if ( '' !== $set ) {
			return $set;
		}
		if ( is_user_logged_in() ) {
			return get_user_locale();
		}
		return get_locale();
	}

	/**
	 * Override our text domain's locale on the front end only.
	 *
	 * @param string $locale Locale.
	 * @param string $domain Domain.
	 * @return string
	 */
	public static function plugin_locale( $locale, $domain ) {
		if ( 'relay-stream-portal' === $domain && ! is_admin() ) {
			return self::locale();
		}
		return $locale;
	}

	/**
	 * Is the portal right-to-left?
	 *
	 * @return bool
	 */
	public static function is_rtl() {
		if ( is_admin() ) {
			return is_rtl();
		}
		$l = strtolower( self::locale() );
		foreach ( array( 'ar', 'he', 'fa', 'ur', 'ps', 'ckb', 'ug', 'dv' ) as $p ) {
			if ( 0 === strpos( $l, $p ) ) {
				return true;
			}
		}
		return is_rtl() && '' === (string) RSP_Settings::get( 'language', '' );
	}
}
