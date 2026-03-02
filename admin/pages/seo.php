<?php
/**
 * BERRADI PRINT - Outils SEO
 * Gestion des balises meta, Open Graph, sitemap, robots.txt
 */

$db = getDB();

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'save_seo') {
        $seo_fields = [
            'seo_meta_title', 'seo_meta_description', 'seo_meta_keywords',
            'seo_og_title', 'seo_og_description', 'seo_og_image',
            'seo_twitter_card', 'seo_canonical_url',
            'seo_google_analytics', 'seo_google_tag_manager',
            'seo_custom_head', 'seo_custom_body',
            'seo_robots_index', 'seo_robots_follow',
            'seo_schema_type', 'seo_schema_name', 'seo_schema_description',
            'seo_schema_phone', 'seo_schema_address', 'seo_schema_city',
            'seo_schema_country', 'seo_schema_postal_code',
            'seo_schema_price_range', 'seo_schema_logo_url',
        ];

        foreach ($seo_fields as $field) {
            $value = $_POST[$field] ?? '';
            setParametre($field, $value);
        }

        setFlash('success', 'Paramètres SEO enregistrés avec succès.');
        redirect('index.php?page=seo');
    }

    if ($action === 'generate_sitemap') {
        $sitemap = generateSitemap();
        file_put_contents(__DIR__ . '/../../sitemap.xml', $sitemap);
        setFlash('success', 'Sitemap généré avec succès (sitemap.xml).');
        redirect('index.php?page=seo');
    }

    if ($action === 'save_robots') {
        $robots_content = $_POST['robots_content'] ?? '';
        file_put_contents(__DIR__ . '/../../robots.txt', $robots_content);
        setFlash('success', 'robots.txt mis à jour avec succès.');
        redirect('index.php?page=seo');
    }
}

// Générer le sitemap XML
function generateSitemap() {
    $db = getDB();
    $base_url = APP_URL;
    $now = date('Y-m-d');

    $urls = [];
    // Pages statiques
    $urls[] = ['loc' => $base_url . '/', 'priority' => '1.0', 'changefreq' => 'daily'];
    $urls[] = ['loc' => $base_url . '/index.php?page=catalogue', 'priority' => '0.9', 'changefreq' => 'daily'];
    $urls[] = ['loc' => $base_url . '/index.php?page=contact', 'priority' => '0.7', 'changefreq' => 'monthly'];
    $urls[] = ['loc' => $base_url . '/index.php?page=devis', 'priority' => '0.8', 'changefreq' => 'monthly'];
    $urls[] = ['loc' => $base_url . '/index.php?page=a-propos', 'priority' => '0.6', 'changefreq' => 'monthly'];

    // Catégories
    $cats = $db->query("SELECT slug FROM categories WHERE actif = 1")->fetchAll();
    foreach ($cats as $cat) {
        $urls[] = ['loc' => $base_url . '/index.php?page=catalogue&cat=' . $cat['slug'], 'priority' => '0.8', 'changefreq' => 'weekly'];
    }

    // Produits
    $prods = $db->query("SELECT id, updated_at FROM produits WHERE actif = 1")->fetchAll();
    foreach ($prods as $prod) {
        $urls[] = [
            'loc' => $base_url . '/index.php?page=produit&id=' . $prod['id'],
            'priority' => '0.7',
            'changefreq' => 'weekly',
            'lastmod' => date('Y-m-d', strtotime($prod['updated_at']))
        ];
    }

    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach ($urls as $url) {
        $xml .= "  <url>\n";
        $xml .= "    <loc>" . htmlspecialchars($url['loc']) . "</loc>\n";
        if (isset($url['lastmod'])) {
            $xml .= "    <lastmod>" . $url['lastmod'] . "</lastmod>\n";
        } else {
            $xml .= "    <lastmod>" . $now . "</lastmod>\n";
        }
        $xml .= "    <changefreq>" . $url['changefreq'] . "</changefreq>\n";
        $xml .= "    <priority>" . $url['priority'] . "</priority>\n";
        $xml .= "  </url>\n";
    }
    $xml .= "</urlset>";
    return $xml;
}

