<?php
/**
 * PDF template: Quote / Devis.
 *
 * @package PrintFlowPro
 */

defined( 'ABSPATH' ) || exit;

$business_name = get_option( 'pfp_business_name', get_bloginfo( 'name' ) );
$business_address = get_option( 'pfp_business_address', '' );
$business_phone = get_option( 'pfp_business_phone', '' );
$business_email = get_option( 'pfp_business_email', get_option( 'admin_email' ) );
$business_ice = get_option( 'pfp_business_ice', '' );
?>
<!DOCTYPE html>
<html lang="fr">
<head>
	<meta charset="UTF-8">
	<style>
		body { font-family: 'Helvetica', Arial, sans-serif; font-size: 12px; color: #333; }
		.header { display: flex; justify-content: space-between; margin-bottom: 30px; }
		.company-info h1 { font-size: 24px; color: #2c3e50; margin: 0 0 10px 0; }
		.quote-title h2 { font-size: 28px; color: #e67e22; margin: 0; }
		.client-info { background: #f5f5f5; padding: 15px; margin-bottom: 20px; }
		.items-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
		.items-table th { background: #e67e22; color: #fff; padding: 10px; text-align: left; }
		.items-table td { padding: 8px 10px; border-bottom: 1px solid #ddd; }
		.text-right { text-align: right; }
		.validity { background: #fff3cd; padding: 10px; border: 1px solid #ffc107; margin-top: 20px; }
		.footer { position: fixed; bottom: 20px; width: 100%; text-align: center; font-size: 10px; color: #666; }
	</style>
</head>
<body>
	<div class="header">
		<div class="company-info">
			<h1><?php echo esc_html( $business_name ); ?></h1>
			<p><?php echo esc_html( $business_address ); ?></p>
			<p>Tél : <?php echo esc_html( $business_phone ); ?></p>
			<p>Email : <?php echo esc_html( $business_email ); ?></p>
		</div>
		<div class="quote-title" style="text-align: right;">
			<h2>DEVIS</h2>
			<p><strong>N° <?php echo esc_html( $quote['quote_number'] ?? '' ); ?></strong></p>
			<p>Date : <?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $quote['created_at'] ?? 'now' ) ) ); ?></p>
		</div>
	</div>

	<div class="client-info">
		<strong>Client :</strong><br>
		<?php echo esc_html( $quote['customer_name'] ?? '' ); ?><br>
		<?php echo esc_html( $quote['customer_email'] ?? '' ); ?><br>
		<?php echo esc_html( $quote['customer_phone'] ?? '' ); ?>
	</div>

	<table class="items-table">
		<thead>
			<tr>
				<th style="width:45%">Description</th>
				<th style="width:10%">Spécifications</th>
				<th style="width:10%" class="text-right">Qté</th>
				<th style="width:15%" class="text-right">Prix unit.</th>
				<th style="width:20%" class="text-right">Total</th>
			</tr>
		</thead>
		<tbody>
			<?php if ( ! empty( $quote['items'] ) ) : ?>
				<?php foreach ( $quote['items'] as $item ) : ?>
				<tr>
					<td><?php echo esc_html( $item['description'] ); ?></td>
					<td><?php echo esc_html( is_string( $item['specifications'] ) ? $item['specifications'] : '' ); ?></td>
					<td class="text-right"><?php echo esc_html( $item['quantity'] ); ?></td>
					<td class="text-right"><?php echo esc_html( number_format( $item['unit_price'], 2, ',', ' ' ) ); ?> MAD</td>
					<td class="text-right"><?php echo esc_html( number_format( $item['total_price'], 2, ',', ' ' ) ); ?> MAD</td>
				</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
		<tfoot>
			<tr>
				<td colspan="4" class="text-right"><strong>Total TTC :</strong></td>
				<td class="text-right"><strong><?php echo esc_html( number_format( $quote['total_amount'] ?? 0, 2, ',', ' ' ) ); ?> MAD</strong></td>
			</tr>
		</tfoot>
	</table>

	<?php if ( ! empty( $quote['notes'] ) ) : ?>
	<div style="margin-top: 15px;">
		<strong>Notes :</strong>
		<p><?php echo esc_html( $quote['notes'] ); ?></p>
	</div>
	<?php endif; ?>

	<div class="validity">
		<strong>Validité du devis :</strong> Ce devis est valable jusqu'au <?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $quote['valid_until'] ?? '+30 days' ) ) ); ?>.
	</div>

	<div style="margin-top: 30px;">
		<table style="width:100%;">
			<tr>
				<td style="width:50%; vertical-align:top;">
					<p><strong>Conditions :</strong></p>
					<ul style="font-size:11px;">
						<li>Délai de production à confirmer après validation</li>
						<li>Fichiers d'impression à fournir en haute résolution (300 DPI)</li>
						<li>Acompte de 50% à la commande</li>
					</ul>
				</td>
				<td style="width:50%; text-align:center; vertical-align:top;">
					<p><strong>Bon pour accord</strong></p>
					<p style="margin-top:60px; border-top: 1px solid #333; display:inline-block; padding-top:5px;">
						Signature et cachet du client
					</p>
				</td>
			</tr>
		</table>
	</div>

	<div class="footer">
		<p><?php echo esc_html( $business_name ); ?> — <?php echo esc_html( $business_address ); ?></p>
		<?php if ( $business_ice ) : ?>
		<p>ICE : <?php echo esc_html( $business_ice ); ?></p>
		<?php endif; ?>
	</div>
</body>
</html>
