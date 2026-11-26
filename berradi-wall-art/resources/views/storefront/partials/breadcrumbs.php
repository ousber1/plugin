<nav aria-label="breadcrumb" class="bwa-breadcrumb">
    <ol class="breadcrumb mb-0">
        <?php foreach ($crumbs as $i => [$label, $href]): ?>
            <?php if ($href !== null && $i < count($crumbs) - 1): ?>
                <li class="breadcrumb-item"><a href="<?= e(url($href)) ?>"><?= e($label) ?></a></li>
            <?php else: ?>
                <li class="breadcrumb-item active" aria-current="page"><?= e($label) ?></li>
            <?php endif; ?>
        <?php endforeach; ?>
    </ol>
</nav>
