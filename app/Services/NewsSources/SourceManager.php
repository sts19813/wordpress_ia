<?php

namespace App\Services\NewsSources;

use App\Contracts\SourceStrategyInterface;
use App\Models\SourceSite;
use App\Services\NewsSources\Strategies\AiWebSourceStrategy;
use App\Services\NewsSources\Strategies\JsonFeedSourceStrategy;
use App\Services\NewsSources\Strategies\RSSSourceStrategy;
use App\Services\NewsSources\Strategies\ScrapingSourceStrategy;
use App\Services\NewsSources\Strategies\SitemapSourceStrategy;
use App\Services\NewsSources\Strategies\WordPressSourceStrategy;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Throwable;

class SourceManager
{
    /**
     * @var array<string, SourceStrategyInterface>
     */
    private array $strategies = [];

    private SourceDiscoveryService $discovery;

    public function __construct(?SourceDiscoveryService $discovery = null)
    {
        $this->discovery = $discovery ?: app(SourceDiscoveryService::class);

        $this
            ->register(SourceSite::TYPE_WORDPRESS_REST, app(WordPressSourceStrategy::class))
            ->register(SourceSite::TYPE_RSS, app(RSSSourceStrategy::class))
            ->register(SourceSite::TYPE_JSON_FEED, app(JsonFeedSourceStrategy::class))
            ->register(SourceSite::TYPE_SITEMAP, app(SitemapSourceStrategy::class))
            ->register(SourceSite::TYPE_HTML, app(ScrapingSourceStrategy::class))
            ->register(SourceSite::TYPE_AI_WEB, app(AiWebSourceStrategy::class));
    }

    public function register(string $type, SourceStrategyInterface $strategy): self
    {
        $this->strategies[$type] = $strategy;

        return $this;
    }

    public function strategyFor(SourceSite $sourceSite): SourceStrategyInterface
    {
        return $this->strategies[$sourceSite->type]
            ?? throw new InvalidArgumentException("No existe una estrategia para el tipo [{$sourceSite->type}].");
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function fetch(SourceSite $sourceSite): Collection
    {
        return $this->resolveAndFetch($sourceSite)['items'];
    }

    /**
     * @return array{type: string, items: Collection<int, array<string, mixed>>, fallback_used: bool}
     */
    public function resolveAndFetch(SourceSite $sourceSite): array
    {
        if ($sourceSite->type !== SourceSite::TYPE_AUTO) {
            return [
                'type' => $sourceSite->type,
                'items' => $this->fetchUsing($sourceSite, $sourceSite->type),
                'fallback_used' => false,
            ];
        }

        try {
            $detected = $this->discovery->detect($sourceSite);
            $detectedType = $detected['type'];
            $items = $this->fetchUsing($sourceSite, $detectedType);

            if ($this->hasUsablePost($items) || ! $this->canUseAiFallback()) {
                return [
                    'type' => $detectedType,
                    'items' => $items,
                    'fallback_used' => false,
                ];
            }
        } catch (Throwable $exception) {
            if (! $this->canUseAiFallback()) {
                throw $exception;
            }
        }

        return [
            'type' => SourceSite::TYPE_AI_WEB,
            'items' => $this->fetchUsing($sourceSite, SourceSite::TYPE_AI_WEB),
            'fallback_used' => true,
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function fetchUsing(SourceSite $sourceSite, string $type): Collection
    {
        $resolvedSite = $sourceSite->replicate();
        $resolvedSite->type = $type;
        $strategy = $this->strategyFor($resolvedSite);

        $strategy->validate($resolvedSite);

        return $strategy->parse(
            $strategy->fetch($resolvedSite),
            $resolvedSite,
        )->take($resolvedSite->daily_limit ?: 20)->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     */
    private function hasUsablePost(Collection $items): bool
    {
        return $items->contains(function (array $item): bool {
            if (($item['_html_document_fallback'] ?? false) === true) {
                return false;
            }

            $url = trim((string) ($item['url'] ?? ''));
            $path = trim((string) parse_url($url, PHP_URL_PATH), '/');

            return filled($item['titulo'] ?? null) && $url !== '' && $path !== '';
        });
    }

    private function canUseAiFallback(): bool
    {
        return filled(config('services.openai.api_key'));
    }
}
