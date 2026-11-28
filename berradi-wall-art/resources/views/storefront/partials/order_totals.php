<?php use App\Helpers\Money; ?>
<dl class="totals mt-3">
    <div><dt><?= e(__('cart.subtotal')) ?></dt><dd><?= e(money((string) $order['subtotal'])) ?></dd></div>
    <?php if (Money::toCents((string) $order['discount_total']) > 0): ?><div class="is-discount"><dt><?= e(__('cart.discount')) ?><?= $order['coupon_code'] ? ' (' . e($order['coupon_code']) . ')' : '' ?></dt><dd>−<?= e(money((string) $order['discount_total'])) ?></dd></div><?php endif; ?>
    <div><dt><?= e(__('cart.shipping')) ?></dt><dd><?= Money::isZero((string) $order['shipping_total']) ? e(__('cart.free')) : e(money((string) $order['shipping_total'])) ?></dd></div>
    <?php if (Money::toCents((string) $order['tax_total']) > 0): ?><div class="is-tax"><dt><?= e(__('cart.tax_amount', ['label' => (string) setting('tax_label', 'TVA')])) ?></dt><dd><?= e(money((string) $order['tax_total'])) ?></dd></div><?php endif; ?>
    <div class="is-total"><dt><?= e(__('cart.total')) ?></dt><dd><?= e(money((string) $order['grand_total'])) ?></dd></div>
    <?php if (Money::toCents((string) $order['amount_paid']) > 0): ?>
        <div><dt><?= e(__('account.paid')) ?></dt><dd><?= e(money((string) $order['amount_paid'])) ?></dd></div>
        <div><dt><?= e(__('account.balance')) ?></dt><dd><?= e(money((string) $order['balance_due'])) ?></dd></div>
    <?php endif; ?>
</dl>
