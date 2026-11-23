<?php

namespace App\Support;

/**
 * Upload chunk size that always fits the host's PHP limits, so large videos
 * upload even on shared hosting with the default upload_max_filesize = 2M.
 */
class Uploads
{
    public static function chunkBytes(): int
    {
        $wanted = max(1, (int) config('academy.video.chunk_mb')) * 1048576;
        $limits = array_filter([
            self::iniBytes((string) ini_get('upload_max_filesize')) - 64 * 1024,
            self::iniBytes((string) ini_get('post_max_size')) - 256 * 1024,
        ], fn ($v) => $v > 0);

        return max(256 * 1024, min([$wanted, ...$limits]));
    }

    public static function iniBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '' || $value === '-1') {
            return 0; // unlimited
        }
        $number = (float) $value;

        return (int) match (strtolower(substr($value, -1))) {
            'g' => $number * 1073741824,
            'm' => $number * 1048576,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
