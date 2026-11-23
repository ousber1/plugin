<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Resizes and compresses uploaded images (GD). Large photos are scaled to the
 * configured maximum width and saved as WebP, which is usually 60–80% lighter.
 * Falls back to storing the original when GD is missing or anything fails.
 */
class ImageOptimizer
{
    public static function available(): bool
    {
        return function_exists('imagecreatetruecolor') && function_exists('imagewebp');
    }

    public static function store(UploadedFile $file, string $dir): string
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension());
        if (! Settings::get('image_optimize') || ! self::available() || ! in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            return $file->store($dir, 'public');
        }
        try {
            $data = self::encode($file->getRealPath(), $ext, true);
            if ($data !== null && strlen($data) < $file->getSize()) {
                $path = trim($dir, '/').'/'.Str::random(40).'.webp';
                Storage::disk('public')->put($path, $data);

                return $path;
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $file->store($dir, 'public');
    }

    /**
     * Re-compress an existing file on the public disk in place (same name and format,
     * so links keep working). Returns bytes saved.
     */
    public static function optimizeExisting(string $path): int
    {
        $disk = Storage::disk('public');
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (! self::available() || ! in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true) || ! $disk->exists($path)) {
            return 0;
        }
        $full = $disk->path($path);
        $before = filesize($full);
        if ($before < 60 * 1024) {
            return 0; // already light
        }
        try {
            $data = self::encode($full, $ext, false);
        } catch (\Throwable) {
            return 0;
        }
        if ($data === null || strlen($data) >= $before * 0.9) {
            return 0;
        }
        file_put_contents($full, $data);

        return $before - strlen($data);
    }

    private static function encode(string $source, string $ext, bool $toWebp): ?string
    {
        $info = @getimagesize($source);
        if (! $info || $info[0] * $info[1] > 40_000_000) {
            return null; // unreadable or too large to process safely
        }
        $img = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($source),
            IMAGETYPE_PNG => @imagecreatefrompng($source),
            IMAGETYPE_WEBP => @imagecreatefromwebp($source),
            default => false,
        };
        if (! $img) {
            return null;
        }
        $max = max(400, min(4000, (int) Settings::get('image_max_width', 1600)));
        $quality = max(50, min(95, (int) Settings::get('image_quality', 82)));
        [$w, $h] = [imagesx($img), imagesy($img)];
        if ($w > $max) {
            $nh = (int) round($h * $max / $w);
            $resized = imagecreatetruecolor($max, $nh);
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            imagecopyresampled($resized, $img, 0, 0, 0, 0, $max, $nh, $w, $h);
            imagedestroy($img);
            $img = $resized;
        } else {
            imagealphablending($img, false);
            imagesavealpha($img, true);
        }
        ob_start();
        $format = $toWebp ? 'webp' : ($ext === 'jpeg' ? 'jpg' : $ext);
        match ($format) {
            'webp' => imagewebp($img, null, $quality),
            'png' => imagepng($img, null, 8),
            default => imagejpeg($img, null, $quality),
        };
        $data = ob_get_clean();
        imagedestroy($img);

        return $data ?: null;
    }
}
