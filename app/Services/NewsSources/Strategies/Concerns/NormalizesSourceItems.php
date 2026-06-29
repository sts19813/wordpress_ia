<?php

namespace App\Services\NewsSources\Strategies\Concerns;

use App\Models\SourceSite;
use Carbon\Carbon;
use Throwable;

trait NormalizesSourceItems
{
    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    protected function normalizeItem(array $item, SourceSite $sourceSite): array
    {
        $contenidoHtml = (string) ($item['contenido_html'] ?? '');
        $contenido = (string) ($item['contenido'] ?? strip_tags($contenidoHtml));
        $titulo = trim((string) ($item['titulo'] ?? ''));
        $resumen = trim((string) ($item['resumen'] ?? str($contenido)->squish()->limit(220)->toString()));
        $url = trim((string) ($item['url'] ?? ''));

        return [
            'titulo' => $titulo,
            'contenido' => str($contenido)->squish()->toString(),
            'autor' => $this->nullableString($item['autor'] ?? null),
            'fecha' => $this->normalizeDate($item['fecha'] ?? null),
            'imagen' => $this->nullableString($item['imagen'] ?? null),
            'url' => $url,
            'categorias' => $this->normalizeList($item['categorias'] ?? []),
            'tags' => $this->normalizeList($item['tags'] ?? []),
            'contenido_html' => $contenidoHtml,
            'resumen' => $resumen,
            'slug' => $this->nullableString($item['slug'] ?? null) ?: str($titulo ?: $url)->slug()->toString(),
            'idioma' => $this->nullableString($item['idioma'] ?? null) ?: $sourceSite->language,
        ];
    }

    protected function normalizeDate(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->toIso8601String();
        } catch (Throwable) {
            return null;
        }
    }

    protected function nullableString(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return trim((string) $value);
    }

    /**
     * @return array<int, string>
     */
    protected function normalizeList(mixed $value): array
    {
        if (blank($value)) {
            return [];
        }

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
