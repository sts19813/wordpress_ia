<?php

namespace App\Services\NewsSources\Strategies;

use App\Contracts\SourceStrategyInterface;
use App\Models\SourceSite;
use App\Services\NewsSources\Strategies\Concerns\BuildsSourceRequests;
use App\Services\NewsSources\Strategies\Concerns\NormalizesSourceItems;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use SimpleXMLElement;

class SitemapSourceStrategy implements SourceStrategyInterface
{
    use BuildsSourceRequests;
    use NormalizesSourceItems;

    public function validate(SourceSite $sourceSite): void
    {
        if ($sourceSite->type !== SourceSite::TYPE_SITEMAP) {
            throw new InvalidArgumentException('La fuente no es un sitemap XML.');
        }

        if (blank($sourceSite->url)) {
            throw new InvalidArgumentException('El sitemap requiere una URL.');
        }
    }

    public function fetch(SourceSite $sourceSite): mixed
    {
        $response = $this->requestFor($sourceSite)->get($sourceSite->url)->throw();
        $body = $response->body();

        if (str_contains($body, '<sitemapindex')) {
            return $this->flattenIndex($sourceSite, $body);
        }

        if (str_contains($body, '<urlset')) {
            return $body;
        }

        $sitemapUrl = rtrim((string) $sourceSite->url, '/').'/sitemap.xml';
        $body = $this->requestFor($sourceSite)->get($sitemapUrl)->throw()->body();

        return str_contains($body, '<sitemapindex')
            ? $this->flattenIndex($sourceSite, $body)
            : $body;
    }

    public function parse(mixed $payload, SourceSite $sourceSite): Collection
    {
        $xml = simplexml_load_string((string) $payload, SimpleXMLElement::class, LIBXML_NOCDATA);

        if (! $xml instanceof SimpleXMLElement) {
            return collect();
        }

        return collect($xml->url ?? [])
            ->map(function (SimpleXMLElement $item) use ($sourceSite): array {
                $namespaces = $item->getNamespaces(true);
                $image = isset($namespaces['image'])
                    ? trim((string) $item->children($namespaces['image'])->image->loc)
                    : null;
                $news = isset($namespaces['news']) ? $item->children($namespaces['news'])->news : null;
                $title = $news ? trim((string) $news->title) : null;
                $date = $news ? trim((string) $news->publication_date) : trim((string) $item->lastmod);

                return $this->placeholderItem(trim((string) $item->loc), $date, $sourceSite, $title, $image);
            })
            ->filter(fn (array $item) => filled($item['url']))
            ->sortByDesc('fecha')
            ->take($sourceSite->daily_limit ?: 20)
            ->values();
    }

    private function placeholderItem(
        string $url,
        ?string $date,
        SourceSite $sourceSite,
        ?string $title = null,
        ?string $image = null,
    ): array {
        $slug = basename(trim((string) parse_url($url, PHP_URL_PATH), '/'));

        return $this->normalizeItem([
            'titulo' => $title ?: str($slug)->replace(['-', '_'], ' ')->title()->toString(),
            'contenido' => '',
            'fecha' => $date,
            'imagen' => $image,
            'url' => $url,
            'categorias' => [],
            'tags' => [],
            'contenido_html' => '',
            'idioma' => $sourceSite->language,
        ], $sourceSite);
    }

    private function flattenIndex(SourceSite $sourceSite, string $body): string
    {
        $index = simplexml_load_string($body, SimpleXMLElement::class, LIBXML_NOCDATA);

        if (! $index instanceof SimpleXMLElement) {
            return $body;
        }

        $urls = [];

        foreach (collect($index->sitemap ?? [])->take(8) as $sitemap) {
            $location = trim((string) $sitemap->loc);

            if ($location === '') {
                continue;
            }

            $childBody = $this->requestFor($sourceSite)->get($location)->throw()->body();
            $child = simplexml_load_string($childBody, SimpleXMLElement::class, LIBXML_NOCDATA);

            if (! $child instanceof SimpleXMLElement) {
                continue;
            }

            foreach ($child->url ?? [] as $url) {
                $urls[] = $url->asXML();

                if (count($urls) >= ($sourceSite->daily_limit ?: 20) * 3) {
                    break 2;
                }
            }
        }

        return '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
            .implode('', $urls)
            .'</urlset>';
    }
}
