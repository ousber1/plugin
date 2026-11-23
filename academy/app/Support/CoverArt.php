<?php

namespace App\Support;

/**
 * Generates original, deterministic SVG artwork for courses, posts and
 * products that have no uploaded image. No third-party assets are used.
 */
class CoverArt
{
    private const PALETTES = [
        ['#2E1F7A', '#4B37C4', '#F5B82E'],
        ['#1E2A5E', '#3651B5', '#FFC857'],
        ['#3B1C6B', '#6A3FD1', '#FFB547'],
        ['#14325C', '#1F6FB2', '#F7C548'],
        ['#2A1B54', '#8B5CF6', '#FCD34D'],
        ['#173A3A', '#1F8A7A', '#F6BD3A'],
        ['#40214F', '#B34777', '#FFD166'],
    ];

    public static function svg(string $title, string $label = '', int $seed = 0, string $dir = 'rtl'): string
    {
        $h = $seed ?: crc32($title);
        [$dark, $mid, $accent] = self::palette($h);
        $motif = $h % 4;
        $e = fn ($s) => htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $lines = self::wrap($title, 22, 3);
        $anchor = $dir === 'rtl' ? 'end' : 'start';
        $x = $dir === 'rtl' ? 600 : 40;
        $text = '';
        $y = 250 - (count($lines) - 1) * 26;
        foreach ($lines as $line) {
            $text .= '<text x="'.$x.'" y="'.$y.'" text-anchor="'.$anchor.'" font-family="Tajawal, Inter, Arial, sans-serif" font-weight="800" font-size="40" fill="#fff">'.$e($line).'</text>';
            $y += 52;
        }

        $shapes = match ($motif) {
            0 => '<circle cx="'.($dir === 'rtl' ? 120 : 520).'" cy="110" r="90" fill="'.$accent.'" opacity=".9"/><circle cx="'.($dir === 'rtl' ? 200 : 440).'" cy="70" r="36" fill="none" stroke="#fff" stroke-width="6" opacity=".5"/>',
            1 => '<rect x="'.($dir === 'rtl' ? 40 : 440).'" y="40" width="160" height="160" rx="36" fill="'.$accent.'" transform="rotate(12 '.($dir === 'rtl' ? 120 : 520).' 120)"/><rect x="'.($dir === 'rtl' ? 90 : 490).'" y="90" width="60" height="60" rx="14" fill="'.$dark.'"/>',
            2 => '<path d="M'.($dir === 'rtl' ? '30 200 L130 40 L230 200 Z' : '410 200 L510 40 L610 200 Z').'" fill="'.$accent.'"/><circle cx="'.($dir === 'rtl' ? 130 : 510).'" cy="150" r="24" fill="'.$dark.'"/>',
            default => '<g stroke="'.$accent.'" stroke-width="10" stroke-linecap="round" fill="none"><path d="M'.($dir === 'rtl' ? '40 60 h150 M40 100 h110 M40 140 h170 M40 180 h80' : '450 60 h150 M490 100 h110 M430 140 h170 M520 180 h80').'"/></g>',
        };

        $badge = $label !== ''
            ? '<rect x="'.($dir === 'rtl' ? 600 - 24 - mb_strlen($label) * 13 : 40).'" y="300" rx="16" height="36" width="'.(24 + mb_strlen($label) * 13).'" fill="'.$accent.'"/><text x="'.($dir === 'rtl' ? 588 : 52).'" y="325" text-anchor="'.$anchor.'" font-family="Tajawal, Inter, Arial, sans-serif" font-weight="700" font-size="18" fill="'.$dark.'">'.$e($label).'</text>'
            : '';

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 360" role="img" aria-label="'.$e($title).'">'
            .'<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="'.$dark.'"/><stop offset="1" stop-color="'.$mid.'"/></linearGradient>'
            .'<pattern id="d" width="24" height="24" patternUnits="userSpaceOnUse"><circle cx="2" cy="2" r="1.6" fill="#fff" opacity=".12"/></pattern></defs>'
            .'<rect width="640" height="360" fill="url(#g)"/><rect width="640" height="360" fill="url(#d)"/>'
            .$shapes.$text.$badge.'</svg>';
    }

    private static function wrap(string $text, int $width, int $maxLines): array
    {
        $words = preg_split('/\s+/u', trim($text)) ?: [];
        $lines = [];
        $current = '';
        foreach ($words as $word) {
            $candidate = $current === '' ? $word : $current.' '.$word;
            if (mb_strlen($candidate) > $width && $current !== '') {
                $lines[] = $current;
                $current = $word;
            } else {
                $current = $candidate;
            }
        }
        if ($current !== '') {
            $lines[] = $current;
        }
        if (count($lines) > $maxLines) {
            $lines = array_slice($lines, 0, $maxLines);
            $lines[$maxLines - 1] = mb_substr($lines[$maxLines - 1], 0, $width - 1).'…';
        }

        return $lines ?: [''];
    }

    /** Default covers follow the site theme colours (original palettes for the Nibras theme). */
    private static function palette(int $h): array
    {
        $p = (string) Settings::get('theme_primary');
        $a = (string) Settings::get('theme_accent');
        if (! preg_match('/^#[0-9a-f]{6}$/i', $p) || ! preg_match('/^#[0-9a-f]{6}$/i', $a) || strtolower($p) === '#3a2a9e') {
            return self::PALETTES[$h % count(self::PALETTES)];
        }
        $mix = function (string $hex, string $with, float $w) {
            [$r1, $g1, $b1] = sscanf($hex, '#%02x%02x%02x');
            [$r2, $g2, $b2] = sscanf($with, '#%02x%02x%02x');

            return sprintf('#%02x%02x%02x', $r1 * $w + $r2 * (1 - $w), $g1 * $w + $g2 * (1 - $w), $b1 * $w + $b2 * (1 - $w));
        };
        // Three variations so neighbouring cards are not identical.
        $variant = $h % 3;
        $dark = $mix($p, '#000000', [.5, .42, .6][$variant]);
        $mid = $mix($p, '#ffffff', [1, .85, .72][$variant]);

        return [$dark, $mid, $a];
    }

    public static function version(): string
    {
        return substr(md5(Settings::get('theme_primary').Settings::get('theme_accent')), 0, 4);
    }
}
