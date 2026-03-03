<?php
/**
 * BERRADI PRINT - Gestion des Pixels de Tracking
 * Meta (Facebook), TikTok Ads, Snapchat Ads
 */

$db = getDB();

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $pixel_fields = [
        // Meta / Facebook
        'pixel_meta_id', 'pixel_meta_token', 'pixel_meta_active',
        'pixel_meta_pageview', 'pixel_meta_viewcontent', 'pixel_meta_addtocart',
        'pixel_meta_purchase', 'pixel_meta_lead', 'pixel_meta_contact',
        // TikTok
        'pixel_tiktok_id', 'pixel_tiktok_active',
        'pixel_tiktok_pageview', 'pixel_tiktok_viewcontent', 'pixel_tiktok_addtocart',
        'pixel_tiktok_purchase', 'pixel_tiktok_contact',
        // Snapchat
        'pixel_snap_id', 'pixel_snap_active',
        'pixel_snap_pageview', 'pixel_snap_viewcontent', 'pixel_snap_addtocart',
        'pixel_snap_purchase',
    ];

    foreach ($pixel_fields as $field) {
        $value = $_POST[$field] ?? '';
        setParametre($field, $value);
    }

    setFlash('success', 'Pixels de tracking enregistrés avec succès.');
    redirect('index.php?page=pixels');
}

// Charger les paramètres
$px = [];
$px_keys = [
    'pixel_meta_id', 'pixel_meta_token', 'pixel_meta_active',
    'pixel_meta_pageview', 'pixel_meta_viewcontent', 'pixel_meta_addtocart',
    'pixel_meta_purchase', 'pixel_meta_lead', 'pixel_meta_contact',
    'pixel_tiktok_id', 'pixel_tiktok_active',
    'pixel_tiktok_pageview', 'pixel_tiktok_viewcontent', 'pixel_tiktok_addtocart',
    'pixel_tiktok_purchase', 'pixel_tiktok_contact',
    'pixel_snap_id', 'pixel_snap_active',
    'pixel_snap_pageview', 'pixel_snap_viewcontent', 'pixel_snap_addtocart',
    'pixel_snap_purchase',
];
foreach ($px_keys as $key) {
    $px[$key] = getParametre($key, '');
}

$active_tab = $_GET['tab'] ?? 'meta';
?>

