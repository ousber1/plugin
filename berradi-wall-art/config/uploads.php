<?php

declare(strict_types=1);

use App\Core\Env;

return [
    // Customer photos (private personal data) — stored outside the web root.
    'private_dir' => dirname(__DIR__) . '/storage/private_uploads',
    'preview_dir' => dirname(__DIR__) . '/storage/previews',
    'max_mb' => (int) Env::get('UPLOAD_MAX_MB', 25),
    'customer_mimes' => [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ],
    // Public catalogue images (products, categories, logo, banners).
    'public_mimes' => [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ],
    'public_max_mb' => 8,
    'preview_max_px' => 1600,
    'thumb_max_px' => 400,
];
