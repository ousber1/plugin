<?php
/**
 * Email template: Order status changed.
 *
 * @package PrintFlowPro
 */

defined( 'ABSPATH' ) || exit;

$status_labels = array(
	'processing'         => 'En cours de traitement',
	'pfp-file-review'    => 'Fichier en cours de révision',
	'pfp-designing'      => 'En cours de design',
	'pfp-printing'       => 'En cours d\'impression',
	'pfp-finishing'      => 'En finition',
	'pfp-ready-delivery' => 'Prêt pour livraison',
	'completed'          => 'Livrée',
);

$status_label = $status_labels[ $new_status ] ?? $new_status;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
	<meta charset="UTF-8">
	<style>
		body { font-family: Arial, sans-serif; color: #333; line-height: 1.6; }
		.container { max-width: 600px; margin: 0 auto; padding: 20px; }
		.header { background: #2c3e50; color: #fff; padding: 20px; text-align: center; }
		.content { padding: 20px; background: #f9f9f9; }
		.status-badge { display: inline-block; padding: 8px 16px; background: #27ae60; color: #fff; border-radius: 4px; font-weight: bold; }
		.footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
	</style>
</head>
<body>
	<div class="container">
		<div class="header">
			<h1><?php echo esc_html( get_option( 'pfp_business_name', get_bloginfo( 'name' ) ) ); ?></h1>
		</div>
		<div class="content">
			<p>Bonjour <?php echo esc_html( $customer_name ); ?>,</p>

			<p>Le statut de votre commande <strong>#<?php echo esc_html( $order_number ); ?></strong> a été mis à jour :</p>

			<p style="text-align:center;">
				<span class="status-badge"><?php echo esc_html( $status_label ); ?></span>
			</p>

			<p>Vous pouvez suivre votre commande en détail depuis votre espace client.</p>

			<p>Cordialement,<br>
			L'équipe <?php echo esc_html( get_option( 'pfp_business_name', get_bloginfo( 'name' ) ) ); ?></p>
		</div>
		<div class="footer">
			<p><?php echo esc_html( get_option( 'pfp_business_name', get_bloginfo( 'name' ) ) ); ?></p>
		</div>
	</div>
</body>
</html>
