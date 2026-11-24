<?php
/**
 * Checkout module: WooCommerce's checkout form (payment gateways, coupons, validation, order creation)
 * rendered inside the portal and restyled. Activation still happens only after a confirmed payment.
 *
 * @var array $vm
 * @var array $args
 * @package RelayStreamPortal
 */

defined( 'ABSPATH' ) || exit;

$rsp_p        = ! empty( $vm['preview'] );
$rsp_received = ! $rsp_p && function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-received' );
$rsp_pay      = ! $rsp_p && function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-pay' );
$rsp_step     = $rsp_received ? 3 : 2;
$rsp_items    = array();
if ( $rsp_p ) {
	$rsp_items[] = array( 'name' => 'Relay Half-Year', 'months' => 6, 'purpose' => __( 'New account', 'relay-stream-portal' ), 'detail' => __( 'First-time streaming setup', 'relay-stream-portal' ), 'price' => '<span class="rsp-price">$66.00</span>', 'features' => array( 'Full authorized catalog', 'Every supported device' ) );
} elseif ( ! $rsp_received && function_exists( 'WC' ) && WC()->cart ) {
	foreach ( WC()->cart->get_cart() as $rsp_item ) {
		$rsp_prod = $rsp_item['data'];
		if ( ! $rsp_prod ) {
			continue;
		}
		$rsp_plan = RSP_Woocommerce::is_plan( $rsp_prod ) ? RSP_Woocommerce::plan_data( $rsp_prod ) : null;
		$rsp_purp = isset( $rsp_item['rsp_purpose'] ) ? $rsp_item['rsp_purpose'] : '';
		$rsp_det  = '';
		if ( 'renew' === $rsp_purp ) {
			$rsp_acc = RSP_Accounts::get_owned( (string) $rsp_item['rsp_account'], get_current_user_id() );
			$rsp_lbl = __( 'Renewal', 'relay-stream-portal' );
			/* translators: %s account */
			$rsp_det = $rsp_acc ? sprintf( __( 'Extends “%s”', 'relay-stream-portal' ), $rsp_acc->nickname ) : '';
		} elseif ( 'gift' === $rsp_purp ) {
			$rsp_lbl = __( 'Gift', 'relay-stream-portal' );
			$rsp_det = ! empty( $rsp_item['rsp_gift_email'] ) ? $rsp_item['rsp_gift_email'] : __( 'Code shown in your portal after payment', 'relay-stream-portal' );
		} elseif ( 'new' === $rsp_purp ) {
			$rsp_lbl = __( 'New account', 'relay-stream-portal' );
			$rsp_det = ! empty( $rsp_item['rsp_nickname'] ) ? $rsp_item['rsp_nickname'] : __( 'First-time streaming setup', 'relay-stream-portal' );
		} else {
			$rsp_lbl = '';
		}
		$rsp_items[] = array(
			'name'     => $rsp_prod->get_name(),
			'months'   => $rsp_plan ? (int) $rsp_plan['months'] : 0,
			'purpose'  => $rsp_lbl,
			'detail'   => $rsp_det,
			'price'    => WC()->cart->get_product_subtotal( $rsp_prod, $rsp_item['quantity'] ),
			'features' => $rsp_plan ? array_slice( $rsp_plan['features'], 0, 4 ) : array(),
		);
	}
}
$rsp_empty = ! $rsp_p && ! $rsp_received && ! $rsp_pay && ( ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->is_empty() );
$rsp_steps = array( 1 => __( 'Choose plan', 'relay-stream-portal' ), 2 => __( 'Details & payment', 'relay-stream-portal' ), 3 => __( 'Activation', 'relay-stream-portal' ) );
?>
<div class="rsp-page rsp-checkout">
	<div class="rsp-page-head"><div>
		<h1><?php echo esc_html( $rsp_received ? __( 'Thank you!', 'relay-stream-portal' ) : RSP_Portal::label( $args, 'title', __( 'Checkout', 'relay-stream-portal' ) ) ); ?></h1>
		<p class="rsp-muted"><?php echo esc_html( $rsp_received ? __( 'Your order was received. Activation starts as soon as the payment is confirmed.', 'relay-stream-portal' ) : __( 'Complete your purchase securely', 'relay-stream-portal' ) ); ?></p>
	</div></div>

	<ol class="rsp-steps-bar" aria-label="<?php esc_attr_e( 'Checkout progress', 'relay-stream-portal' ); ?>">
		<?php foreach ( $rsp_steps as $rsp_n => $rsp_l ) : ?>
			<li class="<?php echo $rsp_n < $rsp_step ? 'is-done' : ( $rsp_n === $rsp_step ? 'is-current' : '' ); ?>"<?php echo $rsp_n === $rsp_step ? ' aria-current="step"' : ''; ?>><span class="rsp-step-dot"><?php echo $rsp_n < $rsp_step ? RSP_Icons::get( 'check', 14 ) : (int) $rsp_n; // phpcs:ignore ?></span><?php echo esc_html( $rsp_l ); ?></li>
		<?php endforeach; ?>
	</ol>

	<?php if ( $rsp_empty ) : ?>
		<div class="rsp-card rsp-empty rsp-empty-lg">
			<span class="rsp-empty-icon"><?php RSP_Icons::e( 'card', 26 ); ?></span>
			<h3><?php esc_html_e( 'Your cart is empty', 'relay-stream-portal' ); ?></h3>
			<p class="rsp-muted"><?php esc_html_e( 'Pick a plan to continue.', 'relay-stream-portal' ); ?></p>
			<a class="rsp-btn rsp-btn-primary" href="<?php echo esc_url( RSP_Settings::page_url( 'plans' ) ); ?>"><?php esc_html_e( 'Choose a plan', 'relay-stream-portal' ); ?></a>
		</div>
	<?php elseif ( $rsp_received ) : ?>
		<div class="rsp-checkout-grid">
			<section class="rsp-card rsp-wc"><?php echo do_shortcode( '[woocommerce_checkout]' ); // phpcs:ignore -- WooCommerce output. ?></section>
			<aside class="rsp-checkout-aside">
				<div class="rsp-card rsp-next">
					<span class="rsp-stat-icon rsp-c-green"><?php RSP_Icons::e( 'check' ); ?></span>
					<h2><?php esc_html_e( 'What happens next', 'relay-stream-portal' ); ?></h2>
					<ol class="rsp-next-list">
						<li><?php esc_html_e( 'The payment provider confirms your payment.', 'relay-stream-portal' ); ?></li>
						<li><?php esc_html_e( 'We activate or extend your account automatically.', 'relay-stream-portal' ); ?></li>
						<li><?php esc_html_e( 'Your player details appear under My Accounts.', 'relay-stream-portal' ); ?></li>
					</ol>
					<?php if ( is_user_logged_in() ) : ?>
						<a class="rsp-btn rsp-btn-primary rsp-btn-block" href="<?php echo esc_url( RSP_Settings::page_url( 'accounts' ) ); ?>"><?php esc_html_e( 'Go to My Accounts', 'relay-stream-portal' ); ?></a>
						<a class="rsp-btn rsp-btn-block" href="<?php echo esc_url( RSP_Settings::page_url( 'transactions' ) ); ?>"><?php esc_html_e( 'View transactions', 'relay-stream-portal' ); ?></a>
					<?php endif; ?>
				</div>
			</aside>
		</div>
	<?php else : ?>
		<div class="rsp-checkout-grid">
			<section class="rsp-card rsp-wc">
				<?php if ( $rsp_p ) : ?>
					<div class="rsp-wc-preview">
						<p class="rsp-muted"><?php esc_html_e( 'The WooCommerce checkout form (billing details, payment methods and the Place order button) appears here, styled like the portal.', 'relay-stream-portal' ); ?></p>
						<div class="rsp-grid rsp-grid-2"><label class="rsp-field"><span><?php esc_html_e( 'First name', 'relay-stream-portal' ); ?></span><input type="text" disabled></label><label class="rsp-field"><span><?php esc_html_e( 'Last name', 'relay-stream-portal' ); ?></span><input type="text" disabled></label></div>
						<label class="rsp-field"><span><?php esc_html_e( 'Email', 'relay-stream-portal' ); ?></span><input type="email" disabled></label>
						<button class="rsp-btn rsp-btn-primary rsp-btn-block" type="button" disabled><?php esc_html_e( 'Place order', 'relay-stream-portal' ); ?></button>
					</div>
				<?php else : ?>
					<?php echo do_shortcode( '[woocommerce_checkout]' ); // phpcs:ignore -- WooCommerce output. ?>
				<?php endif; ?>
			</section>
			<aside class="rsp-checkout-aside">
				<?php if ( $rsp_items ) : ?>
					<div class="rsp-card rsp-summary-card">
						<h2><?php esc_html_e( 'Order summary', 'relay-stream-portal' ); ?></h2>
						<?php foreach ( $rsp_items as $rsp_it ) : ?>
							<div class="rsp-sum-item">
								<div class="rsp-sum-top">
									<div>
										<?php if ( $rsp_it['purpose'] ) : ?><span class="rsp-sum-purpose"><?php echo esc_html( $rsp_it['purpose'] ); ?></span><?php endif; ?>
										<strong><?php echo esc_html( $rsp_it['name'] ); ?></strong>
										<?php if ( $rsp_it['months'] ) : ?><small class="rsp-muted"><?php
											/* translators: %d months */
											echo esc_html( sprintf( _n( '%d Month', '%d Months', $rsp_it['months'], 'relay-stream-portal' ), $rsp_it['months'] ) ); ?></small><?php endif; ?>
									</div>
									<span class="rsp-sum-price"><?php echo wp_kses_post( $rsp_it['price'] ); ?></span>
								</div>
								<?php if ( $rsp_it['detail'] ) : ?><p class="rsp-sum-detail"><?php echo esc_html( $rsp_it['detail'] ); ?></p><?php endif; ?>
								<?php if ( $rsp_it['features'] ) : ?>
									<ul class="rsp-plan-features"><?php foreach ( $rsp_it['features'] as $rsp_f ) : ?><li><?php RSP_Icons::e( 'check', 15 ); ?><?php echo esc_html( $rsp_f ); ?></li><?php endforeach; ?></ul>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>
						<p class="rsp-plan-foot"><?php esc_html_e( 'One-time payment · No auto-renewal', 'relay-stream-portal' ); ?></p>
						<a class="rsp-link" href="<?php echo esc_url( $rsp_p ? '#' : RSP_Settings::page_url( 'plans' ) ); ?>">← <?php esc_html_e( 'Change plan', 'relay-stream-portal' ); ?></a>
					</div>
				<?php endif; ?>
				<div class="rsp-card rsp-secure">
					<h2><?php RSP_Icons::e( 'shield', 20 ); ?> <?php esc_html_e( 'Secure Payment', 'relay-stream-portal' ); ?></h2>
					<p class="rsp-small"><?php echo esc_html( RSP_Settings::get( 'secure_payment_text' ) ); ?></p>
				</div>
			</aside>
		</div>
	<?php endif; ?>
</div>
