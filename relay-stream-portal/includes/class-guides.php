<?php
/**
 * Apps & Guides content model (stored in the rsp_guides option, editable in the admin).
 * Demo content refers to a fictional "Relay Player" app with placeholder links that the
 * operator must replace with their own approved, official downloads.
 *
 * @package RelayStreamPortal
 */

defined( 'ABSPATH' ) || exit;

/**
 * Guides.
 */
class RSP_Guides {

	/**
	 * Icon keys available for devices.
	 *
	 * @return array
	 */
	public static function icons() {
		return array( 'tv', 'android', 'apple', 'windows', 'phone', 'tablet', 'monitor', 'box' );
	}

	/**
	 * Demo data.
	 *
	 * @return array
	 */
	public static function demo() {
		return array(
			'release'     => array(
				'enabled'  => 1,
				'version'  => '2.3.0',
				'title'    => 'Relay Player 2.3',
				'subtitle' => 'A calmer home screen, faster channel switching and offline-friendly start-up.',
				'compat'   => 'Android TV 9+, Fire OS 7+, Android 9+, macOS 12+, Windows 10+',
				'qr_url'   => '',
				'stats'    => array(
					array( 'label' => 'Platforms', 'value' => '6' ),
					array( 'label' => 'Updates', 'value' => 'In-app' ),
					array( 'label' => 'Sign-in', 'value' => 'Portal email' ),
				),
			),
			'login_note'  => 'Sign in to Relay Player with the email and password you use for this portal. The player details shown under My Accounts are only for third-party apps.',
			'devices'     => array(
				array(
					'id'            => 'firetv',
					'label'         => 'Fire TV / Android TV',
					'icon'          => 'tv',
					'version'       => '2.3.0',
					'download_url'  => '',
					'store_url'     => '',
					'download_code' => '',
					'show_qr'       => 0,
					'previous'      => array( array( 'version' => '2.2.4', 'url' => '' ) ),
					'steps'         => array(
						'Allow installs from your downloader app: Settings → My Fire TV → Developer options (Android TV: Settings → Apps → Security).',
						'Install a downloader app from your device\'s official app store.',
						'Open the downloader and enter the download code or address shown on this page.',
						'Install Relay Player and sign in with your portal email and password.',
					),
				),
				array(
					'id'            => 'android',
					'label'         => 'Android phone',
					'icon'          => 'android',
					'version'       => '2.3.0',
					'download_url'  => '',
					'store_url'     => '',
					'download_code' => '',
					'show_qr'       => 1,
					'previous'      => array(),
					'steps'         => array( 'Open the download link on your phone or scan the QR code.', 'Allow the install when Android asks.', 'Sign in with your portal email and password.' ),
				),
				array(
					'id'           => 'macos',
					'label'        => 'macOS',
					'icon'         => 'apple',
					'version'      => '2.3.0',
					'download_url' => '',
					'store_url'    => '',
					'steps'        => array( 'Download the .dmg file.', 'Drag Relay Player to Applications.', 'Open it and sign in.' ),
				),
				array(
					'id'           => 'windows',
					'label'        => 'Windows',
					'icon'         => 'windows',
					'version'      => '2.3.0',
					'download_url' => '',
					'store_url'    => '',
					'steps'        => array( 'Download the installer.', 'Run it and follow the prompts.', 'Sign in with your portal email and password.' ),
				),
				array(
					'id'           => 'ios',
					'label'        => 'iPhone / iPad',
					'icon'         => 'phone',
					'version'      => '',
					'download_url' => '',
					'store_url'    => '',
					'steps'        => array( 'Install a compatible player from the App Store.', 'Add a playlist using the player details from My Accounts.' ),
				),
				array(
					'id'           => 'appletv',
					'label'        => 'Apple TV',
					'icon'         => 'monitor',
					'version'      => '',
					'download_url' => '',
					'store_url'    => '',
					'steps'        => array( 'Install a compatible player from the App Store on Apple TV.', 'Add a playlist using the player details from My Accounts.' ),
				),
			),
			'whats_new'   => array(
				array( 'title' => 'Calmer home screen', 'text' => 'Rows load progressively and remember where you left off.' ),
				array( 'title' => 'Faster zapping', 'text' => 'Channel changes reuse open connections when the source allows it.' ),
				array( 'title' => 'Starts offline', 'text' => 'The last guide is cached so the app opens instantly.' ),
				array( 'title' => 'Safer storage', 'text' => 'Sign-in tokens are kept in the device keystore.' ),
			),
			'third_party' => array(
				array( 'name' => 'Xtream-compatible players', 'status' => 'soon', 'url' => '', 'text' => 'Use server, username and password.' ),
				array( 'name' => 'M3U playlist players', 'status' => 'soon', 'url' => '', 'text' => 'Use the M3U address.' ),
				array( 'name' => 'MAG / STB portals', 'status' => 'soon', 'url' => '', 'text' => 'Use the portal address and your device MAC.' ),
				array( 'name' => 'Media-center add-ons', 'status' => 'soon', 'url' => '', 'text' => 'Use the M3U address in a playlist add-on.' ),
			),
			'third_party_note' => 'Third-party players use the server, username, password or M3U address from My Accounts — never your portal email and password.',
		);
	}

