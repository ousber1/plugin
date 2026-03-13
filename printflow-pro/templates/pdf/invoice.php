<?php
/**
 * PDF template: Invoice.
 *
 * This template is used for generating invoice PDFs.
 * It outputs HTML that can be converted to PDF via a library like DOMPDF.
 *
 * @package PrintFlowPro
 */

defined( 'ABSPATH' ) || exit;

$business_name = get_option( 'pfp_business_name', get_bloginfo( 'name' ) );
$business_address = get_option( 'pfp_business_address', '' );
$business_phone = get_option( 'pfp_business_phone', '' );
$business_email = get_option( 'pfp_business_email', get_option( 'admin_email' ) );
$business_ice = get_option( 'pfp_business_ice', '' );
$business_rc = get_option( 'pfp_business_rc', '' );
?>
<!DOCTYPE html>
<html lang="fr">
<head>
	<meta charset="UTF-8">
	<style>
		body { font-family: 'Helvetica', Arial, sans-serif; font-size: 12px; color: #333; }
		.invoice-header { display: flex; justify-content: space-between; margin-bottom: 30px; }
		.company-info { flex: 1; }
		.company-info h1 { font-size: 24px; color: #2c3e50; margin: 0 0 10px 0; }
		.invoice-title { text-align: right; }
		.invoice-title h2 { font-size: 28px; color: #3498db; margin: 0; }
		.invoice-meta { margin-bottom: 20px; }
		.invoice-meta table { width: 100%; }
		.invoice-meta td { padding: 4px 8px; }
		.client-info { background: #f5f5f5; padding: 15px; margin-bottom: 20px; }
		.items-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
		.items-table th { background: #2c3e50; color: #fff; padding: 10px; text-align: left; }
		.items-table td { padding: 8px 10px; border-bottom: 1px solid #ddd; }
		.items-table .text-right { text-align: right; }
		.totals { float: right; width: 300px; }
		.totals table { width: 100%; }
		.totals td { padding: 6px 10px; }
		.totals .total-row { font-size: 16px; font-weight: bold; border-top: 2px solid #333; }
		.footer { position: fixed; bottom: 20px; width: 100%; text-align: center; font-size: 10px; color: #666; border-top: 1px solid #ddd; padding-top: 10px; }
	</style>
</head>
<body>
	<div class="invoice-header">
		<div class="company-info">
			<h1><?php echo esc_html( $business_name ); ?></h1>
			<p><?php echo esc_html( $business_address ); ?></p>
			<p>Tél : <?php echo esc_html( $business_phone ); ?></p>
			<p>Email : <?php echo esc_html( $business_email ); ?></p>
			<?php if ( $business_ice ) : ?>
			<p>ICE : <?php echo esc_html( $business_ice ); ?></p>
			<?php endif; ?>
			<?php if ( $business_rc ) : ?>
			<p>RC : <?php echo esc_html( $business_rc ); ?></p>
			<?php endif; ?>
		</div>
		<div class="invoice-title">
			<h2>FACTURE</h2>
			<p><strong>N° <?php echo esc_html( $invoice['invoice_number'] ?? '' ); ?></strong></p>
			<p>Date : <?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $invoice['created_at'] ?? 'now' ) ) ); ?></p>
			<p>Échéance : <?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $invoice['due_date'] ?? '+30 days' ) ) ); ?></p>
		</div>
	</div>

	<div class="client-info">
		<strong>Client :</strong><br>
		<?php echo esc_html( $customer_name ?? '' ); ?><br>
		<?php echo esc_html( $customer_address ?? '' ); ?><br>
		<?php echo esc_html( $customer_email ?? '' ); ?>
	</div>

	<table class="items-table">
		<thead>
			<tr>
				<th style="width:50%">Description</th>
				<th style="width:15%" class="text-right">Quantité</th>
				<th style="width:15%" class="text-right">Prix unitaire</th>
				<th style="width:20%" class="text-right">Total</th>
			</tr>
		</thead>
		<tbody>
			<?php if ( ! empty( $items ) ) : ?>
				<?php foreach ( $items as $item ) : ?>
				<tr>
					<td><?php echo esc_html( $item['description'] ); ?></td>
					<td class="text-right"><?php echo esc_html( $item['quantity'] ); ?></td>
					<td class="text-right"><?php echo esc_html( number_format( $item['unit_price'], 2, ',', ' ' ) ); ?> MAD</td>
					<td class="text-right"><?php echo esc_html( number_format( $item['total_price'], 2, ',', ' ' ) ); ?> MAD</td>
				</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

	<div class="totals">
		<table>
			<tr>
				<td>Sous-total HT :</td>
				<td class="text-right"><?php echo esc_html( number_format( ( $invoice['total_amount'] ?? 0 ) - ( $invoice['tax_amount'] ?? 0 ), 2, ',', ' ' ) ); ?> MAD</td>
			</tr>
			<tr>
				<td>TVA (<?php echo esc_html( get_option( 'pfp_tax_rate', 20 ) ); ?>%) :</td>
				<td class="text-right"><?php echo esc_html( number_format( $invoice['tax_amount'] ?? 0, 2, ',', ' ' ) ); ?> MAD</td>
			</tr>
			<tr class="total-row">
				<td>Total TTC :</td>
				<td class="text-right"><?php echo esc_html( number_format( $invoice['total_amount'] ?? 0, 2, ',', ' ' ) ); ?> MAD</td>
			</tr>
		</table>
	</div>

	<div style="clear:both;"></div>

	<div style="margin-top: 40px; padding: 15px; background: #f5f5f5;">
		<p><strong>Conditions de paiement :</strong> Paiement à 30 jours</p>
		<p><strong>Modes de paiement acceptés :</strong> Espèces, Virement bancaire, Carte bancaire</p>
	</div>

	<div class="footer">
		<p><?php echo esc_html( $business_name ); ?> — <?php echo esc_html( $business_address ); ?> — Tél : <?php echo esc_html( $business_phone ); ?></p>
		<?php if ( $business_ice ) : ?>
		<p>ICE : <?php echo esc_html( $business_ice ); ?> | RC : <?php echo esc_html( $business_rc ); ?></p>
		<?php endif; ?>
	</div>
</body>
</html>
