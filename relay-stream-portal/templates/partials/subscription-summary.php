<?php
/**
 * Subscription & expiry summary.
 *
 * @var array $vm
 * @var array $args
 * @package RelayStreamPortal
 */

defined( 'ABSPATH' ) || exit;

$rsp_accounts = isset( $vm['accounts'] ) ? $vm['accounts'] : array();
$rsp_active   = array_filter( $rsp_accounts, static function ( $a ) { return 'active' === $a['status']; } );
$rsp_next     = null;
foreach ( $rsp_active as $rsp_a ) {
	if ( null === $rsp_next || $rsp_a['expires_at'] < $rsp_next['expires_at'] ) {
		$rsp_next = $rsp_a;
	}
}
?>
<section class="rsp-card rsp-summary">
	<div class="rsp-card-head"><h2><?php echo esc_html( RSP_Portal::label( $args, 'title', __( 'Your subscription', 'relay-stream-portal' ) ) ); ?></h2>
		<a class="rsp-link" href="<?php echo esc_url( ! empty( $vm['preview'] ) ? '#' : RSP_Settings::page_url( 'accounts' ) ); ?>"><?php esc_html_e( 'All accounts', 'relay-stream-portal' ); ?></a></div>
	<?php if ( ! $rsp_accounts ) : ?>
		<div class="rsp-empty">
			<?php RSP_Icons::e( 'tv', 36 ); ?>
			<p><?php esc_html_e( 'You have no streaming accounts yet.', 'relay-stream-portal' ); ?></p>
			<a class="rsp-btn rsp-btn-primary" href="<?php echo esc_url( ! empty( $vm['preview'] ) ? '#' : RSP_Settings::page_url( 'plans' ) ); ?>"><?php esc_html_e( 'Choose a plan', 'relay-stream-portal' ); ?></a>
		</div>
	<?php else : ?>
		<?php if ( $rsp_next ) : ?>
			<?php $rsp_pct = max( 4, min( 100, (int) round( 100 * min( 365, (int) $rsp_next['days_left'] ) / 365 ) ) ); ?>
			<div class="rsp-meter-block">
				<div class="rsp-meter-label"><span><?php echo esc_html( $rsp_next['nickname'] ); ?></span><strong><?php
					/* translators: %d days */
					echo esc_html( sprintf( _n( '%d day left', '%d days left', (int) $rsp_next['days_left'], 'relay-stream-portal' ), (int) $rsp_next['days_left'] ) ); ?></strong></div>
				<div class="rsp-meter" role="progressbar" aria-valuemin="0" aria-valuemax="365" aria-valuenow="<?php echo (int) $rsp_next['days_left']; ?>"><span style="width:<?php echo (int) $rsp_pct; ?>%"></span></div>
				<p class="rsp-muted"><?php
					/* translators: %s date */
					printf( esc_html__( 'Next expiry: %s · plans never renew automatically', 'relay-stream-portal' ), RSP_Portal::date( $rsp_next['expires_at'] ) ); // phpcs:ignore ?></p>
			</div>
		<?php endif; ?>
		<ul class="rsp-mini-list">
			<?php foreach ( array_slice( $rsp_accounts, 0, 4 ) as $rsp_a ) : ?>
				<li><span class="rsp-mini-name"><?php echo esc_html( $rsp_a['nickname'] ); ?><small><?php echo esc_html( $rsp_a['plan'] ); ?></small></span>
					<?php echo RSP_Portal::pill( $rsp_a['status'], $rsp_a['status_label'] ); // phpcs:ignore ?>
					<span class="rsp-muted"><?php echo RSP_Portal::date( $rsp_a['expires_at'] ); // phpcs:ignore ?></span></li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</section>
