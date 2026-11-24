<?php
/**
 * Sign-in form.
 *
 * @var array $args
 * @var bool  $preview
 * @package RelayStreamPortal
 */

defined( 'ABSPATH' ) || exit;
$rsp_pages    = (array) get_option( 'rsp_pages', array() );
$rsp_on_login = ! empty( $rsp_pages['login'] ) && (int) $rsp_pages['login'] === get_queried_object_id();
$rsp_redirect = isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : ( $rsp_on_login ? '' : RSP_Portal::current_url() ); // phpcs:ignore WordPress.Security.NonceVerification
$rsp_redirect = RSP_Auth::safe_redirect_target( $rsp_redirect );
$rsp_dis      = $preview ? ' disabled' : '';
?>
<div class="rsp-auth">
	<form method="post" class="rsp-card rsp-auth-card rsp-form">
		<?php require __DIR__ . '/_brand.php'; ?>
		<h1><?php echo esc_html( RSP_Portal::label( $args, 'title', __( 'Sign in', 'relay-stream-portal' ) ) ); ?></h1>
		<?php echo RSP_Forms::fields( 'login' ); // phpcs:ignore ?>
		<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $rsp_redirect ); ?>">
		<label class="rsp-field"><span><?php esc_html_e( 'Email or username', 'relay-stream-portal' ); ?></span><input type="text" name="log" required autocomplete="username"></label>
		<label class="rsp-field"><span><?php esc_html_e( 'Password', 'relay-stream-portal' ); ?></span><input type="password" name="pwd" required autocomplete="current-password"></label>
		<div class="rsp-row-between">
			<label class="rsp-check"><input type="checkbox" name="rememberme" value="1"><span><?php esc_html_e( 'Keep me signed in', 'relay-stream-portal' ); ?></span></label>
			<a class="rsp-link" href="<?php echo esc_url( RSP_Settings::page_url( 'reset' ) ); ?>"><?php esc_html_e( 'Forgot password?', 'relay-stream-portal' ); ?></a>
		</div>
		<button type="submit" class="rsp-btn rsp-btn-primary rsp-btn-block"<?php echo $rsp_dis; // phpcs:ignore ?>><?php echo esc_html( RSP_Portal::label( $args, 'button', __( 'Sign in', 'relay-stream-portal' ) ) ); ?></button>
		<?php if ( RSP_Settings::get( 'registration_open' ) ) : ?>
			<p class="rsp-center rsp-muted"><?php esc_html_e( 'New here?', 'relay-stream-portal' ); ?> <a class="rsp-link" href="<?php echo esc_url( RSP_Settings::page_url( 'register' ) ); ?>"><?php esc_html_e( 'Create an account', 'relay-stream-portal' ); ?></a></p>
		<?php endif; ?>
	</form>
</div>
