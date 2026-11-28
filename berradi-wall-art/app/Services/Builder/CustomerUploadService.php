<?php

declare(strict_types=1);

namespace App\Services\Builder;

use App\Core\Config;
use App\Core\Database;
use App\Core\Response;
use App\Helpers\Str;
use App\Services\ActivityLogger;
use App\Services\Auth;
use App\Services\ImageUploadService;
use App\Services\Installer\RequirementsChecker;
use App\Services\OwnerToken;
use App\Services\UploadException;

/**
 * Customer photos = private personal data.
 *
 *  - originals are kept byte-for-byte (print quality) in storage/private_uploads, outside the web root
 *  - previews/thumbnails are produced by the BROWSER (canvas) and only validated + re-encoded here,
 *    so the shared host never has to decode a 24-megapixel photo; GD is a fallback when memory allows
 *  - files are only served through PHP after an ownership check (guest token, customer, or staff with artwork.view)
 *  - every photo has a retention date; expired photos are purged by the cron job
 */
final class CustomerUploadService
{
    private const PREVIEW_MAX_MB = 6;

    public function __construct(private ?string $privateDir = null, private ?string $previewDir = null)
    {
        $this->privateDir ??= (string) Config::get('uploads.private_dir');
        $this->previewDir ??= (string) Config::get('uploads.preview_dir');
    }

    /** Effective max upload size in MB: admin setting capped by the PHP limits of the host. */
    public static function maxMb(): int
    {
        $setting = (int) setting('upload_max_mb', (int) Config::get('uploads.max_mb', 25));
        $limits = array_filter([
            RequirementsChecker::toMb((string) ini_get('upload_max_filesize')),
            RequirementsChecker::toMb((string) ini_get('post_max_size')),
        ], fn ($v) => $v > 0);
        return max(1, $limits ? min($setting, ...$limits) : $setting);
    }

