<?php
/**
 * Quotation / invoice / credit note PDF (Dompdf). Vars: kind, doc, items, company, number, date, due, totals, vat,
 * original, order, draft, paid, terms, notes, cancelled, locale.
 * Dompdf has no bidi engine: Arabic text is shaped and put in visual order by ArabicShaper, and in Arabic
 * documents the table columns are emitted right-to-left.
 */
use App\Helpers\Money;
use App\Services\Finance\AmountInWords;
use App\Services\Finance\ArabicShaper;

$rtl = $locale === 'ar';
$t = function (?string $text, int $wrap = 0): string {
    $text = (string) $text;
    return implode('<br>', array_map('e', ArabicShaper::lines($text, $wrap)));
};
$L = fn (string $key, array $p = []) => $t(__($key, $p));
$m = fn ($v) => e(money($v));
$cols = fn (array $cells) => $rtl ? array_reverse($cells) : $cells;
$align = $rtl ? 'right' : 'left';
$opp = $rtl ? 'left' : 'right';
$title = match ($kind) {
    'quotation' => __('pdf.quotation'),
    'credit_note' => __('pdf.credit_note'),
    default => __('pdf.invoice'),
};
$logo = !empty($company['company_logo']) && !str_contains((string) $company['company_logo'], '..') && is_file(public_path('uploads/' . $company['company_logo']))
    ? public_path('uploads/' . $company['company_logo']) : null;
