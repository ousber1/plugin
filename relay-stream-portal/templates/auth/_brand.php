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
		<span class="rsp-brand-mark" aria-hidden="true"><svg viewBox="0 0 32 32" width="40" height="40"><rect width="32" height="32" rx="9" fill="var(--rsp-accent)"/><path d="M9 22V10h6.5a4 4 0 0 1 0 8H12m3.5 0L22 22" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
	<?php endif; ?>
	<strong><?php echo esc_html( RSP_Settings::get( 'brand_name' ) ); ?></strong>
</div>
