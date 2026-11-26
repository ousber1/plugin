<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;

/**
 * Secure image upload handling.
 *  - validates PHP upload status, size, real MIME type (finfo) and image structure (getimagesize)
 *  - randomized file names, never trusts client file names/extensions
 *  - re-encodes with GD when available (strips EXIF/GPS metadata and any embedded payload)
 *  - generates resized previews within configured limits (lightweight, shared-hosting friendly)
 */
final class ImageUploadService
{
    /**
     * @param array<string,mixed> $file entry from $_FILES
     * @param array<string,string> $allowedMimes mime => extension
     * @return array{tmp:string,mime:string,ext:string,width:int,height:int,size:int,original:string}
     */
    public static function validate(array $file, array $allowedMimes, int $maxMb): array
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new UploadException(match ($error) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => __('validation.upload_too_large', ['max' => $maxMb]),
                UPLOAD_ERR_NO_FILE => __('validation.upload_missing'),
                default => __('validation.upload_failed'),
            });
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_file($tmp) || (PHP_SAPI !== 'cli' && !is_uploaded_file($tmp))) {
            throw new UploadException(__('validation.upload_failed'));
        }
        $size = (int) filesize($tmp);
        if ($size <= 0 || $size > $maxMb * 1024 * 1024) {
            throw new UploadException(__('validation.upload_too_large', ['max' => $maxMb]));
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($tmp);
        if (!isset($allowedMimes[$mime])) {
            throw new UploadException(__('validation.upload_type'));
        }
        $info = @getimagesize($tmp);
        if ($info === false || $info[0] < 1 || $info[1] < 1 || ($info['mime'] ?? '') !== $mime) {
            throw new UploadException(__('validation.upload_type'));
        }
        // refuse decompression bombs (> 200 megapixels)
        if ($info[0] * $info[1] > 200_000_000) {
            throw new UploadException(__('validation.upload_too_large', ['max' => $maxMb]));
        }
        return [
            'tmp' => $tmp,
            'mime' => $mime,
            'ext' => $allowedMimes[$mime],
            'width' => (int) $info[0],
            'height' => (int) $info[1],
            'size' => $size,
            'original' => mb_substr(basename((string) ($file['name'] ?? 'image')), 0, 191),
        ];
    }

    /**
     * Store a public catalogue image under public/uploads/{folder}/YYYY/MM/.
     *
     * @param array<string,mixed> $file
     * @return string path relative to public/uploads
     */
    public static function storePublic(array $file, string $folder, ?int $maxPx = null): string
    {
        $folder = preg_replace('/[^a-z0-9_-]/', '', strtolower($folder)) ?: 'misc';
        $v = self::validate($file, (array) Config::get('uploads.public_mimes'), (int) Config::get('uploads.public_max_mb', 8));
        $relDir = $folder . '/' . date('Y/m');
        $absDir = public_path('uploads/' . $relDir);
        if (!is_dir($absDir) && !mkdir($absDir, 0755, true) && !is_dir($absDir)) {
            throw new UploadException(__('validation.upload_failed'));
        }
        $name = bin2hex(random_bytes(16)) . '.' . $v['ext'];
        $dest = $absDir . '/' . $name;
        $maxPx ??= (int) Config::get('uploads.preview_max_px', 1600);
        if (!self::reencode($v['tmp'], $dest, $v['mime'], $maxPx)) {
            if (!self::moveFile($v['tmp'], $dest)) {
                throw new UploadException(__('validation.upload_failed'));
            }
        }
        @chmod($dest, 0644);
        return $relDir . '/' . $name;
    }

    private static function moveFile(string $from, string $to): bool
    {
        if (PHP_SAPI !== 'cli' && is_uploaded_file($from)) {
            return move_uploaded_file($from, $to);
        }
        return copy($from, $to);
    }

    /**
     * Re-encode (and optionally downscale) an image using GD. Returns false when GD is unavailable.
     */
    public static function reencode(string $src, string $dest, string $mime, int $maxPx, int $quality = 86): bool
    {
        if (!extension_loaded('gd')) {
            return false;
        }
        $img = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($src),
            'image/png' => @imagecreatefrompng($src),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($src) : false,
            default => false,
        };
        if ($img === false) {
            return false;
        }
        if ($mime === 'image/jpeg') {
            $img = self::applyExifOrientation($img, $src);
        }
        $w = imagesx($img);
        $h = imagesy($img);
        $scale = min(1, $maxPx / max($w, $h));
        if ($scale < 1) {
            $nw = max(1, (int) round($w * $scale));
            $nh = max(1, (int) round($h * $scale));
            $resized = imagecreatetruecolor($nw, $nh);
            if ($mime !== 'image/jpeg') {
                imagealphablending($resized, false);
                imagesavealpha($resized, true);
            }
            imagecopyresampled($resized, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
            imagedestroy($img);
            $img = $resized;
        } elseif ($mime !== 'image/jpeg') {
            imagesavealpha($img, true);
        }
        $ok = match ($mime) {
            'image/jpeg' => imagejpeg($img, $dest, $quality),
            'image/png' => imagepng($img, $dest, 7),
            'image/webp' => function_exists('imagewebp') && imagewebp($img, $dest, $quality),
            default => false,
        };
        imagedestroy($img);
        return (bool) $ok;
    }

    private static function applyExifOrientation(\GdImage $img, string $src): \GdImage
    {
        if (!function_exists('exif_read_data')) {
            return $img;
        }
        $exif = @exif_read_data($src);
        $o = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;
        $rotated = match ($o) {
            3 => imagerotate($img, 180, 0),
            6 => imagerotate($img, -90, 0),
            8 => imagerotate($img, 90, 0),
            default => $img,
        };
        return $rotated instanceof \GdImage ? $rotated : $img;
    }

    /** Delete a public upload (relative path), refusing anything outside public/uploads. */
    public static function deletePublic(?string $relative): void
    {
        if ($relative === null || $relative === '' || str_contains($relative, '..')) {
            return;
        }
        $path = public_path('uploads/' . ltrim($relative, '/'));
        $root = realpath(public_path('uploads'));
        $real = realpath($path);
        if ($root !== false && $real !== false && str_starts_with($real, $root . DIRECTORY_SEPARATOR) && is_file($real)) {
            @unlink($real);
        }
    }
}