// Charger les paramètres SEO
$seo = [];
$seo_keys = [
    'seo_meta_title', 'seo_meta_description', 'seo_meta_keywords',
    'seo_og_title', 'seo_og_description', 'seo_og_image',
    'seo_twitter_card', 'seo_canonical_url',
    'seo_google_analytics', 'seo_google_tag_manager',
    'seo_custom_head', 'seo_custom_body',
    'seo_robots_index', 'seo_robots_follow',
    'seo_schema_type', 'seo_schema_name', 'seo_schema_description',
    'seo_schema_phone', 'seo_schema_address', 'seo_schema_city',
    'seo_schema_country', 'seo_schema_postal_code',
    'seo_schema_price_range', 'seo_schema_logo_url',
];
foreach ($seo_keys as $key) {
    $seo[$key] = getParametre($key, '');
}

// Charger le robots.txt actuel
$robots_file = __DIR__ . '/../../robots.txt';
$robots_content = file_exists($robots_file) ? file_get_contents($robots_file) : "User-agent: *\nAllow: /\n\nSitemap: " . APP_URL . "/sitemap.xml";

// Vérifier l'existence du sitemap
$sitemap_exists = file_exists(__DIR__ . '/../../sitemap.xml');
$sitemap_date = $sitemap_exists ? date('d/m/Y H:i', filemtime(__DIR__ . '/../../sitemap.xml')) : null;

$active_tab = $_GET['tab'] ?? 'meta';
?>

