<?php
/**
 * Announcements.
 *
 * @var array $vm
 * @package RelayStreamPortal
 */

defined( 'ABSPATH' ) || exit;
if ( empty( $vm['announcements'] ) ) {
	return;
}
foreach ( $vm['announcements'] as $rsp_a ) : ?>
	<div class="rsp-announce rsp-tone-<?php echo esc_attr( in_array( $rsp_a['tone'], array( 'info', 'success', 'warning' ), true ) ? $rsp_a['tone'] : 'info' ); ?>" data-rsp-announcement="<?php echo esc_attr( $rsp_a['id'] ); ?>">
		<?php RSP_Icons::e( 'warning' === $rsp_a['tone'] ? 'alert' : 'info' ); ?>
		<div><strong><?php echo esc_html( $rsp_a['title'] ); ?></strong><p><?php echo esc_html( $rsp_a['body'] ); ?></p></div>
		<?php if ( ! empty( $rsp_a['dismissible'] ) && empty( $vm['preview'] ) ) : ?>
			<button type="button" class="rsp-icon-btn rsp-sm" data-rsp-dismiss="<?php echo esc_attr( $rsp_a['id'] ); ?>" aria-label="<?php esc_attr_e( 'Dismiss', 'relay-stream-portal' ); ?>"><?php RSP_Icons::e( 'close', 16 ); ?></button>
		<?php endif; ?>
	</div>
<?php endforeach;
