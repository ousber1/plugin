<?php
/**
 * Email template: Order confirmation.
 *
 * @package PrintFlowPro
 */

defined( 'ABSPATH' ) || exit;

/* Variables available: $order, $order_number, $customer_name */
?>
<!DOCTYPE html>
<html lang="fr">
<head>
	<meta charset="UTF-8">
	<style>
		body { font-family: Arial, sans-serif; color: #333; line-height: 1.6; }
		.container { max-width: 600px; margin: 0 auto; padding: 20px; }
		.header { background: #2c3e50; color: #fff; padding: 20px; text-align: center; }
		.header h1 { margin: 0; font-size: 24px; }
		.content { padding: 20px; background: #f9f9f9; }
		.order-info { background: #fff; padding: 15px; border: 1px solid #ddd; margin: 15px 0; }
		.footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
		.btn { display: inline-block; padding: 10px 25px; background: #3498db; color: #fff; text-decoration: none; border-radius: 4px; }
	</style>
</head>
<body>
	<div class="container">
		<div class="header">
			<h1><?php echo esc_html( get_option( 'pfp_business_name', get_bloginfo( 'name' ) ) ); ?></h1>
		</div>
		<div class="content">
			<p>Bonjour <?php echo esc_html( $customer_name ); ?>,</p>

			<p>Merci pour votre commande ! Nous avons bien reçu votre demande et elle est en cours de traitement.</p>

			<div class="order-info">
				<p><strong>Numéro de commande :</strong> #<?php echo esc_html( $order_number ); ?></p>
				<?php if ( isset( $order ) && $order ) : ?>
				<p><strong>Date :</strong> <?php echo esc_html( $order->get_date_created()->date( 'd/m/Y à H:i' ) ); ?></p>
				<p><strong>Total :</strong> <?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></p>
				<?php endif; ?>
			</div>

			<p>Vous pouvez suivre l'état de votre commande depuis votre espace client.</p>

			<p style="text-align:center;">
				<a href="<?php echo esc_url( wc_get_account_endpoint_url( 'orders' ) ); ?>" class="btn">Suivre ma commande</a>
			</p>

			<p>Si vous avez des questions, n'hésitez pas à nous contacter.</p>

			<p>Cordialement,<br>
			L'équipe <?php echo esc_html( get_option( 'pfp_business_name', get_bloginfo( 'name' ) ) ); ?></p>
		</div>
		<div class="footer">
			<p><?php echo esc_html( get_option( 'pfp_business_name', get_bloginfo( 'name' ) ) ); ?></p>
			<p><?php echo esc_html( get_option( 'pfp_business_address', '' ) ); ?></p>
			<p><?php echo esc_html( get_option( 'pfp_business_phone', '' ) ); ?></p>
		</div>
	</div>
</body>
</html>