<div class="p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1"><i class="bi bi-broadcast me-2"></i>Pixels de Tracking</h4>
            <p class="text-muted mb-0">Gérez vos pixels publicitaires pour le suivi des conversions</p>
        </div>
    </div>

    <!-- Onglets -->
    <ul class="nav nav-tabs mb-4" role="tablist">
        <li class="nav-item">
            <a class="nav-link <?= $active_tab === 'meta' ? 'active' : '' ?>" href="index.php?page=pixels&tab=meta">
                <i class="bi bi-meta me-1"></i>Meta (Facebook)
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $active_tab === 'tiktok' ? 'active' : '' ?>" href="index.php?page=pixels&tab=tiktok">
                <i class="bi bi-tiktok me-1"></i>TikTok Ads
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $active_tab === 'snapchat' ? 'active' : '' ?>" href="index.php?page=pixels&tab=snapchat">
                <i class="bi bi-snapchat me-1"></i>Snapchat Ads
            </a>
        </li>
    </ul>

    <form method="post">
        <?= csrfField() ?>

        <?php if ($active_tab === 'meta'): ?>
        <!-- Meta / Facebook Pixel -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-meta text-primary me-2"></i>Meta Pixel (Facebook)</h5>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="pixel_meta_active" value="1" id="metaActive" <?= $px['pixel_meta_active'] ? 'checked' : '' ?>>
                    <label class="form-check-label fw-semibold" for="metaActive">Activer</label>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Pixel ID</label>
                        <input type="text" class="form-control" name="pixel_meta_id" value="<?= htmlspecialchars($px['pixel_meta_id']) ?>" placeholder="123456789012345">
                        <div class="form-text">Trouvez-le dans Meta Business Suite > Événements</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Access Token (Conversions API)</label>
                        <input type="text" class="form-control" name="pixel_meta_token" value="<?= htmlspecialchars($px['pixel_meta_token']) ?>" placeholder="EAAxxxxxx...">
                        <div class="form-text">Optionnel - Pour l'API Conversions serveur</div>
                    </div>
                </div>

                <h6 class="fw-bold mt-3 mb-3">Événements à suivre:</h6>
                <div class="row">
                    <div class="col-md-4 mb-2">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="pixel_meta_pageview" value="1" id="metaPageView" <?= $px['pixel_meta_pageview'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="metaPageView">PageView (Vue de page)</label>
                        </div>
                    </div>
                    <div class="col-md-4 mb-2">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="pixel_meta_viewcontent" value="1" id="metaViewContent" <?= $px['pixel_meta_viewcontent'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="metaViewContent">ViewContent (Vue produit)</label>
                        </div>
                    </div>
                    <div class="col-md-4 mb-2">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="pixel_meta_addtocart" value="1" id="metaAddToCart" <?= $px['pixel_meta_addtocart'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="metaAddToCart">AddToCart (Ajout panier)</label>
                        </div>
                    </div>
                    <div class="col-md-4 mb-2">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="pixel_meta_purchase" value="1" id="metaPurchase" <?= $px['pixel_meta_purchase'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="metaPurchase">Purchase (Achat)</label>
                        </div>
                    </div>
                    <div class="col-md-4 mb-2">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="pixel_meta_lead" value="1" id="metaLead" <?= $px['pixel_meta_lead'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="metaLead">Lead (Devis)</label>
                        </div>
                    </div>
                    <div class="col-md-4 mb-2">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="pixel_meta_contact" value="1" id="metaContact" <?= $px['pixel_meta_contact'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="metaContact">Contact</label>
                        </div>
                    </div>
                </div>

                <div class="alert alert-info mt-3 small">
                    <i class="bi bi-info-circle me-1"></i>
                    <strong>Comment obtenir votre Pixel ID:</strong>
                    <ol class="mb-0 mt-1">
                        <li>Allez sur <strong>business.facebook.com</strong></li>
                        <li>Menu > Paramètres de l'entreprise > Sources de données > Pixels</li>
                        <li>Créez un pixel ou sélectionnez un pixel existant</li>
                        <li>Copiez l'identifiant du pixel</li>
                    </ol>
                </div>
            </div>
        </div>

        <?php elseif ($active_tab === 'tiktok'): ?>
        <!-- TikTok Pixel -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-tiktok me-2"></i>TikTok Pixel</h5>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="pixel_tiktok_active" value="1" id="tiktokActive" <?= $px['pixel_tiktok_active'] ? 'checked' : '' ?>>
                    <label class="form-check-label fw-semibold" for="tiktokActive">Activer</label>
                </div>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">TikTok Pixel ID</label>
                    <input type="text" class="form-control" name="pixel_tiktok_id" value="<?= htmlspecialchars($px['pixel_tiktok_id']) ?>" placeholder="XXXXXXXXXXXXXXXXX">
                    <div class="form-text">Trouvez-le dans TikTok Ads Manager > Actifs > Événements</div>
                </div>

                <h6 class="fw-bold mt-3 mb-3">Événements à suivre:</h6>
                <div class="row">
                    <div class="col-md-4 mb-2">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="pixel_tiktok_pageview" value="1" id="ttPageView" <?= $px['pixel_tiktok_pageview'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="ttPageView">PageView</label>
                        </div>
                    </div>
                    <div class="col-md-4 mb-2">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="pixel_tiktok_viewcontent" value="1" id="ttViewContent" <?= $px['pixel_tiktok_viewcontent'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="ttViewContent">ViewContent</label>
                        </div>
                    </div>
                    <div class="col-md-4 mb-2">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="pixel_tiktok_addtocart" value="1" id="ttAddToCart" <?= $px['pixel_tiktok_addtocart'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="ttAddToCart">AddToCart</label>
                        </div>
                    </div>
                    <div class="col-md-4 mb-2">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="pixel_tiktok_purchase" value="1" id="ttPurchase" <?= $px['pixel_tiktok_purchase'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="ttPurchase">Purchase (Achat)</label>
                        </div>
                    </div>
                    <div class="col-md-4 mb-2">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="pixel_tiktok_contact" value="1" id="ttContact" <?= $px['pixel_tiktok_contact'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="ttContact">Contact</label>
                        </div>
                    </div>
                </div>

                <div class="alert alert-info mt-3 small">
                    <i class="bi bi-info-circle me-1"></i>
                    <strong>Comment obtenir votre TikTok Pixel ID:</strong>
                    <ol class="mb-0 mt-1">
                        <li>Allez sur <strong>ads.tiktok.com</strong></li>
                        <li>Actifs > Événements > Gérer (Web)</li>
                        <li>Créez un pixel TikTok ou sélectionnez un existant</li>
                        <li>Copiez l'identifiant du pixel</li>
                    </ol>
                </div>
            </div>
        </div>

        <?php elseif ($active_tab === 'snapchat'): ?>
        <!-- Snapchat Pixel -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-snapchat me-2"></i>Snapchat Pixel</h5>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="pixel_snap_active" value="1" id="snapActive" <?= $px['pixel_snap_active'] ? 'checked' : '' ?>>
                    <label class="form-check-label fw-semibold" for="snapActive">Activer</label>
                </div>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Snapchat Pixel ID</label>
                    <input type="text" class="form-control" name="pixel_snap_id" value="<?= htmlspecialchars($px['pixel_snap_id']) ?>" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx">
                    <div class="form-text">Trouvez-le dans Snapchat Ads Manager > Événements</div>
                </div>

                <h6 class="fw-bold mt-3 mb-3">Événements à suivre:</h6>
                <div class="row">
                    <div class="col-md-4 mb-2">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="pixel_snap_pageview" value="1" id="snapPageView" <?= $px['pixel_snap_pageview'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="snapPageView">PAGE_VIEW</label>
                        </div>
                    </div>
                    <div class="col-md-4 mb-2">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="pixel_snap_viewcontent" value="1" id="snapViewContent" <?= $px['pixel_snap_viewcontent'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="snapViewContent">VIEW_CONTENT</label>
                        </div>
                    </div>
                    <div class="col-md-4 mb-2">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="pixel_snap_addtocart" value="1" id="snapAddToCart" <?= $px['pixel_snap_addtocart'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="snapAddToCart">ADD_CART</label>
                        </div>
                    </div>
                    <div class="col-md-4 mb-2">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="pixel_snap_purchase" value="1" id="snapPurchase" <?= $px['pixel_snap_purchase'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="snapPurchase">PURCHASE</label>
                        </div>
                    </div>
                </div>

                <div class="alert alert-info mt-3 small">
                    <i class="bi bi-info-circle me-1"></i>
                    <strong>Comment obtenir votre Snapchat Pixel ID:</strong>
                    <ol class="mb-0 mt-1">
                        <li>Allez sur <strong>ads.snapchat.com</strong></li>
                        <li>Événements Manager > Pixel</li>
                        <li>Créez un pixel Snap ou sélectionnez un existant</li>
                        <li>Copiez l'identifiant du pixel</li>
                    </ol>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <button type="submit" class="btn btn-primary btn-lg">
            <i class="bi bi-check-lg me-1"></i>Enregistrer les Pixels
        </button>
    </form>
</div>
