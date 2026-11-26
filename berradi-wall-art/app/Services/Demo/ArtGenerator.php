<?php

declare(strict_types=1);

namespace App\Services\Demo;

/**
 * Procedural artwork for the optional demo catalogue (GD only, no external assets or APIs).
 * Every style is deterministic for a given seed so re-installing the demo gives the same pictures.
 */
final class ArtGenerator
{
    private \GdImage $im;
    private int $w;
    private int $h;

    public function __construct(int $width = 1000, int $height = 1250, int $seed = 1)
    {
        if (!extension_loaded('gd')) {
            throw new \RuntimeException('GD extension is required to generate demo artwork');
        }
        mt_srand($seed);
        $this->w = $width;
        $this->h = $height;
        $this->im = imagecreatetruecolor($width, $height);
        imageantialias($this->im, true);
    }

    /** @param array{0:int,1:int,2:int} $rgb */
    private function c(array $rgb, int $alpha = 0): int
    {
        return imagecolorallocatealpha($this->im, $rgb[0], $rgb[1], $rgb[2], $alpha);
    }

    private static function hex(string $hex): array
    {
        $hex = ltrim($hex, '#');
        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    private function gradient(string $top, string $bottom, int $y0 = 0, ?int $y1 = null): void
    {
        $y1 ??= $this->h;
        [$r1, $g1, $b1] = self::hex($top);
        [$r2, $g2, $b2] = self::hex($bottom);
        $span = max(1, $y1 - $y0);
        for ($y = $y0; $y < $y1; $y++) {
            $t = ($y - $y0) / $span;
            $col = imagecolorallocate($this->im, (int) ($r1 + ($r2 - $r1) * $t), (int) ($g1 + ($g2 - $g1) * $t), (int) ($b1 + ($b2 - $b1) * $t));
            imageline($this->im, 0, $y, $this->w, $y, $col);
        }
    }

    private function grain(int $amount = 10): void
    {
        for ($i = 0; $i < $this->w * $this->h / 40; $i++) {
            $v = mt_rand(0, 255);
            imagesetpixel($this->im, mt_rand(0, $this->w - 1), mt_rand(0, $this->h - 1), imagecolorallocatealpha($this->im, $v, $v, $v, 127 - $amount));
        }
    }

    private function arch(int $cx, int $bottom, int $width, int $height, int $color): void
    {
        $r = intdiv($width, 2);
        imagefilledrectangle($this->im, $cx - $r, $bottom - $height + $r, $cx + $r, $bottom, $color);
        imagefilledellipse($this->im, $cx, $bottom - $height + $r, $width, $width, $color);
    }

    /** Moroccan arches (doors / riads). @param list<string> $p bg, wall, arch, inner, sun */
    public function arches(array $p): self
    {
        $this->gradient($p[0], $p[1]);
        $sun = $this->c(self::hex($p[4]));
        imagefilledellipse($this->im, (int) ($this->w * .72), (int) ($this->h * .2), (int) ($this->w * .22), (int) ($this->w * .22), $sun);
        $bottom = (int) ($this->h * .9);
        $big = (int) ($this->w * .56);
        $this->arch((int) ($this->w / 2), $bottom, $big, (int) ($this->h * .64), $this->c(self::hex($p[2])));
        $this->arch((int) ($this->w / 2), $bottom, $big - 70, (int) ($this->h * .64) - 35, $this->c(self::hex($p[3])));
        $small = (int) ($this->w * .18);
        foreach ([.14, .86] as $x) {
            $this->arch((int) ($this->w * $x), $bottom, $small, (int) ($this->h * .36), $this->c(self::hex($p[2])));
            $this->arch((int) ($this->w * $x), $bottom, $small - 30, (int) ($this->h * .36) - 15, $this->c(self::hex($p[3])));
        }
        imagefilledrectangle($this->im, 0, $bottom, $this->w, $this->h, $this->c(self::hex($p[2])));
        $this->grain(8);
        return $this;
    }

    private function star(float $cx, float $cy, float $r, int $color, float $inner = .42): void
    {
        $pts = [];
        for ($i = 0; $i < 16; $i++) {
            $a = M_PI / 8 * $i - M_PI / 2;
            $rr = $i % 2 === 0 ? $r : $r * (1 - $inner) + $r * $inner * .55;
            $pts[] = (int) round($cx + cos($a) * $rr);
            $pts[] = (int) round($cy + sin($a) * $rr);
        }
        imagefilledpolygon($this->im, $pts, $color);
    }

    /** Zellige-inspired 8-point star tiling. @param list<string> $p bg, star, center, accent */
    public function zellige(array $p): self
    {
        imagefill($this->im, 0, 0, $this->c(self::hex($p[0])));
        $step = (int) ($this->w / 5);
        $cols = [$this->c(self::hex($p[1])), $this->c(self::hex($p[3]))];
        for ($y = -$step / 2, $row = 0; $y < $this->h + $step; $y += $step, $row++) {
            for ($x = 0, $col = 0; $x < $this->w + $step; $x += $step, $col++) {
                $ox = $row % 2 === 0 ? 0 : $step / 2;
                $this->star($x + $ox, $y, $step * .48, $cols[($row + $col) % 2]);
                $this->star($x + $ox, $y, $step * .22, $this->c(self::hex($p[2])), .3);
                imagefilledellipse($this->im, (int) ($x + $ox), (int) $y, (int) ($step * .1), (int) ($step * .1), $this->c(self::hex($p[0])));
            }
        }
        $this->grain(6);
        return $this;
    }

    /** Layered mountains / dunes. @param list<string> $p skyTop, skyBottom, sun, layer1..layerN */
    public function landscape(array $p, bool $dunes = false): self
    {
        $this->gradient($p[0], $p[1]);
        imagefilledellipse($this->im, (int) ($this->w * .32), (int) ($this->h * .34), (int) ($this->w * .26), (int) ($this->w * .26), $this->c(self::hex($p[2])));
        $layers = array_slice($p, 3);
        $n = count($layers);
        foreach ($layers as $i => $hex) {
            $base = $this->h * (.45 + .14 * $i);
            $amp = $this->h * ($dunes ? .05 : .11) * (1 - $i / ($n + 1));
            $freq = mt_rand(12, 22) / 10 + $i * .3;
            $phase = mt_rand(0, 628) / 100;
            $pts = [0, $this->h];
            for ($x = 0; $x <= $this->w; $x += 10) {
                $t = $x / $this->w;
                $y = $dunes
                    ? $base - $amp * sin($t * M_PI * $freq + $phase) - $amp * .4 * sin($t * M_PI * $freq * 2.3)
                    : $base - $amp * abs(sin($t * M_PI * $freq + $phase)) - $amp * .35 * sin($t * M_PI * 7 + $i);
                $pts[] = $x;
                $pts[] = (int) $y;
            }
            $pts[] = $this->w;
            $pts[] = $this->h;
            imagefilledpolygon($this->im, $pts, $this->c(self::hex($hex)));
        }
        $this->grain(10);
        return $this;
    }

    /** Black & gold abstract: circles and fine lines. @param list<string> $p bg, gold, light, dark */
    public function abstractGold(array $p, bool $orbits = false): self
    {
        $this->gradient($p[0], $p[3]);
        $gold = $this->c(self::hex($p[1]));
        $light = $this->c(self::hex($p[2]), 40);
        imagesetthickness($this->im, 3);
        if ($orbits) {
            for ($i = 0; $i < 9; $i++) {
                $s = (int) ($this->w * (.15 + .09 * $i));
                imagearc($this->im, (int) ($this->w / 2), (int) ($this->h * .46), $s, $s, mt_rand(0, 360), mt_rand(0, 360), $i % 3 === 0 ? $gold : $light);
            }
            imagefilledellipse($this->im, (int) ($this->w / 2), (int) ($this->h * .46), (int) ($this->w * .14), (int) ($this->w * .14), $gold);
        } else {
            imagefilledellipse($this->im, (int) ($this->w * .55), (int) ($this->h * .42), (int) ($this->w * .62), (int) ($this->w * .62), $gold);
            imagefilledellipse($this->im, (int) ($this->w * .47), (int) ($this->h * .40), (int) ($this->w * .6), (int) ($this->w * .6), $this->c(self::hex($p[0])));
            for ($i = 0; $i < 7; $i++) {
                $y = (int) ($this->h * (.72 + $i * .025));
                imageline($this->im, (int) ($this->w * .12), $y, (int) ($this->w * (.5 + $i * .05)), $y, $i % 2 ? $light : $gold);
            }
        }
        imagesetthickness($this->im, 1);
        $this->grain(6);
        return $this;
    }

    /** Mid-century minimal composition. @param list<string> $p bg, a, b, c */
    public function minimal(array $p, int $variant = 0): self
    {
        imagefill($this->im, 0, 0, $this->c(self::hex($p[0])));
        $a = $this->c(self::hex($p[1]));
        $b = $this->c(self::hex($p[2]));
        $c = $this->c(self::hex($p[3]));
        $m = (int) ($this->w * .14);
        if ($variant === 0) {
            imagefilledarc($this->im, (int) ($this->w / 2), (int) ($this->h * .55), $this->w - 2 * $m, $this->w - 2 * $m, 180, 360, $a, IMG_ARC_PIE);
            imagefilledrectangle($this->im, $m, (int) ($this->h * .55), $this->w - $m, (int) ($this->h * .78), $b);
            imagefilledellipse($this->im, (int) ($this->w * .66), (int) ($this->h * .3), (int) ($this->w * .16), (int) ($this->w * .16), $c);
        } else {
            imagefilledrectangle($this->im, $m, (int) ($this->h * .2), (int) ($this->w * .55), (int) ($this->h * .8), $a);
            imagefilledellipse($this->im, (int) ($this->w * .6), (int) ($this->h * .5), (int) ($this->w * .5), (int) ($this->w * .5), $b);
            imagefilledarc($this->im, (int) ($this->w * .6), (int) ($this->h * .5), (int) ($this->w * .5), (int) ($this->w * .5), 270, 90, $c, IMG_ARC_PIE);
        }
        $this->grain(5);
        return $this;
    }

    /** Typographic quote poster. @param list<string> $p bg, text, accent */
    public function quote(array $p, string $text, string $font): self
    {
        imagefill($this->im, 0, 0, $this->c(self::hex($p[0])));
        $ink = $this->c(self::hex($p[1]));
        $accent = $this->c(self::hex($p[2]));
        imagefilledrectangle($this->im, (int) ($this->w * .44), (int) ($this->h * .2), (int) ($this->w * .56), (int) ($this->h * .2) + 3, $accent);
        $size = (int) ($this->w * .075);
        $lines = explode("\n", $text);
        $lh = (int) ($size * 1.45);
        $y = (int) ($this->h / 2 - count($lines) * $lh / 2 + $size);
        foreach ($lines as $line) {
            if (is_file($font)) {
                $box = imagettfbbox($size, 0, $font, $line);
                $tw = $box[2] - $box[0];
                imagettftext($this->im, $size, 0, (int) (($this->w - $tw) / 2), $y, $ink, $font, $line);
            } else {
                imagestring($this->im, 5, (int) ($this->w / 2 - strlen($line) * 4.5), $y, $line, $ink);
            }
            $y += $lh;
        }
        imagefilledrectangle($this->im, (int) ($this->w * .44), (int) ($this->h * .8), (int) ($this->w * .56), (int) ($this->h * .8) + 3, $accent);
        return $this;
    }

    /** Playful nursery poster: rainbow arcs and balloons. @param list<string> $p bg, c1, c2, c3, c4 */
    public function kids(array $p): self
    {
        imagefill($this->im, 0, 0, $this->c(self::hex($p[0])));
        $cx = (int) ($this->w / 2);
        $cy = (int) ($this->h * .62);
        foreach ([1, 2, 3, 4] as $i) {
            $s = (int) ($this->w * (.8 - ($i - 1) * .15));
            imagefilledarc($this->im, $cx, $cy, $s, $s, 180, 360, $this->c(self::hex($p[$i])), IMG_ARC_PIE);
        }
        imagefilledarc($this->im, $cx, $cy, (int) ($this->w * .2), (int) ($this->w * .2), 180, 360, $this->c(self::hex($p[0])), IMG_ARC_PIE);
        foreach ([[.2, .2, 2], [.8, .16, 3], [.68, .3, 1]] as [$x, $y, $ci]) {
            imagefilledellipse($this->im, (int) ($this->w * $x), (int) ($this->h * $y), 90, 110, $this->c(self::hex($p[$ci])));
            imageline($this->im, (int) ($this->w * $x), (int) ($this->h * $y) + 55, (int) ($this->w * $x) + 10, (int) ($this->h * $y) + 180, $this->c(self::hex($p[4])));
        }
        return $this;
    }

    /** Placeholder for products personalised with the customer's photo. */
    public function photoPlaceholder(string $label, string $font): self
    {
        $this->gradient('#D9CBB0', '#8C7246');
        $white = $this->c([255, 255, 255], 30);
        imagesetthickness($this->im, 4);
        $s = (int) ($this->w * .22);
        $cx = (int) ($this->w / 2);
        $cy = (int) ($this->h * .45);
        imagerectangle($this->im, $cx - $s, $cy - (int) ($s * .7), $cx + $s, $cy + (int) ($s * .7), $white);
        imageellipse($this->im, $cx, $cy, (int) ($s * .9), (int) ($s * .9), $white);
        imagefilledrectangle($this->im, $cx - (int) ($s * .5), $cy - (int) ($s * .9), $cx - (int) ($s * .1), $cy - (int) ($s * .7), $white);
        imagesetthickness($this->im, 1);
        if (is_file($font)) {
            $size = (int) ($this->w * .045);
            $box = imagettfbbox($size, 0, $font, $label);
            imagettftext($this->im, $size, 0, (int) (($this->w - ($box[2] - $box[0])) / 2), (int) ($this->h * .72), $white, $font, $label);
        }
        $this->grain(8);
        return $this;
    }

    /** Grayscale + contrast (black & white photography look). */
    public function monochrome(): self
    {
        imagefilter($this->im, IMG_FILTER_GRAYSCALE);
        imagefilter($this->im, IMG_FILTER_CONTRAST, -25);
        return $this;
    }

    public function save(string $path, int $quality = 84): void
    {
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('Cannot create ' . $dir);
        }
        imagejpeg($this->im, $path, $quality);
    }

    /** Save a zoomed "detail" crop of the artwork (used as second gallery image). */
    public function saveDetail(string $path, float $zoom = 1.8): void
    {
        $cw = (int) ($this->w / $zoom);
        $ch = (int) ($this->h / $zoom);
        $crop = imagecreatetruecolor($this->w, $this->h);
        imagecopyresampled($crop, $this->im, 0, 0, (int) (($this->w - $cw) / 2), (int) (($this->h - $ch) / 2), $this->w, $this->h, $cw, $ch);
        imagejpeg($crop, $path, 84);
        imagedestroy($crop);
    }
}
