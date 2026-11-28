<!doctype html>
<html lang="<?= e(locale()) ?>" dir="<?= e(is_rtl() ? 'rtl' : 'ltr') ?>">
<head>
    <?= $this->insert('partials/head', ['title' => $title ?? 'Error', 'noindex' => true]) ?>
</head>
<body class="d-flex align-items-center min-vh-100" style="background:var(--bwa-bg)">
    <main class="container text-center py-5">
        <div class="mb-4"><?= $this->insert('partials/brand', ['textOnly' => true]) ?></div>
        <div class="display-font" style="font-size:7rem;line-height:1;color:var(--bwa-gold)"><?= e((string) $code) ?></div>
        <h1 class="h2 mt-3"><?= e($title) ?></h1>
        <p class="text-muted-bwa mx-auto" style="max-width:480px"><?= e($text) ?></p>
        <a href="<?= e(url('/')) ?>" class="btn btn-bwa mt-3"><?= e(__('errors.home')) ?></a>
        <?php if (!empty($exception)): ?>
            <pre class="code-block text-start mt-5 mx-auto" style="max-width:960px;white-space:pre-wrap"><?= e(get_class($exception) . ': ' . $exception->getMessage() . "\n" . $exception->getFile() . ':' . $exception->getLine() . "\n\n" . $exception->getTraceAsString()) ?></pre>
        <?php endif; ?>
    </main>
</body>
</html>
