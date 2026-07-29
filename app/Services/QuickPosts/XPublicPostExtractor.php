<?php

namespace App\Services\QuickPosts;

use App\Support\SocialPostUrl;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class XPublicPostExtractor
{
    /**
     * @return array<string, mixed>
     */
    public function extract(string $url): array
    {
        $canonicalUrl = SocialPostUrl::canonicalize($url);
        $pageResponse = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (compatible; WordPressIA/1.0)',
            'Accept' => 'text/html,application/xhtml+xml',
            'Accept-Language' => 'es-MX,es;q=0.9,en;q=0.7',
        ])->connectTimeout(10)->timeout(30)->get($canonicalUrl);

        if ($pageResponse->failed()) {
            throw new RuntimeException("X no permitió abrir la publicación ({$pageResponse->status()}).");
        }

        $pageHtml = $pageResponse->body();
        $meta = $this->meta($pageHtml);
        $embed = $this->oEmbed($canonicalUrl);
        $tweetId = $this->tweetId($canonicalUrl);
        $text = $this->tweetText((string) ($embed['html'] ?? ''))
            ?: trim((string) ($meta['og:description'] ?? $meta['twitter:description'] ?? ''));

        if (mb_strlen($text) < 20) {
            throw new RuntimeException('X no expuso el texto de esta publicación pública.');
        }

        if (filled($embed['author_name'] ?? null)) {
            $meta['author_name'] = (string) $embed['author_name'];
        }

        if (filled($embed['author_url'] ?? null)) {
            $meta['author_url'] = (string) $embed['author_url'];
        }

        return [
            'canonical_url' => $canonicalUrl,
            'final_url' => $canonicalUrl,
            'capture_url' => 'https://publish.x.com/oembed?'.http_build_query([
                'url' => $canonicalUrl,
                'omit_script' => 'true',
                'dnt' => 'true',
            ], '', '&', PHP_QUERY_RFC3986),
            'title' => (string) ($meta['og:title'] ?? $meta['twitter:title'] ?? 'Publicación en X'),
            'text' => $text,
            'html_language' => $this->htmlLanguage($pageHtml),
            'meta' => $meta,
            'json_ld' => [],
            'images' => $this->images($this->tweetMarkup($pageHtml, $tweetId), $meta),
            'http_status' => $pageResponse->status(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function oEmbed(string $url): array
    {
        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (compatible; WordPressIA/1.0)',
            'Accept' => 'application/json',
        ])->connectTimeout(10)->timeout(20)->get('https://publish.x.com/oembed', [
            'url' => $url,
            'omit_script' => 'true',
            'dnt' => 'true',
        ]);

        return $response->successful() && is_array($response->json())
            ? $response->json()
            : [];
    }

    /**
     * @return array<string, string>
     */
    private function meta(string $html): array
    {
        $xpath = $this->xpath($html);
        $meta = [];

        foreach ($xpath->query('//meta[@content]') ?: [] as $element) {
            $key = $element->attributes?->getNamedItem('property')?->nodeValue
                ?: $element->attributes?->getNamedItem('name')?->nodeValue
                ?: $element->attributes?->getNamedItem('itemprop')?->nodeValue;
            $value = $element->attributes?->getNamedItem('content')?->nodeValue;

            if ($key && $value && (
                str_starts_with($key, 'og:')
                || str_starts_with($key, 'twitter:')
                || in_array($key, ['article:published_time', 'datePublished', 'headline'], true)
            )) {
                $meta[$key] = $value;
            }
        }

        return $meta;
    }

    private function tweetText(string $html): string
    {
        if ($html === '') {
            return '';
        }

        $paragraph = $this->xpath($html)->query('//blockquote//p')->item(0);

        return trim((string) $paragraph?->textContent);
    }

    private function htmlLanguage(string $html): ?string
    {
        return $this->xpath($html)->query('/html/@lang')->item(0)?->nodeValue;
    }

    private function tweetId(string $url): string
    {
        preg_match('~/status/(\d+)~', (string) parse_url($url, PHP_URL_PATH), $match);

        return (string) ($match[1] ?? '');
    }

    private function tweetMarkup(string $html, string $tweetId): string
    {
        if ($tweetId === '') {
            return '';
        }

        $node = $this->xpath($html)
            ->query("//article[@data-tweet-id='{$tweetId}']")
            ->item(0);

        return $node?->ownerDocument?->saveHTML($node) ?: '';
    }

    /**
     * @param  array<string, string>  $meta
     * @return array<int, array<string, mixed>>
     */
    private function images(string $html, array $meta): array
    {
        preg_match_all(
            '~https://pbs\.twimg\.com/(?:media|amplify_video_thumb)/[^"\'<>\s\\\\]+~i',
            html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            $matches,
        );
        $urls = collect([
            $meta['og:image'] ?? null,
            $meta['twitter:image'] ?? null,
            ...($matches[0] ?? []),
        ])->filter()->map(fn (string $url): string => $this->normalizeImageUrl($url));

        $width = isset($meta['og:image:width']) ? (int) $meta['og:image:width'] : null;
        $height = isset($meta['og:image:height']) ? (int) $meta['og:image:height'] : null;

        return $urls
            ->unique(fn (string $url): string => (string) parse_url($url, PHP_URL_PATH))
            ->values()
            ->map(fn (string $imageUrl, int $index): array => [
                'url' => $imageUrl,
                'alt' => $index === 0 ? ($meta['og:title'] ?? null) : null,
                'width' => $index === 0 ? $width : null,
                'height' => $index === 0 ? $height : null,
            ])
            ->take(20)
            ->all();
    }

    private function normalizeImageUrl(string $url): string
    {
        $url = html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $path = (string) parse_url($url, PHP_URL_PATH);

        if (preg_match('~/media/([^/?]+)~', $path, $match) === 1) {
            $identifier = preg_replace('/\.(?:jpe?g|png|webp)(?::[a-z]+)?$/i', '', $match[1]);

            return "https://pbs.twimg.com/media/{$identifier}?format=jpg&name=large";
        }

        return 'https://pbs.twimg.com'.$path;
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($document);
    }
}
