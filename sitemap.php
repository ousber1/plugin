<?php
/**
 * BERRADI PRINT - Dynamic Sitemap Generator
 * Access: /sitemap.php
 * Generates XML sitemap on-the-fly
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';

header('Content-Type: application/xml; charset=utf-8');

$db = getDB();
$base_url = APP_URL;
$now = date('Y-m-d');

echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <!-- Pages statiques -->
    <url>
        <loc><?= htmlspecialchars($base_url) ?>/</loc>
        <lastmod><?= $now ?></lastmod>
        <changefreq>daily</changefreq>
        <priority>1.0</priority>
    </url>
    <url>
        <loc><?= htmlspecialchars($base_url) ?>/index.php?page=catalogue</loc>
        <lastmod><?= $now ?></lastmod>
        <changefreq>daily</changefreq>
        <priority>0.9</priority>
    </url>
    <url>
        <loc><?= htmlspecialchars($base_url) ?>/index.php?page=contact</loc>
        <lastmod><?= $now ?></lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.7</priority>
    </url>
    <url>
        <loc><?= htmlspecialchars($base_url) ?>/index.php?page=devis</loc>
        <lastmod><?= $now ?></lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.8</priority>
    </url>
    <url>
        <loc><?= htmlspecialchars($base_url) ?>/index.php?page=a-propos</loc>
        <lastmod><?= $now ?></lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.6</priority>
    </url>

    <!-- Catégories -->
<?php
$cats = $db->query("SELECT slug FROM categories WHERE actif = 1 ORDER BY ordre")->fetchAll();
foreach ($cats as $cat):
?>
    <url>
        <loc><?= htmlspecialchars($base_url) ?>/index.php?page=catalogue&amp;cat=<?= htmlspecialchars($cat['slug']) ?></loc>
        <lastmod><?= $now ?></lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.8</priority>
    </url>
<?php endforeach; ?>

    <!-- Produits -->
<?php
$prods = $db->query("SELECT id, updated_at FROM produits WHERE actif = 1 ORDER BY id")->fetchAll();
foreach ($prods as $prod):
    $lastmod = $prod['updated_at'] ? date('Y-m-d', strtotime($prod['updated_at'])) : $now;
?>
    <url>
        <loc><?= htmlspecialchars($base_url) ?>/index.php?page=produit&amp;id=<?= (int)$prod['id'] ?></loc>
        <lastmod><?= $lastmod ?></lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.7</priority>
    </url>
<?php endforeach; ?>
</urlset>
