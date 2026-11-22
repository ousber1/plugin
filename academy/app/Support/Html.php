<?php

namespace App\Support;

use Illuminate\Support\Str;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/** Sanitizes editor-provided rich text before it is stored. */
class Html
{
    private static ?HtmlSanitizer $sanitizer = null;

    public static function clean(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }
        self::$sanitizer ??= new HtmlSanitizer(
            (new HtmlSanitizerConfig)
                ->allowElement('p')->allowElement('br')->allowElement('strong')->allowElement('b')
                ->allowElement('em')->allowElement('i')->allowElement('u')->allowElement('s')
                ->allowElement('h2')->allowElement('h3')->allowElement('h4')
                ->allowElement('ul')->allowElement('ol')->allowElement('li')
                ->allowElement('blockquote')->allowElement('code')->allowElement('pre')->allowElement('hr')
                ->allowElement('a', ['href', 'title'])
                ->allowElement('img', ['src', 'alt', 'width', 'height'])
                ->allowElement('table')->allowElement('thead')->allowElement('tbody')->allowElement('tr')
                ->allowElement('th')->allowElement('td')
                ->allowAttribute('dir', '*')
                ->allowLinkSchemes(['https', 'http', 'mailto', 'tel'])
                ->allowMediaSchemes(['https', 'http'])
                ->allowRelativeLinks()->allowRelativeMedias()
                ->forceAttribute('a', 'rel', 'noopener noreferrer nofollow')
                ->withMaxInputLength(500000)
        );

        return self::$sanitizer->sanitize($html);
    }

    /** Plain-text excerpt from rich text. */
    public static function excerpt(?string $html, int $limit = 160): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));

        return Str::limit($text, $limit);
    }
}