    /**
     * @param array<string,mixed> $file original from $_FILES
     * @param array<string,mixed>|null $preview browser-generated preview (JPEG, <= 1600px)
     * @return array{uuid:string,width:int,height:int,size:int,name:string,has_preview:bool}
     */
    public function store(array $file, ?array $preview = null): array
    {
        $v = ImageUploadService::validate($file, (array) Config::get('uploads.customer_mimes'), self::maxMb());
        [$width, $height] = [$v['width'], $v['height']];
        if ($v['mime'] === 'image/jpeg' && function_exists('exif_read_data')) {
            $exif = @exif_read_data($v['tmp']);
            if (is_array($exif) && in_array((int) ($exif['Orientation'] ?? 1), [5, 6, 7, 8], true)) {
                [$width, $height] = [$height, $width]; // dimensions as the photo is displayed
            }
        }
        if ($width < 200 || $height < 200) {
            throw new UploadException(__('builder.too_small'));
        }

        $uuid = Str::uuid();
        $sub = date('Y/m');
        $rel = $sub . '/' . $uuid . '.' . $v['ext'];
        $this->ensureDir($this->privateDir . '/' . $sub);
        $dest = $this->privateDir . '/' . $rel;
        $moved = PHP_SAPI !== 'cli' && is_uploaded_file($v['tmp']) ? move_uploaded_file($v['tmp'], $dest) : copy($v['tmp'], $dest);
        if (!$moved) {
            throw new UploadException(__('validation.upload_failed'));
        }
        @chmod($dest, 0640);

        $this->ensureDir($this->previewDir . '/' . $sub);
        $previewRel = $sub . '/' . $uuid . '.jpg';
        $thumbRel = $sub . '/' . $uuid . '_thumb.jpg';
        $hasPreview = false;

        if ($preview !== null && (int) ($preview['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            try {
                $p = ImageUploadService::validate($preview, ['image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/png' => 'png'], self::PREVIEW_MAX_MB);
                // the preview must have the same proportions as the original (protects against mismatched files)
                if (abs($p['width'] / $p['height'] - $width / $height) < 0.03) {
                    $hasPreview = ImageUploadService::reencode($p['tmp'], $this->previewDir . '/' . $previewRel, 'image/jpeg' === $p['mime'] ? 'image/jpeg' : $p['mime'], 1600, 85);
                    if ($hasPreview && $p['mime'] !== 'image/jpeg') {
                        // keep a single extension: convert to JPEG
                        $hasPreview = $this->toJpeg($this->previewDir . '/' . $previewRel, $p['mime']);
                    }
                }
            } catch (UploadException) {
                $hasPreview = false;
            }
        }
        if (!$hasPreview && $this->canDecode($v['width'], $v['height'])) {
            $hasPreview = ImageUploadService::reencode($dest, $this->previewDir . '/' . $previewRel, $v['mime'], 1600, 85)
                && ($v['mime'] === 'image/jpeg' || $this->toJpeg($this->previewDir . '/' . $previewRel, $v['mime']));
        }
        if ($hasPreview) {
            ImageUploadService::reencode($this->previewDir . '/' . $previewRel, $this->previewDir . '/' . $thumbRel, 'image/jpeg', 400, 80);
        }

        $customer = Auth::customer();
        $days = (int) setting('retention_unordered_days', 30);
        $id = Database::instance()->insert('uploaded_images', [
            'uuid' => $uuid,
            'customer_id' => $customer['id'] ?? null,
            'owner_token' => OwnerToken::hash(),
            'original_name' => mb_substr($v['original'], 0, 191),
            'stored_path' => $rel,
            'preview_path' => $hasPreview ? $previewRel : null,
            'thumb_path' => $hasPreview && is_file($this->previewDir . '/' . $thumbRel) ? $thumbRel : null,
            'mime_type' => $v['mime'],
            'size_bytes' => $v['size'],
            'width_px' => $width,
            'height_px' => $height,
            'checksum' => (string) hash_file('sha256', $dest),
            'status' => 'temporary',
            'retention_until' => date('Y-m-d H:i:s', time() + max(1, $days) * 86400),
            'ip' => \App\Core\Request::current()?->ip(),
        ]);
        ActivityLogger::log('upload.photo', 'uploaded_image', $id, null, ['bytes' => $v['size'], 'px' => $width . 'x' . $height]);
        return ['uuid' => $uuid, 'width' => $width, 'height' => $height, 'size' => $v['size'], 'name' => $v['original'], 'has_preview' => $hasPreview];
    }

    private function toJpeg(string $path, string $mime): bool
    {
        if (!extension_loaded('gd')) {
            return false;
        }
        $img = match ($mime) {
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default => @imagecreatefromjpeg($path),
        };
        if ($img === false) {
            return false;
        }
        $bg = imagecreatetruecolor(imagesx($img), imagesy($img));
        imagefill($bg, 0, 0, imagecolorallocate($bg, 255, 255, 255));
        imagecopy($bg, $img, 0, 0, 0, 0, imagesx($img), imagesy($img));
        $ok = imagejpeg($bg, $path, 85);
        imagedestroy($img);
        imagedestroy($bg);
        return $ok;
    }

    /** Can GD decode an image of this size within the PHP memory limit? */
    private function canDecode(int $w, int $h): bool
    {
        if (!extension_loaded('gd')) {
            return false;
        }
        $need = (int) ($w * $h * 5 * 1.3) + 16 * 1024 * 1024;
        $limitMb = RequirementsChecker::toMb((string) ini_get('memory_limit'));
        if ($limitMb < 0) {
            return true;
        }
        $free = $limitMb * 1024 * 1024 - memory_get_usage(true);
        if ($need <= $free) {
            return true;
        }
        // try to raise the limit once (allowed on most cPanel hosts), never above 512M
        $wantMb = (int) min(512, ceil((memory_get_usage(true) + $need) / 1048576));
        return $wantMb > $limitMb && @ini_set('memory_limit', $wantMb . 'M') !== false
            && RequirementsChecker::toMb((string) ini_get('memory_limit')) >= $wantMb;
    }

    private function ensureDir(string $dir): void
    {
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new UploadException(__('validation.upload_failed'));
        }
    }

