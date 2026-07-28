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
    public function storeNormalizedItem(SourceSite $sourceSite, array $item, array $filter = []): SourcePost
    {
        $payload = $this->payloadFor($sourceSite, $item, $filter);
        $existingPost = SourcePost::query()
            ->where('hash', $payload['hash'])
            ->orWhere('url', $payload['url'])
            ->first();

        if ($existingPost) {
            if ($existingPost->source_site_id === $sourceSite->id) {
                $existingPost->fill(collect($payload)
                    ->except(['source_site_id', 'hash', 'url'])
                    ->all())->save();
            }

            return $existingPost;
        }

        return SourcePost::query()->create($payload);
    }

    /**
     * @param  iterable<int, array<string, mixed>>  $items
     * @return Collection<int, SourcePost>
     */
    public function storeMany(SourceSite $sourceSite, iterable $items): Collection
    {
        return collect($items)
            ->filter(fn (array $item) => $this->isStorable($item))
            ->map(fn (array $item) => $this->storeNormalizedItem($sourceSite, $item))
            ->values();
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function isStorable(array $item): bool
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
    private function payloadFor(SourceSite $sourceSite, array $item, array $filter = []): array
    {
        $applies = (bool) ($filter['applies'] ?? true);
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
            'status' => $applies ? SourcePost::STATUS_FETCHED : SourcePost::STATUS_DISCARDED,
            'original_json' => $item['original_json'] ?? $item,
            'language' => $item['idioma'] ?? $sourceSite->language,
            'filter_applies' => $applies,
            'filter_reason' => $filter['reason'] ?? 'Sin filtros temáticos.',
            'matched_topics' => $this->listValue($filter['matched_topics'] ?? []),
            'filter_method' => $filter['method'] ?? 'no_filter',
            'scanned_at' => now(),
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
