<?php
/**
 * Sidebar app shell: top bar, left navigation with promo cards, main content, chat button.
 *
 * @var array  $vm
 * @var string $module
 * @var array  $args
 * @var string $content Module HTML (already escaped by its template).
 * @package RelayStreamPortal
 */

defined( 'ABSPATH' ) || exit;

$rsp_s       = RSP_Settings::all();
$rsp_p       = ! empty( $vm['preview'] );
$rsp_u       = static function ( $role, $q = array() ) use ( $rsp_p ) {
	return $rsp_p ? '#' : RSP_Settings::page_url( $role, $q );
};
$rsp_links   = RSP_Settings::nav_modules();
unset( $rsp_links['profile'], $rsp_links['redeem'] );
$rsp_logo    = $rsp_s['logo_id'] ? wp_get_attachment_image( (int) $rsp_s['logo_id'], 'medium', false, array( 'class' => 'rsp-logo-img', 'alt' => esc_attr( $rsp_s['brand_name'] ) ) ) : '';
$rsp_days    = (int) $rsp_s['referral_days'];
$rsp_support = $rsp_s['support_url'] ? $rsp_s['support_url'] : $rsp_u( 'support', array( 'new' => 1 ) );
$rsp_active  = 'checkout' === $module ? 'plans' : $module;
$rsp_icons   = array( 'dashboard' => 'play', 'accounts' => 'accounts', 'apps' => 'grid', 'plans' => 'card', 'transactions' => 'transactions2', 'referrals' => 'referrals', 'support' => 'lifebuoy' );
?>
<div class="rsp-app" data-rsp-app>
	<header class="rsp-topbar">
		<button type="button" class="rsp-icon-btn rsp-burger" data-rsp-sidebar-toggle aria-label="<?php esc_attr_e( 'Toggle menu', 'relay-stream-portal' ); ?>" aria-expanded="false"><?php RSP_Icons::e( 'menu' ); ?></button>
		<a class="rsp-brand" href="<?php echo esc_url( $rsp_u( 'dashboard' ) ); ?>">
			<?php if ( $rsp_logo ) : ?>
				<?php echo $rsp_logo; // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<?php else : ?>
				<span class="rsp-brand-mark" aria-hidden="true"><svg viewBox="0 0 32 32" width="30" height="30"><path d="M16 3 29 28H3z" fill="currentColor"/><path d="M16 12 22 24H10z" fill="var(--rsp-bg)"/></svg></span>
			<?php endif; ?>
			<span class="rsp-brand-text"><strong><?php echo esc_html( $rsp_s['brand_name'] ); ?></strong><small><?php echo esc_html( $rsp_s['brand_tagline'] ); ?></small></span>
		</a>
		<div class="rsp-topbar-actions">
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
						<?php if ( ! empty( $vm['unread'] ) ) : ?><button type="button" class="rsp-link" data-rsp-read-all><?php esc_html_e( 'Mark all read', 'relay-stream-portal' ); ?></button><?php endif; ?></div>
					<?php if ( empty( $vm['notifications'] ) ) : ?>
						<p class="rsp-muted rsp-pad"><?php esc_html_e( 'You are all caught up.', 'relay-stream-portal' ); ?></p>
					<?php else : ?>
						<ul class="rsp-notes">
							<?php foreach ( $vm['notifications'] as $rsp_n ) : ?>
								<li class="<?php echo empty( $rsp_n['read_at'] ) ? 'is-unread' : ''; ?>"><a href="<?php echo esc_url( $rsp_n['link'] ? $rsp_n['link'] : '#' ); ?>"><strong><?php echo esc_html( $rsp_n['title'] ); ?></strong><span><?php echo esc_html( $rsp_n['body'] ); ?></span><time><?php echo RSP_Portal::date( $rsp_n['created_at'] ); // phpcs:ignore ?></time></a></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
			</div>
			<div class="rsp-pop">
				<button type="button" class="rsp-user-btn" data-rsp-pop aria-expanded="false">
					<span class="rsp-avatar" aria-hidden="true"><?php echo esc_html( $vm['user']['initials'] ? $vm['user']['initials'] : '•' ); ?></span>
					<span class="rsp-user-name"><?php echo esc_html( $vm['user']['name'] ); ?></span>
					<?php RSP_Icons::e( 'chevron', 16 ); ?>
				</button>
				<div class="rsp-pop-panel rsp-pop-menu" hidden>
					<a href="<?php echo esc_url( $rsp_u( 'profile' ) ); ?>"><?php RSP_Icons::e( 'profile', 18 ); ?><?php esc_html_e( 'Profile', 'relay-stream-portal' ); ?></a>
					<a href="<?php echo esc_url( $rsp_u( 'redeem' ) ); ?>"><?php RSP_Icons::e( 'redeem', 18 ); ?><?php esc_html_e( 'Redeem a gift', 'relay-stream-portal' ); ?></a>
					<a href="<?php echo esc_url( $rsp_p ? '#' : wp_logout_url( RSP_Settings::page_url( 'login', array( 'rsp_msg' => 'logged_out' ) ) ) ); ?>"><?php RSP_Icons::e( 'logout', 18 ); ?><?php esc_html_e( 'Sign out', 'relay-stream-portal' ); ?></a>
				</div>
			</div>
		</div>
	</header>

	<div class="rsp-body">
		<aside class="rsp-sidebar" id="rsp-sidebar" aria-label="<?php esc_attr_e( 'Portal', 'relay-stream-portal' ); ?>">
			<nav class="rsp-side-nav">
				<?php foreach ( $rsp_links as $rsp_slug => $rsp_label ) : ?>
					<a href="<?php echo esc_url( $rsp_u( $rsp_slug ) ); ?>" class="rsp-side-link<?php echo $rsp_slug === $rsp_active ? ' is-current' : ''; ?>"<?php echo $rsp_slug === $rsp_active ? ' aria-current="page"' : ''; ?>>
						<?php RSP_Icons::e( isset( $rsp_icons[ $rsp_slug ] ) ? $rsp_icons[ $rsp_slug ] : $rsp_slug, 19 ); ?><span><?php echo esc_html( RSP_Portal::label( $args, 'nav_' . $rsp_slug, 'dashboard' === $rsp_slug ? __( 'Dashboard', 'relay-stream-portal' ) : $rsp_label ) ); ?></span>
					</a>
				<?php endforeach; ?>
			</nav>
			<div class="rsp-side-promos">
				<?php if ( $rsp_s['referral_enabled'] ) : ?>
					<div class="rsp-promo">
						<span class="rsp-promo-icon"><?php RSP_Icons::e( 'referrals', 20 ); ?></span>
						<strong><?php
							/* translators: %s: "30 FREE" highlighted */
							printf( esc_html__( 'Get %s Days', 'relay-stream-portal' ), '<mark>' . esc_html( sprintf( /* translators: %d days */ __( '%d FREE', 'relay-stream-portal' ), $rsp_days ) ) . '</mark>' ); ?></strong>
						<small><?php esc_html_e( 'for every referred friend', 'relay-stream-portal' ); ?></small>
						<a class="rsp-btn rsp-btn-gold rsp-btn-block" href="<?php echo esc_url( $rsp_u( 'referrals' ) ); ?>"><?php esc_html_e( 'Refer a friend', 'relay-stream-portal' ); ?></a>
					</div>
				<?php endif; ?>
				<?php if ( $rsp_s['gift_enabled'] && ! empty( $vm['billing'] ) ) : ?>
					<a class="rsp-promo-gift" href="<?php echo esc_url( $rsp_u( 'referrals', array( 'tab' => 'gifts' ) ) ); ?>">
						<span class="rsp-promo-heart"><?php RSP_Icons::e( 'heart', 18 ); ?></span>
						<span><strong><?php esc_html_e( 'Gift a Subscription', 'relay-stream-portal' ); ?></strong><small><?php esc_html_e( 'Buy for someone special', 'relay-stream-portal' ); ?></small></span>
					</a>
				<?php endif; ?>
			</div>
		</aside>
		<div class="rsp-scrim" data-rsp-sidebar-close hidden></div>
		<main class="rsp-main" id="rsp-main" tabindex="-1">
			<div class="rsp-container">
				<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput -- module templates escape their output. ?>
			</div>
		</main>
	</div>
	<?php if ( $rsp_s['floating_support'] ) : ?>
		<a class="rsp-chat" href="<?php echo esc_url( $rsp_support ); ?>" aria-label="<?php echo esc_attr( $rsp_s['floating_label'] ); ?>" title="<?php echo esc_attr( $rsp_s['floating_label'] ); ?>"><?php RSP_Icons::e( 'chat', 24 ); ?></a>
	<?php endif; ?>
</div>
