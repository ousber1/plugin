<?php
$this->layout('layouts/storefront', ['seo' => $seo]);
$c = $design['config'];
$router = app()->router();
$qualityClass = ['ok' => 'is-success', 'warning' => '', 'low' => 'is-danger'][$design['quality_status']] ?? '';
?>
<section class="section-sm">
    <div class="container">
        <?= $this->insert('storefront/partials/breadcrumbs', ['crumbs' => [[__('nav.home'), '/'], [__('nav.custom'), '/custom-wall-art'], [__('builder.design_title'), null]]]) ?>
        <div class="row g-4 g-lg-5 mt-1">
            <div class="col-lg-7">
                <div class="design-preview">
                    <?php if (!empty($design['preview_path'])): ?>
                        <img src="<?= e(route('builder.design.preview', ['uuid' => $design['uuid']])) ?>" alt="<?= e(__('builder.design_title')) ?>">
                    <?php else: ?>
                        <div class="empty-state"><i class="bi bi-image"></i><?= e(__('builder.no_preview')) ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-5">
                <span class="eyebrow"><?= e(__('builder.design_ref', ['ref' => $ref])) ?></span>
                <h1 class="product-name mt-2"><?= e($c['product']['title'] ?? __('builder.design_title')) ?></h1>
                <div class="product-price-lg my-3">
                    <span class="now"><?= e(money((string) $design['unit_price'])) ?></span>
                    <span class="small text-muted-bwa"><?= e(__('builder.per_unit')) ?></span>
                </div>
                <table class="table spec-table">
                    <tbody>
                    <tr><th><?= e(__('builder.size')) ?></th><td><?= e(cm($c['overall_width_cm'])) ?> × <?= e(cm($c['overall_height_cm'])) ?> cm</td></tr>
                    <?php if ((int) $c['panels'] > 1): ?>
                        <tr><th><?= e(__('builder.layout')) ?></th><td><?= e(__('builder.panels_of', ['n' => (int) $c['panels'], 'w' => cm($c['panel_width_cm']), 'h' => cm($c['panel_height_cm'])])) ?> · <?= e($c['mode'] === 'per_panel' ? __('builder.mode_per_panel') : __('builder.mode_span')) ?></td></tr>
                    <?php endif; ?>
                    <tr><th><?= e(__('builder.support')) ?></th><td><?= e($c['material']['name'] ?? '') ?></td></tr>
                    <tr><th><?= e(__('builder.frame')) ?></th><td><?= e($c['frame']['name'] ?? __('builder.no_frame')) ?></td></tr>
                    <?php if (!empty($c['border'])): ?><tr><th><?= e(__('builder.border')) ?></th><td><?= e($c['border']['name']) ?></td></tr><?php endif; ?>
                    <?php if (!empty($c['finish'])): ?><tr><th><?= e(__('builder.finish')) ?></th><td><?= e($c['finish']['name']) ?></td></tr><?php endif; ?>
                    <tr><th><?= e(__('builder.quality')) ?></th><td><span class="badge-status <?= $qualityClass ?>"><?= e(__('builder.quality_' . $design['quality_status'])) ?></span> <small class="text-muted-bwa"><?= (int) ($c['min_dpi'] ?? 0) ?> dpi</small></td></tr>
                    <tr><th><?= e(__('builder.photos')) ?></th><td><?= count($design['images']) ?></td></tr>
                    </tbody>
                </table>
                <details class="price-breakdown mb-4">
                    <summary><?= e(__('builder.price_details')) ?></summary>
                    <ul class="list-unstyled small mt-2 mb-0">
                        <?php foreach ($c['breakdown'] ?? [] as $l): ?>
                            <li class="d-flex justify-content-between gap-3"><span><?= e($l['label']) ?></span><span><?= e(money($l['amount'])) ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                </details>
                <?php if ($design['status'] === 'abandoned'): ?>
                    <div class="alert alert-warning small"><?= e(__('builder.design_expired')) ?></div>
                <?php endif; ?>
                <div class="d-grid gap-2">
                    <?php if ($router->has('cart.add') && in_array($design['status'], ['draft', 'in_cart'], true)): ?>
                        <form method="post" action="<?= e(route('cart.add')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="design" value="<?= e($design['uuid']) ?>">
                            <button class="btn btn-bwa btn-lg w-100" type="submit"><i class="bi bi-bag me-2"></i><?= e(__('product.add_to_cart')) ?></button>
                        </form>
                    <?php elseif ($whatsapp !== '' && $design['status'] !== 'abandoned'): ?>
                        <a class="btn btn-bwa btn-lg" href="<?= e($whatsapp) ?>" target="_blank" rel="noopener"><i class="bi bi-whatsapp me-2"></i><?= e(__('builder.order_whatsapp')) ?></a>
                    <?php endif; ?>
                    <a class="btn btn-outline-bwa" href="<?= e(route('builder.index', $c['product'] ? ['product' => $c['product']['slug']] : [])) ?>"><?= e(__('builder.new_design')) ?></a>
                    <?php if (in_array($design['status'], ['draft', 'abandoned'], true)): ?>
                        <form method="post" action="<?= e(route('builder.design.delete', ['uuid' => $design['uuid']])) ?>" data-confirm="<?= e(__('builder.confirm_delete_design')) ?>">
                            <?= csrf_field() ?>
                            <button class="btn btn-link text-danger w-100 small" type="submit"><?= e(__('builder.delete_design')) ?></button>
                        </form>
                    <?php endif; ?>
                </div>
                <p class="small text-muted-bwa mt-3"><i class="bi bi-shield-lock me-1"></i><?= e(__('builder.private_note')) ?></p>
            </div>
        </div>
    </div>
</section>
