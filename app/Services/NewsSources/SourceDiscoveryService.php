<?php

namespace App\Services\NewsSources;

use App\Models\SourceSite;
use App\Services\NewsSources\Strategies\Concerns\BuildsSourceRequests;
use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;

class SourceDiscoveryService
{
    use BuildsSourceRequests;

    /**
     * @return array{type: string, label: string, confidence: int, reason: string, capabilities: array<int, array<string, mixed>>}
     */
    public function detect(SourceSite $sourceSite): array
    {
        $response = $this->requestFor($sourceSite)->get($sourceSite->url)->throw();
        $body = $response->body();
        $contentType = strtolower((string) $response->header('Content-Type', ''));
        $capabilities = [];

        if ($this->looksLikeJsonFeed($body, $contentType)) {
            $capabilities[] = $this->capability(SourceSite::TYPE_JSON_FEED, 100, 'La URL devuelve un JSON Feed válido.');
        }

        if ($this->looksLikeFeed($body, $contentType)) {
            $capabilities[] = $this->capability(SourceSite::TYPE_RSS, 100, 'La URL devuelve un feed RSS o Atom.');
        }

        if ($this->looksLikeSitemap($body)) {
            $capabilities[] = $this->capability(SourceSite::TYPE_SITEMAP, 100, 'La URL devuelve un sitemap XML.');
        }

        if ($this->looksLikeWordPressApi($sourceSite->url, $body)) {
            $capabilities[] = $this->capability(SourceSite::TYPE_WORDPRESS_REST, 98, 'Se detectó la API REST de WordPress.');
        }

        if (str_contains($contentType, 'text/html') || preg_match('/<html|<!doctype/i', $body)) {
            $capabilities = array_merge($capabilities, $this->htmlCapabilities($body));
            $capabilities[] = $this->capability(SourceSite::TYPE_HTML, 55, 'La página puede procesarse mediante HTML y datos estructurados.');
        }

        if ($capabilities === []) {
            throw new RuntimeException('La URL respondió, pero no se pudo reconocer un formato de publicaciones compatible.');
        }

        $capabilities = collect($capabilities)
            ->unique('type')
            ->sortByDesc('confidence')
            ->values()
            ->all();
        $best = $capabilities[0];

        return [
            ...$best,
            'capabilities' => $capabilities,
        ];
    }

    private function looksLikeJsonFeed(string $body, string $contentType): bool
    {
        $json = json_decode($body, true);

        return is_array($json)
            && isset($json['items'])
            && (str_contains((string) ($json['version'] ?? ''), 'jsonfeed.org')
                || str_contains($contentType, 'feed+json'));
    }

    private function looksLikeFeed(string $body, string $contentType): bool
    {
        $trimmed = ltrim($body);

        return str_contains($contentType, 'rss')
            || str_contains($contentType, 'atom')
            || str_starts_with($trimmed, '<rss')
            || str_starts_with($trimmed, '<feed')
            || (str_starts_with($trimmed, '<?xml') && (str_contains($trimmed, '<rss') || str_contains($trimmed, '<feed')));
    }

    private function looksLikeSitemap(string $body): bool
    {
        return str_contains($body, '<urlset') || str_contains($body, '<sitemapindex');
    }

    private function looksLikeWordPressApi(string $url, string $body): bool
    {
        if (str_contains($url, '/wp-json/')) {
            return true;
        }

        $json = json_decode($body, true);

        return is_array($json)
            && (isset($json['namespaces']) || (isset($json[0]['type']) && isset($json[0]['link'])));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function htmlCapabilities(string $html): array
    {
        $document = new DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        $xpath = new DOMXPath($document);
        $capabilities = [];

        foreach (collect($xpath->query('//link[@href]') ?: [])->filter(fn (mixed $node) => $node instanceof DOMElement) as $link) {
            $type = strtolower($link->getAttribute('type'));

            if (str_contains($type, 'rss+xml') || str_contains($type, 'atom+xml')) {
                $capabilities[] = $this->capability(SourceSite::TYPE_RSS, 92, 'La página publica un enlace de descubrimiento RSS/Atom.');
            }

            if ($type === 'application/feed+json') {
                $capabilities[] = $this->capability(SourceSite::TYPE_JSON_FEED, 92, 'La página publica un enlace de descubrimiento JSON Feed.');
            }
        }

        if (($xpath->query('//link[@rel="https://api.w.org/"]')?->length ?? 0) > 0
            || str_contains($html, '/wp-json/')) {
            $capabilities[] = $this->capability(SourceSite::TYPE_WORDPRESS_REST, 96, 'La página anuncia una API REST de WordPress.');
        }

        return $capabilities;
    }

    /**
     * @return array{type: string, label: string, confidence: int, reason: string}
     */
    private function capability(string $type, int $confidence, string $reason): array
    {
        return [
            'type' => $type,
            'label' => SourceSite::typeOptions()[$type],
            'confidence' => $confidence,
            'reason' => $reason,
        ];
    }
}
