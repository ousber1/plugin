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
if ( 'new' !== $rsp_purpose && 'new' === $rsp_default && 1 === count( $rsp_renewable ) && 1 === count( $vm['accounts'] ) ) {
	$rsp_default = $rsp_renewable[0]['uuid'];
}
$rsp_options = array( 'new' => array( __( 'New account', 'relay-stream-portal' ), __( 'First-time streaming setup', 'relay-stream-portal' ), 'plus' ) );
foreach ( $rsp_renewable as $rsp_a ) {
	/* translators: 1: account 2: plan 3: date */
	$rsp_options[ $rsp_a['uuid'] ] = array( sprintf( __( 'Renew “%s”', 'relay-stream-portal' ), $rsp_a['nickname'] ), sprintf( __( '%1$s · expires %2$s', 'relay-stream-portal' ), $rsp_a['plan'], wp_strip_all_tags( RSP_Portal::date( $rsp_a['expires_at'] ) ) ), 'refresh' );
}
$rsp_sel    = $rsp_options[ $rsp_default ];
$rsp_guides = array_values( array_filter( (array) RSP_Settings::get( 'payment_guides', array() ), static function ( $g ) { return ! empty( $g['title'] ); } ) );
$rsp_feat   = array_filter( array_map( 'trim', preg_split( '/\r?\n/', (string) RSP_Settings::get( 'included_features' ) ) ) );
?>
<div class="rsp-page">
	<div class="rsp-page-head"><div>
		<h1><?php echo esc_html( RSP_Portal::label( $args, 'title', __( 'Plans & Billing', 'relay-stream-portal' ) ) ); ?></h1>
		<p class="rsp-muted"><?php esc_html_e( 'Choose a plan that works for you', 'relay-stream-portal' ); ?></p>
	</div></div>

	<form method="post" class="rsp-checkout-form">
		<?php echo RSP_Forms::fields( 'checkout' ); // phpcs:ignore ?>
		<details class="rsp-target-box" data-rsp-target-box>
			<summary>
				<span class="rsp-target-ic" data-rsp-target-icon><?php RSP_Icons::e( $rsp_sel[2], 20 ); ?></span>
				<span class="rsp-target-text"><strong data-rsp-target-title><?php echo esc_html( $rsp_sel[0] ); ?></strong><small data-rsp-target-sub><?php echo esc_html( $rsp_sel[1] ); ?></small></span>
				<?php if ( count( $rsp_options ) > 1 ) : ?><span class="rsp-target-change"><?php esc_html_e( 'Change', 'relay-stream-portal' ); ?> <?php RSP_Icons::e( 'chevron', 16 ); ?></span><?php endif; ?>
			</summary>
			<div class="rsp-target-options" role="radiogroup" aria-label="<?php esc_attr_e( 'This purchase is for', 'relay-stream-portal' ); ?>">
				<?php foreach ( $rsp_options as $rsp_val => $rsp_o ) : ?>
					<label class="rsp-target-opt">
						<input type="radio" name="target" value="<?php echo esc_attr( $rsp_val ); ?>" data-title="<?php echo esc_attr( $rsp_o[0] ); ?>" data-sub="<?php echo esc_attr( $rsp_o[1] ); ?>"<?php checked( $rsp_default, $rsp_val ); ?>>
						<?php RSP_Icons::e( $rsp_o[2], 18 ); ?><span><strong><?php echo esc_html( $rsp_o[0] ); ?></strong><small><?php echo esc_html( $rsp_o[1] ); ?></small></span>
					</label>
				<?php endforeach; ?>
			</div>
		</details>
		<div class="rsp-nickname-row" data-rsp-new-only<?php echo 'new' === $rsp_default ? '' : ' hidden'; ?>>
			<input type="text" name="nickname" maxlength="80" placeholder="<?php esc_attr_e( 'Nickname for the new account (optional)', 'relay-stream-portal' ); ?>" aria-label="<?php esc_attr_e( 'Nickname', 'relay-stream-portal' ); ?>">
		</div>
		<?php echo RSP_Portal::template( 'partials/plan-cards', array( 'vm' => $vm, 'args' => array_merge( $args, array( 'in_form' => true ) ) ) ); // phpcs:ignore ?>
	</form>

	<div class="rsp-text-toggles">
		<details class="rsp-text-toggle">
			<summary><?php esc_html_e( 'Have a promo code?', 'relay-stream-portal' ); ?></summary>
			<form method="post" class="rsp-input-row">
				<?php echo RSP_Forms::fields( 'promo' ); // phpcs:ignore ?>
				<input type="text" name="promo" maxlength="40" autocomplete="off" aria-label="<?php esc_attr_e( 'Promo code', 'relay-stream-portal' ); ?>">
				<button class="rsp-btn" type="submit"<?php echo $rsp_inert; // phpcs:ignore ?>><?php esc_html_e( 'Apply', 'relay-stream-portal' ); ?></button>
			</form>
		</details>
		<?php if ( RSP_Settings::get( 'referral_enabled' ) && empty( $vm['referral']['has_referrer'] ) && 0 === (int) $vm['order_count'] ) : ?>
			<details class="rsp-text-toggle">
				<summary><?php esc_html_e( 'Were you referred by someone?', 'relay-stream-portal' ); ?></summary>
				<form method="post" class="rsp-input-row">
					<?php echo RSP_Forms::fields( 'referral_code' ); // phpcs:ignore ?>
					<input type="text" name="referral_code" maxlength="16" autocomplete="off" aria-label="<?php esc_attr_e( 'Referral code', 'relay-stream-portal' ); ?>">
					<button class="rsp-btn" type="submit"<?php echo $rsp_inert; // phpcs:ignore ?>><?php esc_html_e( 'Add code', 'relay-stream-portal' ); ?></button>
				</form>
			</details>
		<?php endif; ?>
	</div>

	<?php if ( $rsp_feat ) : ?>
		<details class="rsp-card rsp-accordion">
			<summary><span class="rsp-stat-icon rsp-c-blue rsp-sm-ic"><?php RSP_Icons::e( 'sparkle', 18 ); ?></span><span class="rsp-acc-text"><strong><?php esc_html_e( 'What’s Included with Every Plan', 'relay-stream-portal' ); ?></strong><small><?php echo esc_html( RSP_Settings::get( 'included_subtitle' ) ); ?></small></span><span class="rsp-acc-chev"><?php RSP_Icons::e( 'chevron', 18 ); ?></span></summary>
			<ul class="rsp-checklist">
				<?php foreach ( $rsp_feat as $rsp_f ) : ?><li><?php RSP_Icons::e( 'check', 16 ); ?><?php echo esc_html( $rsp_f ); ?></li><?php endforeach; ?>
			</ul>
		</details>
	<?php endif; ?>

	<section class="rsp-card rsp-secure">
		<h2><?php RSP_Icons::e( 'shield', 20 ); ?> <?php esc_html_e( 'Secure Payment', 'relay-stream-portal' ); ?></h2>
		<p><?php echo esc_html( RSP_Settings::get( 'secure_payment_text' ) ); ?></p>
		<?php if ( $vm['gateways'] ) : ?>
			<ul class="rsp-tags"><?php foreach ( $vm['gateways'] as $rsp_g ) : ?><li><?php echo esc_html( $rsp_g ); ?></li><?php endforeach; ?></ul>
		<?php endif; ?>
		<p class="rsp-muted rsp-small"><?php esc_html_e( 'Your account activates only after the payment provider confirms the payment — not when you return from the payment page. Only pay through this portal’s checkout.', 'relay-stream-portal' ); ?></p>
	</section>

	<?php if ( $rsp_guides ) : ?>
		<section>
			<h2 class="rsp-section-title"><?php echo esc_html( RSP_Settings::get( 'payment_guides_title' ) ); ?></h2>
			<div class="rsp-guide-grid">
				<?php foreach ( $rsp_guides as $rsp_g ) :
					$rsp_icon = ! empty( $rsp_g['icon'] ) ? $rsp_g['icon'] : mb_strtoupper( mb_substr( $rsp_g['title'], 0, 2 ) );
					?>
					<div class="rsp-guide-item">
						<span class="rsp-guide-icon" aria-hidden="true"><?php echo esc_html( $rsp_icon ); ?></span>
						<strong><?php echo esc_html( $rsp_g['title'] ); ?></strong>
						<?php if ( ! empty( $rsp_g['url'] ) ) : ?>
							<a class="rsp-link" href="<?php echo esc_url( $rsp_g['url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'guide', 'relay-stream-portal' ); ?> <?php RSP_Icons::e( 'external', 15 ); ?></a>
						<?php elseif ( ! empty( $rsp_g['text'] ) ) : ?>
							<small class="rsp-muted"><?php echo esc_html( $rsp_g['text'] ); ?></small>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endif; ?>
</div>
