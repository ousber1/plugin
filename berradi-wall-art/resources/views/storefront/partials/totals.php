<?php
/** @var array<string,mixed> $totals */
$ship = $totals['shipping'];
?>
<dl class="totals">
    <div><dt><?= e(__('cart.subtotal')) ?></dt><dd><?= e(money($totals['subtotal'])) ?></dd></div>
    <?php if (\App\Helpers\Money::toCents($totals['discount']) > 0): ?>
        <div class="is-discount"><dt><?= e(__('cart.discount')) ?><?= $totals['coupon'] ? ' (' . e($totals['coupon']['code']) . ')' : '' ?></dt><dd>−<?= e(money($totals['discount'])) ?></dd></div>
    <?php endif; ?>
    <div><dt><?= e(__('cart.shipping')) ?></dt><dd data-total-shipping><?= $ship === null ? '<span class="text-muted-bwa small">' . e(__('cart.shipping_choose_city')) . '</span>' : ($ship['free'] ? e(__('cart.free')) : e(money($totals['shipping_total']))) ?></dd></div>
    <?php if (\App\Helpers\Money::toCents($totals['tax_total']) > 0): ?>
        <div class="is-tax"><dt><?= e($totals['tax_included'] ? __('cart.tax_included', ['label' => (string) setting('tax_label', 'TVA'), 'rate' => rtrim(rtrim($totals['tax_rate'], '0'), '.')]) : __('cart.tax_added', ['label' => (string) setting('tax_label', 'TVA'), 'rate' => rtrim(rtrim($totals['tax_rate'], '0'), '.')])) ?></dt><dd data-total-tax><?= e(money($totals['tax_total'])) ?></dd></div>
    <?php endif; ?>
    <div class="is-total"><dt><?= e(__('cart.total')) ?></dt><dd data-total-grand><?= e(money($totals['grand_total'])) ?></dd></div>
</dl>
<?php if ($ship !== null): ?>
    <p class="small text-muted-bwa mb-0" data-total-estimate><i class="bi bi-truck me-1"></i><?= e(__('cart.delivery_between', ['min' => format_date($ship['date_min']), 'max' => format_date($ship['date_max'])])) ?></p>
    <?php if (!$ship['free'] && $ship['threshold'] !== null): ?><p class="small text-gold mb-0" data-free-hint><?= e(__('cart.free_from', ['amount' => money($ship['threshold'])])) ?></p><?php endif; ?>
<?php endif; ?>
