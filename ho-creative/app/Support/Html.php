<?php

namespace App\Support;

/**
 * Minimal allow-list sanitiser for admin-authored rich text (pages, home blocks).
 */
class Html
{
    public const ALLOWED = '<p><br><strong><b><em><i><u><ul><ol><li><h2><h3><h4><a><blockquote><table><thead><tbody><tr><th><td><hr><span><img>';

    public static function clean(?string $html): string
    {
        $html = strip_tags((string) $html, self::ALLOWED);
        // drop event handlers, inline styles and javascript: URLs
        $html = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
        $html = preg_replace('/\s+style\s*=\s*("[^"]*"|\'[^\']*\')/i', '', $html);
        $html = preg_replace('/(href|src)\s*=\s*("|\')\s*(javascript|data|vbscript):[^"\']*\2/i', '$1="#"', $html);

        return $html;
    }
}
