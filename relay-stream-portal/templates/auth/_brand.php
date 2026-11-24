<?php
/**
 * Auth card brand header.
 *
 * @package RelayStreamPortal
 */

defined( 'ABSPATH' ) || exit;
$rsp_logo_id = (int) RSP_Settings::get( 'logo_id' );
?>
<div class="rsp-auth-brand">
	<?php if ( $rsp_logo_id ) : ?>
		<?php echo wp_get_attachment_image( $rsp_logo_id, 'medium', false, array( 'class' => 'rsp-logo-img', 'alt' => esc_attr( RSP_Settings::get( 'brand_name' ) ) ) ); ?>
	<?php else : ?>
		<span class="rsp-brand-mark" aria-hidden="true"><svg viewBox="0 0 32 32" width="48" height="48"><path d="M16 3 29 28H3z" fill="currentColor"/><path d="M16 12 22 24H10z" fill="var(--rsp-surface)"/></svg></span>
	<?php endif; ?>
	<strong><?php echo esc_html( RSP_Settings::get( 'brand_name' ) ); ?></strong>
</div>
