<?php

namespace App\Services\NewsSources\Strategies;

use App\Contracts\SourceStrategyInterface;
use App\Models\SourceSite;
use App\Services\NewsSources\Strategies\Concerns\BuildsSourceRequests;
use App\Services\NewsSources\Strategies\Concerns\NormalizesSourceItems;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class WordPressSourceStrategy implements SourceStrategyInterface
{
    use BuildsSourceRequests;
    use NormalizesSourceItems;

    public function validate(SourceSite $sourceSite): void
    {
        if ($sourceSite->type !== SourceSite::TYPE_WORDPRESS_REST) {
            throw new InvalidArgumentException('La fuente no es de tipo WordPress REST API.');
        }

        if (blank($sourceSite->url)) {
            throw new InvalidArgumentException('La fuente WordPress REST requiere una URL.');
        }
    }

    public function fetch(SourceSite $sourceSite): mixed
    {
        $limit = min(max((int) ($sourceSite->daily_limit ?: 20), 1), 100);
        [$endpoint, $extraQuery] = $this->endpointFor($sourceSite);

        return $this->requestFor($sourceSite)
            ->get($endpoint, array_merge([
                '_embed' => 1,
                'per_page' => $limit,
                'orderby' => 'date',
                'order' => 'desc',
            ], $extraQuery))
            ->throw()
            ->json();
    }

    public function parse(mixed $payload, SourceSite $sourceSite): Collection
    {
        return collect(is_array($payload) ? $payload : [])
            ->filter(fn (mixed $post) => is_array($post))
            ->map(fn (array $post) => $this->normalizeItem([
                'titulo' => data_get($post, 'title.rendered'),
                'contenido' => strip_tags((string) data_get($post, 'content.rendered', '')),
                'autor' => data_get($post, '_embedded.author.0.name') ?: data_get($post, 'author'),
                'fecha' => data_get($post, 'date_gmt') ?: data_get($post, 'date'),
                'imagen' => data_get($post, '_embedded.wp:featuredmedia.0.source_url'),
                'url' => data_get($post, 'link'),
                'categorias' => $this->embeddedTerms($post, 'category'),
                'tags' => $this->embeddedTerms($post, 'post_tag'),
                'contenido_html' => data_get($post, 'content.rendered'),
                'resumen' => strip_tags((string) data_get($post, 'excerpt.rendered', '')),
                'slug' => data_get($post, 'slug'),
                'idioma' => $sourceSite->language,
                'original_json' => $post,
            ], $sourceSite))
            ->filter(fn (array $item) => filled($item['titulo']) && filled($item['url']))
            ->values();
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function endpointFor(SourceSite $sourceSite): array
    {
        $url = rtrim((string) $sourceSite->url, '/');

        if (str_contains($url, '/wp-json/wp/v2/posts')) {
            return [$url, []];
        }

        if (preg_match('#/wp-json/wp/v2/categories/(\d+)#', $url, $matches)) {
            return [$this->apiRootFor($url).'wp/v2/posts', ['categories' => (int) $matches[1]]];
        }

        $discovery = $this->requiresDiscovery($url)
            ? $this->discoverWordPressEndpoint($sourceSite)
            : [];

        return [
            rtrim($discovery['api_root'] ?? $this->apiRootFor($url), '/').'/wp/v2/posts',
            filled($discovery['category_id'] ?? null) ? ['categories' => (int) $discovery['category_id']] : [],
        ];
    }

    private function requiresDiscovery(string $url): bool
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');

        return $path !== '' && ! str_contains($path, 'wp-json');
    }

    /**
     * @return array{api_root?: string, category_id?: int}
     */
    private function discoverWordPressEndpoint(SourceSite $sourceSite): array
    {
        $response = $this->requestFor($sourceSite)
            ->head($sourceSite->url);

        if (! $response->successful()) {
            $response = $this->requestFor($sourceSite)->get($sourceSite->url);
        }

        $linkHeader = (string) $response->header('Link', '');

        $discovery = [];

        if (preg_match('#<([^>]+/wp-json/)>;\s*rel="https://api\.w\.org/"#', $linkHeader, $matches)) {
            $discovery['api_root'] = $matches[1];
        }

        if (preg_match('#<[^>]+/wp-json/wp/v2/categories/(\d+)[^>]*>;\s*rel="alternate"#', $linkHeader, $matches)) {
            $discovery['category_id'] = (int) $matches[1];
        }

        return $discovery;
    }

    private function apiRootFor(string $url): string
    {
        if (preg_match('#^(https?://.+?/wp-json/)#', $url, $matches)) {
            return $matches[1];
        }

        $parts = parse_url($url);
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? rtrim($url, '/');
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return "{$scheme}://{$host}{$port}/wp-json/";
    }

    /**
     * @return array<int, string>
     */
    private function embeddedTerms(array $post, string $taxonomy): array
    {
        return collect(data_get($post, '_embedded.wp:term', []))
            ->flatten(1)
            ->filter(fn (mixed $term) => is_array($term) && data_get($term, 'taxonomy') === $taxonomy)
            ->pluck('name')
            ->filter()
            ->values()
            ->all();
    }
}
