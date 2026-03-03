<?php
/**
 * BERRADI PRINT - Affichage des Pages Publiques
 */
require 'config/app.php';
require 'config/database.php';
require 'includes/functions.php';

$db = getDB();

// Get slug from URL
$slug = $_GET['slug'] ?? '';
if (!$slug) {
    http_response_code(404);
    die('Page non spécifiée.');
}

// Sanitize slug
$slug = preg_replace('/[^a-z0-9-]/', '', strtolower($slug));

// Fetch page from database
$stmt = $db->prepare("SELECT * FROM pages WHERE slug = ? AND visible = 1");
$stmt->execute([$slug]);
$page = $stmt->fetch();

if (!$page) {
    http_response_code(404);
    die('Page non trouvée.');
}

// Set page-specific meta tags
$page_title = $page['meta_title'] ?: $page['nom'];
$page_description = $page['meta_description'] ?: '';
$page_image = $page['meta_image'] ? APP_URL . 'uploads/pages/' . $page['meta_image'] : '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> - <?= APP_NAME ?></title>
    <meta name="description" content="<?= htmlspecialchars($page_description) ?>">
    <?php if ($page_image): ?>
    <meta property="og:image" content="<?= htmlspecialchars($page_image) ?>">
    <meta name="twitter:image" content="<?= htmlspecialchars($page_image) ?>">
    <?php endif; ?>
    <meta property="og:title" content="<?= htmlspecialchars($page_title) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($page_description) ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= APP_URL ?>pages.php?slug=<?= urlencode($slug) ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <?php
    // Load custom CSS if set in customizer
    $custom_css = getParametre('custom_css', '');
    $custom_js_head = getParametre('custom_js_head', '');
    if ($custom_css): ?>
    <style><?= $custom_css ?></style>
    <?php endif; if ($custom_js_head): ?>
    <?= $custom_js_head ?>
    <?php endif; ?>
</head>
<body>
    <?php if ($page['show_header_footer']): ?>
        <?php include 'includes/header.php'; ?>
    <?php endif; ?>

    <!-- Page Content -->
    <main class="container my-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <?php if ($page['template'] === 'landing'): ?>
                    <!-- Landing page full width -->
                    <div class="page-content">
                        <?= $page['body'] ?>
                    </div>
                <?php else: ?>
                    <!-- Default page with title -->
                    <article class="page-article">
                        <h1 class="fw-bold mb-4"><?= htmlspecialchars($page['nom']) ?></h1>
                        <div class="page-content">
                            <?= $page['body'] ?>
                        </div>
                    </article>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <?php if ($page['show_header_footer']): ?>
        <?php include 'includes/footer.php'; ?>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
    <?php
    // Load custom JS at end of body
    $custom_js_body = getParametre('custom_js_body', '');
    if ($custom_js_body): ?>
    <script><?= $custom_js_body ?></script>
    <?php endif; ?>
</body>
</html>
