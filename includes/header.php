<?php if (!defined('APP_NAME')) { require_once __DIR__ . '/../config/app.php'; require_once __DIR__ . '/../config/database.php'; require_once __DIR__ . '/../includes/functions.php'; } ?>
<?php
// Charger les paramètres SEO
$_seo_title = getParametre('seo_meta_title', APP_NAME . ' - ' . APP_TAGLINE);
$_seo_description = getParametre('seo_meta_description', '');
$_seo_keywords = getParametre('seo_meta_keywords', '');
$_seo_canonical = getParametre('seo_canonical_url', APP_URL);
$_seo_robots_index = getParametre('seo_robots_index', 'index');
$_seo_robots_follow = getParametre('seo_robots_follow', 'follow');
$_seo_og_title = getParametre('seo_og_title', $_seo_title);
$_seo_og_description = getParametre('seo_og_description', $_seo_description);
$_seo_og_image = getParametre('seo_og_image', '');
$_seo_twitter_card = getParametre('seo_twitter_card', 'summary');
$_seo_ga_id = getParametre('seo_google_analytics', '');
$_seo_gtm_id = getParametre('seo_google_tag_manager', '');
$_seo_custom_head = getParametre('seo_custom_head', '');
$_seo_custom_body = getParametre('seo_custom_body', '');
$_seo_gsc = getParametre('seo_gsc_verification', '');
$_seo_bing = getParametre('seo_bing_verification', '');
$_seo_yandex = getParametre('seo_yandex_verification', '');
$_seo_pinterest = getParametre('seo_pinterest_verification', '');
$_schema_type = getParametre('seo_schema_type', 'LocalBusiness');
$_schema_name = getParametre('seo_schema_name', APP_NAME);
$_schema_desc = getParametre('seo_schema_description', '');
$_schema_phone = getParametre('seo_schema_phone', APP_PHONE);
$_schema_logo = getParametre('seo_schema_logo_url', '');
$_schema_address = getParametre('seo_schema_address', APP_ADDRESS);
$_schema_city = getParametre('seo_schema_city', '');
$_schema_country = getParametre('seo_schema_country', 'MA');
$_schema_postal = getParametre('seo_schema_postal_code', '');
$_schema_price = getParametre('seo_schema_price_range', '');
$_px_meta_id = getParametre('pixel_meta_id', '');
$_px_meta_active = getParametre('pixel_meta_active', '');
$_px_tiktok_id = getParametre('pixel_tiktok_id', '');
$_px_tiktok_active = getParametre('pixel_tiktok_active', '');
$_px_snap_id = getParametre('pixel_snap_id', '');
$_px_snap_active = getParametre('pixel_snap_active', '');

