<?php /** @var list<array<string,mixed>> $items order_items rows */ ?>
<ul class="order-lines list-unstyled mb-0">
    <?php foreach ($items as $it): ?>
        <?php $opts = json_decode((string) ($it['options'] ?? '{}'), true) ?: []; ?>
        <li>
            <span class="ol-thumb">
                <?php if (!empty($it['custom_design_id']) && !empty($it['design_uuid'])): ?>
                    <img src="<?= e(route('builder.design.preview', ['uuid' => $it['design_uuid']])) ?>" alt="" loading="lazy">
                <?php elseif (!empty($it['preview_path']) && empty($it['custom_design_id'])): ?>
                    <img src="<?= e(upload_url($it['preview_path'])) ?>" alt="" loading="lazy">
                <?php else: ?><i class="bi bi-image"></i><?php endif; ?>
            </span>
            <span class="ol-main">
                <strong><?= e($it['title']) ?></strong>
                <small><?= e(implode(' · ', array_filter([
                    $it['width_cm'] ? cm($it['width_cm']) . ' × ' . cm($it['height_cm']) . ' cm' : null,
                    $it['material_name'], $it['frame_name'],
                    (int) $it['panels'] > 1 ? (int) $it['panels'] . ' ' . __('cart.panels') : null,
                    !empty($it['custom_design_id']) ? __('cart.custom_design') : null,
                ]))) ?></small>
                <small><?= (int) $it['quantity'] ?> × <?= e(money($it['unit_price'])) ?></small>
            </span>
            <span class="ol-total"><?= e(money($it['line_total'])) ?></span>
        </li>
    <?php endforeach; ?>
</ul>
