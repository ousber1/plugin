<?php
/**
 * Referrals & Gifts module.
 *
 * @var array $vm
 * @var array $args
 * @package RelayStreamPortal
 */

defined( 'ABSPATH' ) || exit;

$rsp_p     = ! empty( $vm['preview'] );
$rsp_inert = RSP_Portal::inert( $vm );
$rsp_tab   = ! empty( $args['tab'] ) ? $args['tab'] : ( isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'referrals' ); // phpcs:ignore WordPress.Security.NonceVerification
$rsp_tab   = 'gifts' === $rsp_tab ? 'gifts' : 'referrals';
$rsp_ref   = $vm['referral'];
$rsp_days  = (int) RSP_Settings::get( 'referral_days', 30 );
$rsp_gifts_on = $vm['billing'] && RSP_Settings::get( 'gift_enabled' );
?>
<div class="rsp-page">
	<div class="rsp-page-head"><div><h1><?php echo esc_html( RSP_Portal::label( $args, 'title', __( 'Referrals & Gifts', 'relay-stream-portal' ) ) ); ?></h1>
		<p class="rsp-muted"><?php esc_html_e( 'Invite friends, gift a plan and collect free days.', 'relay-stream-portal' ); ?></p></div></div>

	<div class="rsp-tabs" role="tablist">
		<a role="tab" class="rsp-tab<?php echo 'referrals' === $rsp_tab ? ' is-current' : ''; ?>" aria-selected="<?php echo 'referrals' === $rsp_tab ? 'true' : 'false'; ?>" href="<?php echo esc_url( $rsp_p ? '#' : add_query_arg( 'tab', 'referrals' ) ); ?>"><?php RSP_Icons::e( 'referrals', 18 ); ?><?php esc_html_e( 'Referrals', 'relay-stream-portal' ); ?></a>
		<?php if ( $rsp_gifts_on ) : ?>
			<a role="tab" class="rsp-tab<?php echo 'gifts' === $rsp_tab ? ' is-current' : ''; ?>" aria-selected="<?php echo 'gifts' === $rsp_tab ? 'true' : 'false'; ?>" href="<?php echo esc_url( $rsp_p ? '#' : add_query_arg( 'tab', 'gifts' ) ); ?>"><?php RSP_Icons::e( 'redeem', 18 ); ?><?php esc_html_e( 'Gift a plan', 'relay-stream-portal' ); ?></a>
		<?php endif; ?>
	</div>

	<?php if ( 'referrals' === $rsp_tab ) : ?>
		<?php if ( ! RSP_Settings::get( 'referral_enabled' ) ) : ?>
			<div class="rsp-card rsp-empty"><p><?php esc_html_e( 'The referral program is currently paused.', 'relay-stream-portal' ); ?></p></div>
		<?php else : ?>
			<div class="rsp-hero-card">
				<div class="rsp-hero-icon"><?php RSP_Icons::e( 'referrals', 28 ); ?></div>
				<div><h2><?php
					/* translators: %d days */
					printf( esc_html__( 'Get %s for every friend who subscribes', 'relay-stream-portal' ), '<mark>' . esc_html( sprintf( _n( '%d free day', '%d free days', $rsp_days, 'relay-stream-portal' ), $rsp_days ) ) . '</mark>' ); ?></h2>
					<p class="rsp-muted"><?php
						/* translators: %d days */
						echo esc_html( sprintf( __( 'Share your link. After your friend’s first confirmed payment and a %d-day review period, the days are added to your reward balance. You choose which account to apply them to.', 'relay-stream-portal' ), (int) RSP_Settings::get( 'referral_review_days', 7 ) ) ); ?></p></div>
			</div>

			<div class="rsp-grid rsp-grid-2">
				<div class="rsp-card"><span class="rsp-eyebrow"><?php esc_html_e( 'Your referral code', 'relay-stream-portal' ); ?></span>
					<div class="rsp-copy-row"><code class="rsp-code-big"><?php echo esc_html( $rsp_ref['code'] ); ?></code><button type="button" class="rsp-btn rsp-btn-sm" data-rsp-copy="<?php echo esc_attr( $rsp_ref['code'] ); ?>"><?php RSP_Icons::e( 'copy', 16 ); ?><span><?php esc_html_e( 'Copy', 'relay-stream-portal' ); ?></span></button></div></div>
				<div class="rsp-card"><span class="rsp-eyebrow"><?php esc_html_e( 'Your share link', 'relay-stream-portal' ); ?></span>
					<div class="rsp-copy-row"><input class="rsp-copy-input" type="text" readonly value="<?php echo esc_attr( $rsp_ref['link'] ); ?>" aria-label="<?php esc_attr_e( 'Share link', 'relay-stream-portal' ); ?>"><button type="button" class="rsp-btn rsp-btn-sm" data-rsp-copy="<?php echo esc_attr( $rsp_ref['link'] ); ?>"><?php RSP_Icons::e( 'link', 16 ); ?><span><?php esc_html_e( 'Copy', 'relay-stream-portal' ); ?></span></button></div></div>
			</div>

			<div class="rsp-stats">
				<div class="rsp-stat"><span class="rsp-stat-icon"><?php RSP_Icons::e( 'accounts' ); ?></span><div><strong><?php echo (int) $rsp_ref['stats']['invited']; ?></strong><small><?php esc_html_e( 'Total invited', 'relay-stream-portal' ); ?></small></div></div>
				<div class="rsp-stat"><span class="rsp-stat-icon"><?php RSP_Icons::e( 'check' ); ?></span><div><strong><?php echo (int) $rsp_ref['stats']['converted']; ?></strong><small><?php esc_html_e( 'Converted', 'relay-stream-portal' ); ?></small></div></div>
				<div class="rsp-stat"><span class="rsp-stat-icon"><?php RSP_Icons::e( 'clock' ); ?></span><div><strong><?php echo (int) $rsp_ref['stats']['pending']; ?></strong><small><?php esc_html_e( 'Pending', 'relay-stream-portal' ); ?></small></div></div>
				<div class="rsp-stat"><span class="rsp-stat-icon"><?php RSP_Icons::e( 'referrals' ); ?></span><div><strong><?php echo (int) $rsp_ref['stats']['earned']; ?></strong><small><?php esc_html_e( 'Days earned', 'relay-stream-portal' ); ?></small></div></div>
			</div>

			<?php if ( (int) $rsp_ref['stats']['balance'] > 0 ) : ?>
				<div class="rsp-announce rsp-tone-success"><?php RSP_Icons::e( 'referrals' ); ?><div><strong><?php
					/* translators: %d days */
					echo esc_html( sprintf( __( 'You have %d reward days to use', 'relay-stream-portal' ), (int) $rsp_ref['stats']['balance'] ) ); ?></strong>
					<p><?php esc_html_e( 'Apply them from My Accounts on the account you want to extend.', 'relay-stream-portal' ); ?> <a class="rsp-link" href="<?php echo esc_url( $rsp_p ? '#' : RSP_Settings::page_url( 'accounts' ) ); ?>"><?php esc_html_e( 'Go to My Accounts', 'relay-stream-portal' ); ?></a></p></div></div>
			<?php endif; ?>

			<section class="rsp-card">
				<div class="rsp-card-head"><h2><?php esc_html_e( 'Your referrals', 'relay-stream-portal' ); ?></h2></div>
				<?php if ( empty( $rsp_ref['history'] ) ) : ?>
					<div class="rsp-empty rsp-empty-sm"><?php RSP_Icons::e( 'accounts', 30 ); ?><p><?php esc_html_e( 'No referrals yet. Share your code to get started.', 'relay-stream-portal' ); ?></p></div>
				<?php else : ?>
					<ul class="rsp-mini-list">
						<?php foreach ( $rsp_ref['history'] as $rsp_h ) : ?>
							<li><span class="rsp-mini-name"><?php echo esc_html( $rsp_h['label'] ); ?><small><?php echo RSP_Portal::date( $rsp_h['date'] ); // phpcs:ignore ?></small></span>
								<?php echo RSP_Portal::pill( $rsp_h['status'], RSP_Referrals::status_label( $rsp_h['status'] ) ); // phpcs:ignore ?>
								<span><?php echo $rsp_h['days'] ? esc_html( '+' . (int) $rsp_h['days'] ) : '—'; ?></span></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</section>
		<?php endif; ?>
	<?php else : ?>
		<section class="rsp-card">
			<div class="rsp-card-head"><h2><?php esc_html_e( 'Buy a gift subscription', 'relay-stream-portal' ); ?></h2></div>
			<p class="rsp-muted"><?php
				/* translators: %d days */
				echo esc_html( sprintf( __( 'After payment you receive a single-use code valid for %d days. The recipient redeems it for a new account or to extend one of theirs.', 'relay-stream-portal' ), (int) RSP_Settings::get( 'gift_expiry_days', 365 ) ) ); ?></p>
			<form method="post">
				<?php echo RSP_Forms::fields( 'checkout' ); // phpcs:ignore ?>
				<input type="hidden" name="purpose" value="gift">
				<label class="rsp-field"><span><?php esc_html_e( 'Recipient email (optional — we email the code after payment)', 'relay-stream-portal' ); ?></span><input type="email" name="gift_email" maxlength="190"></label>
				<?php echo RSP_Portal::template( 'partials/plan-cards', array( 'vm' => $vm, 'args' => array( 'in_form' => true, 'gift_only' => true ) ) ); // phpcs:ignore ?>
			</form>
		</section>
		<section class="rsp-card">
			<div class="rsp-card-head"><h2><?php esc_html_e( 'Gifts you bought', 'relay-stream-portal' ); ?></h2><a class="rsp-link" href="<?php echo esc_url( $rsp_p ? '#' : RSP_Settings::page_url( 'redeem' ) ); ?>"><?php esc_html_e( 'Redeem a code', 'relay-stream-portal' ); ?></a></div>
			<?php if ( empty( $vm['gifts'] ) ) : ?>
				<div class="rsp-empty rsp-empty-sm"><?php RSP_Icons::e( 'redeem', 30 ); ?><p><?php esc_html_e( 'No gifts yet.', 'relay-stream-portal' ); ?></p></div>
			<?php else : ?>
				<div class="rsp-gift-list">
				<?php foreach ( $vm['gifts'] as $rsp_g ) : ?>
					<div class="rsp-gift">
						<div class="rsp-gift-main"><strong><?php echo esc_html( $rsp_g['plan'] ); ?></strong>
							<?php echo RSP_Portal::pill( $rsp_g['status'], RSP_Gifts::status_label( $rsp_g['status'] ) ); // phpcs:ignore ?>
							<small class="rsp-muted"><?php
								/* translators: %s date */
								printf( esc_html__( 'Expires %s', 'relay-stream-portal' ), RSP_Portal::date( $rsp_g['expires_at'] ) ); // phpcs:ignore ?></small></div>
						<?php if ( $rsp_g['code'] ) : ?>
							<div class="rsp-copy-row"><code class="rsp-code-big"><?php echo esc_html( $rsp_g['code'] ); ?></code><button type="button" class="rsp-btn rsp-btn-sm" data-rsp-copy="<?php echo esc_attr( $rsp_g['code'] ); ?>"><?php RSP_Icons::e( 'copy', 16 ); ?><span><?php esc_html_e( 'Copy', 'relay-stream-portal' ); ?></span></button></div>
							<form method="post" class="rsp-input-row">
								<?php echo RSP_Forms::fields( 'gift_email' ); // phpcs:ignore ?>
								<input type="hidden" name="gift" value="<?php echo (int) $rsp_g['id']; ?>">
								<input type="email" name="email" required placeholder="<?php esc_attr_e( 'friend@example.com', 'relay-stream-portal' ); ?>" value="<?php echo esc_attr( $rsp_g['recipient'] ); ?>" aria-label="<?php esc_attr_e( 'Recipient email', 'relay-stream-portal' ); ?>">
								<button class="rsp-btn rsp-btn-sm" type="submit"<?php echo $rsp_inert; // phpcs:ignore ?>><?php esc_html_e( 'Send by email', 'relay-stream-portal' ); ?></button>
							</form>
						<?php else : ?>
							<p class="rsp-muted rsp-small"><?php
								/* translators: %s last 4 */
								echo esc_html( sprintf( __( 'Code ending in %s', 'relay-stream-portal' ), $rsp_g['hint'] ) ); ?></p>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</section>
	<?php endif; ?>
</div>
