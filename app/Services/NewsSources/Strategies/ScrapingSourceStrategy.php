<?php

namespace App\Services\NewsSources\Strategies;

use App\Contracts\SourceStrategyInterface;
use App\Models\SourceSite;
use App\Services\NewsSources\Strategies\Concerns\BuildsSourceRequests;
use App\Services\NewsSources\Strategies\Concerns\NormalizesSourceItems;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class ScrapingSourceStrategy implements SourceStrategyInterface
{
    use BuildsSourceRequests;
    use NormalizesSourceItems;

    public function validate(SourceSite $sourceSite): void
    {
        if ($sourceSite->type !== SourceSite::TYPE_HTML) {
            throw new InvalidArgumentException('La fuente no es de tipo HTML para scraping.');
        }

        if (blank($sourceSite->url)) {
            throw new InvalidArgumentException('La fuente HTML requiere una URL.');
        }
    }

    public function fetch(SourceSite $sourceSite): mixed
    {
        return $this->requestFor($sourceSite)
            ->get($sourceSite->url)
            ->throw()
            ->body();
    }

    public function parse(mixed $payload, SourceSite $sourceSite): Collection
    {
        $document = new DOMDocument;

        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.(string) $payload, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();

        $xpath = new DOMXPath($document);
        $nodes = $xpath->query('//article');

        if (! $nodes || $nodes->length === 0) {
            $nodes = $xpath->query('//main | //body');
        }

        return collect($nodes ?: [])
            ->take($sourceSite->daily_limit ?: 20)
            ->filter(fn (mixed $node) => $node instanceof DOMElement)
            ->map(fn (DOMElement $node) => $this->normalizeItem([
                'titulo' => $this->nodeText($xpath, './/h1 | .//h2 | .//h3', $node) ?: $this->meta($xpath, 'og:title') ?: $this->documentTitle($xpath),
                'contenido' => $this->paragraphText($xpath, $node) ?: $node->textContent,
                'autor' => $this->nodeText($xpath, './/*[@rel="author"] | .//*[contains(@class, "author")]', $node) ?: $this->meta($xpath, 'author', 'name'),
                'fecha' => $this->nodeAttribute($xpath, './/time[@datetime]', 'datetime', $node) ?: $this->meta($xpath, 'article:published_time'),
                'imagen' => $this->absoluteUrl($this->nodeAttribute($xpath, './/img[@src]', 'src', $node) ?: $this->meta($xpath, 'og:image'), $sourceSite->url),
                'url' => $this->absoluteUrl($this->nodeAttribute($xpath, './/a[@href]', 'href', $node) ?: $this->meta($xpath, 'og:url') ?: $sourceSite->url, $sourceSite->url),
                'categorias' => $this->meta($xpath, 'article:section') ? [$this->meta($xpath, 'article:section')] : [],
                'tags' => $this->meta($xpath, 'keywords', 'name'),
                'contenido_html' => $this->innerHtml($node),
                'resumen' => $this->meta($xpath, 'description', 'name') ?: $this->nodeText($xpath, './/p', $node),
                'slug' => null,
                'idioma' => $sourceSite->language,
            ], $sourceSite))
            ->filter(fn (array $item) => filled($item['titulo']) && filled($item['url']))
            ->values();
    }

    private function nodeText(DOMXPath $xpath, string $query, ?DOMNode $context = null): ?string
    {
        $node = $xpath->query($query, $context)->item(0);

        return $node ? str($node->textContent)->squish()->toString() : null;
    }

    private function nodeAttribute(DOMXPath $xpath, string $query, string $attribute, ?DOMNode $context = null): ?string
    {
        $node = $xpath->query($query, $context)->item(0);

        if (! $node instanceof DOMElement) {
            return null;
        }

        return $node->getAttribute($attribute) ?: null;
    }

    private function paragraphText(DOMXPath $xpath, DOMNode $context): ?string
    {
        $paragraphs = collect($xpath->query('.//p', $context))
            ->map(fn (DOMNode $node) => str($node->textContent)->squish()->toString())
            ->filter()
            ->implode("\n\n");

        return $paragraphs !== '' ? $paragraphs : null;
    }

    private function meta(DOMXPath $xpath, string $key, string $attribute = 'property'): ?string
    {
        $content = $this->nodeAttribute($xpath, sprintf('//meta[@%s="%s"][@content]', $attribute, $key), 'content');

        return $content ?: null;
    }

    private function documentTitle(DOMXPath $xpath): ?string
    {
        return $this->nodeText($xpath, '//title');
    }

    private function innerHtml(DOMNode $node): string
    {
        $html = '';

        foreach ($node->childNodes as $childNode) {
            $html .= $node->ownerDocument?->saveHTML($childNode) ?: '';
        }

        return $html;
    }

    private function absoluteUrl(?string $url, string $baseUrl): ?string
    {
        if (blank($url)) {
            return null;
        }

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        $base = parse_url($baseUrl);
        $scheme = $base['scheme'] ?? 'https';
        $host = $base['host'] ?? null;

        if (! $host) {
            return $url;
        }

        if (str_starts_with($url, '//')) {
            return $scheme.':'.$url;
        }

        if (str_starts_with($url, '/')) {
            return "{$scheme}://{$host}{$url}";
        }

        $path = trim(dirname($base['path'] ?? '/'), '/');

        return "{$scheme}://{$host}/".($path ? "{$path}/" : '').$url;
    }
}