<div class="p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1"><i class="bi bi-search me-2"></i>Outils SEO</h4>
            <p class="text-muted mb-0">Optimisez votre référencement naturel</p>
        </div>
    </div>

    <!-- Onglets -->
    <ul class="nav nav-tabs mb-4" role="tablist">
        <li class="nav-item">
            <a class="nav-link <?= $active_tab === 'meta' ? 'active' : '' ?>" href="index.php?page=seo&tab=meta">
                <i class="bi bi-code-slash me-1"></i>Balises Meta
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $active_tab === 'opengraph' ? 'active' : '' ?>" href="index.php?page=seo&tab=opengraph">
                <i class="bi bi-share me-1"></i>Open Graph
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $active_tab === 'schema' ? 'active' : '' ?>" href="index.php?page=seo&tab=schema">
                <i class="bi bi-braces me-1"></i>Schema.org
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $active_tab === 'analytics' ? 'active' : '' ?>" href="index.php?page=seo&tab=analytics">
                <i class="bi bi-bar-chart me-1"></i>Analytics
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $active_tab === 'sitemap' ? 'active' : '' ?>" href="index.php?page=seo&tab=sitemap">
                <i class="bi bi-diagram-3 me-1"></i>Sitemap
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $active_tab === 'robots' ? 'active' : '' ?>" href="index.php?page=seo&tab=robots">
                <i class="bi bi-robot me-1"></i>Robots.txt
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $active_tab === 'custom' ? 'active' : '' ?>" href="index.php?page=seo&tab=custom">
                <i class="bi bi-code me-1"></i>Code Personnalisé
            </a>
        </li>
    </ul>

    <?php if ($active_tab === 'meta'): ?>
    <!-- Balises Meta -->
    <form method="post">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="save_seo">
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-code-slash text-primary me-2"></i>Balises Meta Globales</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Titre Meta (title)</label>
                    <input type="text" class="form-control" name="seo_meta_title" value="<?= htmlspecialchars($seo['seo_meta_title']) ?>" maxlength="70" placeholder="BERRADI PRINT - Services d'Impression Professionnels au Maroc">
                    <div class="form-text">Recommandé: 50-60 caractères</div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Description Meta</label>
                    <textarea class="form-control" name="seo_meta_description" rows="3" maxlength="160" placeholder="Services d'impression professionnels au Maroc. Cartes de visite, flyers, banderoles, stickers et plus..."><?= htmlspecialchars($seo['seo_meta_description']) ?></textarea>
                    <div class="form-text">Recommandé: 150-160 caractères</div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Mots-clés Meta</label>
                    <input type="text" class="form-control" name="seo_meta_keywords" value="<?= htmlspecialchars($seo['seo_meta_keywords']) ?>" placeholder="impression, imprimerie, cartes de visite, flyers, Maroc, Casablanca">
                    <div class="form-text">Séparés par des virgules</div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">URL Canonique</label>
                    <input type="url" class="form-control" name="seo_canonical_url" value="<?= htmlspecialchars($seo['seo_canonical_url']) ?>" placeholder="<?= APP_URL ?>">
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Indexation</label>
                        <select class="form-select" name="seo_robots_index">
                            <option value="index" <?= ($seo['seo_robots_index'] ?? 'index') === 'index' ? 'selected' : '' ?>>Index (Indexer)</option>
                            <option value="noindex" <?= ($seo['seo_robots_index'] ?? '') === 'noindex' ? 'selected' : '' ?>>Noindex (Ne pas indexer)</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Suivi des liens</label>
                        <select class="form-select" name="seo_robots_follow">
                            <option value="follow" <?= ($seo['seo_robots_follow'] ?? 'follow') === 'follow' ? 'selected' : '' ?>>Follow (Suivre)</option>
                            <option value="nofollow" <?= ($seo['seo_robots_follow'] ?? '') === 'nofollow' ? 'selected' : '' ?>>Nofollow (Ne pas suivre)</option>
                        </select>
                    </div>
                </div>

                <!-- Aperçu Google -->
                <div class="border rounded p-3 bg-light mt-3">
                    <h6 class="fw-bold text-muted mb-2"><i class="bi bi-google me-1"></i>Aperçu dans Google</h6>
                    <div style="font-family: Arial, sans-serif;">
                        <div style="color: #1a0dab; font-size: 18px;" id="previewTitle">
                            <?= htmlspecialchars($seo['seo_meta_title'] ?: APP_NAME . ' - ' . APP_TAGLINE) ?>
                        </div>
                        <div style="color: #006621; font-size: 14px;"><?= APP_URL ?></div>
                        <div style="color: #545454; font-size: 13px;" id="previewDesc">
                            <?= htmlspecialchars($seo['seo_meta_description'] ?: 'Services d\'impression professionnels au Maroc...') ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-white">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Enregistrer</button>
            </div>
        </div>
    </form>

    <?php elseif ($active_tab === 'opengraph'): ?>
    <!-- Open Graph -->
    <form method="post">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="save_seo">
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-share text-primary me-2"></i>Open Graph / Réseaux Sociaux</h5>
            </div>
            <div class="card-body">
                <p class="text-muted">Ces informations apparaissent lorsque votre site est partagé sur les réseaux sociaux (Facebook, LinkedIn, etc.)</p>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Titre OG</label>
                    <input type="text" class="form-control" name="seo_og_title" value="<?= htmlspecialchars($seo['seo_og_title']) ?>" placeholder="<?= APP_NAME ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Description OG</label>
                    <textarea class="form-control" name="seo_og_description" rows="3" placeholder="Description pour les réseaux sociaux..."><?= htmlspecialchars($seo['seo_og_description']) ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Image OG (URL)</label>
                    <input type="url" class="form-control" name="seo_og_image" value="<?= htmlspecialchars($seo['seo_og_image']) ?>" placeholder="https://votre-site.com/image-og.jpg">
                    <div class="form-text">Taille recommandée: 1200x630 pixels</div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Type de carte Twitter</label>
                    <select class="form-select" name="seo_twitter_card">
                        <option value="summary" <?= ($seo['seo_twitter_card'] ?? 'summary') === 'summary' ? 'selected' : '' ?>>Summary</option>
                        <option value="summary_large_image" <?= ($seo['seo_twitter_card'] ?? '') === 'summary_large_image' ? 'selected' : '' ?>>Summary Large Image</option>
                    </select>
                </div>
            </div>
            <div class="card-footer bg-white">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Enregistrer</button>
            </div>
        </div>
    </form>

    <?php elseif ($active_tab === 'schema'): ?>
    <!-- Schema.org -->
    <form method="post">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="save_seo">
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-braces text-primary me-2"></i>Données Structurées Schema.org</h5>
            </div>
            <div class="card-body">
                <p class="text-muted">Les données structurées aident Google à comprendre votre activité et à afficher des résultats enrichis.</p>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Type d'entreprise</label>
                    <select class="form-select" name="seo_schema_type">
                        <option value="LocalBusiness" <?= ($seo['seo_schema_type'] ?? 'LocalBusiness') === 'LocalBusiness' ? 'selected' : '' ?>>LocalBusiness</option>
                        <option value="PrintingService" <?= ($seo['seo_schema_type'] ?? '') === 'PrintingService' ? 'selected' : '' ?>>PrintingService (Imprimerie)</option>
                        <option value="Store" <?= ($seo['seo_schema_type'] ?? '') === 'Store' ? 'selected' : '' ?>>Store (Magasin)</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Nom de l'entreprise</label>
                    <input type="text" class="form-control" name="seo_schema_name" value="<?= htmlspecialchars($seo['seo_schema_name'] ?: APP_NAME) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Description</label>
                    <textarea class="form-control" name="seo_schema_description" rows="2"><?= htmlspecialchars($seo['seo_schema_description']) ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">URL du Logo</label>
                    <input type="url" class="form-control" name="seo_schema_logo_url" value="<?= htmlspecialchars($seo['seo_schema_logo_url']) ?>" placeholder="https://votre-site.com/logo.png">
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Téléphone</label>
                        <input type="text" class="form-control" name="seo_schema_phone" value="<?= htmlspecialchars($seo['seo_schema_phone'] ?: APP_PHONE) ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Gamme de prix</label>
                        <input type="text" class="form-control" name="seo_schema_price_range" value="<?= htmlspecialchars($seo['seo_schema_price_range'] ?: '$$') ?>" placeholder="$, $$, $$$">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Adresse</label>
                    <input type="text" class="form-control" name="seo_schema_address" value="<?= htmlspecialchars($seo['seo_schema_address'] ?: APP_ADDRESS) ?>">
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-semibold">Ville</label>
                        <input type="text" class="form-control" name="seo_schema_city" value="<?= htmlspecialchars($seo['seo_schema_city']) ?>" placeholder="Casablanca">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-semibold">Pays</label>
                        <input type="text" class="form-control" name="seo_schema_country" value="<?= htmlspecialchars($seo['seo_schema_country'] ?: 'MA') ?>" placeholder="MA">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-semibold">Code Postal</label>
                        <input type="text" class="form-control" name="seo_schema_postal_code" value="<?= htmlspecialchars($seo['seo_schema_postal_code']) ?>">
                    </div>
                </div>
            </div>
            <div class="card-footer bg-white">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Enregistrer</button>
            </div>
        </div>
    </form>

    <?php elseif ($active_tab === 'analytics'): ?>
    <!-- Analytics -->
    <form method="post">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="save_seo">
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-bar-chart text-primary me-2"></i>Google Analytics & Tag Manager</h5>
            </div>
            <div class="card-body">
                <div class="mb-4">
                    <label class="form-label fw-semibold">ID Google Analytics (GA4)</label>
                    <input type="text" class="form-control" name="seo_google_analytics" value="<?= htmlspecialchars($seo['seo_google_analytics']) ?>" placeholder="G-XXXXXXXXXX">
                    <div class="form-text">Votre identifiant de mesure Google Analytics 4</div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">ID Google Tag Manager</label>
                    <input type="text" class="form-control" name="seo_google_tag_manager" value="<?= htmlspecialchars($seo['seo_google_tag_manager']) ?>" placeholder="GTM-XXXXXXX">
                    <div class="form-text">Votre identifiant Google Tag Manager</div>
                </div>
            </div>
            <div class="card-footer bg-white">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Enregistrer</button>
            </div>
        </div>
    </form>

    <?php elseif ($active_tab === 'sitemap'): ?>
    <!-- Sitemap -->
    <div class="card shadow-sm">
        <div class="card-header bg-white">
            <h5 class="mb-0"><i class="bi bi-diagram-3 text-primary me-2"></i>Sitemap XML</h5>
        </div>
        <div class="card-body">
            <?php if ($sitemap_exists): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle me-2"></i>
                <strong>Sitemap existant</strong> - Dernière génération: <?= $sitemap_date ?>
                <br><a href="<?= APP_URL ?>/sitemap.xml" target="_blank" class="alert-link">Voir le sitemap</a>
            </div>
            <?php else: ?>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle me-2"></i>
                Aucun sitemap n'a été généré. Cliquez ci-dessous pour en créer un.
            </div>
            <?php endif; ?>

            <p class="text-muted">Le sitemap aide les moteurs de recherche à découvrir et indexer toutes les pages de votre site.</p>

            <h6 class="fw-bold mt-3">Pages incluses dans le sitemap:</h6>
            <ul class="list-group mb-3">
                <li class="list-group-item d-flex justify-content-between">Page d'accueil <span class="badge bg-primary">Priorité 1.0</span></li>
                <li class="list-group-item d-flex justify-content-between">Catalogue <span class="badge bg-primary">Priorité 0.9</span></li>
                <li class="list-group-item d-flex justify-content-between">
                    Catégories (<?php
                        $cat_count = $db->query("SELECT COUNT(*) FROM categories WHERE actif = 1")->fetchColumn();
                        echo $cat_count;
                    ?>)
                    <span class="badge bg-info">Priorité 0.8</span>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    Produits (<?php
                        $prod_count = $db->query("SELECT COUNT(*) FROM produits WHERE actif = 1")->fetchColumn();
                        echo $prod_count;
                    ?>)
                    <span class="badge bg-info">Priorité 0.7</span>
                </li>
                <li class="list-group-item d-flex justify-content-between">Pages statiques (Contact, Devis, À propos) <span class="badge bg-secondary">Priorité 0.6-0.8</span></li>
            </ul>

            <form method="post">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="generate_sitemap">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-arrow-clockwise me-1"></i>
                    <?= $sitemap_exists ? 'Régénérer le Sitemap' : 'Générer le Sitemap' ?>
                </button>
            </form>
        </div>
    </div>

    <?php elseif ($active_tab === 'robots'): ?>
    <!-- Robots.txt -->
    <form method="post">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="save_robots">
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-robot text-primary me-2"></i>Fichier robots.txt</h5>
            </div>
            <div class="card-body">
                <p class="text-muted">Le fichier robots.txt indique aux robots des moteurs de recherche quelles pages explorer ou ignorer.</p>
                <div class="mb-3">
                    <textarea class="form-control font-monospace" name="robots_content" rows="12" style="font-size: 13px;"><?= htmlspecialchars($robots_content) ?></textarea>
                </div>
                <div class="alert alert-info small">
                    <i class="bi bi-info-circle me-1"></i>
                    <strong>Exemple de contenu recommandé:</strong>
                    <pre class="mb-0 mt-2">User-agent: *
