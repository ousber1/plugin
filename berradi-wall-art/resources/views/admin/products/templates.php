<?php $this->layout('layouts/admin', ['title' => __('templates.title')]); ?>
<p class="text-muted-bwa small"><?= e(__('templates.intro')) ?></p>
<?php foreach (['gift' => 'bi-gift', 'laser' => 'bi-lightning-charge'] as $family => $icon): ?>
    <h2 class="h5 mt-4 mb-3"><i class="bi <?= $icon ?> text-gold me-2"></i><?= e(__('templates.family_' . $family)) ?></h2>
    <div class="row g-3">
        <?php foreach ($templates as $key => $t): if ($t['family'] !== $family) continue;
            $title = locale() === 'ar' ? $t['title'][1] : $t['title'][0];
            $short = locale() === 'ar' ? $t['short'][1] : $t['short'][0];
            $exists = in_array($t['sku'], $existing, true); ?>
            <div class="col-md-6 col-xl-4">
                <div class="admin-card h-100"><div class="card-body d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <h3 class="h6 mb-1"><?= e($title) ?></h3>
                        <span class="badge-status is-muted text-nowrap"><?= e(__('shop.type_' . $t['type'])) ?></span>
                    </div>
                    <p class="small text-muted-bwa mb-2"><?= e($short) ?></p>
                    <ul class="small mb-3 ps-3">
                        <li><?= e(__('templates.price_from', ['price' => money($t['price'])])) ?></li>
                        <?php if ($t['variants'] !== []): ?><li><?= e(__('templates.n_variants', ['n' => count($t['variants'])])) ?> : <?= e(implode(', ', array_map(fn ($v) => implode(' ', array_filter([$v[0], $v[1]])), array_slice($t['variants'], 0, 4)))) ?><?= count($t['variants']) > 4 ? '…' : '' ?></li><?php endif; ?>
                        <li><?= e(__('templates.fields')) ?> : <?= e(implode(', ', array_map(fn ($f) => locale() === 'ar' ? $f['ar'] : $f['fr'], $t['fields']))) ?></li>
                        <?php if ($t['tiers'] !== []): ?><li><?= e(__('templates.tiers', ['list' => implode(' · ', array_map(fn ($x) => $x[0] . '+ : −' . $x[1] . ' %', $t['tiers']))])) ?></li><?php endif; ?>
                        <?php if (!empty($t['proof'])): ?><li><?= e(__('templates.proof')) ?></li><?php endif; ?>
                    </ul>
                    <form method="post" action="<?= e(route('admin.products.templates.create')) ?>" class="mt-auto">
                        <?= csrf_field() ?><input type="hidden" name="template" value="<?= e($key) ?>">
                        <button class="btn btn-sm <?= $exists ? 'btn-outline-bwa' : 'btn-bwa' ?> w-100" type="submit"><i class="bi bi-plus-lg me-1"></i><?= e($exists ? __('templates.create_again') : __('templates.create')) ?></button>
                    </form>
                </div></div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endforeach; ?>
