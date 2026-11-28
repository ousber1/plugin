<?php

declare(strict_types=1);

namespace App\Services\Finance;

/**
 * Minimal Arabic shaping for PDF output (Dompdf renders glyphs one by one, without joining or bidi).
 *
 * 1. Letters are replaced by their contextual presentation forms (isolated / final / initial / medial)
 *    and lam-alef ligatures, which the bundled DejaVu Sans font contains.
 * 2. Each line is reordered visually for right-to-left display while keeping Latin words and numbers
 *    (e.g. "60 × 40 cm", "BWA-2026-000012", amounts) left-to-right.
 *
 * Long paragraphs are wrapped first (by characters) so that wrapped lines keep the logical order.
 */
final class ArabicShaper
{
    /** letter => [isolated, final, initial, medial] (null when the form does not exist) */
    private const FORMS = [
        "\u{0621}" => ["\u{FE80}", null, null, null],
        "\u{0622}" => ["\u{FE81}", "\u{FE82}", null, null],
        "\u{0623}" => ["\u{FE83}", "\u{FE84}", null, null],
        "\u{0624}" => ["\u{FE85}", "\u{FE86}", null, null],
        "\u{0625}" => ["\u{FE87}", "\u{FE88}", null, null],
        "\u{0626}" => ["\u{FE89}", "\u{FE8A}", "\u{FE8B}", "\u{FE8C}"],
        "\u{0627}" => ["\u{FE8D}", "\u{FE8E}", null, null],
        "\u{0628}" => ["\u{FE8F}", "\u{FE90}", "\u{FE91}", "\u{FE92}"],
        "\u{0629}" => ["\u{FE93}", "\u{FE94}", null, null],
        "\u{062A}" => ["\u{FE95}", "\u{FE96}", "\u{FE97}", "\u{FE98}"],
        "\u{062B}" => ["\u{FE99}", "\u{FE9A}", "\u{FE9B}", "\u{FE9C}"],
        "\u{062C}" => ["\u{FE9D}", "\u{FE9E}", "\u{FE9F}", "\u{FEA0}"],
        "\u{062D}" => ["\u{FEA1}", "\u{FEA2}", "\u{FEA3}", "\u{FEA4}"],
        "\u{062E}" => ["\u{FEA5}", "\u{FEA6}", "\u{FEA7}", "\u{FEA8}"],
        "\u{062F}" => ["\u{FEA9}", "\u{FEAA}", null, null],
        "\u{0630}" => ["\u{FEAB}", "\u{FEAC}", null, null],
        "\u{0631}" => ["\u{FEAD}", "\u{FEAE}", null, null],
        "\u{0632}" => ["\u{FEAF}", "\u{FEB0}", null, null],
        "\u{0633}" => ["\u{FEB1}", "\u{FEB2}", "\u{FEB3}", "\u{FEB4}"],
        "\u{0634}" => ["\u{FEB5}", "\u{FEB6}", "\u{FEB7}", "\u{FEB8}"],
        "\u{0635}" => ["\u{FEB9}", "\u{FEBA}", "\u{FEBB}", "\u{FEBC}"],
        "\u{0636}" => ["\u{FEBD}", "\u{FEBE}", "\u{FEBF}", "\u{FEC0}"],
        "\u{0637}" => ["\u{FEC1}", "\u{FEC2}", "\u{FEC3}", "\u{FEC4}"],
        "\u{0638}" => ["\u{FEC5}", "\u{FEC6}", "\u{FEC7}", "\u{FEC8}"],
        "\u{0639}" => ["\u{FEC9}", "\u{FECA}", "\u{FECB}", "\u{FECC}"],
        "\u{063A}" => ["\u{FECD}", "\u{FECE}", "\u{FECF}", "\u{FED0}"],
        "\u{0641}" => ["\u{FED1}", "\u{FED2}", "\u{FED3}", "\u{FED4}"],
        "\u{0642}" => ["\u{FED5}", "\u{FED6}", "\u{FED7}", "\u{FED8}"],
        "\u{0643}" => ["\u{FED9}", "\u{FEDA}", "\u{FEDB}", "\u{FEDC}"],
        "\u{0644}" => ["\u{FEDD}", "\u{FEDE}", "\u{FEDF}", "\u{FEE0}"],
        "\u{0645}" => ["\u{FEE1}", "\u{FEE2}", "\u{FEE3}", "\u{FEE4}"],
        "\u{0646}" => ["\u{FEE5}", "\u{FEE6}", "\u{FEE7}", "\u{FEE8}"],
        "\u{0647}" => ["\u{FEE9}", "\u{FEEA}", "\u{FEEB}", "\u{FEEC}"],
        "\u{0648}" => ["\u{FEED}", "\u{FEEE}", null, null],
        "\u{0649}" => ["\u{FEEF}", "\u{FEF0}", null, null],
        "\u{064A}" => ["\u{FEF1}", "\u{FEF2}", "\u{FEF3}", "\u{FEF4}"],
    ];

    /** alef variant => [isolated, final] lam-alef ligature */
    private const LAM_ALEF = [
        "\u{0622}" => ["\u{FEF5}", "\u{FEF6}"],
        "\u{0623}" => ["\u{FEF7}", "\u{FEF8}"],
        "\u{0625}" => ["\u{FEF9}", "\u{FEFA}"],
        "\u{0627}" => ["\u{FEFB}", "\u{FEFC}"],
    ];

