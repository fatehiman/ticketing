<?php

namespace App\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/** Cleans rich-text HTML from the editor before it is stored. */
class Html
{
    private static ?HtmlSanitizer $sanitizer = null;

    public static function clean(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        self::$sanitizer ??= new HtmlSanitizer(
            (new HtmlSanitizerConfig)
                ->allowSafeElements()
                ->allowAttribute('style', '*')
                ->allowAttribute('class', '*')
                ->allowAttribute('dir', '*')
                ->allowAttribute('width', ['img', 'table', 'td', 'th', 'col'])
                ->allowAttribute('height', ['img', 'table', 'td', 'th'])
                ->allowLinkSchemes(['http', 'https', 'mailto', 'tel'])
                ->allowMediaSchemes(['http', 'https'])
                ->allowRelativeLinks()
                ->allowRelativeMedias()
                ->forceAttribute('a', 'rel', 'noopener noreferrer')
                ->withMaxInputLength(2_000_000)
        );

        return self::$sanitizer->sanitize($html);
    }

    /** Plain-text excerpt of HTML content. */
    public static function excerpt(?string $html, int $length = 140): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $html))));

        return mb_strimwidth($text, 0, $length, '…');
    }
}
