<?php
/**
 * Plans & Billing module.
 *
 * @var array $vm
 * @var array $args
 * @package RelayStreamPortal
 */

defined( 'ABSPATH' ) || exit;

$rsp_p     = ! empty( $vm['preview'] );
$rsp_inert = RSP_Portal::inert( $vm );
// phpcs:disable WordPress.Security.NonceVerification.Recommended
$rsp_renew   = isset( $_GET['renew'] ) ? sanitize_text_field( wp_unslash( $_GET['renew'] ) ) : '';
$rsp_purpose = isset( $_GET['purpose'] ) ? sanitize_key( wp_unslash( $_GET['purpose'] ) ) : '';
// phpcs:enable
$rsp_renewable = array_values( array_filter( $vm['accounts'], static function ( $a ) { return ! empty( $a['renewable'] ); } ) );
$rsp_default   = 'new';
foreach ( $rsp_renewable as $rsp_a ) {
	if ( $rsp_a['uuid'] === $rsp_renew ) {
		$rsp_default = $rsp_a['uuid'];
	}
}
if ( 'new' !== $rsp_purpose && 'new' === $rsp_default && 1 === count( $rsp_renewable ) && count( $vm['accounts'] ) === 1 ) {
	$rsp_default = $rsp_renewable[0]['uuid'];
}
$rsp_guides = (array) RSP_Settings::get( 'payment_guides', array() );
?>
<div class="rsp-page">
	<div class="rsp-page-head"><div>
		<h1><?php echo esc_html( RSP_Portal::label( $args, 'title', __( 'Plans & Billing', 'relay-stream-portal' ) ) ); ?></h1>
		<p class="rsp-muted"><?php esc_html_e( 'Pay once, watch for the whole term. Nothing renews automatically.', 'relay-stream-portal' ); ?></p>
	</div></div>

	<form method="post" class="rsp-checkout-form">
		<?php echo RSP_Forms::fields( 'checkout' ); // phpcs:ignore ?>
		<div class="rsp-card rsp-target">
			<label for="rsp-target"><strong><?php esc_html_e( 'This purchase is for', 'relay-stream-portal' ); ?></strong></label>
			<div class="rsp-target-row">
				<select id="rsp-target" name="target" data-rsp-target>
					<option value="new"<?php selected( $rsp_default, 'new' ); ?>><?php esc_html_e( 'A new streaming account', 'relay-stream-portal' ); ?></option>
					<?php foreach ( $rsp_renewable as $rsp_a ) : ?>
						<option value="<?php echo esc_attr( $rsp_a['uuid'] ); ?>"<?php selected( $rsp_default, $rsp_a['uuid'] ); ?>><?php
							/* translators: 1: account 2: date */
							echo esc_html( sprintf( __( 'Renew “%1$s” (expires %2$s)', 'relay-stream-portal' ), $rsp_a['nickname'], wp_strip_all_tags( RSP_Portal::date( $rsp_a['expires_at'] ) ) ) ); ?></option>
					<?php endforeach; ?>
				</select>
				<input type="text" name="nickname" maxlength="80" placeholder="<?php esc_attr_e( 'Nickname for the new account (optional)', 'relay-stream-portal' ); ?>" data-rsp-new-only aria-label="<?php esc_attr_e( 'Nickname', 'relay-stream-portal' ); ?>">
			</div>
			<p class="rsp-muted rsp-small"><?php esc_html_e( 'Renewals extend the exact account you choose. The new term is added after the panel confirms it.', 'relay-stream-portal' ); ?></p>
		</div>
		<?php echo RSP_Portal::template( 'partials/plan-cards', array( 'vm' => $vm, 'args' => array_merge( $args, array( 'in_form' => true ) ) ) ); // phpcs:ignore ?>
	</form>

	<div class="rsp-grid rsp-grid-2">
		<form method="post" class="rsp-card rsp-inline-card">
			<?php echo RSP_Forms::fields( 'promo' ); // phpcs:ignore ?>
			<label for="rsp-promo"><strong><?php esc_html_e( 'Have a promo code?', 'relay-stream-portal' ); ?></strong></label>
			<div class="rsp-input-row"><input id="rsp-promo" type="text" name="promo" maxlength="40" autocomplete="off"><button class="rsp-btn" type="submit"<?php echo $rsp_inert; // phpcs:ignore ?>><?php esc_html_e( 'Apply', 'relay-stream-portal' ); ?></button></div>
		</form>
		<?php if ( RSP_Settings::get( 'referral_enabled' ) && empty( $vm['referral']['has_referrer'] ) && 0 === (int) $vm['order_count'] ) : ?>
			<form method="post" class="rsp-card rsp-inline-card">
				<?php echo RSP_Forms::fields( 'referral_code' ); // phpcs:ignore ?>
				<label for="rsp-refcode"><strong><?php esc_html_e( 'Were you referred by someone?', 'relay-stream-portal' ); ?></strong></label>
				<div class="rsp-input-row"><input id="rsp-refcode" type="text" name="referral_code" maxlength="16" autocomplete="off"><button class="rsp-btn" type="submit"<?php echo $rsp_inert; // phpcs:ignore ?>><?php esc_html_e( 'Add code', 'relay-stream-portal' ); ?></button></div>
			</form>
		<?php endif; ?>
	</div>

	<details class="rsp-card rsp-disclosure">
		<summary><?php RSP_Icons::e( 'star' ); ?><span><strong><?php esc_html_e( 'What’s included with every plan', 'relay-stream-portal' ); ?></strong></span></summary>
		<ul class="rsp-checklist">
			<?php foreach ( array_filter( array_map( 'trim', preg_split( '/\r?\n/', (string) RSP_Settings::get( 'included_features' ) ) ) ) as $rsp_f ) : ?>
				<li><?php RSP_Icons::e( 'check', 16 ); ?><?php echo esc_html( $rsp_f ); ?></li>
			<?php endforeach; ?>
		</ul>
	</details>

	<section class="rsp-card">
		<div class="rsp-card-head"><h2><?php RSP_Icons::e( 'shield' ); ?> <?php esc_html_e( 'Payment', 'relay-stream-portal' ); ?></h2></div>
		<?php if ( $vm['gateways'] ) : ?>
			<p><?php esc_html_e( 'Available at checkout:', 'relay-stream-portal' ); ?></p>
			<ul class="rsp-tags"><?php foreach ( $vm['gateways'] as $rsp_g ) : ?><li><?php echo esc_html( $rsp_g ); ?></li><?php endforeach; ?></ul>
		<?php else : ?>
			<p class="rsp-muted"><?php esc_html_e( 'No payment methods are enabled yet.', 'relay-stream-portal' ); ?></p>
		<?php endif; ?>
		<p class="rsp-muted rsp-small"><?php esc_html_e( 'Your account activates only after the payment is confirmed by the payment provider — not when you return from the payment page. Only pay through this portal’s checkout.', 'relay-stream-portal' ); ?></p>
		<?php if ( RSP_Settings::get( 'refund_policy_text' ) ) : ?><p class="rsp-muted rsp-small"><?php echo esc_html( RSP_Settings::get( 'refund_policy_text' ) ); ?></p><?php endif; ?>
	</section>

	<?php if ( $rsp_guides ) : ?>
		<section>
			<h2 class="rsp-section-title"><?php esc_html_e( 'Payment guides', 'relay-stream-portal' ); ?></h2>
			<div class="rsp-guide-links">
				<?php foreach ( $rsp_guides as $rsp_g ) : ?>
					<div class="rsp-card rsp-guide-link">
						<strong><?php echo esc_html( $rsp_g['title'] ); ?></strong>
						<?php if ( ! empty( $rsp_g['text'] ) ) : ?><p class="rsp-muted rsp-small"><?php echo esc_html( $rsp_g['text'] ); ?></p><?php endif; ?>
						<?php if ( ! empty( $rsp_g['url'] ) ) : ?><a class="rsp-link" href="<?php echo esc_url( $rsp_g['url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open guide', 'relay-stream-portal' ); ?> <?php RSP_Icons::e( 'external', 14 ); ?></a><?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endif; ?>
</div>
