<?php

namespace App\Services\NewsSources;

use App\Contracts\SourceStrategyInterface;
use App\Models\SourceSite;
use App\Services\NewsSources\Strategies\RSSSourceStrategy;
use App\Services\NewsSources\Strategies\ScrapingSourceStrategy;
use App\Services\NewsSources\Strategies\WordPressSourceStrategy;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class SourceManager
{
    /**
     * @var array<string, SourceStrategyInterface>
     */
    private array $strategies = [];

    public function __construct()
    {
        $this
            ->register(SourceSite::TYPE_WORDPRESS_REST, app(WordPressSourceStrategy::class))
            ->register(SourceSite::TYPE_RSS, app(RSSSourceStrategy::class))
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
        $strategy = $this->strategyFor($sourceSite);

        $strategy->validate($sourceSite);

        return $strategy->parse(
            $strategy->fetch($sourceSite),
            $sourceSite,
        );
    }
}
