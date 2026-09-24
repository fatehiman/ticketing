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

    /** Full plain text of HTML content, keeping paragraphs and line breaks (for the bot API). */
    public static function toText(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }
        $text = preg_replace(['#<br\s*/?>\R?#i', '#</(p|h[1-6]|blockquote)>\R?#i', '#</(div|li|tr)>\R?#i'], ["\n", "\n\n", "\n"], $html);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace(["/[ \t\x{00A0}]+/u", "/ *\n */u", "/\n{3,}/u"], [' ', "\n", "\n\n"], $text);

        return trim($text) === '' ? null : trim($text);
    }

    /** Plain-text excerpt of HTML content. */
    public static function excerpt(?string $html, int $length = 140): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $html))));

        return mb_strimwidth($text, 0, $length, '…');
    }
}
