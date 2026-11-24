<?php
/**
 * Inline SVG icon set (original, stroke-based, 24px grid).
 *
 * @package RelayStreamPortal
 */

defined( 'ABSPATH' ) || exit;

/**
 * Icons.
 */
class RSP_Icons {

	/**
	 * Path data per icon.
	 *
	 * @return array
	 */
	private static function paths() {
		return array(
			'dashboard'    => '<rect x="3.5" y="3.5" width="7" height="9" rx="1.5"/><rect x="13.5" y="3.5" width="7" height="5" rx="1.5"/><rect x="13.5" y="11.5" width="7" height="9" rx="1.5"/><rect x="3.5" y="15.5" width="7" height="5" rx="1.5"/>',
			'accounts'     => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.8-3.6 3.4-5.5 6.5-5.5s5.7 1.9 6.5 5.5"/><path d="M16 4.8a3.3 3.3 0 0 1 0 6.4M18.2 14.8c1.7.7 2.9 2.3 3.3 5.2"/>',
			'apps'         => '<rect x="5" y="2.5" width="14" height="19" rx="2.5"/><path d="M10 18.5h4"/><path d="M12 6.5v6m0 0-2.5-2.5M12 12.5l2.5-2.5"/>',
			'plans'        => '<path d="M4 7.5h16v11a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 4 18.5z"/><path d="M4 11h16M8 4h8"/><path d="M8 15.5h3"/>',
			'transactions' => '<path d="M6 3.5h12v17l-3-2-3 2-3-2-3 2z"/><path d="M9 8.5h6M9 12h6"/>',
			'referrals'    => '<rect x="3.5" y="9" width="17" height="11.5" rx="1.5"/><path d="M12 9v11.5M3.5 13h17"/><path d="M12 9c-1.5-4.5-6-4.5-6-2s3 2 6 2zm0 0c1.5-4.5 6-4.5 6-2s-3 2-6 2z"/>',
			'redeem'       => '<path d="M3.5 8.5h17v3a2 2 0 0 0 0 4v3h-17v-3a2 2 0 0 0 0-4z"/><path d="M14 8.5v10" stroke-dasharray="2 2"/>',
			'support'      => '<path d="M4 12a8 8 0 1 1 3.3 6.5L3.5 20l1.2-3.6A8 8 0 0 1 4 12z"/><path d="M9.5 10a2.5 2.5 0 1 1 3.3 2.4c-.5.2-.8.6-.8 1.1v.5M12 16.8v.2"/>',
			'profile'      => '<circle cx="12" cy="8.5" r="4"/><path d="M4.5 20.5c1-4 4-6 7.5-6s6.5 2 7.5 6"/>',
			'bell'         => '<path d="M6 16.5V11a6 6 0 1 1 12 0v5.5l1.5 2h-15z"/><path d="M10 20.5a2 2 0 0 0 4 0"/>',
			'sun'          => '<circle cx="12" cy="12" r="4"/><path d="M12 2.5v2M12 19.5v2M4.5 12h-2M21.5 12h-2M5.6 5.6l1.4 1.4M17 17l1.4 1.4M5.6 18.4 7 17M17 7l1.4-1.4"/>',
			'moon'         => '<path d="M19.5 14.5A8 8 0 0 1 9.5 4.5a8 8 0 1 0 10 10z"/>',
			'copy'         => '<rect x="8.5" y="8.5" width="11" height="11" rx="2"/><path d="M15.5 8.5v-2a2 2 0 0 0-2-2h-7a2 2 0 0 0-2 2v7a2 2 0 0 0 2 2h2"/>',
			'eye'          => '<path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z"/><circle cx="12" cy="12" r="3"/>',
			'plus'         => '<path d="M12 5v14M5 12h14"/>',
			'refresh'      => '<path d="M20 11.5A8 8 0 0 0 5.6 7M4 12.5A8 8 0 0 0 18.4 17"/><path d="M5 3v4.5h4.5M19 21v-4.5h-4.5"/>',
			'play'         => '<circle cx="12" cy="12" r="9"/><path d="m10 8.5 5.5 3.5-5.5 3.5z"/>',
			'check'        => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
			'clock'        => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
			'alert'        => '<path d="M12 3.5 21.5 20h-19z"/><path d="M12 10v4.5M12 17.2v.3"/>',
			'info'         => '<circle cx="12" cy="12" r="8.5"/><path d="M12 11v5.5M12 7.8v.2"/>',
			'close'        => '<path d="M6 6l12 12M18 6 6 18"/>',
			'menu'         => '<path d="M4 7h16M4 12h16M4 17h10"/>',
			'logout'       => '<path d="M14.5 4.5h3a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2h-3"/><path d="M10 16.5 5.5 12 10 7.5M5.5 12h10"/>',
			'link'         => '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>',
			'download'     => '<path d="M12 3.5v12m0 0-4.5-4.5M12 15.5l4.5-4.5"/><path d="M4.5 17.5v1.5a1.5 1.5 0 0 0 1.5 1.5h12a1.5 1.5 0 0 0 1.5-1.5v-1.5"/>',
			'tv'           => '<rect x="2.5" y="5" width="19" height="12.5" rx="2"/><path d="M8 20.5h8M9 2.5l3 2.5 3-2.5"/>',
			'android'      => '<rect x="5" y="9" width="14" height="10" rx="2"/><path d="M5 12a7 7 0 0 1 14 0M8.5 4.5l1.3 2.3M15.5 4.5l-1.3 2.3"/><circle cx="9.5" cy="9.8" r=".3"/><circle cx="14.5" cy="9.8" r=".3"/>',
			'apple'        => '<path d="M16.5 12.8c0-2.4 2-3.3 2-3.3s-1.1-1.8-3.3-1.8c-1.4 0-2.3.8-3.2.8s-1.9-.8-3.2-.8C6.6 7.7 4.5 9.6 4.5 13c0 3.6 2.5 7.5 4.3 7.5 1 0 1.6-.7 3.2-.7s2 .7 3.2.7c1.7 0 3.1-3.3 3.3-3.9 0 0-2-.8-2-3.8z"/><path d="M12.5 5.5c.4-1.6 1.7-2.6 3-2.8-.1 1.6-1.4 2.9-3 2.8z"/>',
			'windows'      => '<path d="M3.5 6 10.5 5v6.5h-7zM12.5 4.7 20.5 3.5v8h-8zM3.5 13h7v6.5l-7-1zM12.5 13h8v7.5l-8-1.2z"/>',
			'phone'        => '<rect x="6.5" y="2.5" width="11" height="19" rx="2.5"/><path d="M11 18.5h2"/>',
			'tablet'       => '<rect x="4" y="2.5" width="16" height="19" rx="2.5"/><path d="M11 18.5h2"/>',
			'monitor'      => '<rect x="2.5" y="4" width="19" height="13" rx="2"/><path d="M9 20.5h6M12 17v3.5"/>',
			'box'          => '<path d="M3.5 7.5 12 3l8.5 4.5v9L12 21l-8.5-4.5z"/><path d="M3.5 7.5 12 12l8.5-4.5M12 12v9"/>',
			'shield'       => '<path d="M12 3 4.5 6v5.5c0 4.5 3.2 8 7.5 9.5 4.3-1.5 7.5-5 7.5-9.5V6z"/><path d="m9 12 2 2 4-4"/>',
			'qr'           => '<rect x="3.5" y="3.5" width="6.5" height="6.5" rx="1"/><rect x="14" y="3.5" width="6.5" height="6.5" rx="1"/><rect x="3.5" y="14" width="6.5" height="6.5" rx="1"/><path d="M14 14h2.5v2.5H14zM18 18h2.5v2.5H18zM14 20.5h2M20.5 14v2"/>',
			'star'         => '<path d="m12 3.5 2.6 5.4 5.9.8-4.3 4.1 1 5.8L12 16.9l-5.2 2.7 1-5.8L3.5 9.7l5.9-.8z"/>',
			'external'     => '<path d="M13.5 4.5h6v6M19.5 4.5 11 13"/><path d="M17.5 14v4.5a1.5 1.5 0 0 1-1.5 1.5H6a1.5 1.5 0 0 1-1.5-1.5V8.5A1.5 1.5 0 0 1 6 7h4.5"/>',
			'paperclip'    => '<path d="m19 11.5-7 7a4.5 4.5 0 0 1-6.4-6.4l7.6-7.6a3 3 0 0 1 4.2 4.2l-7.5 7.5a1.5 1.5 0 0 1-2.1-2.1l6.8-6.8"/>',
		);
	}

	/**
	 * SVG markup.
	 *
	 * @param string $name Name.
	 * @param int    $size Size.
	 * @return string
	 */
	public static function get( $name, $size = 20 ) {
		$p = self::paths();
		$d = isset( $p[ $name ] ) ? $p[ $name ] : $p['box'];
		return '<svg class="rsp-icon" width="' . (int) $size . '" height="' . (int) $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $d . '</svg>';
	}

	/**
	 * Echo icon (markup is static and trusted).
	 *
	 * @param string $name Name.
	 * @param int    $size Size.
	 */
	public static function e( $name, $size = 20 ) {
		echo self::get( $name, $size ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
}
