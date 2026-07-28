<?php

namespace App\Services\NewsSources;

use App\Models\SourceSite;
use RuntimeException;

class SourceSiteTester
{
    public function __construct(
        private readonly SourceDiscoveryService $discovery,
        private readonly SourceManager $manager,
        private readonly ArticleContentExtractor $extractor,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function test(SourceSite $sourceSite): array
    {
        $resolved = $this->manager->resolveAndFetch($sourceSite);
        $testedType = $resolved['type'];
        $items = $resolved['items']
            ->sortByDesc(fn (array $item) => $item['fecha'] ?? '')
            ->values();

        try {
            $detection = $this->discovery->detect($sourceSite);
        } catch (\Throwable) {
            $detection = $this->recommendationFor($testedType, []);
        }

        if ($resolved['fallback_used'] || $testedType === SourceSite::TYPE_AI_WEB) {
            $detection = $this->recommendationFor(
                SourceSite::TYPE_AI_WEB,
                $detection['capabilities'] ?? [],
            );
        }

        $testSite = $sourceSite->replicate();
        $testSite->type = $testedType;
        $latest = $items->first();

        if (! is_array($latest)) {
            throw new RuntimeException('La conexión fue correcta, pero no se encontró una publicación reciente.');
        }

        $latest = $this->extractor->extract($testSite, $latest);
        $content = trim((string) ($latest['contenido'] ?? ''));

        return [
            'ok' => true,
            'tested_type' => $testedType,
            'tested_type_label' => SourceSite::typeOptions()[$testedType] ?? $testedType,
            'recommendation' => $detection,
            'items_found' => $items->count(),
            'post' => [
                'title' => $latest['titulo'] ?? null,
                'url' => $latest['url'] ?? null,
                'image_url' => $latest['imagen'] ?? null,
                'author' => $latest['autor'] ?? null,
                'published_at' => $latest['fecha'] ?? null,
                'summary' => $latest['resumen'] ?? null,
                'categories' => $latest['categorias'] ?? [],
                'tags' => $latest['tags'] ?? [],
                'content' => $content,
                'content_html' => $latest['contenido_html'] ?? null,
                'raw_html' => $latest['raw_html'] ?? null,
                'extraction_error' => $latest['extraction_error'] ?? null,
                'has_full_content' => mb_strlen($content) >= 300,
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $capabilities
     * @return array<string, mixed>
     */
    private function recommendationFor(string $type, array $capabilities): array
    {
        $capability = [
            'type' => $type,
            'label' => SourceSite::typeOptions()[$type] ?? $type,
            'confidence' => $type === SourceSite::TYPE_AI_WEB ? 70 : 50,
            'reason' => $type === SourceSite::TYPE_AI_WEB
                ? 'Los conectores convencionales no localizaron publicaciones; la IA sí pudo interpretar la estructura.'
                : 'La conexión pudo obtener publicaciones.',
        ];

        return [
            ...$capability,
            'capabilities' => collect([$capability, ...$capabilities])
                ->unique('type')
                ->values()
                ->all(),
        ];
    }
}
