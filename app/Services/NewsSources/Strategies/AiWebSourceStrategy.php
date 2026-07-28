<?php

namespace App\Services\NewsSources\Strategies;

use App\Contracts\SourceStrategyInterface;
use App\Models\SourceSite;
use App\Services\NewsSources\AiWebPageAnalyzer;
use App\Services\NewsSources\Strategies\Concerns\BuildsSourceRequests;
use App\Services\NewsSources\Strategies\Concerns\NormalizesSourceItems;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class AiWebSourceStrategy implements SourceStrategyInterface
{
    use BuildsSourceRequests;
    use NormalizesSourceItems;

    public function __construct(
        private readonly AiWebPageAnalyzer $analyzer,
    ) {}

    public function validate(SourceSite $sourceSite): void
    {
        if ($sourceSite->type !== SourceSite::TYPE_AI_WEB) {
            throw new InvalidArgumentException('La fuente no está configurada para navegación con IA.');
        }

        if (blank($sourceSite->url)) {
            throw new InvalidArgumentException('La navegación con IA requiere una URL.');
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
        $analysis = $this->analyzer->discoverPosts($sourceSite, (string) $payload);

        return collect($analysis['posts'])
            ->map(function (array $post) use ($sourceSite, $analysis): array {
                $normalized = $this->normalizeItem([
                    'titulo' => $post['title'],
                    'contenido' => '',
                    'contenido_html' => '',
                    'resumen' => $post['summary'],
                    'autor' => null,
                    'fecha' => $post['published_at'],
                    'imagen' => $post['image_url'],
                    'url' => $post['url'],
                    'categorias' => [],
                    'tags' => [],
                    'idioma' => $sourceSite->language,
                ], $sourceSite);

                return [
                    ...$normalized,
                    '_ai_discovered' => true,
                    '_ai_structure_summary' => $analysis['structure_summary'],
                ];
            })
            ->values();
    }
}
