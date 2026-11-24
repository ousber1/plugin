<?php
/**
 * Dashboard module.
 *
 * @var array $vm
 * @var array $args
 * @package RelayStreamPortal
 */

defined( 'ABSPATH' ) || exit;

$rsp_p      = ! empty( $vm['preview'] );
$rsp_url    = static function ( $role, $q = array() ) use ( $rsp_p ) {
	return $rsp_p ? '#' : RSP_Settings::page_url( $role, $q );
};
$rsp_acc    = $vm['accounts'];
$rsp_active = array_values( array_filter( $rsp_acc, static function ( $a ) { return 'active' === $a['status']; } ) );
$rsp_renew  = array_values( array_filter( $rsp_acc, static function ( $a ) { return ! empty( $a['renewable'] ); } ) );
usort( $rsp_renew, static function ( $a, $b ) { return strcmp( (string) $a['expires_at'], (string) $b['expires_at'] ); } );
$rsp_status = $rsp_active ? 'active' : ( $rsp_acc ? $rsp_acc[0]['status'] : 'none' );
$rsp_next   = $rsp_active ? min( array_column( $rsp_active, 'expires_at' ) ) : null;
$rsp_hello  = str_replace( '{name}', $vm['user']['first'], RSP_Portal::label( $args, 'welcome', RSP_Settings::get( 'text_welcome' ) ) );
?>
<div class="rsp-page">
	<div class="rsp-page-head">
		<div><h1><?php echo esc_html( $rsp_hello ); ?></h1><p class="rsp-muted"><?php echo esc_html( RSP_Settings::get( 'text_welcome_sub' ) ); ?></p></div>
		<?php if ( ! empty( $vm['player']['enabled'] ) && $rsp_active ) : ?>
			<a class="rsp-btn rsp-btn-primary" href="<?php echo esc_url( $vm['player']['url'] ); ?>" target="_blank" rel="noopener"><?php RSP_Icons::e( 'play' ); ?><?php echo esc_html( $vm['player']['label'] ); ?></a>
		<?php endif; ?>
	</div>

	<?php echo RSP_Portal::template( 'partials/announcements', array( 'vm' => $vm ) ); // phpcs:ignore ?>

	<div class="rsp-stats">
		<div class="rsp-stat"><span class="rsp-stat-icon"><?php RSP_Icons::e( 'shield' ); ?></span><div><small><?php esc_html_e( 'Subscription', 'relay-stream-portal' ); ?></small><strong class="rsp-tone-<?php echo esc_attr( 'active' === $rsp_status ? 'ok' : ( 'none' === $rsp_status ? 'muted' : 'warn' ) ); ?>"><?php echo esc_html( 'none' === $rsp_status ? __( 'None yet', 'relay-stream-portal' ) : RSP_Accounts::status_label( $rsp_status ) ); ?></strong></div></div>
		<div class="rsp-stat"><span class="rsp-stat-icon"><?php RSP_Icons::e( 'clock' ); ?></span><div><small><?php esc_html_e( 'Next expiry', 'relay-stream-portal' ); ?></small><strong><?php echo RSP_Portal::date( $rsp_next ); // phpcs:ignore ?></strong></div></div>
		<div class="rsp-stat"><span class="rsp-stat-icon"><?php RSP_Icons::e( 'referrals' ); ?></span><div><small><?php esc_html_e( 'Reward days', 'relay-stream-portal' ); ?></small><strong><?php echo (int) $vm['referral']['stats']['balance']; ?></strong></div></div>
		<div class="rsp-stat"><span class="rsp-stat-icon"><?php RSP_Icons::e( 'transactions' ); ?></span><div><small><?php esc_html_e( 'Orders', 'relay-stream-portal' ); ?></small><strong><?php echo (int) $vm['order_count']; ?></strong></div></div>
	</div>

	<div class="rsp-grid rsp-grid-2-1">
		<?php echo RSP_Portal::template( 'partials/subscription-summary', array( 'vm' => $vm, 'args' => array() ) ); // phpcs:ignore ?>
		<section class="rsp-card">
			<div class="rsp-card-head"><h2><?php esc_html_e( 'Shortcuts', 'relay-stream-portal' ); ?></h2></div>
			<div class="rsp-shortcuts">
				<?php if ( $vm['billing'] ) : ?>
					<a href="<?php echo esc_url( $rsp_url( 'plans' ) ); ?>"><?php RSP_Icons::e( 'plans' ); ?><span><?php esc_html_e( 'Choose a plan', 'relay-stream-portal' ); ?></span></a>
					<?php if ( $rsp_renew ) : ?>
						<a href="<?php echo esc_url( $rsp_url( 'plans', array( 'renew' => $rsp_renew[0]['uuid'] ) ) ); ?>"><?php RSP_Icons::e( 'refresh' ); ?><span><?php
							/* translators: %s account */
							echo esc_html( sprintf( __( 'Renew %s', 'relay-stream-portal' ), $rsp_renew[0]['nickname'] ) ); ?></span></a>
					<?php endif; ?>
				<?php endif; ?>
				<?php if ( count( $rsp_acc ) > 1 ) : ?>
					<a href="<?php echo esc_url( $rsp_url( 'accounts' ) ); ?>"><?php RSP_Icons::e( 'accounts' ); ?><span><?php esc_html_e( 'Switch account', 'relay-stream-portal' ); ?></span></a>
				<?php endif; ?>
				<a href="<?php echo esc_url( $rsp_url( 'apps' ) ); ?>"><?php RSP_Icons::e( 'download' ); ?><span><?php esc_html_e( 'Download apps', 'relay-stream-portal' ); ?></span></a>
				<a href="<?php echo esc_url( $rsp_url( 'support', array( 'new' => 1 ) ) ); ?>"><?php RSP_Icons::e( 'support' ); ?><span><?php esc_html_e( 'Open a ticket', 'relay-stream-portal' ); ?></span></a>
				<?php if ( RSP_Settings::get( 'referral_enabled' ) ) : ?>
					<a href="<?php echo esc_url( $rsp_url( 'referrals' ) ); ?>"><?php RSP_Icons::e( 'referrals' ); ?><span><?php esc_html_e( 'Refer a friend', 'relay-stream-portal' ); ?></span></a>
				<?php endif; ?>
				<?php if ( $vm['billing'] && RSP_Settings::get( 'gift_enabled' ) ) : ?>
					<a href="<?php echo esc_url( $rsp_url( 'referrals', array( 'tab' => 'gifts' ) ) ); ?>"><?php RSP_Icons::e( 'redeem' ); ?><span><?php esc_html_e( 'Gift a subscription', 'relay-stream-portal' ); ?></span></a>
				<?php endif; ?>
			</div>
		</section>
	</div>

	<div class="rsp-grid rsp-grid-2">
		<?php if ( $vm['billing'] ) : ?>
			<section class="rsp-card">
				<div class="rsp-card-head"><h2><?php esc_html_e( 'Recent transactions', 'relay-stream-portal' ); ?></h2><a class="rsp-link" href="<?php echo esc_url( $rsp_url( 'transactions' ) ); ?>"><?php esc_html_e( 'View all', 'relay-stream-portal' ); ?></a></div>
				<?php if ( empty( $vm['orders']['rows'] ) ) : ?>
					<div class="rsp-empty rsp-empty-sm"><?php RSP_Icons::e( 'transactions', 30 ); ?><p><?php esc_html_e( 'No orders yet.', 'relay-stream-portal' ); ?></p></div>
				<?php else : ?>
					<ul class="rsp-mini-list">
						<?php foreach ( array_slice( $vm['orders']['rows'], 0, 4 ) as $rsp_o ) : ?>
							<li><span class="rsp-mini-name">#<?php echo esc_html( $rsp_o['number'] ); ?><small><?php echo RSP_Portal::date( $rsp_o['date'] ); // phpcs:ignore ?></small></span>
								<span><?php echo wp_kses_post( $rsp_o['total'] ); ?></span>
								<?php echo RSP_Portal::pill( $rsp_o['prov'], $rsp_o['prov_label'] ); // phpcs:ignore ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</section>
		<?php endif; ?>
		<section class="rsp-card">
			<div class="rsp-card-head"><h2><?php esc_html_e( 'Recent tickets', 'relay-stream-portal' ); ?></h2><a class="rsp-link" href="<?php echo esc_url( $rsp_url( 'support' ) ); ?>"><?php esc_html_e( 'View all', 'relay-stream-portal' ); ?></a></div>
			<?php if ( empty( $vm['tickets'] ) ) : ?>
				<div class="rsp-empty rsp-empty-sm"><?php RSP_Icons::e( 'support', 30 ); ?><p><?php esc_html_e( 'No support tickets.', 'relay-stream-portal' ); ?></p></div>
			<?php else : ?>
				<ul class="rsp-mini-list">
					<?php foreach ( array_slice( $vm['tickets'], 0, 4 ) as $rsp_t ) : ?>
						<li><a class="rsp-mini-name" href="<?php echo esc_url( $rsp_url( 'support', array( 'ticket' => $rsp_t['id'] ) ) ); ?>"><?php echo esc_html( $rsp_t['subject'] ); ?><small>#<?php echo (int) $rsp_t['id']; ?> · <?php echo RSP_Portal::date( $rsp_t['updated_at'] ); // phpcs:ignore ?></small></a>
							<?php echo RSP_Portal::pill( $rsp_t['status'], $rsp_t['status_label'] ); // phpcs:ignore ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</section>
	</div>
</div>