	/**
	 * Stored guides merged with an empty skeleton.
	 *
	 * @return array
	 */
	public static function get() {
		$g = get_option( 'rsp_guides', array() );
		$g = is_array( $g ) ? $g : array();
		return wp_parse_args(
			$g,
			array(
				'release'          => array(),
				'login_note'       => '',
				'devices'          => array(),
				'whats_new'        => array(),
				'third_party'      => array(),
				'third_party_note' => '',
			)
		);
	}

	/**
	 * Whether a URL is acceptable for a public QR/download link.
	 * Only http(s) URLs without embedded credentials or credential-like query params.
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	public static function is_public_url( $url ) {
		$url = (string) $url;
		if ( '' === $url || ! wp_http_validate_url( $url ) ) {
			return false;
		}
		$p = wp_parse_url( $url );
		if ( isset( $p['user'] ) || isset( $p['pass'] ) ) {
			return false;
		}
		if ( ! empty( $p['query'] ) && preg_match( '/(^|&)(username|password|pass|user|token|api_key|mac)=/i', $p['query'] ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Sanitize the admin-submitted structure.
	 *
	 * @param array $in Input.
	 * @return array
	 */
	public static function sanitize( array $in ) {
		$url = static function ( $u ) {
			$u = esc_url_raw( trim( (string) $u ), array( 'http', 'https' ) );
			return RSP_Guides::is_public_url( $u ) ? $u : '';
		};
		$r   = isset( $in['release'] ) ? (array) $in['release'] : array();
		$out = array(
			'release'          => array(
				'enabled'  => empty( $r['enabled'] ) ? 0 : 1,
				'version'  => sanitize_text_field( isset( $r['version'] ) ? $r['version'] : '' ),
				'title'    => sanitize_text_field( isset( $r['title'] ) ? $r['title'] : '' ),
				'subtitle' => sanitize_textarea_field( isset( $r['subtitle'] ) ? $r['subtitle'] : '' ),
				'compat'   => sanitize_text_field( isset( $r['compat'] ) ? $r['compat'] : '' ),
				'qr_url'   => $url( isset( $r['qr_url'] ) ? $r['qr_url'] : '' ),
				'stats'    => array(),
			),
			'login_note'       => sanitize_textarea_field( isset( $in['login_note'] ) ? $in['login_note'] : '' ),
			'third_party_note' => sanitize_textarea_field( isset( $in['third_party_note'] ) ? $in['third_party_note'] : '' ),
			'devices'          => array(),
			'whats_new'        => array(),
			'third_party'      => array(),
		);
		foreach ( isset( $r['stats'] ) ? (array) $r['stats'] : array() as $s ) {
			if ( ! empty( $s['label'] ) ) {
				$out['release']['stats'][] = array( 'label' => sanitize_text_field( $s['label'] ), 'value' => sanitize_text_field( isset( $s['value'] ) ? $s['value'] : '' ) );
			}
		}
		foreach ( isset( $in['devices'] ) ? (array) $in['devices'] : array() as $d ) {
			if ( empty( $d['label'] ) ) {
				continue;
			}
			$prev = array();
			foreach ( preg_split( '/\r?\n/', isset( $d['previous_text'] ) ? (string) $d['previous_text'] : '' ) as $line ) {
				$parts = array_map( 'trim', explode( '|', $line, 2 ) );
				if ( '' !== $parts[0] ) {
					$prev[] = array( 'version' => sanitize_text_field( $parts[0] ), 'url' => $url( isset( $parts[1] ) ? $parts[1] : '' ) );
				}
			}
			$out['devices'][] = array(
				'id'            => sanitize_key( ! empty( $d['id'] ) ? $d['id'] : $d['label'] ),
				'label'         => sanitize_text_field( $d['label'] ),
				'icon'          => in_array( isset( $d['icon'] ) ? $d['icon'] : '', self::icons(), true ) ? $d['icon'] : 'box',
				'version'       => sanitize_text_field( isset( $d['version'] ) ? $d['version'] : '' ),
				'download_url'  => $url( isset( $d['download_url'] ) ? $d['download_url'] : '' ),
				'store_url'     => $url( isset( $d['store_url'] ) ? $d['store_url'] : '' ),
				'download_code' => preg_replace( '/[^A-Za-z0-9]/', '', isset( $d['download_code'] ) ? (string) $d['download_code'] : '' ),
				'show_qr'       => empty( $d['show_qr'] ) ? 0 : 1,
				'previous'      => $prev,
				'steps'         => array_values( array_filter( array_map( 'sanitize_text_field', preg_split( '/\r?\n/', isset( $d['steps_text'] ) ? (string) $d['steps_text'] : '' ) ) ) ),
			);
		}
		foreach ( isset( $in['whats_new'] ) ? (array) $in['whats_new'] : array() as $w ) {
			if ( ! empty( $w['title'] ) ) {
				$out['whats_new'][] = array( 'title' => sanitize_text_field( $w['title'] ), 'text' => sanitize_text_field( isset( $w['text'] ) ? $w['text'] : '' ) );
			}
		}
		foreach ( isset( $in['third_party'] ) ? (array) $in['third_party'] : array() as $t ) {
			if ( ! empty( $t['name'] ) ) {
				$out['third_party'][] = array(
					'name'   => sanitize_text_field( $t['name'] ),
					'status' => isset( $t['status'] ) && 'ready' === $t['status'] ? 'ready' : 'soon',
					'url'    => $url( isset( $t['url'] ) ? $t['url'] : '' ),
					'text'   => sanitize_textarea_field( isset( $t['text'] ) ? $t['text'] : '' ),
				);
			}
		}
		return $out;
	}
}
