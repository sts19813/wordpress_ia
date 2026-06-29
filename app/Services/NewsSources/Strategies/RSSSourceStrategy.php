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
use SimpleXMLElement;

class RSSSourceStrategy implements SourceStrategyInterface
{
    use BuildsSourceRequests;
    use NormalizesSourceItems;

    public function validate(SourceSite $sourceSite): void
    {
        if ($sourceSite->type !== SourceSite::TYPE_RSS) {
            throw new InvalidArgumentException('La fuente no es de tipo RSS.');
        }

        if (blank($sourceSite->url)) {
            throw new InvalidArgumentException('La fuente RSS requiere una URL.');
        }
    }

    public function fetch(SourceSite $sourceSite): mixed
    {
        $body = $this->requestFor($sourceSite)
            ->get($sourceSite->url)
            ->throw()
            ->body();

        if ($this->looksLikeFeed($body)) {
            return $body;
        }

        $feedUrl = $this->feedUrlFromHtml($body, $sourceSite->url);

        if (! $feedUrl) {
            return $body;
        }

        return $this->requestFor($sourceSite)
            ->get($feedUrl)
            ->throw()
            ->body();
    }

    public function parse(mixed $payload, SourceSite $sourceSite): Collection
    {
        $xml = simplexml_load_string((string) $payload, SimpleXMLElement::class, LIBXML_NOCDATA);

        if (! $xml instanceof SimpleXMLElement) {
            return collect();
        }

        return $this->itemsFrom($xml)
            ->map(fn (SimpleXMLElement $item) => $this->normalizeItem([
                'titulo' => $this->text($item->title),
                'contenido' => strip_tags($this->contentFor($item)),
                'autor' => $this->authorFor($item),
                'fecha' => $this->text($item->pubDate) ?: $this->text($item->published) ?: $this->text($item->updated),
                'imagen' => $this->imageFor($item),
                'url' => $this->linkFor($item),
                'categorias' => $this->categoriesFor($item),
                'tags' => [],
                'contenido_html' => $this->contentFor($item),
                'resumen' => strip_tags($this->text($item->description) ?: $this->text($item->summary)),
                'slug' => null,
                'idioma' => $sourceSite->language,
            ], $sourceSite))
            ->filter(fn (array $item) => filled($item['titulo']) && filled($item['url']))
            ->values();
    }

    /**
     * @return Collection<int, SimpleXMLElement>
     */
    private function itemsFrom(SimpleXMLElement $xml): Collection
    {
        if (isset($xml->channel->item)) {
            $items = [];

            foreach ($xml->channel->item as $item) {
                $items[] = $item;
            }

            return collect($items);
        }

        if (isset($xml->entry)) {
            $items = [];

            foreach ($xml->entry as $item) {
                $items[] = $item;
            }

            return collect($items);
        }

        return collect();
    }

    private function contentFor(SimpleXMLElement $item): string
    {
        $content = $item->children('content', true);

        return $this->text($content->encoded)
            ?: $this->text($item->description)
            ?: $this->text($item->summary)
            ?: $this->text($item->content);
    }

    private function authorFor(SimpleXMLElement $item): ?string
    {
        $dc = $item->children('dc', true);

        return $this->text($dc->creator)
            ?: $this->text($item->author->name)
            ?: $this->text($item->author);
    }

    private function imageFor(SimpleXMLElement $item): ?string
    {
        if (isset($item->enclosure)) {
            $attributes = $item->enclosure->attributes();
            $url = (string) ($attributes['url'] ?? '');

            if (filled($url)) {
                return $url;
            }
        }

        $media = $item->children('media', true);

        if (isset($media->content)) {
            $attributes = $media->content->attributes();

            return (string) ($attributes['url'] ?? '') ?: null;
        }

        return null;
    }

    private function linkFor(SimpleXMLElement $item): ?string
    {
        if (isset($item->link)) {
            $attributes = $item->link->attributes();

            return (string) ($attributes['href'] ?? '') ?: $this->text($item->link);
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function categoriesFor(SimpleXMLElement $item): array
    {
        $categories = [];

        foreach ($item->category as $category) {
            $categories[] = $this->text($category);
        }

        return collect($categories)->filter()->values()->all();
    }

    private function text(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text !== '' ? $text : null;
    }

    private function looksLikeFeed(string $body): bool
    {
        $body = ltrim($body);

        return str_starts_with($body, '<rss')
            || str_starts_with($body, '<feed')
            || (str_starts_with($body, '<?xml') && (str_contains($body, '<rss') || str_contains($body, '<feed')));
    }

    private function feedUrlFromHtml(string $html, string $baseUrl): ?string
    {
        $document = new DOMDocument;

        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();

        $xpath = new DOMXPath($document);
        $links = collect($xpath->query('//link[@href]') ?: [])
            ->filter(fn (mixed $node) => $node instanceof DOMElement)
            ->filter(function (DOMElement $link): bool {
                $type = strtolower($link->getAttribute('type'));

                return str_contains($type, 'rss+xml') || str_contains($type, 'atom+xml');
            });

        $preferred = $links->first(function (DOMElement $link): bool {
            $href = $link->getAttribute('href');
            $title = strtolower($link->getAttribute('title'));

            return str_contains($href, '/category/')
                || str_contains($title, 'categor')
                || str_contains($title, 'category');
        }) ?: $links->first(fn (DOMElement $link): bool => ! str_contains($link->getAttribute('href'), '/comments/feed/'));

        if (! $preferred instanceof DOMElement) {
            return null;
        }

        return $this->absoluteUrl($preferred->getAttribute('href'), $baseUrl);
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
