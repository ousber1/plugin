<?php
/**
 * Gift redemption.
 *
 * @var array $vm
 * @var array $args
 * @package RelayStreamPortal
 */

defined( 'ABSPATH' ) || exit;

$rsp_inert     = RSP_Portal::inert( $vm );
$rsp_renewable = array_values( array_filter( $vm['accounts'], static function ( $a ) { return ! empty( $a['renewable'] ); } ) );
?>
<div class="rsp-page rsp-narrow">
	<div class="rsp-page-head"><div><h1><?php echo esc_html( RSP_Portal::label( $args, 'title', __( 'Redeem a gift', 'relay-stream-portal' ) ) ); ?></h1>
		<p class="rsp-muted"><?php esc_html_e( 'Enter the 12-character code you received. Each code works once.', 'relay-stream-portal' ); ?></p></div></div>
	<form method="post" class="rsp-card rsp-form">
		<?php echo RSP_Forms::fields( 'redeem' ); // phpcs:ignore ?>
		<label class="rsp-field"><span><?php esc_html_e( 'Gift code', 'relay-stream-portal' ); ?></span>
			<input type="text" name="code" required maxlength="20" autocomplete="off" spellcheck="false" class="rsp-code-input" placeholder="XXXX-XXXX-XXXX"></label>
		<label class="rsp-field"><span><?php esc_html_e( 'Apply it to', 'relay-stream-portal' ); ?></span>
			<select name="target" data-rsp-target>
				<option value="new"><?php esc_html_e( 'A new streaming account', 'relay-stream-portal' ); ?></option>
				<?php foreach ( $rsp_renewable as $rsp_a ) : ?>
					<option value="<?php echo esc_attr( $rsp_a['uuid'] ); ?>"><?php
						/* translators: %s account */
						echo esc_html( sprintf( __( 'Extend “%s”', 'relay-stream-portal' ), $rsp_a['nickname'] ) ); ?></option>
				<?php endforeach; ?>
			</select></label>
		<label class="rsp-field" data-rsp-new-only><span><?php esc_html_e( 'Nickname for the new account (optional)', 'relay-stream-portal' ); ?></span><input type="text" name="nickname" maxlength="80"></label>
		<button type="submit" class="rsp-btn rsp-btn-primary rsp-btn-block"<?php echo $rsp_inert; // phpcs:ignore ?>><?php RSP_Icons::e( 'redeem', 18 ); ?><?php esc_html_e( 'Redeem', 'relay-stream-portal' ); ?></button>
		<p class="rsp-muted rsp-small"><?php esc_html_e( 'After redeeming, the activation status appears under My Accounts.', 'relay-stream-portal' ); ?></p>
	</form>
</div>
