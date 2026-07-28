<?php

namespace App\Services\NewsSources;

use App\Contracts\SourceStrategyInterface;
use App\Models\SourceSite;
use App\Services\NewsSources\Strategies\JsonFeedSourceStrategy;
use App\Services\NewsSources\Strategies\RSSSourceStrategy;
use App\Services\NewsSources\Strategies\ScrapingSourceStrategy;
use App\Services\NewsSources\Strategies\SitemapSourceStrategy;
use App\Services\NewsSources\Strategies\WordPressSourceStrategy;
use Illuminate\Support\Collection;
use InvalidArgumentException;

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
            ->register(SourceSite::TYPE_HTML, app(ScrapingSourceStrategy::class));
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
        if ($sourceSite->type === SourceSite::TYPE_AUTO) {
            $detected = $this->discovery->detect($sourceSite);
            $sourceSite = $sourceSite->replicate();
            $sourceSite->type = $detected['type'];
        }

        $strategy = $this->strategyFor($sourceSite);

        $strategy->validate($sourceSite);

        return $strategy->parse(
            $strategy->fetch($sourceSite),
            $sourceSite,
        )->take($sourceSite->daily_limit ?: 20)->values();
    }
}
