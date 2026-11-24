<?php
/**
 * Floating support button.
 *
 * @package RelayStreamPortal
 */

defined( 'ABSPATH' ) || exit;
$rsp_url = RSP_Settings::get( 'support_url' ) ? RSP_Settings::get( 'support_url' ) : RSP_Settings::page_url( 'support', array( 'new' => 1 ) );
?>
<div class="rsp-root rsp-floating" data-rsp-theme="<?php echo esc_attr( RSP_Settings::get( 'theme_default', 'auto' ) ); ?>" dir="<?php echo RSP_I18n::is_rtl() ? 'rtl' : 'ltr'; ?>">
	<a class="rsp-fab" href="<?php echo esc_url( $rsp_url ); ?>">
		<?php RSP_Icons::e( 'support', 22 ); ?><span><?php echo esc_html( RSP_Settings::get( 'floating_label' ) ); ?></span>
	</a>
</div>
