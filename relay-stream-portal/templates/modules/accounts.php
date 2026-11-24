<?php
/**
 * My Accounts module.
 *
 * @var array $vm
 * @var array $args
 * @package RelayStreamPortal
 */

defined( 'ABSPATH' ) || exit;

$rsp_p       = ! empty( $vm['preview'] );
$rsp_inert   = RSP_Portal::inert( $vm );
$rsp_poll    = false;
$rsp_balance = isset( $vm['referral']['stats']['balance'] ) ? (int) $vm['referral']['stats']['balance'] : 0;
$rsp_state_text = array(
	'pending_payment'    => __( 'We are waiting for your payment to be confirmed.', 'relay-stream-portal' ),
	'pending_activation' => __( 'Payment confirmed. We are activating this account — this page updates automatically.', 'relay-stream-portal' ),
	'review'             => __( 'Our team is checking this activation and will update it shortly. No action is needed from you.', 'relay-stream-portal' ),
	'expired'            => __( 'This account has expired. Renew it to keep watching — your settings stay the same.', 'relay-stream-portal' ),
	'suspended'          => __( 'This account is suspended. Contact support for details.', 'relay-stream-portal' ),
);
?>
<div class="rsp-page">
	<div class="rsp-page-head">
		<div><h1><?php echo esc_html( RSP_Portal::label( $args, 'title', __( 'My Accounts', 'relay-stream-portal' ) ) ); ?></h1>
			<p class="rsp-muted"><?php esc_html_e( 'Each streaming account has its own expiry and player details. Your portal sign-in stays the same.', 'relay-stream-portal' ); ?></p></div>
		<?php if ( $vm['billing'] ) : ?>
			<a class="rsp-btn rsp-btn-primary" href="<?php echo esc_url( $rsp_p ? '#' : RSP_Settings::page_url( 'plans', array( 'purpose' => 'new' ) ) ); ?>"><?php RSP_Icons::e( 'plus' ); ?><?php esc_html_e( 'Add another account', 'relay-stream-portal' ); ?></a>
		<?php endif; ?>
	</div>

	<?php foreach ( $vm['pending_orders'] as $rsp_po ) : ?>
		<div class="rsp-card rsp-account is-pending">
			<div class="rsp-account-head"><div><h3><?php echo esc_html( $rsp_po['plan'] ); ?></h3><small class="rsp-muted"><?php
				/* translators: %s order number */
				echo esc_html( sprintf( __( 'Order #%s', 'relay-stream-portal' ), $rsp_po['number'] ) ); ?></small></div>
				<?php echo RSP_Portal::pill( 'pending_payment', RSP_Accounts::status_label( 'pending_payment' ) ); // phpcs:ignore ?></div>
			<p class="rsp-state-note"><?php echo esc_html( $rsp_state_text['pending_payment'] ); ?></p>
			<?php if ( $rsp_po['pay_url'] ) : ?><a class="rsp-btn" href="<?php echo esc_url( $rsp_po['pay_url'] ); ?>"><?php esc_html_e( 'Complete payment', 'relay-stream-portal' ); ?></a><?php endif; ?>
		</div>
	<?php endforeach; ?>

	<?php if ( empty( $vm['accounts'] ) && empty( $vm['pending_orders'] ) ) : ?>
		<div class="rsp-card rsp-empty">
			<?php RSP_Icons::e( 'tv', 40 ); ?>
			<h3><?php esc_html_e( 'No streaming accounts yet', 'relay-stream-portal' ); ?></h3>
			<p class="rsp-muted"><?php esc_html_e( 'Pick a plan to create your first account, or redeem a gift code.', 'relay-stream-portal' ); ?></p>
			<div class="rsp-actions">
				<?php if ( $vm['billing'] ) : ?><a class="rsp-btn rsp-btn-primary" href="<?php echo esc_url( $rsp_p ? '#' : RSP_Settings::page_url( 'plans' ) ); ?>"><?php esc_html_e( 'Choose a plan', 'relay-stream-portal' ); ?></a><?php endif; ?>
				<a class="rsp-btn" href="<?php echo esc_url( $rsp_p ? '#' : RSP_Settings::page_url( 'redeem' ) ); ?>"><?php esc_html_e( 'Redeem a gift', 'relay-stream-portal' ); ?></a>
			</div>
		</div>
	<?php endif; ?>

	<div class="rsp-accounts">
	<?php foreach ( $vm['accounts'] as $rsp_a ) :
		$rsp_status = $rsp_a['status'];
		if ( in_array( $rsp_status, array( 'pending_activation', 'review' ), true ) || ( $rsp_a['job'] && in_array( $rsp_a['job']['state'], array( 'pending', 'working' ), true ) ) ) {
			$rsp_poll = true;
		}
		?>
		<article class="rsp-card rsp-account is-<?php echo esc_attr( $rsp_status ); ?>" data-rsp-account="<?php echo esc_attr( $rsp_a['uuid'] ); ?>">
			<div class="rsp-account-head">
				<div>
					<h3><?php echo esc_html( $rsp_a['nickname'] ); ?></h3>
					<small class="rsp-muted"><?php echo esc_html( $rsp_a['plan'] ? $rsp_a['plan'] : __( 'Plan pending', 'relay-stream-portal' ) ); ?></small>
				</div>
				<?php echo RSP_Portal::pill( $rsp_status, $rsp_a['status_label'] ); // phpcs:ignore ?>
			</div>

			<dl class="rsp-facts">
				<div><dt><?php esc_html_e( 'Activated', 'relay-stream-portal' ); ?></dt><dd><?php echo RSP_Portal::date( $rsp_a['activated_at'] ); // phpcs:ignore ?></dd></div>
				<div><dt><?php esc_html_e( 'Expires', 'relay-stream-portal' ); ?></dt><dd><?php echo RSP_Portal::date( $rsp_a['expires_at'] ); // phpcs:ignore ?></dd></div>
				<div><dt><?php esc_html_e( 'Connections', 'relay-stream-portal' ); ?></dt><dd><?php echo null === $rsp_a['max_connections'] ? '—' : (int) $rsp_a['max_connections']; ?></dd></div>
				<?php if ( 'active' === $rsp_status && null !== $rsp_a['days_left'] ) : ?>
					<div><dt><?php esc_html_e( 'Remaining', 'relay-stream-portal' ); ?></dt><dd><?php
						/* translators: %d days */
						echo esc_html( sprintf( _n( '%d day', '%d days', (int) $rsp_a['days_left'], 'relay-stream-portal' ), (int) $rsp_a['days_left'] ) ); ?></dd></div>
				<?php endif; ?>
			</dl>

			<?php if ( isset( $rsp_state_text[ $rsp_status ] ) ) : ?>
				<p class="rsp-state-note"><?php echo esc_html( $rsp_state_text[ $rsp_status ] ); ?></p>
			<?php elseif ( $rsp_a['job'] ) : ?>
				<p class="rsp-state-note"><?php
					/* translators: %s state */
					echo esc_html( sprintf( __( 'An update to this account is in progress (%s).', 'relay-stream-portal' ), $rsp_a['job']['label'] ) ); ?></p>
			<?php endif; ?>

			<?php if ( $rsp_a['has_credentials'] && in_array( $rsp_status, array( 'active', 'expired' ), true ) && RSP_Settings::get( 'show_credentials' ) ) : ?>
				<details class="rsp-creds">
					<summary><?php RSP_Icons::e( 'eye', 18 ); ?><?php esc_html_e( 'Player details for third-party apps', 'relay-stream-portal' ); ?></summary>
					<p class="rsp-muted rsp-small"><?php esc_html_e( 'These are not your portal sign-in. Keep them private.', 'relay-stream-portal' ); ?></p>
					<div class="rsp-cred-grid" data-rsp-creds="<?php echo esc_attr( $rsp_a['uuid'] ); ?>">
						<?php
						foreach ( array( 'server' => __( 'Server', 'relay-stream-portal' ), 'username' => __( 'Username', 'relay-stream-portal' ), 'password' => __( 'Password', 'relay-stream-portal' ), 'm3u' => __( 'M3U address', 'relay-stream-portal' ), 'mac' => __( 'MAC', 'relay-stream-portal' ) ) as $rsp_k => $rsp_l ) :
							?>
							<div class="rsp-cred" data-field="<?php echo esc_attr( $rsp_k ); ?>"<?php echo 'mac' === $rsp_k ? ' hidden' : ''; ?>>
								<span class="rsp-cred-label"><?php echo esc_html( $rsp_l ); ?></span>
								<code class="rsp-cred-value">••••••••</code>
								<button type="button" class="rsp-icon-btn rsp-sm" data-rsp-copy-field disabled aria-label="<?php echo esc_attr( sprintf( /* translators: %s field */ __( 'Copy %s', 'relay-stream-portal' ), $rsp_l ) ); ?>"><?php RSP_Icons::e( 'copy', 16 ); ?></button>
							</div>
						<?php endforeach; ?>
					</div>
					<button type="button" class="rsp-btn rsp-btn-sm" data-rsp-reveal="<?php echo esc_attr( $rsp_a['uuid'] ); ?>"<?php echo $rsp_inert; // phpcs:ignore ?>><?php RSP_Icons::e( 'eye', 16 ); ?><span><?php esc_html_e( 'Show details', 'relay-stream-portal' ); ?></span></button>
				</details>
			<?php endif; ?>

			<div class="rsp-actions">
				<?php if ( ! empty( $rsp_a['renewable'] ) && $vm['billing'] ) : ?>
					<a class="rsp-btn rsp-btn-primary" href="<?php echo esc_url( $rsp_p ? '#' : RSP_Settings::page_url( 'plans', array( 'renew' => $rsp_a['uuid'] ) ) ); ?>"><?php RSP_Icons::e( 'refresh', 18 ); ?><?php esc_html_e( 'Renew this account', 'relay-stream-portal' ); ?></a>
				<?php endif; ?>
				<?php if ( 'active' === $rsp_status && ! empty( $vm['player']['enabled'] ) ) : ?>
					<a class="rsp-btn" href="<?php echo esc_url( $vm['player']['url'] ); ?>" target="_blank" rel="noopener"><?php RSP_Icons::e( 'play', 18 ); ?><?php echo esc_html( $vm['player']['label'] ); ?></a>
				<?php endif; ?>
				<?php if ( ! empty( $rsp_a['renewable'] ) && $rsp_balance > 0 ) : ?>
					<form method="post" class="rsp-inline-form">
						<?php echo RSP_Forms::fields( 'apply_days' ); // phpcs:ignore ?>
						<input type="hidden" name="account" value="<?php echo esc_attr( $rsp_a['uuid'] ); ?>">
						<button class="rsp-btn" type="submit"<?php echo $rsp_inert; // phpcs:ignore ?>><?php RSP_Icons::e( 'referrals', 18 ); ?><?php
							/* translators: %d days */
							echo esc_html( sprintf( __( 'Apply %d reward days', 'relay-stream-portal' ), $rsp_balance ) ); ?></button>
					</form>
				<?php endif; ?>
				<details class="rsp-rename">
					<summary class="rsp-link"><?php esc_html_e( 'Rename', 'relay-stream-portal' ); ?></summary>
					<form method="post" class="rsp-inline-form">
						<?php echo RSP_Forms::fields( 'account_rename' ); // phpcs:ignore ?>
						<input type="hidden" name="account" value="<?php echo esc_attr( $rsp_a['uuid'] ); ?>">
						<label class="screen-reader-text" for="rsp-nn-<?php echo esc_attr( $rsp_a['uuid'] ); ?>"><?php esc_html_e( 'Nickname', 'relay-stream-portal' ); ?></label>
						<input id="rsp-nn-<?php echo esc_attr( $rsp_a['uuid'] ); ?>" type="text" name="nickname" maxlength="80" value="<?php echo esc_attr( $rsp_a['nickname'] ); ?>" required>
						<button class="rsp-btn rsp-btn-sm" type="submit"<?php echo $rsp_inert; // phpcs:ignore ?>><?php esc_html_e( 'Save', 'relay-stream-portal' ); ?></button>
					</form>
				</details>
			</div>
		</article>
	<?php endforeach; ?>
	</div>
	<?php if ( $rsp_poll && ! $rsp_p ) : ?><span data-rsp-poll hidden></span><?php endif; ?>
</div>
