<?php

namespace App\Modules\CRM\Support;

use HTMLPurifier;
use HTMLPurifier_Config;

/**
 * Cold-email bodies are rich text (the Playbook editor): a small, safe subset of HTML.
 * Older drafts and the playbook templates are plain text; toHtml() turns those into paragraphs.
 */
class EmailBody
{
    private const ALLOWED = 'p,br,strong,b,em,i,u,a[href],ul,ol,li,h3,blockquote';

    public static function isHtml(string $body): bool
    {
        return (bool) preg_match('/<(p|br|strong|b|em|i|u|a|ul|ol|li|h3|blockquote|div)\b/i', $body);
    }

    /** Safe HTML for the editor and the email, from either plain text or (untrusted) HTML. */
    public static function toHtml(?string $body): string
    {
        $body = trim((string) $body);
        if ($body === '') {
            return '';
        }
        if (! self::isHtml($body)) {
            $paragraphs = preg_split("/\n{2,}/", str_replace("\r\n", "\n", $body));
            $body = collect($paragraphs)->map(fn ($p) => '<p>'.self::linkify(nl2br(e(trim($p)), false)).'</p>')->join('');
        }

        return self::sanitize($body);
    }

    public static function sanitize(string $html): string
    {
        static $purifier;
        if (! $purifier) {
            $config = HTMLPurifier_Config::createDefault();
            $config->set('Cache.DefinitionImpl', null);
            $config->set('HTML.Allowed', self::ALLOWED);
            $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true, 'tel' => true]);
            $config->set('AutoFormat.RemoveEmpty', true);
            $purifier = new HTMLPurifier($config);
        }
        // The editor wraps lines in <div>; treat them as paragraphs.
        $html = preg_replace(['/<div\b[^>]*>/i', '/<\/div>/i'], ['<p>', '</p>'], $html);

        return trim($purifier->purify($html));
    }

    /** Plain-text version (text email part, timeline notes, copy). */
    public static function toText(?string $body): string
    {
        $body = (string) $body;
        if (! self::isHtml($body)) {
            return trim($body);
        }
        $body = preg_replace('/\s*\n\s*/', ' ', $body); // raw newlines in HTML are just spaces
        $t = preg_replace_callback('/<a\b[^>]*href="([^"]*)"[^>]*>(.*?)<\/a>/is', function ($m) {
            $label = trim(strip_tags($m[2]));
            $href = html_entity_decode($m[1]);

            return $label === '' || $label === $href || str_contains($href, $label) ? $href : $label.' ('.$href.')';
        }, $body);
        $t = preg_replace(['/<br\s*\/?>/i', '/<li\b[^>]*>/i', '/<\/(p|h3|blockquote|ul|ol)>/i', '/<\/li>/i'], ["\n", '- ', "\n\n", "\n"], $t);
        $t = html_entity_decode(strip_tags($t), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $t = preg_replace(["/[ \t]+\n/", "/\n[ \t]+/"], "\n", $t);

        return trim(preg_replace("/\n{3,}/", "\n\n", $t));
    }

    /** The HTML with the inline styles email clients need (Ink design). */
    public static function forEmail(string $html): string
    {
        $p = 'margin:0 0 16px;font-size:15px;line-height:1.65;';

        return strtr(self::toHtml($html), [
            '<p>' => '<p style="'.$p.'">',
            '<ul>' => '<ul style="margin:0 0 16px;padding-left:22px;font-size:15px;line-height:1.65;">',
            '<ol>' => '<ol style="margin:0 0 16px;padding-left:22px;font-size:15px;line-height:1.65;">',
            '<li>' => '<li style="margin:0 0 6px;">',
            '<h3>' => '<h3 style="margin:8px 0 10px;font-size:16px;line-height:1.4;">',
            '<blockquote>' => '<blockquote style="margin:0 0 16px;padding:4px 0 4px 14px;border-left:3px solid #EDE6E7;color:#5C4A4E;">',
            '<a href=' => '<a style="color:#DC2626;" href=',
        ]);
    }

    private static function linkify(string $escaped): string
    {
        return preg_replace('~(?<!["\'=])\b(https?://[^\s<]+[^\s<.,;:!?)\]])~i', '<a href="$1">$1</a>', $escaped);
    }
}
