<?php
/**
 * Shared <head>. Accepts either $seo (from SeoService::page) or $title / $metaDescription / $noindex.
 * Extra: $extraCss (list<string>).
 */
$siteName = (string) setting('company_name', 'BERRADI WALL ART');
$seo = is_array($seo ?? null) ? $seo : [];
$rawTitle = (string) ($seo['title'] ?? $title ?? '');
$pageTitle = $rawTitle !== '' ? $rawTitle . ' — ' . $siteName : (string) setting('seo_meta_title', $siteName);
$description = (string) ($seo['description'] ?? $metaDescription ?? setting('seo_meta_description', ''));
$robotsNo = !empty($seo['noindex']) || !empty($noindex);
?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<title><?= e($pageTitle) ?></title>
<?php if ($description !== ''): ?>
<meta name="description" content="<?= e($description) ?>">
<?php endif; ?>
<?php if ($robotsNo): ?>
<meta name="robots" content="noindex, follow">
<?php endif; ?>
<?php if (!empty($seo['canonical'])): ?>
<link rel="canonical" href="<?= e($seo['canonical']) ?>">
<meta property="og:url" content="<?= e($seo['canonical']) ?>">
<?php endif; ?>
<?php if ($seo !== []): ?>
<meta property="og:site_name" content="<?= e($siteName) ?>">
<meta property="og:type" content="<?= e($seo['og_type'] ?? 'website') ?>">
<meta property="og:title" content="<?= e($rawTitle !== '' ? $rawTitle : $pageTitle) ?>">
<meta property="og:locale" content="<?= locale() === 'ar' ? 'ar_MA' : 'fr_MA' ?>">
<?php if ($description !== ''): ?><meta property="og:description" content="<?= e($description) ?>"><?php endif; ?>
<?php if (!empty($seo['og_image'])): ?>
<meta property="og:image" content="<?= e($seo['og_image']) ?>">
<meta name="twitter:card" content="summary_large_image">
<?php endif; ?>
<?php foreach (($seo['jsonLd'] ?? []) as $ld): ?>
<script type="application/ld+json"><?= \App\Services\SeoService::encode($ld) ?></script>
<?php endforeach; ?>
<?php endif; ?>
<meta name="theme-color" content="#171717">
<link rel="icon" href="<?= e(url('favicon.svg')) ?>" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;1,400;1,500&family=Inter:wght@400;500;600;700&family=Tajawal:wght@400;500;700&display=swap">
<link rel="stylesheet" href="<?= e(asset(is_rtl() ? 'vendor/bootstrap/bootstrap.rtl.min.css' : 'vendor/bootstrap/bootstrap.min.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<?php foreach (($extraCss ?? []) as $css): ?>
<link rel="stylesheet" href="<?= e(asset($css)) ?>">
<?php endforeach; ?>
