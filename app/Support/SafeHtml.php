<?php

namespace App\Support;

class SafeHtml
{
    public static function clean(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }

        $html = strip_tags($html, '<p><br><h2><h3><ul><ol><li><strong><em><blockquote><a>');

        return preg_replace_callback('/<([a-z][a-z0-9]*)\b([^>]*)>/i', function (array $matches): string {
            $tag = strtolower($matches[1]);

            if ($tag !== 'a') {
                return '<'.$tag.'>';
            }

            if (! preg_match('/\bhref\s*=\s*(["\'])(.*?)\1/i', $matches[2], $hrefMatch)) {
                return '<a>';
            }

            $url = html_entity_decode($hrefMatch[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

            if (! in_array($scheme, ['http', 'https'], true) || filter_var($url, FILTER_VALIDATE_URL) === false) {
                return '<a>';
            }

            return '<a href="'.e($url).'" target="_blank" rel="nofollow noopener noreferrer">';
        }, $html) ?? $html;
    }
}