    /**
     * Row for an upload the current visitor may access, or null.
     *
     * @return array<string,mixed>|null
     */
    public static function findAccessible(string $uuid, bool $allowStaff = true): ?array
    {
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $uuid)) {
            return null;
        }
        $row = Database::instance()->fetch("SELECT * FROM uploaded_images WHERE uuid = ? AND status <> 'purged' AND deleted_at IS NULL", [$uuid]);
        if ($row === null) {
            return null;
        }
        return self::canAccess($row, $allowStaff) ? $row : null;
    }

    /** @param array<string,mixed> $row */
    public static function canAccess(array $row, bool $allowStaff = true): bool
    {
        $customer = Auth::customer();
        if ($customer !== null && $row['customer_id'] !== null && (int) $row['customer_id'] === (int) $customer['id']) {
            return true;
        }
        if ($row['owner_token'] !== null && hash_equals((string) $row['owner_token'], OwnerToken::hash())) {
            // a guest token never opens a photo that now belongs to another customer account
            return $row['customer_id'] === null || ($customer !== null && (int) $row['customer_id'] === (int) $customer['id']);
        }
        return $allowStaff && Auth::can('artwork.view');
    }

    /** Stream a preview / thumbnail / original. Originals are only for staff (production). */
    public function serve(array $row, string $variant): Response
    {
        [$base, $rel, $mime] = match ($variant) {
            'thumb' => [$this->previewDir, $row['thumb_path'] ?? $row['preview_path'], 'image/jpeg'],
            'original' => [$this->privateDir, $row['stored_path'], (string) $row['mime_type']],
            default => [$this->previewDir, $row['preview_path'], 'image/jpeg'],
        };
        if ($variant === 'original' && !Auth::can('artwork.view')) {
            throw new \App\Core\HttpException(403);
        }
        $path = $rel !== null ? $base . '/' . $rel : null;
        if ($path === null || str_contains((string) $rel, '..') || !is_file($path)) {
            throw new \App\Core\HttpException(404);
        }
        return Response::file($path, $mime, $variant === 'original' ? $row['original_name'] : null, $variant !== 'original');
    }

    /** Delete files and mark the row as purged (kept for audit, without personal data files). */
    public function purge(array $row, string $reason = 'customer'): void
    {
        foreach ([[$this->privateDir, $row['stored_path']], [$this->previewDir, $row['preview_path']], [$this->previewDir, $row['thumb_path']]] as [$dir, $rel]) {
            if ($rel !== null && $rel !== '' && !str_contains((string) $rel, '..') && is_file($dir . '/' . $rel)) {
                @unlink($dir . '/' . $rel);
            }
        }
        Database::instance()->query(
            "UPDATE uploaded_images SET status = 'purged', deleted_at = NOW(), original_name = '[supprimé]' WHERE id = ?",
            [(int) $row['id']]
        );
        ActivityLogger::log('upload.purged', 'uploaded_image', (int) $row['id'], $reason);
    }

    /** Can the customer delete it? Not once it is part of an order (production needs it). */
    public static function isDeletable(array $row): bool
    {
        return $row['status'] !== 'ordered';
    }

    /** Cron: purge photos whose retention date has passed. @return int purged count */
    public function purgeExpired(int $limit = 200): int
    {
        $rows = Database::instance()->fetchAll(
            "SELECT * FROM uploaded_images WHERE status <> 'purged' AND retention_until IS NOT NULL AND retention_until < NOW() ORDER BY id LIMIT " . $limit
        );
        foreach ($rows as $row) {
            $this->purge($row, 'retention');
        }
        if ($rows !== []) {
            // designs whose photos are gone can no longer be ordered
            Database::instance()->query(
                "UPDATE custom_designs d SET d.status = 'abandoned'
                 WHERE d.status IN ('draft','in_cart') AND EXISTS (
                    SELECT 1 FROM custom_design_images di JOIN uploaded_images u ON u.id = di.uploaded_image_id
                    WHERE di.custom_design_id = d.id AND u.status = 'purged')"
            );
        }
        return count($rows);
    }

    public function previewDir(): string
    {
        return (string) $this->previewDir;
    }
}
