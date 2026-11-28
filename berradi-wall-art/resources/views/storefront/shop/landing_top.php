<?php
/**
 * Landing of a universe (wall art / personalised gifts / laser) or of a recipient / occasion page:
 * hero with photo collage and "add a photo / add a text" bubbles, recipient pills, categories as round tiles.
 * Vars: $landing (family), $heading, $intro, $activeTag (optional).
 */
$menu = \App\Services\Catalog\Navigation::menu();
$family = (string) $landing;
$router = app()->router();
$images = empty($activeTag) ? \App\Services\CatalogService::familyImages($family, 5) : [];
$types = array_values(array_filter($menu[$family]['categories'] ?? [], fn ($c) => $c['count'] > 0));
$recipients = $menu['recipients'];
?>
<?php if (empty($activeTag)): ?>
<section class="landing-hero">
    <div class="container">
        <div class="lh-card">
            <div class="lh-text">
                <span class="eyebrow"><?= e(__('universe.eyebrow')) ?></span>
                <h1 class="lh-title"><?= e($heading) ?></h1>
                <p class="lh-lead"><?= e((string) $intro) ?></p>
                <?php if ($family !== 'wall' && $recipients !== [] && $router->has('shop.recipient')): ?>
                    <div class="lh-quick">
                        <?php foreach (array_slice($recipients, 0, 4) as $r): ?>
                            <a href="<?= e(route('shop.recipient', ['slug' => $r['slug']])) ?>"><?= e(__('gifts.for_name', ['name' => $r['name']])) ?></a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <a class="btn btn-bwa mt-2" href="#products"><?= e(__('gifts.discover')) ?></a>
                <?php endif; ?>
            </div>
            <div class="lh-visual" aria-hidden="true">
                <?php if ($images !== []): ?>
                    <div class="lh-collage n<?= count($images) ?>">
                        <?php foreach ($images as $img): ?><span><img src="<?= e(upload_url($img)) ?>" alt="" loading="lazy"></span><?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="lh-placeholder"><i class="bi <?= $family === 'laser' ? 'bi-lightning-charge' : ($family === 'gift' ? 'bi-gift' : 'bi-image') ?>"></i></div>
                <?php endif; ?>
                <?php if ($family !== 'wall'): ?>
                    <a class="lh-bubble b1" href="#products"><i class="bi bi-camera"></i><?= e(__('gifts.add_photo')) ?></a>
                    <a class="lh-bubble b2" href="#products"><i class="bi bi-fonts"></i><?= e(__('gifts.add_text')) ?></a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($recipients !== [] && $router->has('shop.recipient') && $family !== 'wall'): ?>
<section class="landing-pills">
    <div class="container">
        <div class="pill-row" role="list">
            <?php foreach ($recipients as $r): ?>
                <a role="listitem" class="pill<?= !empty($activeTag) && $activeTag['slug'] === $r['slug'] ? ' is-active' : '' ?>" href="<?= e(route('shop.recipient', ['slug' => $r['slug']])) ?>"><?= e($r['name']) ?></a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($types !== [] && empty($activeTag)): ?>
<section class="landing-types">
    <div class="container">
        <h2 class="lt-title"><?= e(__('gifts.by_type_' . $family)) ?></h2>
        <p class="lt-lead"><?= e(__('gifts.by_type_lead_' . $family)) ?></p>
        <div class="type-row" data-scroll-row>
            <?php foreach ($types as $c): ?>
                <a class="type-circle" href="<?= e($c['url']) ?>">
                    <span class="tc-img"><?php if ($c['image']): ?><img src="<?= e(upload_url($c['image'])) ?>" alt="" loading="lazy"><?php else: ?><i class="bi bi-stars"></i><?php endif; ?></span>
                    <span class="tc-label"><?= e($c['name']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>
