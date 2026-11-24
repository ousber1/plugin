<?php
/**
 * Registration form.
 *
 * @var array $args
 * @var bool  $preview
 * @package RelayStreamPortal
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.Security.NonceVerification.Recommended
$rsp_ref = isset( $_GET['rsp_ref'] ) ? sanitize_text_field( wp_unslash( $_GET['rsp_ref'] ) ) : ( isset( $_COOKIE[ RSP_Referrals::COOKIE ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ RSP_Referrals::COOKIE ] ) ) : '' );
$rsp_redirect = isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : '';
// phpcs:enable
$rsp_dis = $preview ? ' disabled' : '';
?>
<div class="rsp-auth">
	<?php if ( ! RSP_Settings::get( 'registration_open' ) ) : ?>
		<div class="rsp-card rsp-auth-card"><p><?php esc_html_e( 'Registration is currently closed.', 'relay-stream-portal' ); ?></p></div>
	<?php else : ?>
	<form method="post" class="rsp-card rsp-auth-card rsp-form">
		<?php require __DIR__ . '/_brand.php'; ?>
		<h1><?php echo esc_html( RSP_Portal::label( $args, 'title', __( 'Create your account', 'relay-stream-portal' ) ) ); ?></h1>
		<?php echo RSP_Forms::fields( 'register' ); // phpcs:ignore ?>
		<input type="hidden" name="redirect_to" value="<?php echo esc_attr( RSP_Auth::safe_redirect_target( $rsp_redirect ) ); ?>">
		<label class="rsp-field"><span><?php esc_html_e( 'First name', 'relay-stream-portal' ); ?></span><input type="text" name="first_name" maxlength="80" autocomplete="given-name"></label>
		<label class="rsp-field"><span><?php esc_html_e( 'Email', 'relay-stream-portal' ); ?></span><input type="email" name="email" required autocomplete="email"></label>
		<label class="rsp-field"><span><?php esc_html_e( 'Password (at least 8 characters)', 'relay-stream-portal' ); ?></span><input type="password" name="password" required minlength="8" autocomplete="new-password"></label>
		<?php if ( RSP_Settings::get( 'referral_enabled' ) ) : ?>
			<label class="rsp-field"><span><?php esc_html_e( 'Referral code (optional)', 'relay-stream-portal' ); ?></span><input type="text" name="rsp_referral_code" maxlength="16" value="<?php echo esc_attr( $rsp_ref ); ?>" autocomplete="off"></label>
		<?php endif; ?>
		<button type="submit" class="rsp-btn rsp-btn-primary rsp-btn-block"<?php echo $rsp_dis; // phpcs:ignore ?>><?php echo esc_html( RSP_Portal::label( $args, 'button', __( 'Create account', 'relay-stream-portal' ) ) ); ?></button>
		<p class="rsp-center rsp-muted"><?php esc_html_e( 'Already registered?', 'relay-stream-portal' ); ?> <a class="rsp-link" href="<?php echo esc_url( RSP_Settings::page_url( 'login' ) ); ?>"><?php esc_html_e( 'Sign in', 'relay-stream-portal' ); ?></a></p>
	</form>
	<?php endif; ?>
</div>
