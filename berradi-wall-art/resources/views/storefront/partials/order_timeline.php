<?php
use App\Services\Sales\OrderStatus;
$reached = OrderStatus::milestone((string) $order['status']);
$cancelled = in_array($order['status'], [OrderStatus::CANCELLED, OrderStatus::RETURNED], true);
?>
<?php if ($cancelled): ?>
    <div class="alert alert-danger mb-0"><i class="bi bi-x-circle me-2"></i><?= e(OrderStatus::label((string) $order['status'])) ?></div>
<?php else: ?>
    <ol class="order-timeline">
        <?php $i = 0; foreach (array_keys(OrderStatus::TIMELINE) as $step): ?>
            <li class="<?= $i < $reached ? 'done' : ($i === $reached ? 'current' : '') ?>">
                <span class="dot"><?= $i < $reached ? '<i class="bi bi-check"></i>' : $i + 1 ?></span>
                <span class="lbl"><?= e(__('track.step_' . $step)) ?></span>
            </li>
        <?php $i++; endforeach; ?>
    </ol>
    <p class="small text-muted-bwa mt-3 mb-0"><?= e(__('track.current_status')) ?> : <strong class="text-dark"><?= e(OrderStatus::label((string) $order['status'])) ?></strong></p>
<?php endif; ?>