// Header/Footer settings
$_site_logo = getParametre('site_logo', '');
$_site_favicon = getParametre('site_favicon', '');
$_header_bg = getParametre('header_bg_color', '#ffffff');
$_header_text = getParametre('header_text_color', '#212529');
$_announcement_active = getParametre('header_announcement_active', '');
$_announcement_text = getParametre('header_announcement', '');
$_whatsapp_number = getParametre('whatsapp_number', '');
$_whatsapp_clean = str_replace(['+', ' ', '-', '(', ')'], '', $_whatsapp_number ?: APP_PHONE);
?>
<!DOCTYPE html>
<html lang="fr" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($_seo_title) ?></title>

    <?php if ($_site_favicon): ?>
    <link rel="icon" href="<?= htmlspecialchars($_site_favicon) ?>">
    <?php endif; ?>

    <?php if ($_seo_description): ?>
    <meta name="description" content="<?= htmlspecialchars($_seo_description) ?>">
    <?php endif; ?>
    <?php if ($_seo_keywords): ?>
    <meta name="keywords" content="<?= htmlspecialchars($_seo_keywords) ?>">
    <?php endif; ?>
    <meta name="robots" content="<?= $_seo_robots_index ?>, <?= $_seo_robots_follow ?>">
    <?php if ($_seo_canonical): ?>
    <link rel="canonical" href="<?= htmlspecialchars($_seo_canonical) ?>">
    <?php endif; ?>

    <!-- Open Graph -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= htmlspecialchars($_seo_og_title) ?>">
    <?php if ($_seo_og_description): ?><meta property="og:description" content="<?= htmlspecialchars($_seo_og_description) ?>"><?php endif; ?>
    <?php if ($_seo_og_image): ?><meta property="og:image" content="<?= htmlspecialchars($_seo_og_image) ?>"><?php endif; ?>
    <meta property="og:url" content="<?= htmlspecialchars($_seo_canonical ?: APP_URL) ?>">
    <meta property="og:site_name" content="<?= htmlspecialchars(APP_NAME) ?>">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="<?= htmlspecialchars($_seo_twitter_card) ?>">
    <meta name="twitter:title" content="<?= htmlspecialchars($_seo_og_title) ?>">
    <?php if ($_seo_og_description): ?><meta name="twitter:description" content="<?= htmlspecialchars($_seo_og_description) ?>"><?php endif; ?>
    <?php if ($_seo_og_image): ?><meta name="twitter:image" content="<?= htmlspecialchars($_seo_og_image) ?>"><?php endif; ?>

    <!-- Webmaster Verification -->
    <?php if ($_seo_gsc): ?><meta name="google-site-verification" content="<?= htmlspecialchars($_seo_gsc) ?>"><?php endif; ?>
    <?php if ($_seo_bing): ?><meta name="msvalidate.01" content="<?= htmlspecialchars($_seo_bing) ?>"><?php endif; ?>
    <?php if ($_seo_yandex): ?><meta name="yandex-verification" content="<?= htmlspecialchars($_seo_yandex) ?>"><?php endif; ?>
    <?php if ($_seo_pinterest): ?><meta name="p:domain_verify" content="<?= htmlspecialchars($_seo_pinterest) ?>"><?php endif; ?>

    <?php if ($_seo_gtm_id): ?>
    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','<?= htmlspecialchars($_seo_gtm_id) ?>');</script>
    <?php endif; ?>

    <?php if ($_seo_ga_id): ?>
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?= htmlspecialchars($_seo_ga_id) ?>"></script>
    <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','<?= htmlspecialchars($_seo_ga_id) ?>');</script>
    <?php endif; ?>

    <?php if ($_px_meta_active && $_px_meta_id): ?>
    <script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init','<?= htmlspecialchars($_px_meta_id) ?>');fbq('track','PageView');</script>
    <noscript><img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id=<?= htmlspecialchars($_px_meta_id) ?>&ev=PageView&noscript=1"/></noscript>
    <?php endif; ?>

    <?php if ($_px_tiktok_active && $_px_tiktok_id): ?>
    <script>!function(w,d,t){w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=["page","track","identify","instances","debug","on","off","once","ready","alias","group","enableCookie","disableCookie"],ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.instance=function(t){for(var e=ttq._i[t]||[],n=0;n<ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);return e};ttq.load=function(e,n){var i="https://analytics.tiktok.com/i18n/pixel/events.js";ttq._i=ttq._i||{},ttq._i[e]=[],ttq._i[e]._u=i,ttq._t=ttq._t||{},ttq._t[e]=+new Date,ttq._o=ttq._o||{},ttq._o[e]=n||{};var o=document.createElement("script");o.type="text/javascript",o.async=!0,o.src=i+"?sdkid="+e+"&lib="+t;var a=document.getElementsByTagName("script")[0];a.parentNode.insertBefore(o,a)};ttq.load('<?= htmlspecialchars($_px_tiktok_id) ?>');ttq.page();}(window,document,'ttq');</script>
    <?php endif; ?>

    <?php if ($_px_snap_active && $_px_snap_id): ?>
    <script>(function(e,t,n){if(e.snaptr)return;var a=e.snaptr=function(){a.handleRequest?a.handleRequest.apply(a,arguments):a.queue.push(arguments)};a.queue=[];var s='script';r=t.createElement(s);r.async=!0;r.src=n;var u=t.getElementsByTagName(s)[0];u.parentNode.insertBefore(r,u);})(window,document,'https://sc-static.net/scevent.min.js');snaptr('init','<?= htmlspecialchars($_px_snap_id) ?>',{});snaptr('track','PAGE_VIEW');</script>
    <?php endif; ?>

    <?php if ($_schema_name): ?>
    <script type="application/ld+json">
    {"@context":"https://schema.org","@type":"<?= htmlspecialchars($_schema_type) ?>","name":"<?= htmlspecialchars($_schema_name) ?>",<?php if ($_schema_desc): ?>"description":"<?= htmlspecialchars($_schema_desc) ?>",<?php endif; ?><?php if ($_schema_logo): ?>"logo":"<?= htmlspecialchars($_schema_logo) ?>",<?php endif; ?>"telephone":"<?= htmlspecialchars($_schema_phone) ?>","url":"<?= htmlspecialchars(APP_URL) ?>",<?php if ($_schema_price): ?>"priceRange":"<?= htmlspecialchars($_schema_price) ?>",<?php endif; ?>"address":{"@type":"PostalAddress","streetAddress":"<?= htmlspecialchars($_schema_address) ?>",<?php if ($_schema_city): ?>"addressLocality":"<?= htmlspecialchars($_schema_city) ?>",<?php endif; ?><?php if ($_schema_postal): ?>"postalCode":"<?= htmlspecialchars($_schema_postal) ?>",<?php endif; ?>"addressCountry":"<?= htmlspecialchars($_schema_country) ?>"}}
    </script>
    <?php endif; ?>

    <?php if ($_seo_custom_head): ?><?= $_seo_custom_head ?><?php endif; ?>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <?php if ($_header_bg !== '#ffffff' || $_header_text !== '#212529'): ?>
    <style>
        .navbar-main-custom { background-color: <?= $_header_bg ?> !important; }
        .navbar-main-custom .nav-link, .navbar-main-custom .navbar-brand { color: <?= $_header_text ?> !important; }
        .navbar-main-custom .nav-link:hover { opacity: 0.8; }
    </style>
    <?php endif; ?>
