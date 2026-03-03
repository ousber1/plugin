<?php
/**
 * BERRADI PRINT - Configuration Générale
 */

// Informations de l'entreprise
define('APP_NAME', 'BERRADI PRINT');
define('APP_TAGLINE', 'Services d\'Impression Professionnels');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost/plugin');
define('APP_EMAIL', 'contact@berradiprint.ma');
define('APP_PHONE', '+212 6XX-XXXXXX');
define('APP_ADDRESS', 'Maroc');
define('APP_CURRENCY', 'MAD');
define('APP_CURRENCY_SYMBOL', 'DH');
define('APP_LANG', 'fr');
define('APP_TIMEZONE', 'Africa/Casablanca');

// Paramètres de livraison
define('DELIVERY_METHOD', 'cash_on_delivery');
define('FREE_DELIVERY_MIN', 500); // Livraison gratuite à partir de 500 DH
define('DELIVERY_FEE', 30); // Frais de livraison par défaut en DH

// Paramètres d'upload
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 50 * 1024 * 1024); // 50 MB
define('ALLOWED_EXTENSIONS', ['pdf', 'jpg', 'jpeg', 'png', 'ai', 'psd', 'eps', 'svg', 'doc', 'docx', 'tiff', 'tif']);

// Paramètres de session
define('SESSION_LIFETIME', 3600 * 24); // 24 heures

// TVA Maroc
define('TAX_RATE', 0.20); // 20% TVA

date_default_timezone_set(APP_TIMEZONE);
