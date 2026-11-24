<?php
/**
 * Forgot / reset password.
 *
 * @var array $args
 * @var bool  $preview
 * @package RelayStreamPortal
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.Security.NonceVerification.Recommended
$rsp_key   = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';
$rsp_login = isset( $_GET['login'] ) ? sanitize_text_field( rawurldecode( wp_unslash( $_GET['login'] ) ) ) : '';
// phpcs:enable
$rsp_dis = $preview ? ' disabled' : '';
?>
<div class="rsp-auth">
	<form method="post" class="rsp-card rsp-auth-card rsp-form">
		<?php require __DIR__ . '/_brand.php'; ?>
		<?php if ( $rsp_key && $rsp_login ) : ?>
			<h1><?php esc_html_e( 'Choose a new password', 'relay-stream-portal' ); ?></h1>
			<?php echo RSP_Forms::fields( 'resetpass' ); // phpcs:ignore ?>
			<input type="hidden" name="key" value="<?php echo esc_attr( $rsp_key ); ?>">
			<input type="hidden" name="login" value="<?php echo esc_attr( $rsp_login ); ?>">
			<label class="rsp-field"><span><?php esc_html_e( 'New password (at least 8 characters)', 'relay-stream-portal' ); ?></span><input type="password" name="pass1" required minlength="8" autocomplete="new-password"></label>
			<button type="submit" class="rsp-btn rsp-btn-primary rsp-btn-block"<?php echo $rsp_dis; // phpcs:ignore ?>><?php esc_html_e( 'Save password', 'relay-stream-portal' ); ?></button>
		<?php else : ?>
			<h1><?php echo esc_html( RSP_Portal::label( $args, 'title', __( 'Reset your password', 'relay-stream-portal' ) ) ); ?></h1>
			<p class="rsp-muted"><?php esc_html_e( 'Enter your email and we will send you a link to choose a new password.', 'relay-stream-portal' ); ?></p>
			<?php echo RSP_Forms::fields( 'lostpassword' ); // phpcs:ignore ?>
			<label class="rsp-field"><span><?php esc_html_e( 'Email or username', 'relay-stream-portal' ); ?></span><input type="text" name="user_login" required autocomplete="username"></label>
			<button type="submit" class="rsp-btn rsp-btn-primary rsp-btn-block"<?php echo $rsp_dis; // phpcs:ignore ?>><?php esc_html_e( 'Send reset link', 'relay-stream-portal' ); ?></button>
		<?php endif; ?>
		<p class="rsp-center"><a class="rsp-link" href="<?php echo esc_url( RSP_Settings::page_url( 'login' ) ); ?>"><?php esc_html_e( 'Back to sign in', 'relay-stream-portal' ); ?></a></p>
	</form>
</div>
