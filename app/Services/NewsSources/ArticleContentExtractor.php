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

    public function __construct(
        private readonly AiWebPageAnalyzer $aiAnalyzer,
        private readonly ArticleBodyCleaner $bodyCleaner,
    ) {}

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
        $existingBody = $this->bodyCleaner->clean(
            $item['contenido_html'] ?? null,
            $item['contenido'] ?? null,
        );

        if ($this->isCompleteBody($sourceSite, $existingBody) && ($item['_ai_discovered'] ?? false) !== true) {
            return [
                ...$item,
                'contenido' => $existingBody['text'],
                'contenido_html' => $existingBody['html'],
                'raw_html' => $item['contenido_html'] ?? null,
            ];
        }

        if ($url === '') {
            return $this->withExistingBody($item, $existingBody);
        }

        try {
            $rawHtml = $this->sourceDocument($sourceSite, $url);

            if (! preg_match('/<html|<!doctype|<article|<main/i', $rawHtml)) {
                return [
                    ...$this->withExistingBody($item, $existingBody),
                    'raw_html' => $rawHtml,
                ];
            }

            if (($item['_ai_discovered'] ?? false) === true || $sourceSite->type === SourceSite::TYPE_AI_WEB) {
                try {
                    $aiExtracted = [
                        ...$item,
                        ...$this->aiAnalyzer->extractArticle($sourceSite, $rawHtml, $url, $item),
                    ];
                    $aiBody = $this->bodyCleaner->clean(
                        $aiExtracted['contenido_html'] ?? null,
                        $aiExtracted['contenido'] ?? null,
                    );

                    return [
                        ...$aiExtracted,
                        'contenido' => $aiBody['text'],
                        'contenido_html' => $aiBody['html'],
                    ];
                } catch (Throwable $exception) {
                    return [
                        ...$this->fromHtml($item, $rawHtml, $url, $existingBody),
                        'extraction_error' => 'La extracción con IA falló; se usó el extractor HTML: '.$exception->getMessage(),
                    ];
                }
            }

            return $this->fromHtml($item, $rawHtml, $url, $existingBody);
        } catch (Throwable $exception) {
            return [
                ...$this->withExistingBody($item, $existingBody),
                'extraction_error' => $exception->getMessage(),
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array{html: string, text: string, score: int, paragraphs: int}  $existingBody
     * @return array<string, mixed>
     */
    private function fromHtml(array $item, string $rawHtml, string $url, array $existingBody): array
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

        $article = $this->bestArticleNode($xpath);
        $html = $article ? $this->innerHtml($article) : '';
        $extractedBody = $this->bodyCleaner->clean(
            $html,
            $article ? $this->paragraphText($xpath, $article) : null,
        );
        $body = $existingBody['score'] >= $extractedBody['score']
            ? $existingBody
            : $extractedBody;

        return [
            ...$item,
            'titulo' => $this->firstFilled([
                $this->meta($xpath, 'og:title'),
                data_get($jsonLd, 'headline'),
                $this->meta($xpath, 'twitter:title', 'name'),
                $this->text($xpath, '//h1'),
                $item['titulo'] ?? null,
            ]),
            'contenido' => $body['text'] !== '' ? $body['text'] : ($item['contenido'] ?? null),
            'contenido_html' => $body['html'] !== '' ? $body['html'] : ($item['contenido_html'] ?? null),
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

    /**
     * @param  array{html: string, text: string, score: int, paragraphs: int}  $body
     */
    private function isCompleteBody(SourceSite $sourceSite, array $body): bool
    {
        if ($sourceSite->type === SourceSite::TYPE_WORDPRESS_REST) {
            return $body['paragraphs'] >= 2
                && mb_strlen($body['text']) >= 300;
        }

        return $body['paragraphs'] >= 3
            && mb_strlen($body['text']) >= 700;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array{html: string, text: string, score: int, paragraphs: int}  $existingBody
     * @return array<string, mixed>
     */
    private function withExistingBody(array $item, array $existingBody): array
    {
        return [
            ...$item,
            'contenido' => $existingBody['text'] !== '' ? $existingBody['text'] : ($item['contenido'] ?? null),
            'contenido_html' => $existingBody['html'] !== '' ? $existingBody['html'] : ($item['contenido_html'] ?? null),
        ];
    }

    private function bestArticleNode(DOMXPath $xpath): ?DOMNode
    {
        $explicitQuery = '//*[@itemprop="articleBody"]'
            .' | //*[contains(translate(@class, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "entry-content")]'
            .' | //*[contains(translate(@class, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "article-body")]'
            .' | //*[contains(translate(@class, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "article-content")]'
            .' | //*[contains(translate(@class, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "post-content")]'
            .' | //*[contains(translate(@class, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "blog-content")]';

        $explicit = collect($xpath->query($explicitQuery) ?: [])
            ->filter(fn (mixed $node) => $node instanceof DOMElement)
            ->unique(fn (DOMElement $node) => spl_object_id($node))
            ->sortByDesc(fn (DOMElement $node) => $this->articleNodeScore($xpath, $node))
            ->first();

        if ($explicit instanceof DOMElement
            && mb_strlen(str($explicit->textContent)->squish()->toString()) >= 250
            && ($xpath->query('.//p', $explicit)?->length ?? 0) >= 2) {
            return $explicit;
        }

        return collect($xpath->query('//article | //main') ?: [])
            ->filter(fn (mixed $node) => $node instanceof DOMElement)
            ->unique(fn (DOMElement $node) => spl_object_id($node))
            ->sortByDesc(fn (DOMElement $node) => $this->articleNodeScore($xpath, $node))
            ->first();
    }

    private function articleNodeScore(DOMXPath $xpath, DOMElement $node): int
    {
        $textLength = mb_strlen(str($node->textContent)->squish()->toString());
        $paragraphs = $xpath->query('.//p', $node)?->length ?? 0;
        $headings = $xpath->query('.//h1 | .//h2 | .//h3', $node)?->length ?? 0;
        $identity = strtolower($node->getAttribute('class').' '.$node->getAttribute('id'));
        $score = $textLength + ($paragraphs * 180) + ($headings * 80);

        if ($node->getAttribute('itemprop') === 'articleBody') {
            $score += 4000;
        }

        if (str_contains($identity, 'entry-content')
            || str_contains($identity, 'article-body')
            || str_contains($identity, 'article-content')
            || str_contains($identity, 'post-content')) {
            $score += 3000;
        }

        if (strtolower($node->tagName) === 'article') {
            $score += 500;
        }

        foreach (['search', 'quick', 'aside', 'related', 'recommend', 'footer', 'header', 'sidebar', 'share', 'promo', 'card', 'list'] as $penalty) {
            if (str_contains($identity, $penalty)) {
                $score -= 5000;
            }
        }

        if ($textLength < 250 || $paragraphs === 0) {
            $score -= 2500;
        }

        return $score;
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
