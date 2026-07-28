<?php

namespace App\Services\NewsSources;

use App\Models\SourceSite;
use App\Services\NewsSources\Strategies\Concerns\BuildsSourceRequests;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Throwable;

class ArticleContentExtractor
{
    use BuildsSourceRequests;

    /**
     * Completa un elemento normalizado desde la página de la nota y conserva
     * el HTML crudo como respaldo para diagnóstico y configuración.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    public function extract(SourceSite $sourceSite, array $item): array
    {
        $url = trim((string) ($item['url'] ?? ''));

        if ($url === '') {
            return $item;
        }

        try {
            $response = $this->requestFor($sourceSite)->get($url)->throw();
            $rawHtml = $response->body();

            if (! preg_match('/<html|<!doctype|<article|<main/i', $rawHtml)) {
                return [...$item, 'raw_html' => $rawHtml];
            }

            return $this->fromHtml($item, $rawHtml, $url);
        } catch (Throwable $exception) {
            return [
                ...$item,
                'extraction_error' => $exception->getMessage(),
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function fromHtml(array $item, string $rawHtml, string $url): array
    {
        $document = new DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$rawHtml, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        $xpath = new DOMXPath($document);
        $jsonLd = $this->articleJsonLd($xpath);

        foreach (collect($xpath->query('//script|//style|//noscript|//nav|//footer|//aside|//form') ?: []) as $node) {
            $node->parentNode?->removeChild($node);
        }

        $article = $xpath->query('//article | //*[@itemprop="articleBody"] | //main')?->item(0);
        $html = $article ? $this->innerHtml($article) : '';
        $text = $article ? $this->paragraphText($xpath, $article) : '';

        return [
            ...$item,
            'titulo' => $this->firstFilled([
                data_get($jsonLd, 'headline'),
                $this->meta($xpath, 'og:title'),
                $this->meta($xpath, 'twitter:title', 'name'),
                $this->text($xpath, '//h1'),
                $item['titulo'] ?? null,
            ]),
            'contenido' => $text !== '' ? $text : ($item['contenido'] ?? null),
            'contenido_html' => $html !== '' ? $html : ($item['contenido_html'] ?? null),
            'resumen' => $this->firstFilled([
                data_get($jsonLd, 'description'),
                $this->meta($xpath, 'description', 'name'),
                $item['resumen'] ?? null,
            ]),
            'autor' => $this->firstFilled([
                data_get($jsonLd, 'author.name'),
                data_get($jsonLd, 'author.0.name'),
                $this->meta($xpath, 'author', 'name'),
                $item['autor'] ?? null,
            ]),
            'fecha' => $this->firstFilled([
                data_get($jsonLd, 'datePublished'),
                $this->meta($xpath, 'article:published_time'),
                $item['fecha'] ?? null,
            ]),
            'imagen' => $this->absoluteUrl($this->firstFilled([
                data_get($jsonLd, 'image.url'),
                data_get($jsonLd, 'image.0.url'),
                data_get($jsonLd, 'image.0'),
                data_get($jsonLd, 'image'),
                $this->meta($xpath, 'og:image'),
                $this->meta($xpath, 'twitter:image', 'name'),
                $item['imagen'] ?? null,
            ]), $url),
            'url' => $this->firstFilled([
                $this->meta($xpath, 'og:url'),
                $this->attribute($xpath, '//link[@rel="canonical"]', 'href'),
                $item['url'] ?? null,
            ]),
            'raw_html' => $rawHtml,
        ];
    }

    private function paragraphText(DOMXPath $xpath, DOMNode $context): string
    {
        $paragraphs = collect($xpath->query('.//p | .//h2 | .//h3 | .//li', $context) ?: [])
            ->map(fn (DOMNode $node) => str($node->textContent)->squish()->toString())
            ->filter()
            ->values();

        return $paragraphs->isNotEmpty()
            ? $paragraphs->implode("\n\n")
            : str($context->textContent)->squish()->toString();
    }

    /**
     * @return array<string, mixed>
     */
    private function articleJsonLd(DOMXPath $xpath): array
    {
        foreach ($xpath->query('//script[@type="application/ld+json"]') ?: [] as $node) {
            $decoded = json_decode((string) $node->textContent, true);

            foreach ($this->jsonLdNodes($decoded) as $candidate) {
                $types = (array) ($candidate['@type'] ?? []);

                if (array_intersect($types, ['Article', 'NewsArticle', 'BlogPosting', 'ReportageNewsArticle'])) {
                    return $candidate;
                }
            }
        }

        return [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function jsonLdNodes(mixed $decoded): array
    {
        if (! is_array($decoded)) {
            return [];
        }

        if (isset($decoded['@graph']) && is_array($decoded['@graph'])) {
            return array_values(array_filter($decoded['@graph'], 'is_array'));
        }

        if (array_is_list($decoded)) {
            return array_values(array_filter($decoded, 'is_array'));
        }

        return [$decoded];
    }

    private function meta(DOMXPath $xpath, string $key, string $attribute = 'property'): ?string
    {
        return $this->attribute($xpath, sprintf('//meta[@%s="%s"]', $attribute, $key), 'content');
    }

    private function text(DOMXPath $xpath, string $query): ?string
    {
        $node = $xpath->query($query)?->item(0);

        return $node ? str($node->textContent)->squish()->toString() : null;
    }

    private function attribute(DOMXPath $xpath, string $query, string $attribute): ?string
    {
        $node = $xpath->query($query)?->item(0);

        return $node instanceof DOMElement ? ($node->getAttribute($attribute) ?: null) : null;
    }

    private function innerHtml(DOMNode $node): string
    {
        $html = '';

        foreach ($node->childNodes as $childNode) {
            $html .= $node->ownerDocument?->saveHTML($childNode) ?: '';
        }

        return trim($html);
    }

    /**
     * @param  array<int, mixed>  $values
     */
    private function firstFilled(array $values): mixed
    {
        return collect($values)->first(fn (mixed $value) => filled($value));
    }

    private function absoluteUrl(mixed $url, string $baseUrl): ?string
    {
        if (! is_string($url) || blank($url)) {
            return null;
        }

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        $parts = parse_url($baseUrl);
        $origin = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '');

        return $origin.'/'.ltrim($url, '/');
    }
}
