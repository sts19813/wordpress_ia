<?php

namespace App\Services\NewsSources\Strategies;

use App\Contracts\SourceStrategyInterface;
use App\Models\SourceSite;
use App\Services\NewsSources\Strategies\Concerns\BuildsSourceRequests;
use App\Services\NewsSources\Strategies\Concerns\NormalizesSourceItems;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class JsonFeedSourceStrategy implements SourceStrategyInterface
{
    use BuildsSourceRequests;
    use NormalizesSourceItems;

    public function validate(SourceSite $sourceSite): void
    {
        if ($sourceSite->type !== SourceSite::TYPE_JSON_FEED) {
            throw new InvalidArgumentException('La fuente no es de tipo JSON Feed.');
        }

        if (blank($sourceSite->url)) {
            throw new InvalidArgumentException('La fuente JSON Feed requiere una URL.');
        }
    }

    public function fetch(SourceSite $sourceSite): mixed
    {
        $response = $this->requestFor($sourceSite)->get($sourceSite->url)->throw();
        $payload = $response->json();

        if (is_array($payload) && isset($payload['items'])) {
            return $payload;
        }

        $feedUrl = $this->discoverFeedUrl($response->body(), $sourceSite->url);

        if (! $feedUrl) {
            throw new InvalidArgumentException('No se encontró un JSON Feed válido en la URL.');
        }

        return $this->requestFor($sourceSite)->get($feedUrl)->throw()->json();
    }

    public function parse(mixed $payload, SourceSite $sourceSite): Collection
    {
        return collect(is_array($payload) ? ($payload['items'] ?? []) : [])
            ->filter(fn (mixed $item) => is_array($item))
            ->take($sourceSite->daily_limit ?: 20)
            ->map(fn (array $item) => $this->normalizeItem([
                'titulo' => $item['title'] ?? null,
                'contenido' => $item['content_text'] ?? strip_tags((string) ($item['content_html'] ?? '')),
                'contenido_html' => $item['content_html'] ?? null,
                'resumen' => $item['summary'] ?? null,
                'autor' => data_get($item, 'authors.0.name') ?: data_get($item, 'author.name'),
                'fecha' => $item['date_published'] ?? $item['date_modified'] ?? null,
                'imagen' => $item['image'] ?? $item['banner_image'] ?? null,
                'url' => $item['url'] ?? $item['external_url'] ?? null,
                'categorias' => [],
                'tags' => $item['tags'] ?? [],
                'idioma' => $item['language'] ?? $sourceSite->language,
                'original_json' => $item,
            ], $sourceSite))
            ->filter(fn (array $item) => filled($item['titulo']) && filled($item['url']))
            ->values();
    }

    private function discoverFeedUrl(string $html, string $baseUrl): ?string
    {
        $document = new DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();

        $xpath = new DOMXPath($document);
        $node = collect($xpath->query('//link[@href]') ?: [])
            ->first(fn (mixed $link) => $link instanceof DOMElement
                && strtolower($link->getAttribute('type')) === 'application/feed+json');

        return $node instanceof DOMElement
            ? $this->absoluteUrl($node->getAttribute('href'), $baseUrl)
            : null;
    }

    private function absoluteUrl(string $url, string $baseUrl): string
    {
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        $parts = parse_url($baseUrl);
        $origin = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '');

        return $origin.'/'.ltrim($url, '/');
    }
}