    public static function hasArabic(string $text): bool
    {
        return (bool) preg_match('/[\x{0600}-\x{06FF}]/u', $text);
    }

    /** Contextual forms (logical order kept). */
    public static function shape(string $text): string
    {
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $n = count($chars);
        $out = '';
        $isHaraka = fn (?string $c) => $c !== null && preg_match('/[\x{064B}-\x{065F}\x{0670}]/u', $c) === 1;
        $prevLetter = function (int $i) use ($chars, $isHaraka): ?string {
            for ($j = $i - 1; $j >= 0; $j--) {
                if (!$isHaraka($chars[$j])) {
                    return $chars[$j];
                }
            }
            return null;
        };
        $nextLetter = function (int $i) use ($chars, $n, $isHaraka): ?string {
            for ($j = $i + 1; $j < $n; $j++) {
                if (!$isHaraka($chars[$j])) {
                    return $chars[$j];
                }
            }
            return null;
        };
        // a letter joins to the next one when it has an initial/medial form
        $joinsForward = fn (?string $c) => $c !== null && isset(self::FORMS[$c]) && self::FORMS[$c][2] !== null;
        $joinsBackward = fn (?string $c) => $c !== null && isset(self::FORMS[$c]) && self::FORMS[$c][1] !== null;
        for ($i = 0; $i < $n; $i++) {
            $c = $chars[$i];
            if (!isset(self::FORMS[$c])) {
                $out .= $c;
                continue;
            }
            $prev = $prevLetter($i);
            $next = $nextLetter($i);
            // lam + alef ligature
            if ($c === "\u{0644}" && $next !== null && isset(self::LAM_ALEF[$next])) {
                $out .= self::LAM_ALEF[$next][$joinsForward($prev) ? 1 : 0];
                // skip the alef (and harakat in between)
                for ($j = $i + 1; $j < $n; $j++) {
                    if ($chars[$j] === $next) {
                        $i = $j;
                        break;
                    }
                }
                continue;
            }
            $fromPrev = $joinsForward($prev);
            $toNext = $joinsBackward($next) && self::FORMS[$c][2] !== null;
            $forms = self::FORMS[$c];
            $glyph = match (true) {
                $fromPrev && $toNext => $forms[3],
                $fromPrev => $forms[1],
                $toNext => $forms[2],
                default => $forms[0],
            };
            $out .= $glyph ?? $forms[0];
        }
        return $out;
    }

    /**
     * Visual order of one line for an RTL paragraph: Arabic runs reversed, LTR runs (Latin, digits) kept,
     * and the sequence of runs reversed.
     */
    public static function visualLine(string $line): string
    {
        $shaped = self::shape($line);
        // runs: LTR = latin letters/digits and the punctuation that sits between them
        preg_match_all('/[A-Za-z0-9\x{00C0}-\x{024F}][A-Za-z0-9\x{00C0}-\x{024F}\.,:\/\-+×%@_#\x{00A0} ]*[A-Za-z0-9\x{00C0}-\x{024F}%]|[A-Za-z0-9\x{00C0}-\x{024F}]|[^A-Za-z0-9\x{00C0}-\x{024F}]+/u', $shaped, $m);
        $runs = $m[0];
        $out = [];
        foreach ($runs as $run) {
            if (preg_match('/^[A-Za-z0-9\x{00C0}-\x{024F}]/u', $run)) {
                $out[] = $run;
            } else {
                $chars = preg_split('//u', $run, -1, PREG_SPLIT_NO_EMPTY) ?: [];
                $mirror = ['(' => ')', ')' => '(', '[' => ']', ']' => '[', '«' => '»', '»' => '«', '<' => '>', '>' => '<'];
                $out[] = implode('', array_map(fn ($ch) => $mirror[$ch] ?? $ch, array_reverse($chars)));
            }
        }
        return implode('', array_reverse($out));
    }

    /**
     * Text ready for Dompdf: unchanged when it holds no Arabic, otherwise wrapped (logical order)
     * and each line put in visual order. Returns lines; join them with <br> in the template.
     *
     * @return list<string>
     */
    public static function lines(string $text, int $maxChars = 0): array
    {
        $out = [];
        foreach (preg_split('/\R/u', $text) ?: [] as $paragraph) {
            if (!self::hasArabic($paragraph)) {
                $out[] = $paragraph;
                continue;
            }
            $lines = $maxChars > 0 ? self::wrap($paragraph, $maxChars) : [$paragraph];
            foreach ($lines as $l) {
                $out[] = self::visualLine($l);
            }
        }
        return $out;
    }

    /** @return list<string> */
    private static function wrap(string $text, int $max): array
    {
        $words = preg_split('/\s+/u', trim($text)) ?: [];
        $lines = [];
        $cur = '';
        foreach ($words as $w) {
            if ($cur !== '' && mb_strlen($cur . ' ' . $w) > $max) {
                $lines[] = $cur;
                $cur = $w;
            } else {
                $cur = $cur === '' ? $w : $cur . ' ' . $w;
            }
        }
        if ($cur !== '') {
            $lines[] = $cur;
        }
        return $lines;
    }
}
