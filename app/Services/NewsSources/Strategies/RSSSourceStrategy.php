<?php

namespace App\Services\NewsSources\Strategies;

use App\Contracts\SourceStrategyInterface;
use App\Models\SourceSite;
use App\Services\NewsSources\Strategies\Concerns\BuildsSourceRequests;
use App\Services\NewsSources\Strategies\Concerns\NormalizesSourceItems;
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
        return $this->requestFor($sourceSite)
            ->get($sourceSite->url)
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
            return collect($xml->channel->item);
        }

        if (isset($xml->entry)) {
            return collect($xml->entry);
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
        return collect($item->category)
            ->map(fn (SimpleXMLElement $category) => $this->text($category))
            ->filter()
            ->values()
            ->all();
    }

    private function text(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text !== '' ? $text : null;
    }
}
