<?php
/**
 * Plan cards. Inside Plans & Billing they submit the checkout form; standalone they link to it.
 *
 * @var array $vm
 * @var array $args
 * @package RelayStreamPortal
 */

defined( 'ABSPATH' ) || exit;

$rsp_in_form = ! empty( $args['in_form'] );
$rsp_plans   = isset( $vm['plans'] ) ? $vm['plans'] : array();
if ( ! empty( $args['gift_only'] ) ) {
	$rsp_plans = array_values( array_filter( $rsp_plans, static function ( $p ) { return ! empty( $p['giftable'] ); } ) );
}
$rsp_btn = RSP_Portal::label( $args, 'button', ! empty( $args['gift_only'] ) ? __( 'Gift this plan', 'relay-stream-portal' ) : __( 'Subscribe', 'relay-stream-portal' ) );
if ( ! $rsp_plans ) : ?>
	<div class="rsp-empty rsp-empty-sm"><p><?php esc_html_e( 'No plans are available right now.', 'relay-stream-portal' ); ?></p></div>
	<?php
	return;
endif;
?>
<div class="rsp-plans">
	<?php foreach ( $rsp_plans as $rsp_plan ) :
		$rsp_price   = isset( $rsp_plan['price'] ) ? (float) $rsp_plan['price'] : 0;
		$rsp_monthly = isset( $rsp_plan['monthly'] ) ? (float) $rsp_plan['monthly'] : $rsp_price;
		?>
		<div class="rsp-plan<?php echo $rsp_plan['featured'] ? ' is-featured' : ''; ?>">
			<?php if ( $rsp_plan['featured'] || $rsp_plan['badge'] ) : ?><span class="rsp-plan-badge"><?php echo esc_html( $rsp_plan['badge'] ? $rsp_plan['badge'] : __( 'Most Popular', 'relay-stream-portal' ) ); ?></span><?php endif; ?>
			<h3 class="rsp-plan-name"><?php echo esc_html( $rsp_plan['name'] ); ?></h3>
			<p class="rsp-plan-term"><?php
				/* translators: %d months */
				echo esc_html( sprintf( _n( '%d Month', '%d Months', (int) $rsp_plan['months'], 'relay-stream-portal' ), (int) $rsp_plan['months'] ) ); ?></p>
			<p class="rsp-plan-price"><?php echo $rsp_price ? RSP_Portal::price_html( $rsp_price ) : wp_kses_post( $rsp_plan['price_html'] ); // phpcs:ignore ?></p>
			<?php if ( $rsp_plan['savings'] > 0 ) : ?>
				<p class="rsp-save"><?php
					/* translators: 1: percent 2: monthly price */
					echo esc_html( sprintf( __( 'Save %1$d%% — %2$s/mo', 'relay-stream-portal' ), (int) $rsp_plan['savings'], wp_strip_all_tags( html_entity_decode( $rsp_plan['monthly_html'] ) ) ) ); ?></p>
			<?php else : ?>
				<p class="rsp-plan-monthly"><?php
					/* translators: %s monthly price */
					echo esc_html( sprintf( __( '%s/mo', 'relay-stream-portal' ), wp_strip_all_tags( html_entity_decode( $rsp_plan['monthly_html'] ) ) ) ); ?></p>
			<?php endif; ?>
			<?php if ( ! empty( $rsp_plan['features'] ) && ! empty( $args['show_features'] ) ) : ?>
				<ul class="rsp-plan-features"><?php foreach ( $rsp_plan['features'] as $rsp_f ) : ?><li><?php RSP_Icons::e( 'check', 16 ); ?><?php echo esc_html( $rsp_f ); ?></li><?php endforeach; ?></ul>
			<?php endif; ?>
			<?php if ( $rsp_in_form ) : ?>
				<button type="submit" name="plan" value="<?php echo (int) $rsp_plan['id']; ?>" class="rsp-btn <?php echo $rsp_plan['featured'] ? 'rsp-btn-white' : 'rsp-btn-primary'; ?> rsp-btn-block"<?php echo RSP_Portal::inert( $vm ); // phpcs:ignore ?>><?php echo esc_html( $rsp_btn ); ?></button>
			<?php else : ?>
				<a class="rsp-btn <?php echo $rsp_plan['featured'] ? 'rsp-btn-white' : 'rsp-btn-primary'; ?> rsp-btn-block" href="<?php echo esc_url( ! empty( $vm['preview'] ) ? '#' : ( is_user_logged_in() ? RSP_Settings::page_url( 'plans', array( 'plan' => $rsp_plan['id'] ) ) : RSP_Settings::page_url( 'register' ) ) ); ?>"><?php echo esc_html( $rsp_btn ); ?></a>
			<?php endif; ?>
			<p class="rsp-plan-foot"><?php esc_html_e( 'One-time payment · No auto-renewal', 'relay-stream-portal' ); ?></p>
		</div>
	<?php endforeach; ?>
</div>
