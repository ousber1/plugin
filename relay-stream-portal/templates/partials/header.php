<?php
/**
 * Portal header: brand, navigation, theme toggle, notifications, account menu.
 *
 * @var array  $vm
 * @var string $module
 * @var array  $args
 * @package RelayStreamPortal
 */

defined( 'ABSPATH' ) || exit;

$rsp_s     = RSP_Settings::all();
$rsp_logo  = $rsp_s['logo_id'] ? wp_get_attachment_image( (int) $rsp_s['logo_id'], 'medium', false, array( 'class' => 'rsp-logo-img', 'alt' => esc_attr( $rsp_s['brand_name'] ) ) ) : '';
$rsp_links = RSP_Settings::nav_modules();
unset( $rsp_links['profile'] );
$rsp_preview = ! empty( $vm['preview'] );
?>
<header class="rsp-header">
	<div class="rsp-header-row">
		<a class="rsp-brand" href="<?php echo esc_url( $rsp_preview ? '#' : RSP_Settings::page_url( 'dashboard' ) ); ?>">
			<?php if ( $rsp_logo ) : ?>
				<?php echo $rsp_logo; // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<?php else : ?>
				<span class="rsp-brand-mark" aria-hidden="true"><svg viewBox="0 0 32 32" width="30" height="30"><defs><linearGradient id="rspg" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="var(--rsp-accent)"/><stop offset="1" stop-color="var(--rsp-accent-2)"/></linearGradient></defs><rect width="32" height="32" rx="9" fill="url(#rspg)"/><path d="M9 22V10h6.5a4 4 0 0 1 0 8H12m3.5 0L22 22" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
			<?php endif; ?>
			<span class="rsp-brand-text"><strong><?php echo esc_html( $rsp_s['brand_name'] ); ?></strong><small><?php echo esc_html( $rsp_s['brand_tagline'] ); ?></small></span>
		</a>
		<div class="rsp-header-actions">
			<?php if ( $rsp_s['allow_theme_toggle'] ) : ?>
				<button type="button" class="rsp-icon-btn" data-rsp-theme-toggle aria-label="<?php esc_attr_e( 'Toggle color theme', 'relay-stream-portal' ); ?>"><span class="rsp-when-dark"><?php RSP_Icons::e( 'sun' ); ?></span><span class="rsp-when-light"><?php RSP_Icons::e( 'moon' ); ?></span></button>
			<?php endif; ?>
			<div class="rsp-pop">
				<button type="button" class="rsp-icon-btn" data-rsp-pop aria-expanded="false" aria-label="<?php esc_attr_e( 'Notifications', 'relay-stream-portal' ); ?>">
					<?php RSP_Icons::e( 'bell' ); ?>
					<?php if ( ! empty( $vm['unread'] ) ) : ?><span class="rsp-dot" data-rsp-unread><?php echo (int) $vm['unread']; ?></span><?php endif; ?>
				</button>
				<div class="rsp-pop-panel" hidden>
					<div class="rsp-pop-head"><strong><?php esc_html_e( 'Notifications', 'relay-stream-portal' ); ?></strong>
						<?php if ( ! empty( $vm['unread'] ) ) : ?><button type="button" class="rsp-link" data-rsp-read-all><?php esc_html_e( 'Mark all read', 'relay-stream-portal' ); ?></button><?php endif; ?>
					</div>
					<?php if ( empty( $vm['notifications'] ) ) : ?>
						<p class="rsp-muted rsp-pad"><?php esc_html_e( 'You are all caught up.', 'relay-stream-portal' ); ?></p>
					<?php else : ?>
						<ul class="rsp-notes">
							<?php foreach ( $vm['notifications'] as $rsp_n ) : ?>
								<li class="<?php echo empty( $rsp_n['read_at'] ) ? 'is-unread' : ''; ?>">
									<a href="<?php echo esc_url( $rsp_n['link'] ? $rsp_n['link'] : '#' ); ?>"><strong><?php echo esc_html( $rsp_n['title'] ); ?></strong><span><?php echo esc_html( $rsp_n['body'] ); ?></span><time><?php echo RSP_Portal::date( $rsp_n['created_at'] ); // phpcs:ignore ?></time></a>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
			</div>
			<div class="rsp-pop">
				<button type="button" class="rsp-user-btn" data-rsp-pop aria-expanded="false">
					<span class="rsp-avatar" aria-hidden="true"><?php echo esc_html( $vm['user']['initials'] ? $vm['user']['initials'] : '•' ); ?></span>
					<span class="rsp-user-name"><?php echo esc_html( $vm['user']['name'] ); ?></span>
				</button>
				<div class="rsp-pop-panel rsp-pop-menu" hidden>
					<a href="<?php echo esc_url( $rsp_preview ? '#' : RSP_Settings::page_url( 'profile' ) ); ?>"><?php RSP_Icons::e( 'profile', 18 ); ?><?php esc_html_e( 'Profile', 'relay-stream-portal' ); ?></a>
					<a href="<?php echo esc_url( $rsp_preview ? '#' : wp_logout_url( RSP_Settings::page_url( 'login', array( 'rsp_msg' => 'logged_out' ) ) ) ); ?>"><?php RSP_Icons::e( 'logout', 18 ); ?><?php esc_html_e( 'Sign out', 'relay-stream-portal' ); ?></a>
				</div>
			</div>
		</div>
	</div>
	<nav class="rsp-nav" aria-label="<?php esc_attr_e( 'Portal', 'relay-stream-portal' ); ?>">
		<?php foreach ( $rsp_links as $rsp_slug => $rsp_label ) : ?>
			<a href="<?php echo esc_url( $rsp_preview ? '#' : RSP_Settings::page_url( $rsp_slug ) ); ?>" class="rsp-nav-link<?php echo $rsp_slug === $module ? ' is-current' : ''; ?>"<?php echo $rsp_slug === $module ? ' aria-current="page"' : ''; ?>>
				<?php RSP_Icons::e( $rsp_slug, 18 ); ?><span><?php echo esc_html( RSP_Portal::label( $args, 'nav_' . $rsp_slug, $rsp_label ) ); ?></span>
			</a>
		<?php endforeach; ?>
	</nav>
</header>
