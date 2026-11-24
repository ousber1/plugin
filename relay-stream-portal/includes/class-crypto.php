<?php
/**
 * Authenticated encryption for recoverable secrets (provider API key, player credentials, gift codes).
 *
 * Key source (in order):
 *  1. RSP_ENCRYPTION_KEY constant in wp-config.php (base64 of 32 random bytes) — recommended.
 *  2. HKDF over the site's AUTH_KEY and SECURE_AUTH_KEY salts.
 * The key never leaves the server and is never stored in the database.
 *
 * @package RelayStreamPortal
 */

defined( 'ABSPATH' ) || exit;

/**
 * Crypto helper (libsodium secretbox).
 */
class RSP_Crypto {

	const PREFIX = 'rsp1:';

	/**
	 * Whether a dedicated key constant is configured.
	 *
	 * @return bool
	 */
	public static function has_dedicated_key() {
		return defined( 'RSP_ENCRYPTION_KEY' ) && 32 === strlen( (string) base64_decode( RSP_ENCRYPTION_KEY, true ) );
	}

	/**
	 * Derive the 32-byte key.
	 *
	 * @return string
	 */
	private static function key() {
		if ( self::has_dedicated_key() ) {
			return base64_decode( RSP_ENCRYPTION_KEY, true );
		}
		$ikm = ( defined( 'AUTH_KEY' ) ? AUTH_KEY : '' ) . '|' . ( defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : '' );
		if ( '|' === $ikm ) {
			$ikm = wp_salt( 'secure_auth' );
		}
		return hash_hkdf( 'sha256', $ikm, 32, 'relay-stream-portal/v1' );
	}

	/**
	 * Encrypt a string.
	 *
	 * @param string $plain Plain text.
	 * @return string
	 */
	public static function encrypt( $plain ) {
		if ( '' === (string) $plain ) {
			return '';
		}
		$nonce  = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$cipher = sodium_crypto_secretbox( (string) $plain, $nonce, self::key() );
		return self::PREFIX . base64_encode( $nonce . $cipher );
	}

	/**
	 * Decrypt a string. Returns '' on failure.
	 *
	 * @param string $stored Stored value.
	 * @return string
	 */
	public static function decrypt( $stored ) {
		$stored = (string) $stored;
		if ( 0 !== strpos( $stored, self::PREFIX ) ) {
			return '';
		}
		$raw = base64_decode( substr( $stored, strlen( self::PREFIX ) ), true );
		if ( false === $raw || strlen( $raw ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
			return '';
		}
		$plain = sodium_crypto_secretbox_open( substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), self::key() );
		return false === $plain ? '' : $plain;
	}

	/**
	 * Encrypt an array as JSON.
	 *
	 * @param array $data Data.
	 * @return string
	 */
	public static function encrypt_array( array $data ) {
		return self::encrypt( wp_json_encode( $data ) );
	}

	/**
	 * Decrypt JSON array.
	 *
	 * @param string $stored Stored.
	 * @return array
	 */
	public static function decrypt_array( $stored ) {
		$json = self::decrypt( $stored );
		$data = $json ? json_decode( $json, true ) : array();
		return is_array( $data ) ? $data : array();
	}

	/**
	 * Mask a secret for display: keep last 4 characters.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	public static function mask( $value ) {
		$value = (string) $value;
		if ( '' === $value ) {
			return '';
		}
		$len = strlen( $value );
		return $len <= 4 ? str_repeat( '•', $len ) : str_repeat( '•', min( 8, $len - 4 ) ) . substr( $value, -4 );
	}

	/**
	 * Keyed hash for lookup of secret codes.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	public static function lookup_hash( $value ) {
		return hash_hmac( 'sha256', strtoupper( trim( (string) $value ) ), self::key() );
	}

	/**
	 * Random human-friendly code (no ambiguous characters).
	 *
	 * @param int $length Length.
	 * @return string
	 */
	public static function random_code( $length = 12 ) {
		$alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
		$out      = '';
		for ( $i = 0; $i < $length; $i++ ) {
			$out .= $alphabet[ random_int( 0, strlen( $alphabet ) - 1 ) ];
		}
		return $out;
	}
}
