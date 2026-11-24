<?php
/**
 * Apps & Guides module.
 *
 * @var array $vm
 * @var array $args
 * @package RelayStreamPortal
 */

defined( 'ABSPATH' ) || exit;

$rsp_g = RSP_Guides::get();
$rsp_r = $rsp_g['release'];
?>
<div class="rsp-page">
	<?php if ( ! empty( $rsp_r['enabled'] ) ) : ?>
		<section class="rsp-release">
			<div class="rsp-release-text">
				<?php if ( ! empty( $rsp_r['version'] ) ) : ?><span class="rsp-chip"><?php
					/* translators: %s version */
					echo esc_html( sprintf( __( 'Version %s available', 'relay-stream-portal' ), $rsp_r['version'] ) ); ?></span><?php endif; ?>
				<h1><?php echo esc_html( $rsp_r['title'] ? $rsp_r['title'] : __( 'Apps & Guides', 'relay-stream-portal' ) ); ?></h1>
				<?php if ( ! empty( $rsp_r['subtitle'] ) ) : ?><p><?php echo esc_html( $rsp_r['subtitle'] ); ?></p><?php endif; ?>
				<?php if ( ! empty( $rsp_r['stats'] ) ) : ?>
					<dl class="rsp-release-stats"><?php foreach ( $rsp_r['stats'] as $rsp_s ) : ?><div><dt><?php echo esc_html( $rsp_s['label'] ); ?></dt><dd><?php echo esc_html( $rsp_s['value'] ); ?></dd></div><?php endforeach; ?></dl>
				<?php endif; ?>
				<?php if ( ! empty( $rsp_r['compat'] ) ) : ?><p class="rsp-small rsp-muted"><?php
					/* translators: %s compatibility */
					echo esc_html( sprintf( __( 'Compatible with: %s', 'relay-stream-portal' ), $rsp_r['compat'] ) ); ?></p><?php endif; ?>
			</div>
			<?php if ( ! empty( $rsp_r['qr_url'] ) && RSP_Guides::is_public_url( $rsp_r['qr_url'] ) ) : ?>
				<div class="rsp-qr-wrap rsp-qr-hero"><div class="rsp-qr" data-rsp-qr="<?php echo esc_attr( $rsp_r['qr_url'] ); ?>" role="img" aria-label="<?php esc_attr_e( 'QR code for the install page', 'relay-stream-portal' ); ?>"></div><small><?php esc_html_e( 'Scan with your phone to open the install page', 'relay-stream-portal' ); ?></small></div>
			<?php endif; ?>
		</section>
	<?php else : ?>
		<div class="rsp-page-head"><div><h1><?php echo esc_html( RSP_Portal::label( $args, 'title', __( 'Apps & Guides', 'relay-stream-portal' ) ) ); ?></h1></div></div>
	<?php endif; ?>

	<?php if ( ! empty( $rsp_g['login_note'] ) ) : ?>
		<div class="rsp-announce rsp-tone-info"><?php RSP_Icons::e( 'info' ); ?><div><strong><?php esc_html_e( 'Signing in to the app', 'relay-stream-portal' ); ?></strong><p><?php echo esc_html( $rsp_g['login_note'] ); ?></p></div></div>
	<?php endif; ?>

	<h2 class="rsp-section-title"><?php esc_html_e( 'Pick your device', 'relay-stream-portal' ); ?></h2>
	<?php echo RSP_Portal::template( 'partials/device-guides', array( 'vm' => $vm, 'args' => $args ) ); // phpcs:ignore ?>

	<?php if ( ! empty( $rsp_g['whats_new'] ) ) : ?>
		<h2 class="rsp-section-title"><?php esc_html_e( 'What’s new', 'relay-stream-portal' ); ?><?php if ( ! empty( $rsp_r['version'] ) ) : ?> <span class="rsp-muted"><?php echo esc_html( $rsp_r['version'] ); ?></span><?php endif; ?></h2>
		<div class="rsp-features">
			<?php foreach ( $rsp_g['whats_new'] as $rsp_w ) : ?>
				<div class="rsp-card rsp-feature"><strong><?php echo esc_html( $rsp_w['title'] ); ?></strong><p class="rsp-muted"><?php echo esc_html( $rsp_w['text'] ); ?></p></div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $rsp_g['third_party'] ) ) : ?>
		<h2 class="rsp-section-title"><?php esc_html_e( 'Using another player', 'relay-stream-portal' ); ?></h2>
		<?php if ( ! empty( $rsp_g['third_party_note'] ) ) : ?><div class="rsp-announce rsp-tone-warning"><?php RSP_Icons::e( 'alert' ); ?><div><p><?php echo esc_html( $rsp_g['third_party_note'] ); ?></p></div></div><?php endif; ?>
		<div class="rsp-third">
			<?php foreach ( $rsp_g['third_party'] as $rsp_t ) : ?>
				<div class="rsp-card rsp-third-card">
					<span class="rsp-third-mark" aria-hidden="true"><?php echo esc_html( mb_strtoupper( mb_substr( $rsp_t['name'], 0, 2 ) ) ); ?></span>
					<strong><?php echo esc_html( $rsp_t['name'] ); ?></strong>
					<?php if ( ! empty( $rsp_t['text'] ) ) : ?><small class="rsp-muted"><?php echo esc_html( $rsp_t['text'] ); ?></small><?php endif; ?>
					<?php if ( 'ready' === $rsp_t['status'] && ! empty( $rsp_t['url'] ) ) : ?>
						<a class="rsp-link" href="<?php echo esc_url( $rsp_t['url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Setup guide', 'relay-stream-portal' ); ?></a>
					<?php else : ?>
						<small class="rsp-muted"><?php esc_html_e( 'Guide coming soon', 'relay-stream-portal' ); ?></small>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
