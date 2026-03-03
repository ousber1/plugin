<?php
/**
 * BERRADI PRINT - SEO Kit
 * Google Search Console, Bing Webmaster Tools, vérification de propriété
 */

$db = getDB();

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $kit_fields = [
        // Google Search Console
        'seo_gsc_verification', 'seo_gsc_active',
        // Bing Webmaster
        'seo_bing_verification', 'seo_bing_active',
        // Yandex
        'seo_yandex_verification',
        // Pinterest
        'seo_pinterest_verification',
        // Baidu
        'seo_baidu_verification',
    ];

    foreach ($kit_fields as $field) {
        $value = $_POST[$field] ?? '';
        setParametre($field, $value);
    }

    setFlash('success', 'Paramètres SEO Kit enregistrés avec succès.');
    redirect('index.php?page=seo_kit');
}

// Charger les paramètres
$kit = [];
$kit_keys = [
    'seo_gsc_verification', 'seo_gsc_active',
    'seo_bing_verification', 'seo_bing_active',
    'seo_yandex_verification', 'seo_pinterest_verification', 'seo_baidu_verification',
];
foreach ($kit_keys as $key) {
    $kit[$key] = getParametre($key, '');
}

// Vérifier la connectivité du sitemap
$sitemap_exists = file_exists(__DIR__ . '/../../sitemap.xml');
$robots_exists = file_exists(__DIR__ . '/../../robots.txt');
?>