Allow: /
Disallow: /admin/
Disallow: /config/
Disallow: /install.php

Sitemap: <?= APP_URL ?>/sitemap.xml</pre>
                </div>
            </div>
            <div class="card-footer bg-white">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Enregistrer robots.txt</button>
            </div>
        </div>
    </form>

    <?php elseif ($active_tab === 'custom'): ?>
    <!-- Code personnalisé -->
    <form method="post">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="save_seo">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-code text-primary me-2"></i>Code Personnalisé - Head</h5>
            </div>
            <div class="card-body">
                <p class="text-muted">Ce code sera injecté dans la section <code>&lt;head&gt;</code> de chaque page.</p>
                <textarea class="form-control font-monospace" name="seo_custom_head" rows="8" style="font-size: 13px;" placeholder="<!-- Votre code personnalisé ici -->"><?= htmlspecialchars($seo['seo_custom_head']) ?></textarea>
            </div>
        </div>
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-code text-primary me-2"></i>Code Personnalisé - Body</h5>
            </div>
            <div class="card-body">
                <p class="text-muted">Ce code sera injecté juste après l'ouverture de <code>&lt;body&gt;</code>.</p>
                <textarea class="form-control font-monospace" name="seo_custom_body" rows="8" style="font-size: 13px;" placeholder="<!-- Votre code personnalisé ici -->"><?= htmlspecialchars($seo['seo_custom_body']) ?></textarea>
            </div>
            <div class="card-footer bg-white">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Enregistrer</button>
            </div>
        </div>
    </form>
    <?php endif; ?>
</div>

<script>
// Aperçu en temps réel pour les balises meta
document.addEventListener('DOMContentLoaded', function() {
    const titleInput = document.querySelector('input[name="seo_meta_title"]');
    const descInput = document.querySelector('textarea[name="seo_meta_description"]');
    const previewTitle = document.getElementById('previewTitle');
    const previewDesc = document.getElementById('previewDesc');

    if (titleInput && previewTitle) {
        titleInput.addEventListener('input', function() {
            previewTitle.textContent = this.value || '<?= APP_NAME ?> - <?= APP_TAGLINE ?>';
        });
    }
    if (descInput && previewDesc) {
        descInput.addEventListener('input', function() {
            previewDesc.textContent = this.value || 'Services d\'impression professionnels au Maroc...';
        });
    }
});
</script>
