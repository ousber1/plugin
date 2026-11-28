<?php /** @var list<array<string,mixed>> $items */ ?>
<div class="row g-3 g-md-4 <?= e($rowClass ?? 'row-cols-2 row-cols-md-3 row-cols-xl-4') ?>">
    <?php foreach ($items as $p): ?>
        <div class="col"><?= $this->insert('storefront/partials/product_card', ['p' => $p]) ?></div>
    <?php endforeach; ?>
</div>
