<?php
/**
 * Transactions module.
 *
 * @var array $vm
 * @var array $args
 * @package RelayStreamPortal
 */

defined( 'ABSPATH' ) || exit;

$rsp_p = ! empty( $vm['preview'] );
// phpcs:disable WordPress.Security.NonceVerification.Recommended
$rsp_status = isset( $_GET['rsp_status'] ) ? sanitize_key( wp_unslash( $_GET['rsp_status'] ) ) : '';
$rsp_hide   = isset( $_GET['rsp_hide'] ) ? '1' === $_GET['rsp_hide'] : (bool) RSP_Settings::get( 'hide_stale_unpaid' );
// phpcs:enable
$rsp_statuses = function_exists( 'wc_get_order_statuses' ) ? wc_get_order_statuses() : array();
unset( $rsp_statuses['wc-checkout-draft'] );
$rsp_o = $vm['orders'];
?>
<div class="rsp-page">
	<div class="rsp-page-head"><div><h1><?php echo esc_html( RSP_Portal::label( $args, 'title', __( 'Transactions', 'relay-stream-portal' ) ) ); ?></h1>
		<p class="rsp-muted"><?php esc_html_e( 'Payment status and activation status are tracked separately, so you can see paid orders that are still activating.', 'relay-stream-portal' ); ?></p></div></div>

	<form method="get" class="rsp-filters">
		<?php if ( isset( $_GET['rsp_view'] ) ) : // phpcs:ignore ?><input type="hidden" name="rsp_view" value="transactions"><?php endif; ?>
		<label><span><?php esc_html_e( 'Status', 'relay-stream-portal' ); ?></span>
			<select name="rsp_status">
				<option value=""><?php esc_html_e( 'All statuses', 'relay-stream-portal' ); ?></option>
				<?php foreach ( $rsp_statuses as $rsp_k => $rsp_l ) : $rsp_k = substr( $rsp_k, 3 ); ?>
					<option value="<?php echo esc_attr( $rsp_k ); ?>"<?php selected( $rsp_status, $rsp_k ); ?>><?php echo esc_html( $rsp_l ); ?></option>
				<?php endforeach; ?>
			</select></label>
		<label class="rsp-check"><input type="hidden" name="rsp_hide" value="0"><input type="checkbox" name="rsp_hide" value="1"<?php checked( $rsp_hide ); ?>><span><?php esc_html_e( 'Hide expired unpaid payment requests', 'relay-stream-portal' ); ?></span></label>
		<button class="rsp-btn rsp-btn-sm" type="submit"><?php esc_html_e( 'Filter', 'relay-stream-portal' ); ?></button>
	</form>

	<?php if ( empty( $rsp_o['rows'] ) ) : ?>
		<div class="rsp-card rsp-empty"><?php RSP_Icons::e( 'transactions', 36 ); ?><p><?php esc_html_e( 'No transactions match.', 'relay-stream-portal' ); ?></p>
			<a class="rsp-btn rsp-btn-primary" href="<?php echo esc_url( $rsp_p ? '#' : RSP_Settings::page_url( 'plans' ) ); ?>"><?php esc_html_e( 'Make your first purchase', 'relay-stream-portal' ); ?></a></div>
	<?php else : ?>
		<div class="rsp-card rsp-table-wrap">
			<table class="rsp-table">
				<thead><tr>
					<th scope="col"><?php esc_html_e( 'Order', 'relay-stream-portal' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Date', 'relay-stream-portal' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Amount', 'relay-stream-portal' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Method', 'relay-stream-portal' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Payment', 'relay-stream-portal' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Activation', 'relay-stream-portal' ); ?></th>
					<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'relay-stream-portal' ); ?></span></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $rsp_o['rows'] as $rsp_r ) : ?>
					<tr>
						<td data-label="<?php esc_attr_e( 'Order', 'relay-stream-portal' ); ?>">#<?php echo esc_html( $rsp_r['number'] ); ?></td>
						<td data-label="<?php esc_attr_e( 'Date', 'relay-stream-portal' ); ?>"><?php echo RSP_Portal::date( $rsp_r['date'] ); // phpcs:ignore ?></td>
						<td data-label="<?php esc_attr_e( 'Amount', 'relay-stream-portal' ); ?>"><?php echo wp_kses_post( $rsp_r['total'] ); ?></td>
						<td data-label="<?php esc_attr_e( 'Method', 'relay-stream-portal' ); ?>"><?php echo esc_html( $rsp_r['method'] ? $rsp_r['method'] : '—' ); ?></td>
						<td data-label="<?php esc_attr_e( 'Payment', 'relay-stream-portal' ); ?>"><?php echo RSP_Portal::pill( $rsp_r['status'], $rsp_r['status_label'] ); // phpcs:ignore ?></td>
						<td data-label="<?php esc_attr_e( 'Activation', 'relay-stream-portal' ); ?>"><?php echo RSP_Portal::pill( $rsp_r['prov'], $rsp_r['prov_label'] ); // phpcs:ignore ?></td>
						<td class="rsp-td-actions">
							<?php if ( $rsp_r['pay_url'] ) : ?><a class="rsp-btn rsp-btn-sm rsp-btn-primary" href="<?php echo esc_url( $rsp_r['pay_url'] ); ?>"><?php esc_html_e( 'Pay', 'relay-stream-portal' ); ?></a><?php endif; ?>
							<a class="rsp-link" href="<?php echo esc_url( $rsp_r['view_url'] ); ?>"><?php esc_html_e( 'Details', 'relay-stream-portal' ); ?></a>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php if ( $rsp_o['pages'] > 1 ) : ?>
			<nav class="rsp-pager" aria-label="<?php esc_attr_e( 'Pagination', 'relay-stream-portal' ); ?>">
				<?php for ( $rsp_i = 1; $rsp_i <= $rsp_o['pages']; $rsp_i++ ) : ?>
					<a class="<?php echo (int) $rsp_o['page'] === $rsp_i ? 'is-current' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'rsp_page', $rsp_i ) ); ?>"<?php echo (int) $rsp_o['page'] === $rsp_i ? ' aria-current="page"' : ''; ?>><?php echo (int) $rsp_i; ?></a>
				<?php endfor; ?>
			</nav>
		<?php endif; ?>
	<?php endif; ?>
</div>
