<?php

namespace App\Services;

use App\Models\SourcePost;
use App\Models\SourceSite;
use Illuminate\Support\Collection;

class SourcePostService
{
    /**
     * @param  array<string, mixed>  $item
     */
    public function storeNormalizedItem(SourceSite $sourceSite, array $item): SourcePost
    {
        $payload = $this->payloadFor($sourceSite, $item);

        return SourcePost::query()->firstOrCreate(
            ['hash' => $payload['hash']],
            $payload,
        );
    }

    /**
     * @param  iterable<int, array<string, mixed>>  $items
     * @return Collection<int, SourcePost>
     */
    public function storeMany(SourceSite $sourceSite, iterable $items): Collection
    {
        return collect($items)
            ->filter(fn (array $item) => $this->shouldStore($item))
            ->map(fn (array $item) => $this->storeNormalizedItem($sourceSite, $item))
            ->values();
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function shouldStore(array $item): bool
    {
        $url = trim((string) ($item['url'] ?? ''));

        if (blank($item['titulo'] ?? null) || blank($url)) {
            return false;
        }

        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');

        return $path !== '';
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function payloadFor(SourceSite $sourceSite, array $item): array
    {
        $payload = [
            'source_site_id' => $sourceSite->id,
            'title' => (string) ($item['titulo'] ?? ''),
            'content' => $item['contenido'] ?? null,
            'content_html' => $item['contenido_html'] ?? null,
            'summary' => $item['resumen'] ?? null,
            'author' => $item['autor'] ?? null,
            'published_at' => $item['fecha'] ?? null,
            'image_url' => $item['imagen'] ?? null,
            'categories' => $this->listValue($item['categorias'] ?? []),
            'tags' => $this->listValue($item['tags'] ?? []),
            'url' => (string) ($item['url'] ?? ''),
            'status' => SourcePost::STATUS_FETCHED,
            'original_json' => $item['original_json'] ?? $item,
            'language' => $item['idioma'] ?? $sourceSite->language,
        ];

        $payload['hash'] = $this->hashFor($payload);

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function hashFor(array $payload): string
    {
        return hash('sha256', implode('|', [
            $payload['url'],
            $payload['title'],
            $payload['published_at'],
            $payload['content_html'],
        ]));
    }

    /**
     * @return array<int, string>
     */
    private function listValue(mixed $value): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        if (! is_iterable($value)) {
            return [];
        }

        return collect($value)
            ->map(fn (mixed $item) => trim((string) $item))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
