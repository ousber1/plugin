<?php
/**
 * Bottom of a universe landing: occasions, information tabs, FAQ, real customer reviews.
 * Vars: $landing (family).
 */
$family = (string) $landing;
$menu = \App\Services\Catalog\Navigation::menu();
$router = app()->router();
$faqs = \App\Services\CatalogService::faqs(6);
$reviews = \App\Services\CatalogService::featuredReviews(4);
$stats = \App\Services\CatalogService::reviewStats();
?>
<?php if ($menu['occasions'] !== [] && $router->has('shop.occasion') && $family !== 'wall'): ?>
<section class="section-sm landing-occasions">
    <div class="container">
        <h2 class="lt-title"><?= e(__('gifts.by_occasion')) ?></h2>
        <div class="occasion-grid">
            <?php foreach ($menu['occasions'] as $o): ?>
                <a class="occasion-card" href="<?= e(route('shop.occasion', ['slug' => $o['slug']])) ?>"><i class="bi bi-<?= e((string) ($o['icon'] ?: 'calendar-heart')) ?>"></i><span><?= e($o['name']) ?></span></a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="section-sm landing-info">
    <div class="container">
        <ul class="nav info-tabs" role="tablist">
            <?php foreach (['vibe', 'easy', 'delivery'] as $i => $k): ?>
                <li class="nav-item" role="presentation"><button class="nav-link<?= $i === 0 ? ' active' : '' ?>" data-bs-toggle="tab" data-bs-target="#info-<?= $k ?>" type="button" role="tab" aria-selected="<?= $i === 0 ? 'true' : 'false' ?>"><?= e(__('gifts.info_' . $k . '_title')) ?></button></li>
            <?php endforeach; ?>
        </ul>
        <div class="tab-content info-content">
            <?php foreach (['vibe', 'easy', 'delivery'] as $i => $k): ?>
                <div class="tab-pane fade<?= $i === 0 ? ' show active' : '' ?>" id="info-<?= $k ?>" role="tabpanel">
                    <h3 class="h6"><?= e(__('gifts.info_' . $k . '_title')) ?></h3>
                    <?php foreach (explode("\n", __('gifts.info_' . $k . '_' . $family)) as $para): ?><p><?= e($para) ?></p><?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php if ($faqs !== []): ?>
<section class="section-sm landing-faq">
    <div class="container">
        <h2 class="lt-title text-start"><?= e(__('gifts.faq_title')) ?></h2>
        <div class="faq-list">
            <?php foreach ($faqs as $f): ?>
                <details class="faq-item"><summary dir="auto"><?= e($f['question']) ?></summary><div class="faq-answer" dir="auto"><?= nl2br(e($f['answer'])) ?></div></details>
            <?php endforeach; ?>
        </div>
        <?php if ($router->has('page.faq')): ?><a class="btn btn-outline-bwa btn-sm mt-3" href="<?= e(route('page.faq')) ?>"><?= e(__('gifts.more_faq')) ?></a><?php endif; ?>
    </div>
</section>
<?php endif; ?>

<?php if ($reviews !== [] && (int) ($stats['count'] ?? 0) > 0): ?>
<section class="section-sm landing-reviews">
    <div class="container">
        <div class="lr-row">
            <div class="lr-score">
                <strong><?= e(number_format((float) $stats['avg'], 1, ',', '')) ?>/5</strong>
                <?= $this->insert('storefront/partials/stars', ['rating' => (float) $stats['avg']]) ?>
                <span><?= e(__('gifts.reviews_count', ['n' => (int) $stats['count']])) ?></span>
            </div>
            <?php foreach ($reviews as $r): ?>
                <div class="lr-card">
                    <?= $this->insert('storefront/partials/stars', ['rating' => (float) $r['rating']]) ?>
                    <?php if (!empty($r['title'])): ?><strong class="d-block mt-1" dir="auto"><?= e($r['title']) ?></strong><?php endif; ?>
                    <p dir="auto"><?= e(\App\Helpers\Str::limit((string) $r['body'], 110)) ?></p>
                    <span class="small text-muted-bwa"><?= e((string) $r['author_name']) ?><?= !empty($r['is_verified_purchase']) ? ' · ' . e(__('gifts.verified')) : '' ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>
