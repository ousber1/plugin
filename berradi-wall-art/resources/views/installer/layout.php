<!doctype html>
<html lang="<?= e(locale()) ?>" dir="<?= e(is_rtl() ? 'rtl' : 'ltr') ?>">
<head>
    <?= $this->insert('partials/head', ['title' => __('installer.title'), 'noindex' => true]) ?>
</head>
<body class="installer-body">
    <?= $this->insert('partials/alerts', ['hideErrorToast' => true]) ?>
    <div class="installer-shell">
        <div class="d-flex justify-content-between align-items-center mb-5">
            <?= $this->insert('partials/brand', ['textOnly' => true]) ?>
            <span class="lang-switch">
                <a href="?lang=fr" class="<?= locale() === 'fr' ? 'active' : '' ?>">FR</a>
                <a href="?lang=ar" class="<?= locale() === 'ar' ? 'active' : '' ?>">العربية</a>
            </span>
        </div>
        <div class="row g-4">
            <div class="col-lg-3">
                <ol class="installer-steps">
                    <?php foreach ($steps as $n => $key): ?>
                        <li class="<?= $n < $step ? 'done' : ($n === $step ? 'current' : '') ?>">
                            <span class="dot"><?= $n < $step ? '<i class="bi bi-check"></i>' : $n ?></span>
                            <?php if ($n < $step && $n <= (int) ($state['max_step'] ?? 1) && $step !== 10): ?>
                                <a href="<?= e(route('installer.step', ['step' => $n])) ?>" class="text-reset"><?= e(__('installer.step_' . $key)) ?></a>
                            <?php else: ?>
                                <?= e(__('installer.step_' . $key)) ?>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </div>
            <div class="col-lg-9">
                <div class="card-bwa">
                    <div class="card-body p-4 p-md-5">
                        <span class="eyebrow"><?= e(__('installer.step_x', ['n' => $step, 'total' => count($steps)])) ?></span>
                        <h1 class="h2 mt-2 mb-4"><?= e(__('installer.step_' . $steps[$step])) ?></h1>
                        <?= $this->section('content') ?>
                    </div>
                </div>
                <p class="text-center small text-muted-bwa mt-4">BERRADI WALL ART · <?= e(__('installer.title')) ?> · PHP <?= e(PHP_VERSION) ?></p>
            </div>
        </div>
    </div>
    <script src="<?= e(asset('vendor/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
    <script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