</head>
<body>
    <?php if ($_seo_gtm_id): ?>
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=<?= htmlspecialchars($_seo_gtm_id) ?>" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    <?php endif; ?>
    <?php if ($_seo_custom_body): ?><?= $_seo_custom_body ?><?php endif; ?>

    <!-- Announcement Banner -->
    <?php if ($_announcement_active && $_announcement_text): ?>
    <div class="announcement-bar bg-warning text-dark text-center py-2 small fw-semibold">
        <div class="container">
            <i class="bi bi-megaphone me-2"></i><?= htmlspecialchars($_announcement_text) ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Top Bar -->
    <div class="top-bar bg-dark text-white py-2 d-none d-md-block">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <small>
                        <i class="bi bi-telephone-fill me-1"></i> <?= APP_PHONE ?>
                        <span class="mx-2">|</span>
                        <i class="bi bi-envelope-fill me-1"></i> <?= APP_EMAIL ?>
                    </small>
                </div>
                <div class="col-md-6 text-end">
                    <small>
                        <i class="bi bi-clock me-1"></i> Lundi - Samedi: 9h00 - 19h00
                        <span class="mx-2">|</span>
                        <i class="bi bi-geo-alt-fill me-1"></i> Maroc
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation principale -->
    <nav class="navbar navbar-expand-lg shadow-sm sticky-top <?= ($_header_bg !== '#ffffff') ? 'navbar-main-custom' : 'navbar-light bg-white' ?>">
        <div class="container">
            <a class="navbar-brand fw-bold d-flex align-items-center" href="index.php">
                <?php if ($_site_logo && file_exists($_site_logo)): ?>
                <img src="<?= htmlspecialchars($_site_logo) ?>" alt="<?= APP_NAME ?>" style="max-height:45px;" class="me-2">
                <?php else: ?>
                <i class="bi bi-printer-fill text-primary me-2 fs-4"></i>
                <?php endif; ?>
                <span class="brand-text"><?= APP_NAME ?></span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarMain">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link <?= ($page ?? '') === 'accueil' ? 'active' : '' ?>" href="index.php">
                            <i class="bi bi-house me-1"></i> Accueil
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?= ($page ?? '') === 'catalogue' ? 'active' : '' ?>" href="#" data-bs-toggle="dropdown">
                            <i class="bi bi-grid me-1"></i> Nos Services
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="index.php?page=catalogue">Tous les services</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <?php
                            try {
                                $db = getDB();
                                $cats = $db->query("SELECT * FROM categories WHERE actif = 1 ORDER BY ordre")->fetchAll();
                                foreach ($cats as $cat):
                                    $cat_nav_icon = '';
                                    if (!empty($cat['image']) && file_exists('uploads/categories/' . $cat['image'])) {
                                        $cat_nav_icon = 'uploads/categories/' . $cat['image'];
                                    }
                            ?>
                                    <li><a class="dropdown-item d-flex align-items-center" href="index.php?page=catalogue&cat=<?= $cat['slug'] ?>">
                                        <?php if ($cat_nav_icon): ?>
                                        <img src="<?= $cat_nav_icon ?>" alt="" style="width:20px;height:20px;object-fit:contain;" class="me-2">
                                        <?php else: ?>
                                        <i class="<?= $cat['icone'] ?> me-2"></i>
                                        <?php endif; ?>
                                        <?= htmlspecialchars($cat['nom']) ?>
                                    </a></li>
                            <?php endforeach;
                            } catch(Exception $e) {} ?>
                        </ul>
                    </li>
                    <?php
                    // Dynamic custom pages
                    try {
                        $db = getDB();
                        $custom_pages = $db->query("SELECT slug, titre FROM pages WHERE actif = 1 AND show_in_menu = 1 ORDER BY ordre LIMIT 5")->fetchAll();
                        foreach ($custom_pages as $cp): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= ($page ?? '') === $cp['slug'] ? 'active' : '' ?>" href="index.php?page=<?= $cp['slug'] ?>">
                            <?= htmlspecialchars($cp['titre']) ?>
                        </a>
                    </li>
                    <?php endforeach;
                    } catch(Exception $e) {} ?>
                    <li class="nav-item">
                        <a class="nav-link <?= ($page ?? '') === 'devis' ? 'active' : '' ?>" href="index.php?page=devis">
                            <i class="bi bi-file-text me-1"></i> Devis
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= ($page ?? '') === 'contact' ? 'active' : '' ?>" href="index.php?page=contact">
                            <i class="bi bi-chat-dots me-1"></i> Contact
                        </a>
                    </li>
                </ul>
                <div class="d-flex align-items-center gap-2">
                    <a href="index.php?page=panier" class="btn btn-outline-primary position-relative">
                        <i class="bi bi-cart3"></i>
                        <span class="badge bg-danger position-absolute top-0 start-100 translate-middle badge-panier" style="<?= nombreArticlesPanier() > 0 ? '' : 'display:none' ?>">
                            <?= nombreArticlesPanier() ?>
                        </span>
                    </a>
                    <a href="https://wa.me/<?= $_whatsapp_clean ?>" class="btn btn-success btn-sm" target="_blank">
                        <i class="bi bi-whatsapp me-1"></i> <span class="d-none d-md-inline">WhatsApp</span>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <?php $flash = getFlash(); if ($flash): ?>
    <div class="container mt-3">
        <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show">
            <?= $flash['message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    </div>
    <?php endif; ?>

    <main>
