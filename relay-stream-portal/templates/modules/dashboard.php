<?php
/**
 * Dashboard module.
 *
 * @var array $vm
 * @var array $args
 * @package RelayStreamPortal
 */

defined( 'ABSPATH' ) || exit;

$rsp_p   = ! empty( $vm['preview'] );
$rsp_url = static function ( $role, $q = array() ) use ( $rsp_p ) {
	return $rsp_p ? '#' : RSP_Settings::page_url( $role, $q );
};
$rsp_acc    = $vm['accounts'];
$rsp_active = array_values( array_filter( $rsp_acc, static function ( $a ) { return 'active' === $a['status']; } ) );
usort( $rsp_active, static function ( $a, $b ) { return strcmp( (string) $a['expires_at'], (string) $b['expires_at'] ); } );
$rsp_main   = $rsp_active ? $rsp_active[0] : ( $rsp_acc ? $rsp_acc[0] : null );
$rsp_status = $rsp_main ? $rsp_main['status'] : 'expired';
$rsp_label  = $rsp_main ? $rsp_main['status_label'] : ( empty( $rsp_acc ) ? __( 'Inactive', 'relay-stream-portal' ) : RSP_Accounts::status_label( 'expired' ) );
$rsp_hour   = (int) current_time( 'G' );
$rsp_greet  = $rsp_hour < 12 ? __( 'Good morning', 'relay-stream-portal' ) : ( $rsp_hour < 18 ? __( 'Good afternoon', 'relay-stream-portal' ) : __( 'Good evening', 'relay-stream-portal' ) );
$rsp_hello  = strtr( RSP_Portal::label( $args, 'welcome', RSP_Settings::get( 'text_welcome' ) ), array( '{greeting}' => $rsp_greet, '{name}' => $vm['user']['first'] ) );
$rsp_tone   = 'active' === $rsp_status ? 'ok' : ( in_array( $rsp_status, array( 'pending_activation', 'review', 'pending_payment' ), true ) ? 'warn' : 'bad' );
$rsp_player = ! empty( $vm['player']['enabled'] ) && $rsp_active;
$rsp_c      = $rsp_main && ! empty( $rsp_main['has_credentials'] ) && in_array( $rsp_main['status'], array( 'active', 'expired' ), true ) && RSP_Settings::get( 'show_credentials' );
?>
<div class="rsp-page">
	<div class="rsp-page-head"><div>
		<h1><?php echo esc_html( $rsp_hello ); ?></h1>
		<p class="rsp-muted"><?php echo esc_html( RSP_Settings::get( 'text_welcome_sub' ) ); ?></p>
	</div></div>

	<?php echo RSP_Portal::template( 'partials/announcements', array( 'vm' => $vm ) ); // phpcs:ignore ?>

	<div class="rsp-stats">
		<div class="rsp-stat"><span class="rsp-stat-icon rsp-c-green"><?php RSP_Icons::e( 'shield' ); ?></span><div><small><?php esc_html_e( 'Subscription', 'relay-stream-portal' ); ?></small><strong class="rsp-tone-<?php echo esc_attr( $rsp_tone ); ?>"><?php echo esc_html( $rsp_label ); ?></strong></div></div>
		<div class="rsp-stat"><span class="rsp-stat-icon rsp-c-blue"><?php RSP_Icons::e( 'shield' ); ?></span><div><small><?php esc_html_e( 'Expires', 'relay-stream-portal' ); ?></small><strong><?php echo $rsp_main && $rsp_main['expires_at'] ? RSP_Portal::date( $rsp_main['expires_at'] ) : esc_html__( 'N/A', 'relay-stream-portal' ); // phpcs:ignore ?></strong></div></div>
		<div class="rsp-stat"><span class="rsp-stat-icon rsp-c-blue"><?php RSP_Icons::e( 'referrals' ); ?></span><div><small><?php esc_html_e( 'Referrals', 'relay-stream-portal' ); ?></small><strong><?php echo (int) $vm['referral']['stats']['invited']; ?></strong></div></div>
		<div class="rsp-stat"><span class="rsp-stat-icon rsp-c-gold"><?php RSP_Icons::e( 'transactions2' ); ?></span><div><small><?php esc_html_e( 'Transactions', 'relay-stream-portal' ); ?></small><strong><?php echo (int) $vm['order_count']; ?></strong></div></div>
	</div>

	<div class="rsp-dash-grid">
		<section class="rsp-card rsp-account-details">
			<div class="rsp-card-head"><h2><?php esc_html_e( 'Account Details', 'relay-stream-portal' ); ?></h2>
				<?php echo RSP_Portal::pill( $rsp_main ? $rsp_status : 'expired', $rsp_label ); // phpcs:ignore ?></div>
			<?php if ( $rsp_main && in_array( $rsp_main['status'], array( 'active', 'pending_activation', 'review' ), true ) ) : ?>
				<dl class="rsp-detail-list">
					<div><dt><?php esc_html_e( 'Account', 'relay-stream-portal' ); ?></dt><dd><?php echo esc_html( $rsp_main['nickname'] ); ?></dd></div>
					<div><dt><?php esc_html_e( 'Plan', 'relay-stream-portal' ); ?></dt><dd><?php echo esc_html( $rsp_main['plan'] ? $rsp_main['plan'] : '—' ); ?></dd></div>
					<div><dt><?php esc_html_e( 'Activated', 'relay-stream-portal' ); ?></dt><dd><?php echo RSP_Portal::date( $rsp_main['activated_at'] ); // phpcs:ignore ?></dd></div>
					<div><dt><?php esc_html_e( 'Expires', 'relay-stream-portal' ); ?></dt><dd><?php echo RSP_Portal::date( $rsp_main['expires_at'] ); // phpcs:ignore ?></dd></div>
					<div><dt><?php esc_html_e( 'Connections', 'relay-stream-portal' ); ?></dt><dd><?php echo null === $rsp_main['max_connections'] ? '—' : (int) $rsp_main['max_connections']; ?></dd></div>
				</dl>
				<?php if ( $rsp_c ) : ?>
					<div class="rsp-cred-grid" data-rsp-creds="<?php echo esc_attr( $rsp_main['uuid'] ); ?>">
						<?php foreach ( array( 'server' => __( 'Server URL', 'relay-stream-portal' ), 'username' => __( 'Username', 'relay-stream-portal' ), 'password' => __( 'Password', 'relay-stream-portal' ), 'm3u' => __( 'M3U URL', 'relay-stream-portal' ), 'mac' => __( 'MAC', 'relay-stream-portal' ) ) as $rsp_k => $rsp_l ) : ?>
							<div class="rsp-cred" data-field="<?php echo esc_attr( $rsp_k ); ?>"<?php echo 'mac' === $rsp_k ? ' hidden' : ''; ?>><span class="rsp-cred-label"><?php echo esc_html( $rsp_l ); ?></span><code class="rsp-cred-value">••••••••</code><button type="button" class="rsp-icon-btn rsp-sm" data-rsp-copy-field disabled aria-label="<?php echo esc_attr( sprintf( /* translators: %s field */ __( 'Copy %s', 'relay-stream-portal' ), $rsp_l ) ); ?>"><?php RSP_Icons::e( 'copy', 16 ); ?></button></div>
						<?php endforeach; ?>
					</div>
					<div class="rsp-actions"><button type="button" class="rsp-btn rsp-btn-sm" data-rsp-reveal="<?php echo esc_attr( $rsp_main['uuid'] ); ?>"<?php echo RSP_Portal::inert( $vm ); // phpcs:ignore ?>><?php RSP_Icons::e( 'eye', 16 ); ?><span><?php esc_html_e( 'Show details', 'relay-stream-portal' ); ?></span></button>
						<a class="rsp-btn rsp-btn-sm" href="<?php echo esc_url( $rsp_url( 'accounts' ) ); ?>"><?php esc_html_e( 'Manage accounts', 'relay-stream-portal' ); ?></a></div>
				<?php endif; ?>
			<?php else : ?>
				<div class="rsp-empty">
					<p><?php esc_html_e( 'No active subscription', 'relay-stream-portal' ); ?></p>
					<a class="rsp-btn rsp-btn-primary" href="<?php echo esc_url( $rsp_main && $rsp_main['renewable'] ? $rsp_url( 'plans', array( 'renew' => $rsp_main['uuid'] ) ) : $rsp_url( 'plans' ) ); ?>"><?php echo esc_html( $rsp_main && $rsp_main['renewable'] ? __( 'Renew Now', 'relay-stream-portal' ) : __( 'Subscribe Now', 'relay-stream-portal' ) ); ?></a>
				</div>
			<?php endif; ?>
		</section>

		<div class="rsp-quick">
			<?php if ( $rsp_player ) : ?>
				<a class="rsp-quick-card" href="<?php echo esc_url( $vm['player']['url'] ); ?>" target="_blank" rel="noopener"><span class="rsp-stat-icon rsp-c-blue"><?php RSP_Icons::e( 'play' ); ?></span><span><strong><?php echo esc_html( $vm['player']['label'] ); ?></strong><small><?php esc_html_e( 'Open the web player', 'relay-stream-portal' ); ?></small></span></a>
			<?php else : ?>
				<div class="rsp-quick-card is-disabled" aria-disabled="true"><span class="rsp-stat-icon rsp-c-blue"><?php RSP_Icons::e( 'play' ); ?></span><span><strong><?php echo esc_html( $vm['player']['label'] ? $vm['player']['label'] : __( 'Start Watching', 'relay-stream-portal' ) ); ?></strong><small><?php esc_html_e( 'Web player coming soon', 'relay-stream-portal' ); ?></small></span><span class="rsp-soon"><?php esc_html_e( 'Soon', 'relay-stream-portal' ); ?></span></div>
			<?php endif; ?>
			<a class="rsp-quick-card" href="<?php echo esc_url( $rsp_url( 'apps' ) ); ?>"><span class="rsp-stat-icon rsp-c-blue"><?php RSP_Icons::e( 'grid' ); ?></span><span><strong><?php esc_html_e( 'Download Apps', 'relay-stream-portal' ); ?></strong><small><?php esc_html_e( 'Setup guides', 'relay-stream-portal' ); ?></small></span></a>
			<a class="rsp-quick-card" href="<?php echo esc_url( $rsp_url( 'support', array( 'new' => 1 ) ) ); ?>"><span class="rsp-stat-icon rsp-c-gold"><?php RSP_Icons::e( 'plus' ); ?></span><span><strong><?php esc_html_e( 'Open Ticket', 'relay-stream-portal' ); ?></strong><small><?php esc_html_e( 'Get support', 'relay-stream-portal' ); ?></small></span></a>
			<?php if ( RSP_Settings::get( 'referral_enabled' ) ) : ?>
				<a class="rsp-quick-card" href="<?php echo esc_url( $rsp_url( 'referrals' ) ); ?>"><span class="rsp-stat-icon rsp-c-green"><?php RSP_Icons::e( 'referrals' ); ?></span><span><strong><?php esc_html_e( 'Refer a Friend', 'relay-stream-portal' ); ?></strong><small><?php
					/* translators: %d days */
					echo esc_html( sprintf( __( 'Earn %d free days', 'relay-stream-portal' ), (int) RSP_Settings::get( 'referral_days', 30 ) ) ); ?></small></span></a>
			<?php endif; ?>
		</div>
	</div>

	<div class="rsp-grid rsp-grid-2">
		<?php if ( $vm['billing'] ) : ?>
			<section class="rsp-card rsp-recent">
				<div class="rsp-card-head"><h2><?php esc_html_e( 'Recent Transactions', 'relay-stream-portal' ); ?></h2><a class="rsp-link" href="<?php echo esc_url( $rsp_url( 'transactions' ) ); ?>"><?php esc_html_e( 'View All', 'relay-stream-portal' ); ?></a></div>
				<?php if ( empty( $vm['orders']['rows'] ) ) : ?>
					<div class="rsp-empty rsp-empty-sm"><?php RSP_Icons::e( 'transactions2', 30 ); ?><p><?php esc_html_e( 'No transactions yet', 'relay-stream-portal' ); ?></p><a class="rsp-link" href="<?php echo esc_url( $rsp_url( 'plans' ) ); ?>"><?php esc_html_e( 'Make your first purchase', 'relay-stream-portal' ); ?></a></div>
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
		<section class="rsp-card rsp-recent">
			<div class="rsp-card-head"><h2><?php esc_html_e( 'Recent Tickets', 'relay-stream-portal' ); ?></h2><a class="rsp-link" href="<?php echo esc_url( $rsp_url( 'support' ) ); ?>"><?php esc_html_e( 'View All', 'relay-stream-portal' ); ?></a></div>
			<?php if ( empty( $vm['tickets'] ) ) : ?>
				<div class="rsp-empty rsp-empty-sm"><?php RSP_Icons::e( 'lifebuoy', 30 ); ?><p><?php esc_html_e( 'No support tickets', 'relay-stream-portal' ); ?></p><a class="rsp-link" href="<?php echo esc_url( $rsp_url( 'support', array( 'new' => 1 ) ) ); ?>"><?php esc_html_e( 'Create a ticket', 'relay-stream-portal' ); ?></a></div>
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
