<?php
/**
 * Apps & Guides module.
 *
 * @var array $vm
 * @var array $args
 * @package RelayStreamPortal
 */

defined( 'ABSPATH' ) || exit;

$rsp_g    = RSP_Guides::get();
$rsp_r    = wp_parse_args( $rsp_g['release'], array( 'enabled' => 0, 'version' => '', 'title' => '', 'subtitle' => '', 'description' => '', 'compat' => '', 'qr_url' => '', 'stats' => array(), 'availability' => '' ) );
$rsp_logo = (int) RSP_Settings::get( 'logo_id' );
$rsp_wn_icons = array( 'sparkle', 'mic', 'bolt', 'shield' );
?>
<div class="rsp-page">
	<?php if ( $rsp_r['enabled'] ) : ?>
		<section class="rsp-release">
			<div class="rsp-release-text">
				<?php if ( $rsp_r['version'] ) : ?><span class="rsp-chip-live"><?php
					/* translators: %s version */
					echo esc_html( sprintf( __( 'v%s · Now available', 'relay-stream-portal' ), ltrim( $rsp_r['version'], 'vV' ) ) ); ?></span><?php endif; ?>
				<div class="rsp-release-brand">
					<?php if ( $rsp_logo ) : ?>
						<?php echo wp_get_attachment_image( $rsp_logo, 'medium', false, array( 'class' => 'rsp-release-logo', 'alt' => '' ) ); ?>
					<?php else : ?>
						<span class="rsp-release-mark" aria-hidden="true"><svg viewBox="0 0 32 32" width="72" height="72"><path d="M16 3 29 28H3z" fill="currentColor"/><path d="M16 12 22 24H10z" fill="var(--rsp-surface)"/></svg></span>
					<?php endif; ?>
					<div><h1><?php echo esc_html( $rsp_r['title'] ? $rsp_r['title'] : RSP_Settings::get( 'brand_name' ) ); ?></h1><?php if ( $rsp_r['subtitle'] ) : ?><p class="rsp-release-sub"><?php echo esc_html( $rsp_r['subtitle'] ); ?></p><?php endif; ?></div>
				</div>
				<?php if ( $rsp_r['description'] ) : ?><p class="rsp-release-desc"><?php echo esc_html( $rsp_r['description'] ); ?></p><?php endif; ?>
				<?php if ( $rsp_r['stats'] ) : ?>
					<dl class="rsp-release-stats"><?php foreach ( $rsp_r['stats'] as $rsp_s ) : ?><div><dt><?php echo esc_html( $rsp_s['label'] ); ?></dt><dd><?php echo esc_html( $rsp_s['value'] ); ?></dd></div><?php endforeach; ?></dl>
				<?php endif; ?>
			</div>
			<?php if ( $rsp_r['qr_url'] && RSP_Guides::is_public_url( $rsp_r['qr_url'] ) ) : ?>
				<div class="rsp-qr-wrap rsp-qr-hero"><div class="rsp-qr" data-rsp-qr="<?php echo esc_attr( $rsp_r['qr_url'] ); ?>" role="img" aria-label="<?php esc_attr_e( 'QR code for the install page', 'relay-stream-portal' ); ?>"></div><small><?php esc_html_e( 'Scan with your phone to grab the install link', 'relay-stream-portal' ); ?></small></div>
			<?php endif; ?>
		</section>
	<?php else : ?>
		<div class="rsp-page-head"><div><h1><?php echo esc_html( RSP_Portal::label( $args, 'title', __( 'Apps & Guides', 'relay-stream-portal' ) ) ); ?></h1></div></div>
	<?php endif; ?>

	<?php if ( $rsp_g['login_note'] ) : ?>
		<div class="rsp-info-box"><span class="rsp-info-i">i</span><div><strong><?php echo esc_html( ! empty( $rsp_g['login_title'] ) ? $rsp_g['login_title'] : __( 'App login', 'relay-stream-portal' ) ); ?></strong><p><?php echo RSP_Portal::rich( $rsp_g['login_note'] ); // phpcs:ignore ?></p><?php if ( ! empty( $rsp_g['login_small'] ) ) : ?><small><?php echo esc_html( $rsp_g['login_small'] ); ?></small><?php endif; ?></div></div>
	<?php endif; ?>

	<div class="rsp-section-row"><h2 class="rsp-section-title"><?php esc_html_e( 'Pick your device', 'relay-stream-portal' ); ?></h2><?php if ( $rsp_r['version'] ) : ?><small class="rsp-muted"><?php
		/* translators: %s version */
		echo esc_html( sprintf( __( 'Auto-updates inside the app · v%s', 'relay-stream-portal' ), ltrim( $rsp_r['version'], 'vV' ) ) ); ?></small><?php endif; ?></div>
	<?php echo RSP_Portal::template( 'partials/device-guides', array( 'vm' => $vm, 'args' => $args ) ); // phpcs:ignore ?>
	<?php if ( $rsp_r['availability'] ) : ?><p class="rsp-muted rsp-small"><?php echo RSP_Portal::rich( $rsp_r['availability'] ); // phpcs:ignore ?></p><?php endif; ?>

	<?php if ( $rsp_g['whats_new'] ) : ?>
		<h2 class="rsp-section-title"><?php esc_html_e( 'What’s new', 'relay-stream-portal' ); ?><?php if ( $rsp_r['version'] ) : ?> <span class="rsp-muted"><?php
			/* translators: %s version */
			echo esc_html( sprintf( __( 'in v%s', 'relay-stream-portal' ), ltrim( $rsp_r['version'], 'vV' ) ) ); ?></span><?php endif; ?></h2>
		<div class="rsp-features">
			<?php foreach ( $rsp_g['whats_new'] as $rsp_i => $rsp_w ) : ?>
				<div class="rsp-card rsp-feature"><span class="rsp-stat-icon rsp-c-blue rsp-sm-ic"><?php RSP_Icons::e( ! empty( $rsp_w['icon'] ) ? $rsp_w['icon'] : $rsp_wn_icons[ $rsp_i % 4 ], 18 ); ?></span><div><strong><?php echo esc_html( $rsp_w['title'] ); ?></strong><p class="rsp-muted"><?php echo esc_html( $rsp_w['text'] ); ?></p></div></div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php if ( $rsp_g['third_party'] ) : ?>
		<h2 class="rsp-section-title"><?php esc_html_e( 'Use a different player', 'relay-stream-portal' ); ?></h2>
		<?php if ( ! empty( $rsp_g['third_party_intro'] ) ) : ?><p class="rsp-muted"><?php echo esc_html( $rsp_g['third_party_intro'] ); ?></p><?php endif; ?>
		<?php if ( $rsp_g['third_party_note'] ) : ?><div class="rsp-warn-box"><?php echo RSP_Portal::rich( $rsp_g['third_party_note'] ); // phpcs:ignore ?> <a href="<?php echo esc_url( ! empty( $vm['preview'] ) ? '#' : RSP_Settings::page_url( 'accounts' ) ); ?>"><?php esc_html_e( 'My Accounts → player details', 'relay-stream-portal' ); ?></a></div><?php endif; ?>
		<div class="rsp-third">
			<?php foreach ( $rsp_g['third_party'] as $rsp_t ) : ?>
				<?php $rsp_ready = 'ready' === $rsp_t['status'] && $rsp_t['url']; ?>
				<<?php echo $rsp_ready ? 'a href="' . esc_url( $rsp_t['url'] ) . '" target="_blank" rel="noopener"' : 'div'; ?> class="rsp-third-card">
					<span class="rsp-third-mark" aria-hidden="true"><?php echo esc_html( mb_strtoupper( mb_substr( preg_replace( '/[^\p{L}\p{N}]/u', '', $rsp_t['name'] ), 0, 2 ) ) ); ?></span>
					<strong><?php echo esc_html( $rsp_t['name'] ); ?></strong>
					<small class="rsp-muted"><?php echo esc_html( $rsp_ready ? __( 'Setup guide', 'relay-stream-portal' ) : __( 'Coming soon', 'relay-stream-portal' ) ); ?></small>
				</<?php echo $rsp_ready ? 'a' : 'div'; ?>>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
