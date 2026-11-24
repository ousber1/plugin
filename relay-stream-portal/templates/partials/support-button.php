<?php
/**
 * Support button widget.
 *
 * @var array $vm
 * @var array $args
 * @package RelayStreamPortal
 */

defined( 'ABSPATH' ) || exit;
$rsp_url = RSP_Settings::get( 'support_url' ) ? RSP_Settings::get( 'support_url' ) : RSP_Settings::page_url( 'support', array( 'new' => 1 ) );
?>
<a class="rsp-btn rsp-btn-primary rsp-support-btn" href="<?php echo esc_url( ! empty( $vm['preview'] ) ? '#' : $rsp_url ); ?>"><?php RSP_Icons::e( 'support' ); ?><span><?php echo esc_html( RSP_Portal::label( $args, 'button', __( 'Contact support', 'relay-stream-portal' ) ) ); ?></span></a>