$legal = array_filter([
    $company['company_ice'] ? 'ICE : ' . $company['company_ice'] : null,
    $company['company_rc'] ? 'RC : ' . $company['company_rc'] : null,
    $company['company_if'] ? 'IF : ' . $company['company_if'] : null,
    $company['company_patente'] ? __('pdf.patente') . ' : ' . $company['company_patente'] : null,
    $company['company_cnss'] ? 'CNSS : ' . $company['company_cnss'] : null,
]);
$rateLabel = fn ($r) => rtrim(rtrim(number_format((float) $r, 2, '.', ''), '0'), '.') . ' %';
$qty = fn ($q) => rtrim(rtrim(number_format((float) $q, 2, ',', ' '), '0'), ',');
$taxLabel = (string) setting('tax_label', 'TVA');
?><!doctype html>
<html lang="<?= e($locale) ?>">
<head>
<meta charset="utf-8">
<title><?= e($title . ' ' . ($number ?? '')) ?></title>
<style>
    @page { margin: 26mm 14mm 22mm 14mm; }
    * { font-family: "DejaVu Sans", sans-serif; }
    body { font-size: 9pt; color: #171717; line-height: 1.35; }
    .a { text-align: <?= $align ?>; }
    .o { text-align: <?= $opp ?>; }
    .muted { color: #6F6A62; }
    .gold { color: #8C7140; }
    header { position: fixed; top: -19mm; left: 0; right: 0; height: 16mm; }
    footer { position: fixed; bottom: -16mm; left: 0; right: 0; height: 12mm; font-size: 7pt; color: #6F6A62; border-top: 0.5pt solid #D8D2C6; padding-top: 2mm; text-align: center; }
    .brand { font-size: 15pt; font-weight: bold; letter-spacing: 3pt; }
    .brand small { display: block; font-size: 6.5pt; letter-spacing: 4pt; color: #8C7140; font-weight: normal; }
    table { border-collapse: collapse; width: 100%; }
    .meta td { vertical-align: top; padding: 0; }
    .doc-title { font-size: 18pt; font-weight: bold; letter-spacing: 1pt; }
    .box { border: 0.6pt solid #D8D2C6; padding: 3mm; }
    .lines { margin-top: 6mm; }
    .lines th { background: #171717; color: #FFFFFF; font-size: <?= $rtl ? '7pt' : '7.5pt' ?>; font-weight: bold; padding: 2.2mm 1.5mm; text-transform: uppercase; white-space: nowrap; }
    .lines td { padding: 2.2mm 2mm; border-bottom: 0.5pt solid #E5E1D8; vertical-align: top; }
    .lines td.o, .totals td.o { white-space: nowrap; }
    .lines .spec { font-size: 7.5pt; color: #6F6A62; }
    .totals td { padding: 1.4mm 2mm; }
    .totals .grand td { background: #F8F7F4; font-weight: bold; font-size: 10.5pt; border-top: 0.8pt solid #171717; }
    .watermark { position: fixed; z-index: -1; top: 95mm; left: 0; right: 0; text-align: center; font-size: 64pt; color: #EFEAE0; font-weight: bold; letter-spacing: 6pt; }
    .small { font-size: 7.5pt; }
    .stamp { border: 1pt solid #842F29; color: #842F29; padding: 1mm 3mm; font-weight: bold; display: inline-block; }
</style>
</head>
<body>
<?php if ($draft): ?><div class="watermark"><?= $L('pdf.draft') ?></div><?php elseif ($cancelled): ?><div class="watermark" style="color:#F3DEDC"><?= $L('pdf.cancelled') ?></div><?php endif; ?>
<header>
    <table class="meta"><tr>
        <?php foreach ($cols([
            '<td class="a" style="width:60%">' . ($logo ? '<img src="' . e($logo) . '" style="max-height:14mm;max-width:55mm">' : '<div class="brand">' . e(mb_strtoupper((string) ($company['company_name'] ?: 'BERRADI'))) . '<small>WALL ART</small></div>') . '</td>',
            '<td class="o" style="width:40%"><span class="small muted">' . $t((string) ($company['company_website'] ?? '')) . '</span></td>',
        ]) as $cell) { echo $cell; } ?>
    </tr></table>
</header>
<footer>
    <?= $t(implode(' — ', array_filter([(string) ($company['company_legal_name'] ?: $company['company_name']), trim($company['company_address'] . ' ' . $company['company_city']), (string) $company['company_phone'], (string) $company['company_email']]))) ?><br>
    <?= e(implode(' · ', $legal)) ?>
    <?php if (!empty($company['company_rib'])): ?><br><?= $L('pdf.bank') ?> <?= e(trim((string) $company['company_bank_name'])) ?> — RIB <?= e((string) $company['company_rib']) ?><?php endif; ?>
</footer>

<table class="meta"><tr>
    <?php
    $left = '<td class="a" style="width:52%"><div class="doc-title">' . $t($title) . '</div>'
        . '<div style="margin-top:1.5mm;font-size:10.5pt"><strong>' . e((string) ($number ?: __('pdf.draft'))) . '</strong></div>'
        . '<div class="muted" style="margin-top:1mm">' . $L('pdf.date') . ' : ' . e($date ? format_date($date) : '—') . '</div>'
        . ($due ? '<div class="muted">' . $L($kind === 'quotation' ? 'pdf.valid_until' : 'pdf.due_date') . ' : ' . e(format_date($due)) . '</div>' : '')
        . ($order ? '<div class="muted">' . $L('pdf.order_ref') . ' : ' . e($order['order_number']) . '</div>' : '')
        . ($original ? '<div class="muted">' . $L('pdf.credits_invoice') . ' : ' . e($original['invoice_number']) . ' (' . e(format_date($original['issue_date'])) . ')</div>' : '')
        . '</td>';
    $right = '<td style="width:48%"><div class="box a"><div class="small gold" style="text-transform:uppercase;letter-spacing:1pt">' . $L($kind === 'quotation' ? 'pdf.prepared_for' : 'pdf.billed_to') . '</div>'
        . '<div style="font-size:10pt;margin-top:1mm"><strong>' . $t((string) ($doc['customer_company'] ?: $doc['customer_name'])) . '</strong></div>'
        . ($doc['customer_company'] ? '<div>' . $t((string) $doc['customer_name']) . '</div>' : '')
        . ($doc['customer_address'] ? '<div>' . $t((string) $doc['customer_address'], 48) . '</div>' : '')
        . ($doc['customer_phone'] ? '<div>' . e((string) $doc['customer_phone']) . '</div>' : '')
        . ($doc['customer_email'] ? '<div>' . e((string) $doc['customer_email']) . '</div>' : '')
        . ($doc['customer_ice'] ? '<div style="margin-top:1mm"><strong>ICE : ' . e((string) $doc['customer_ice']) . '</strong></div>' : '')
        . '</div></td>';
    foreach ($cols([$left, $right]) as $cell) { echo $cell; }
    ?>
</tr></table>

<table class="lines">
    <thead><tr>
        <?php foreach ($cols([
            '<th class="a" style="width:36%">' . $L('pdf.description') . '</th>',
            '<th class="o" style="width:7%">' . $L('pdf.qty') . '</th>',
            '<th class="o" style="width:15%">' . $L('pdf.unit_ht') . '</th>',
            '<th class="o" style="width:8%">' . e($taxLabel) . '</th>',
            '<th class="o" style="width:16%">' . $L('pdf.total_ht') . '</th>',
            '<th class="o" style="width:18%">' . $L('pdf.total_ttc') . '</th>',
        ]) as $cell) { echo $cell; } ?>
    </tr></thead>
    <tbody>
    <?php foreach ($items as $it): ?>
        <tr>
            <?php foreach ($cols([
                '<td class="a">' . $t((string) $it['description'], 52) . ($it['specifications'] ? '<div class="spec">' . $t((string) $it['specifications'], 70) . '</div>' : '')
                    . ((float) $it['discount'] > 0 ? '<div class="spec">' . $L('pdf.line_discount') . ' : −' . $m($it['discount']) . '</div>' : '') . '</td>',
                '<td class="o">' . e($qty($it['quantity'])) . '</td>',
                '<td class="o">' . $m($it['unit']) . '</td>',
                '<td class="o">' . e($rateLabel($it['rate'])) . '</td>',
                '<td class="o">' . $m($it['ht']) . '</td>',
                '<td class="o">' . $m($it['ttc']) . '</td>',
            ]) as $cell) { echo $cell; } ?>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<table style="margin-top:5mm"><tr>
    <?php
    $vatTable = '<table class="totals small" style="width:100%"><tr><td class="a muted" colspan="3">' . $L('pdf.vat_breakdown') . '</td></tr>';
    foreach ($vat as $rate => $v) {
        $vatTable .= '<tr>' . implode('', $cols(['<td class="a">' . e($taxLabel . ' ' . $rate . ' %') . '</td>', '<td class="o">' . $L('pdf.base') . ' ' . $m($v['base']) . '</td>', '<td class="o">' . $m($v['tax']) . '</td>'])) . '</tr>';
    }
    $vatTable .= '</table>';
    $row = fn (string $label, string $value, string $cls = '') => '<tr class="' . $cls . '">' . implode('', $cols(['<td class="a">' . $label . '</td>', '<td class="o">' . $value . '</td>'])) . '</tr>';
    $tot = '<table class="totals">';
    $tot .= $row($L('pdf.subtotal_ht'), $m($totals['subtotal']));
    if ((float) $totals['discount'] > 0) {
        $tot .= $row($L('pdf.discount_ht'), '−' . $m($totals['discount']));
    }
    if ((float) $totals['shipping'] > 0) {
        $tot .= $row($L('pdf.shipping_ht'), $m($totals['shipping']));
    }
    $tot .= $row($L('pdf.total_ht'), $m(Money::add(Money::sub((string) $totals['subtotal'], (string) $totals['discount']), (string) $totals['shipping'])));
    $tot .= $row(e($taxLabel), $m($totals['tax']));
    $tot .= $row($L($kind === 'credit_note' ? 'pdf.credit_total' : 'pdf.total_ttc'), $m($totals['total']), 'grand');
    if ($paid !== null && (float) $paid > 0) {
        $tot .= $row($L('pdf.paid'), $m($paid));
        $left = Money::sub((string) $totals['total'], (string) $paid);
        $tot .= $row($L('pdf.balance'), $m(Money::toCents($left) > 0 ? $left : '0.00'));
    }
    $tot .= '</table>';
    foreach ($cols(['<td style="width:52%;vertical-align:top;padding-' . ($rtl ? 'left' : 'right') . ':6mm">' . $vatTable . '</td>', '<td style="width:48%;vertical-align:top">' . $tot . '</td>']) as $cell) {
        echo $cell;
    }
    ?>
</tr></table>

<?php if ($locale === 'fr' && $kind !== 'quotation'): ?>
    <p style="margin-top:5mm"><?= e($kind === 'credit_note' ? 'Arrêté le présent avoir à la somme de ' : 'Arrêtée la présente facture à la somme de ') ?><strong><?= e(AmountInWords::fr((string) $totals['total'])) ?></strong> <?= e(__('pdf.ttc')) ?>.</p>
<?php endif; ?>
<?php if ($kind === 'credit_note' && $notes): ?>
    <p class="a"><strong><?= $L('pdf.reason') ?> :</strong> <?= $t((string) $notes, 90) ?></p>
<?php endif; ?>
<?php if ($terms): ?>
    <div class="a small" style="margin-top:4mm"><strong><?= $L($kind === 'quotation' ? 'pdf.conditions' : 'pdf.payment_terms') ?></strong><br><?= $t((string) $terms, 100) ?></div>
<?php endif; ?>
<?php if ($kind === 'quotation'): ?>
    <table style="margin-top:10mm"><tr>
        <?php foreach ($cols(['<td style="width:50%"></td>', '<td class="box a" style="width:50%;height:22mm;vertical-align:top"><span class="small muted">' . $L('pdf.signature') . '</span></td>']) as $cell) { echo $cell; } ?>
    </tr></table>
<?php endif; ?>
<?php if (!empty($footer = (string) setting('invoice_footer_note', '')) && $kind !== 'quotation'): ?>
    <p class="small muted a" style="margin-top:4mm"><?= $t($footer, 110) ?></p>
<?php endif; ?>
</body>
</html>
