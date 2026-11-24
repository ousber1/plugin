<?php
/**
 * Plan cards. Inside the Plans module they are submit buttons of the checkout form;
 * standalone (widget/shortcode) they link to the Plans page.
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
$rsp_btn = RSP_Portal::label( $args, 'button', ! empty( $args['gift_only'] ) ? __( 'Gift this plan', 'relay-stream-portal' ) : __( 'Select', 'relay-stream-portal' ) );
if ( ! $rsp_plans ) : ?>
	<div class="rsp-empty rsp-empty-sm"><p><?php esc_html_e( 'No plans are available right now.', 'relay-stream-portal' ); ?></p></div>
	<?php
	return;
endif;
?>
<div class="rsp-plans">
	<?php foreach ( $rsp_plans as $rsp_plan ) : ?>
		<div class="rsp-plan<?php echo $rsp_plan['featured'] ? ' is-featured' : ''; ?>">
			<?php if ( $rsp_plan['badge'] ) : ?><span class="rsp-plan-badge"><?php RSP_Icons::e( 'star', 14 ); ?><?php echo esc_html( $rsp_plan['badge'] ); ?></span><?php endif; ?>
			<h3 class="rsp-plan-name"><?php echo esc_html( $rsp_plan['name'] ); ?></h3>
			<p class="rsp-plan-term"><?php
				/* translators: %d months */
				echo esc_html( sprintf( _n( '%d month', '%d months', (int) $rsp_plan['months'], 'relay-stream-portal' ), (int) $rsp_plan['months'] ) ); ?></p>
			<p class="rsp-plan-price"><?php echo wp_kses_post( $rsp_plan['price_html'] ); ?></p>
			<?php if ( $rsp_plan['months'] > 1 ) : ?>
				<p class="rsp-plan-monthly"><?php
					/* translators: %s price */
					printf( esc_html__( '%s per month', 'relay-stream-portal' ), wp_kses_post( $rsp_plan['monthly_html'] ) ); ?>
					<?php if ( $rsp_plan['savings'] > 0 ) : ?><span class="rsp-save"><?php
						/* translators: %d percent */
						echo esc_html( sprintf( __( 'Save %d%%', 'relay-stream-portal' ), (int) $rsp_plan['savings'] ) ); ?></span><?php endif; ?>
				</p>
			<?php endif; ?>
			<?php if ( $rsp_plan['features'] ) : ?>
				<ul class="rsp-plan-features"><?php foreach ( $rsp_plan['features'] as $rsp_f ) : ?><li><?php RSP_Icons::e( 'check', 16 ); ?><?php echo esc_html( $rsp_f ); ?></li><?php endforeach; ?></ul>
			<?php endif; ?>
			<?php if ( $rsp_in_form ) : ?>
				<button type="submit" name="plan" value="<?php echo (int) $rsp_plan['id']; ?>" class="rsp-btn <?php echo $rsp_plan['featured'] ? 'rsp-btn-primary' : 'rsp-btn-outline'; ?> rsp-btn-block"<?php echo RSP_Portal::inert( $vm ); // phpcs:ignore ?>><?php echo esc_html( $rsp_btn ); ?></button>
			<?php else : ?>
				<a class="rsp-btn <?php echo $rsp_plan['featured'] ? 'rsp-btn-primary' : 'rsp-btn-outline'; ?> rsp-btn-block" href="<?php echo esc_url( ! empty( $vm['preview'] ) ? '#' : ( is_user_logged_in() ? RSP_Settings::page_url( 'plans', array( 'plan' => $rsp_plan['id'] ) ) : RSP_Settings::page_url( 'register' ) ) ); ?>"><?php echo esc_html( $rsp_btn ); ?></a>
			<?php endif; ?>
			<p class="rsp-plan-foot"><?php esc_html_e( 'One-time payment · no auto-renewal', 'relay-stream-portal' ); ?></p>
		</div>
	<?php endforeach; ?>
</div>
