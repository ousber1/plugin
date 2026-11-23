<?php

namespace App\Support;

/**
 * Public URLs for uploaded images (course thumbnails, products, logos...).
 * Built from the current request host (not APP_URL), so a slightly wrong
 * APP_URL (http/https, www, /public) never breaks images. The /storage
 * route below serves the file when the hosting does not allow the
 * public/storage symbolic link.
 */
class Media
{
    public static function url(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return asset('storage/'.ltrim(str_replace('\\', '/', $path), '/'));
    }
}
