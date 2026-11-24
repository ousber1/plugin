<?php
/**
 * Profile editing.
 *
 * @var array $vm
 * @var array $args
 * @package RelayStreamPortal
 */

defined( 'ABSPATH' ) || exit;

$rsp_u     = ! empty( $vm['preview'] ) ? null : wp_get_current_user();
$rsp_inert = RSP_Portal::inert( $vm );
$rsp_v     = static function ( $k ) use ( $rsp_u, $vm ) {
	if ( ! $rsp_u ) {
		return 'email' === $k ? $vm['user']['email'] : ( 'display_name' === $k ? $vm['user']['name'] : '' );
	}
	return 'email' === $k ? $rsp_u->user_email : $rsp_u->$k;
};
?>
<div class="rsp-page rsp-narrow">
	<div class="rsp-page-head"><div><h1><?php echo esc_html( RSP_Portal::label( $args, 'title', __( 'Profile', 'relay-stream-portal' ) ) ); ?></h1>
		<p class="rsp-muted"><?php esc_html_e( 'This is your portal sign-in. It is separate from the player details of your streaming accounts.', 'relay-stream-portal' ); ?></p></div></div>
	<form method="post" class="rsp-card rsp-form" autocomplete="on">
		<?php echo RSP_Forms::fields( 'profile' ); // phpcs:ignore ?>
		<div class="rsp-grid rsp-grid-2">
			<label class="rsp-field"><span><?php esc_html_e( 'First name', 'relay-stream-portal' ); ?></span><input type="text" name="first_name" maxlength="80" value="<?php echo esc_attr( $rsp_v( 'first_name' ) ); ?>" autocomplete="given-name"></label>
			<label class="rsp-field"><span><?php esc_html_e( 'Last name', 'relay-stream-portal' ); ?></span><input type="text" name="last_name" maxlength="80" value="<?php echo esc_attr( $rsp_v( 'last_name' ) ); ?>" autocomplete="family-name"></label>
			<label class="rsp-field"><span><?php esc_html_e( 'Display name', 'relay-stream-portal' ); ?></span><input type="text" name="display_name" maxlength="80" value="<?php echo esc_attr( $rsp_v( 'display_name' ) ); ?>"></label>
			<label class="rsp-field"><span><?php esc_html_e( 'Email', 'relay-stream-portal' ); ?></span><input type="email" name="email" required value="<?php echo esc_attr( $rsp_v( 'email' ) ); ?>" autocomplete="email"></label>
		</div>
		<fieldset class="rsp-fieldset"><legend><?php esc_html_e( 'Change password', 'relay-stream-portal' ); ?></legend>
			<div class="rsp-grid rsp-grid-2">
				<label class="rsp-field"><span><?php esc_html_e( 'New password', 'relay-stream-portal' ); ?></span><input type="password" name="new_password" minlength="8" autocomplete="new-password"></label>
				<label class="rsp-field"><span><?php esc_html_e( 'Current password (required to change email or password)', 'relay-stream-portal' ); ?></span><input type="password" name="current_password" autocomplete="current-password"></label>
			</div>
		</fieldset>
		<label class="rsp-check"><input type="checkbox" name="email_opt_in" value="1"<?php checked( ! $rsp_u || '0' !== get_user_meta( $rsp_u->ID, 'rsp_email_opt_in', true ) ); ?>><span><?php esc_html_e( 'Email me about activations, expiry reminders and ticket replies', 'relay-stream-portal' ); ?></span></label>
		<button class="rsp-btn rsp-btn-primary" type="submit"<?php echo $rsp_inert; // phpcs:ignore ?>><?php esc_html_e( 'Save profile', 'relay-stream-portal' ); ?></button>
	</form>
</div>
