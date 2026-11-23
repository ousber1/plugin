<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;

/**
 * Fallback for /storage/{path} when public/storage is not a working symlink
 * (common on shared hosting). Only files of the public disk are served, and
 * only image/document types; private media never lives on this disk.
 */
class PublicFileController extends Controller
{
    private const ALLOWED = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'ico', 'pdf'];

    public function show(string $path)
    {
        $path = str_replace('\\', '/', $path);
        abort_if(str_contains($path, '..') || str_starts_with($path, '.') || str_contains($path, '/.'), 404);
        abort_unless(in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::ALLOWED, true), 404);

        $disk = Storage::disk('public');
        abort_unless($disk->exists($path), 404);

        $headers = ['Cache-Control' => 'public, max-age=2592000', 'X-Content-Type-Options' => 'nosniff'];
        if (str_ends_with(strtolower($path), '.svg')) {
            $headers['Content-Security-Policy'] = "default-src 'none'; style-src 'unsafe-inline'";
        }

        return response()->file($disk->path($path), $headers);
    }
}