<div class="p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1"><i class="bi bi-tools me-2"></i>SEO Kit - Outils Webmaster</h4>
            <p class="text-muted mb-0">Connectez votre site aux outils de recherche</p>
        </div>
    </div>

    <!-- Statut rapide -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card shadow-sm border-0">
                <div class="card-body text-center p-3">
                    <i class="bi bi-google fs-2 <?= $kit['seo_gsc_verification'] ? 'text-success' : 'text-muted' ?>"></i>
                    <div class="fw-bold mt-1">Google</div>
                    <small class="<?= $kit['seo_gsc_verification'] ? 'text-success' : 'text-danger' ?>">
                        <?= $kit['seo_gsc_verification'] ? 'Configuré' : 'Non configuré' ?>
                    </small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-0">
                <div class="card-body text-center p-3">
                    <i class="bi bi-microsoft fs-2 <?= $kit['seo_bing_verification'] ? 'text-success' : 'text-muted' ?>"></i>
                    <div class="fw-bold mt-1">Bing</div>
                    <small class="<?= $kit['seo_bing_verification'] ? 'text-success' : 'text-danger' ?>">
                        <?= $kit['seo_bing_verification'] ? 'Configuré' : 'Non configuré' ?>
                    </small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-0">
                <div class="card-body text-center p-3">
                    <i class="bi bi-diagram-3 fs-2 <?= $sitemap_exists ? 'text-success' : 'text-muted' ?>"></i>
                    <div class="fw-bold mt-1">Sitemap</div>
                    <small class="<?= $sitemap_exists ? 'text-success' : 'text-danger' ?>">
                        <?= $sitemap_exists ? 'Généré' : 'Non généré' ?>
                    </small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-0">
                <div class="card-body text-center p-3">
                    <i class="bi bi-robot fs-2 <?= $robots_exists ? 'text-success' : 'text-muted' ?>"></i>
                    <div class="fw-bold mt-1">Robots.txt</div>
                    <small class="<?= $robots_exists ? 'text-success' : 'text-danger' ?>">
                        <?= $robots_exists ? 'Configuré' : 'Non configuré' ?>
                    </small>
                </div>
            </div>
        </div>
    </div>

    <form method="post">
        <?= csrfField() ?>

        <!-- Google Search Console -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-google text-danger me-2"></i>Google Search Console</h5>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="seo_gsc_active" value="1" id="gscActive" <?= $kit['seo_gsc_active'] ? 'checked' : '' ?>>
                    <label class="form-check-label fw-semibold" for="gscActive">Activer</label>
                </div>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Code de vérification Google</label>
                    <input type="text" class="form-control" name="seo_gsc_verification" value="<?= htmlspecialchars($kit['seo_gsc_verification']) ?>" placeholder="google-site-verification=XXXXXXXXXXXXXXX">
                    <div class="form-text">Le contenu de la balise meta de vérification Google (content="...")</div>
                </div>

                <div class="alert alert-light border small">
                    <h6 class="fw-bold"><i class="bi bi-question-circle me-1"></i>Comment configurer Google Search Console:</h6>
                    <ol class="mb-0">
                        <li>Allez sur <strong>search.google.com/search-console</strong></li>
                        <li>Ajoutez votre propriété (URL prefix: <code><?= APP_URL ?></code>)</li>
                        <li>Choisissez la méthode "Balise HTML"</li>
                        <li>Copiez le contenu de l'attribut <code>content</code></li>
                        <li>Collez-le dans le champ ci-dessus</li>
                        <li>Retournez sur Google Search Console et cliquez "Vérifier"</li>
                    </ol>
                </div>

                <h6 class="fw-bold mt-3">Fonctionnalités Search Console:</h6>
                <div class="row">
                    <div class="col-md-6">
                        <ul class="list-unstyled">
                            <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Performance des recherches</li>
                            <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Couverture de l'index</li>
                            <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Inspection d'URL</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <ul class="list-unstyled">
                            <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Soumission du sitemap</li>
                            <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Expérience sur la page</li>
                            <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Résultats enrichis</li>
                        </ul>
                    </div>
                </div>

                <?php if ($sitemap_exists): ?>
                <div class="alert alert-success small mt-2">
                    <i class="bi bi-check-circle me-1"></i>
                    <strong>Sitemap prêt!</strong> Soumettez votre sitemap dans Google Search Console:
                    <code><?= APP_URL ?>/sitemap.xml</code>
                </div>
                <?php else: ?>
                <div class="alert alert-warning small mt-2">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    N'oubliez pas de <a href="index.php?page=seo&tab=sitemap">générer votre sitemap</a> et de le soumettre dans Search Console.
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Bing Webmaster Tools -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-microsoft text-info me-2"></i>Bing Webmaster Tools</h5>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="seo_bing_active" value="1" id="bingActive" <?= $kit['seo_bing_active'] ? 'checked' : '' ?>>
                    <label class="form-check-label fw-semibold" for="bingActive">Activer</label>
                </div>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Code de vérification Bing</label>
                    <input type="text" class="form-control" name="seo_bing_verification" value="<?= htmlspecialchars($kit['seo_bing_verification']) ?>" placeholder="XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX">
                    <div class="form-text">Le contenu de la balise meta de vérification Bing (content="...")</div>
                </div>

                <div class="alert alert-light border small">
                    <h6 class="fw-bold"><i class="bi bi-question-circle me-1"></i>Comment configurer Bing Webmaster Tools:</h6>
                    <ol class="mb-0">
                        <li>Allez sur <strong>bing.com/webmasters</strong></li>
                        <li>Ajoutez votre site: <code><?= APP_URL ?></code></li>
                        <li>Choisissez la méthode "Meta Tag"</li>
                        <li>Copiez la valeur du content</li>
                        <li>Collez-la dans le champ ci-dessus</li>
                        <li>Retournez sur Bing et cliquez "Vérifier"</li>
                    </ol>
                </div>

                <h6 class="fw-bold mt-3">Fonctionnalités Bing Webmaster:</h6>
                <div class="row">
                    <div class="col-md-6">
                        <ul class="list-unstyled">
                            <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Rapport de performance</li>
                            <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Analyse SEO</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <ul class="list-unstyled">
                            <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Soumission d'URL</li>
                            <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Diagnostic du site</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Autres vérifications -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-shield-check text-success me-2"></i>Autres Vérifications</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Yandex Webmaster</label>
                    <input type="text" class="form-control" name="seo_yandex_verification" value="<?= htmlspecialchars($kit['seo_yandex_verification']) ?>" placeholder="Code de vérification Yandex">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Pinterest</label>
                    <input type="text" class="form-control" name="seo_pinterest_verification" value="<?= htmlspecialchars($kit['seo_pinterest_verification']) ?>" placeholder="Code de vérification Pinterest">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Baidu</label>
                    <input type="text" class="form-control" name="seo_baidu_verification" value="<?= htmlspecialchars($kit['seo_baidu_verification']) ?>" placeholder="Code de vérification Baidu">
                </div>
            </div>
        </div>

        <!-- Checklist SEO -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-clipboard-check text-warning me-2"></i>Checklist SEO</h5>
            </div>
            <div class="card-body">
                <?php
                $checklist = [
                    ['done' => !empty($kit['seo_gsc_verification']), 'text' => 'Google Search Console configuré'],
                    ['done' => !empty($kit['seo_bing_verification']), 'text' => 'Bing Webmaster Tools configuré'],
                    ['done' => $sitemap_exists, 'text' => 'Sitemap XML généré'],
                    ['done' => $robots_exists, 'text' => 'Fichier robots.txt configuré'],
                    ['done' => !empty(getParametre('seo_meta_title')), 'text' => 'Balise meta title définie'],
                    ['done' => !empty(getParametre('seo_meta_description')), 'text' => 'Balise meta description définie'],
                    ['done' => !empty(getParametre('seo_og_title')), 'text' => 'Open Graph configuré'],
                    ['done' => !empty(getParametre('seo_schema_name')), 'text' => 'Données structurées Schema.org'],
                    ['done' => !empty(getParametre('seo_google_analytics')), 'text' => 'Google Analytics connecté'],
                    ['done' => !empty(getParametre('pixel_meta_id')), 'text' => 'Meta Pixel installé'],
                ];
                $score = 0;
                foreach ($checklist as $item) {
                    if ($item['done']) $score++;
                }
                $pct = round(($score / count($checklist)) * 100);
                ?>
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="fw-bold">Score SEO</span>
                        <span class="fw-bold"><?= $pct ?>%</span>
                    </div>
                    <div class="progress" style="height: 10px;">
                        <div class="progress-bar <?= $pct >= 80 ? 'bg-success' : ($pct >= 50 ? 'bg-warning' : 'bg-danger') ?>" style="width: <?= $pct ?>%"></div>
                    </div>
                </div>
                <?php foreach ($checklist as $item): ?>
                <div class="d-flex align-items-center py-2 border-bottom">
                    <i class="bi <?= $item['done'] ? 'bi-check-circle-fill text-success' : 'bi-circle text-muted' ?> me-3 fs-5"></i>
                    <span class="<?= $item['done'] ? '' : 'text-muted' ?>"><?= $item['text'] ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <button type="submit" class="btn btn-primary btn-lg">
            <i class="bi bi-check-lg me-1"></i>Enregistrer la Configuration
        </button>
    </form>
</div>
