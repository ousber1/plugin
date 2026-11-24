<?php
/**
 * Device tabs + per-device install card and numbered steps. QR codes only from admin-approved public URLs.
 *
 * @var array $vm
 * @var array $args
 * @package RelayStreamPortal
 */

defined( 'ABSPATH' ) || exit;

$rsp_g       = RSP_Guides::get();
$rsp_devices = $rsp_g['devices'];
if ( ! empty( $args['device'] ) ) {
	$rsp_devices = array_values( array_filter( $rsp_devices, static function ( $d ) use ( $args ) { return $d['id'] === $args['device']; } ) );
}
if ( ! $rsp_devices ) {
	return;
}
$rsp_uid = 'rsp-dev-' . wp_rand( 1000, 999999 );
?>
<div class="rsp-devices" data-rsp-tabs>
	<?php if ( count( $rsp_devices ) > 1 ) : ?>
		<div class="rsp-device-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Choose your device', 'relay-stream-portal' ); ?>">
			<?php foreach ( $rsp_devices as $rsp_i => $rsp_d ) : ?>
				<button type="button" role="tab" id="<?php echo esc_attr( $rsp_uid . '-t' . $rsp_i ); ?>" aria-controls="<?php echo esc_attr( $rsp_uid . '-p' . $rsp_i ); ?>" aria-selected="<?php echo 0 === $rsp_i ? 'true' : 'false'; ?>" class="rsp-device-tab<?php echo 0 === $rsp_i ? ' is-current' : ''; ?>"<?php echo 0 === $rsp_i ? '' : ' tabindex="-1"'; ?>><?php RSP_Icons::e( $rsp_d['icon'], 17 ); ?><span><?php echo esc_html( $rsp_d['label'] ); ?></span></button>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
	<?php foreach ( $rsp_devices as $rsp_i => $rsp_d ) :
		$rsp_d    = wp_parse_args( $rsp_d, array( 'subtitle' => '', 'full_url' => '', 'download_code' => '', 'download_url' => '', 'store_url' => '', 'previous' => array(), 'steps' => array(), 'version' => '', 'show_qr' => 0 ) );
		$rsp_main = $rsp_d['download_url'] ? $rsp_d['download_url'] : $rsp_d['store_url'];
		$rsp_sub  = $rsp_d['subtitle'] ? $rsp_d['subtitle'] : ( $rsp_d['version'] ? sprintf( /* translators: %s version */ __( 'Version %s', 'relay-stream-portal' ), $rsp_d['version'] ) : '' );
		?>
		<div role="tabpanel" id="<?php echo esc_attr( $rsp_uid . '-p' . $rsp_i ); ?>" aria-labelledby="<?php echo esc_attr( $rsp_uid . '-t' . $rsp_i ); ?>" class="rsp-device-panel"<?php echo 0 === $rsp_i ? '' : ' hidden'; ?>>
			<div class="rsp-card rsp-device-card">
				<div class="rsp-device-id"><span class="rsp-stat-icon rsp-c-blue"><?php RSP_Icons::e( $rsp_d['icon'] ); ?></span><div><strong><?php echo esc_html( $rsp_d['label'] ); ?></strong><?php if ( $rsp_sub ) : ?><small class="rsp-muted"><?php echo esc_html( $rsp_sub ); ?></small><?php endif; ?></div></div>
				<?php if ( $rsp_d['download_code'] ) : ?>
					<span class="rsp-eyebrow"><?php esc_html_e( 'Downloader code', 'relay-stream-portal' ); ?></span>
					<div class="rsp-code-box"><code><?php echo esc_html( $rsp_d['download_code'] ); ?></code><button type="button" class="rsp-icon-btn rsp-sm" data-rsp-copy="<?php echo esc_attr( $rsp_d['download_code'] ); ?>" aria-label="<?php esc_attr_e( 'Copy download code', 'relay-stream-portal' ); ?>"><?php RSP_Icons::e( 'copy', 16 ); ?></button></div>
				<?php endif; ?>
				<?php if ( $rsp_d['full_url'] ) : ?>
					<span class="rsp-eyebrow"><?php esc_html_e( 'Or full URL', 'relay-stream-portal' ); ?></span>
					<div class="rsp-url-box"><code><?php echo esc_html( $rsp_d['full_url'] ); ?></code><button type="button" class="rsp-icon-btn rsp-sm" data-rsp-copy="<?php echo esc_attr( $rsp_d['full_url'] ); ?>" aria-label="<?php esc_attr_e( 'Copy URL', 'relay-stream-portal' ); ?>"><?php RSP_Icons::e( 'copy', 16 ); ?></button></div>
				<?php endif; ?>
				<?php if ( $rsp_d['download_url'] ) : ?>
					<a class="rsp-btn rsp-btn-primary rsp-btn-block" href="<?php echo esc_url( $rsp_d['download_url'] ); ?>" rel="noopener"><?php RSP_Icons::e( 'download', 18 ); ?><?php esc_html_e( 'Download installer', 'relay-stream-portal' ); ?></a>
				<?php endif; ?>
				<?php if ( $rsp_d['store_url'] ) : ?>
					<a class="rsp-btn rsp-btn-block" href="<?php echo esc_url( $rsp_d['store_url'] ); ?>" target="_blank" rel="noopener"><?php RSP_Icons::e( 'external', 18 ); ?><?php esc_html_e( 'Open in app store', 'relay-stream-portal' ); ?></a>
				<?php endif; ?>
				<?php if ( ! $rsp_main && ! $rsp_d['download_code'] && ! $rsp_d['full_url'] ) : ?><p class="rsp-muted rsp-small"><?php esc_html_e( 'Download link coming soon.', 'relay-stream-portal' ); ?></p><?php endif; ?>
				<?php if ( $rsp_d['show_qr'] && $rsp_main && RSP_Guides::is_public_url( $rsp_main ) ) : ?>
					<div class="rsp-qr-wrap"><div class="rsp-qr" data-rsp-qr="<?php echo esc_attr( $rsp_main ); ?>" role="img" aria-label="<?php esc_attr_e( 'QR code for the download link', 'relay-stream-portal' ); ?>"></div><small class="rsp-muted"><?php esc_html_e( 'Scan to open the download page', 'relay-stream-portal' ); ?></small></div>
				<?php endif; ?>
				<?php if ( $rsp_d['previous'] ) : ?>
					<details class="rsp-prev"><summary><span><?php esc_html_e( 'Need the previous version?', 'relay-stream-portal' ); ?></span><span><?php echo esc_html( $rsp_d['previous'][0]['version'] ); ?></span></summary>
						<ul><?php foreach ( $rsp_d['previous'] as $rsp_pv ) : ?><li><?php echo esc_html( $rsp_pv['version'] ); ?><?php if ( ! empty( $rsp_pv['url'] ) ) : ?> — <a class="rsp-link" href="<?php echo esc_url( $rsp_pv['url'] ); ?>" rel="noopener"><?php esc_html_e( 'Download', 'relay-stream-portal' ); ?></a><?php endif; ?></li><?php endforeach; ?></ul>
					</details>
				<?php endif; ?>
			</div>
			<?php if ( $rsp_d['steps'] ) : ?>
				<ol class="rsp-card rsp-steps">
					<?php foreach ( $rsp_d['steps'] as $rsp_step ) : ?>
						<li><span><?php echo RSP_Portal::rich( $rsp_step ); // phpcs:ignore -- escaped, **bold** only. ?>
							<?php if ( $rsp_d['download_code'] && false !== stripos( $rsp_step, 'code' ) ) : ?>
								<span class="rsp-eyebrow rsp-step-eyebrow"><?php esc_html_e( 'Downloader code', 'relay-stream-portal' ); ?></span>
								<span class="rsp-url-box"><code><?php echo esc_html( $rsp_d['download_code'] ); ?></code><button type="button" class="rsp-icon-btn rsp-sm" data-rsp-copy="<?php echo esc_attr( $rsp_d['download_code'] ); ?>" aria-label="<?php esc_attr_e( 'Copy download code', 'relay-stream-portal' ); ?>"><?php RSP_Icons::e( 'copy', 16 ); ?></button></span>
							<?php endif; ?></span></li>
					<?php endforeach; ?>
				</ol>
			<?php endif; ?>
		</div>
	<?php endforeach; ?>
</div>
