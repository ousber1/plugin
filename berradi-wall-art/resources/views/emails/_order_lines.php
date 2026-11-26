<?php use App\Helpers\Money; ?>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:16px 0;font-size:14px">
    <?php foreach ($items as $it): ?>
        <tr>
            <td style="padding:10px 0;border-bottom:1px solid #E6E1D7">
                <strong><?= e($it['title']) ?></strong><br>
                <span style="color:#6F6A62;font-size:12px"><?= e(implode(' · ', array_filter([
                    $it['width_cm'] ? cm($it['width_cm']) . ' × ' . cm($it['height_cm']) . ' cm' : null, $it['material_name'], $it['frame_name'],
                    (int) $it['panels'] > 1 ? (int) $it['panels'] . ' ' . __('cart.panels') : null,
                ]))) ?></span><br>
                <span style="color:#6F6A62;font-size:12px"><?= (int) $it['quantity'] ?> × <?= e(money($it['unit_price'])) ?></span>
            </td>
            <td style="padding:10px 0;border-bottom:1px solid #E6E1D7;text-align:<?= is_rtl() ? 'left' : 'right' ?>;white-space:nowrap"><?= e(money($it['line_total'])) ?></td>
        </tr>
    <?php endforeach; ?>
    <?php $rows = [[__('cart.subtotal'), money((string) $order['subtotal'])]];
    if (Money::toCents((string) $order['discount_total']) > 0) { $rows[] = [__('cart.discount'), '−' . money((string) $order['discount_total'])]; }
    $rows[] = [__('cart.shipping'), Money::isZero((string) $order['shipping_total']) ? __('cart.free') : money((string) $order['shipping_total'])];
    if (Money::toCents((string) $order['tax_total']) > 0) { $rows[] = [__('cart.tax_amount', ['label' => (string) setting('tax_label', 'TVA')]), money((string) $order['tax_total'])]; } ?>
    <?php foreach ($rows as [$l, $v]): ?>
        <tr><td style="padding:4px 0;color:#6F6A62"><?= e($l) ?></td><td style="padding:4px 0;text-align:<?= is_rtl() ? 'left' : 'right' ?>"><?= e($v) ?></td></tr>
    <?php endforeach; ?>
    <tr><td style="padding:10px 0;font-weight:bold;border-top:2px solid #171717"><?= e(__('cart.total')) ?></td><td style="padding:10px 0;font-weight:bold;border-top:2px solid #171717;text-align:<?= is_rtl() ? 'left' : 'right' ?>"><?= e(money((string) $order['grand_total'])) ?></td></tr>
</table>
