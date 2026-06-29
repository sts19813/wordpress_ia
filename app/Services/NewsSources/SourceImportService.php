<?php

namespace App\Services\NewsSources;

use App\Models\SourceSite;
use App\Services\SourcePostService;
use Illuminate\Support\Collection;
use Throwable;

class SourceImportService
{
    public function __construct(
        private readonly SourceManager $sourceManager,
        private readonly SourcePostService $sourcePostService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function import(?int $sourceSiteId = null): array
    {
        $result = [
            'sites' => 0,
            'fetched' => 0,
            'created' => 0,
            'duplicates' => 0,
            'errors' => [],
        ];

        foreach ($this->sitesFor($sourceSiteId) as $sourceSite) {
            $result['sites']++;

            $siteResult = $this->importSource($sourceSite);

            $result['fetched'] += $siteResult['fetched'];
            $result['created'] += $siteResult['created'];
            $result['duplicates'] += $siteResult['duplicates'];

            if ($siteResult['error']) {
                $result['errors'][] = $siteResult['error'];
            }
        }

        return $result;
    }

    /**
     * @return array{fetched: int, created: int, duplicates: int, error: ?string}
     */
    public function importSource(SourceSite $sourceSite): array
    {
        try {
            $items = $this->sourceManager->fetch($sourceSite);
            $storedPosts = $this->sourcePostService->storeMany($sourceSite, $items);

            $created = $storedPosts
                ->filter(fn ($sourcePost) => $sourcePost->wasRecentlyCreated)
                ->count();

            $sourceSite->forceFill([
                'status' => SourceSite::STATUS_ACTIVE,
                'last_synced_at' => now(),
            ])->save();

            return [
                'fetched' => $items->count(),
                'created' => $created,
                'duplicates' => $storedPosts->count() - $created,
                'error' => null,
            ];
        } catch (Throwable $exception) {
            $sourceSite->forceFill([
                'status' => SourceSite::STATUS_ERROR,
                'last_synced_at' => now(),
            ])->save();

            return [
                'fetched' => 0,
                'created' => 0,
                'duplicates' => 0,
                'error' => "{$sourceSite->name}: {$exception->getMessage()}",
            ];
        }
    }

    /**
     * @return Collection<int, SourceSite>
     */
    private function sitesFor(?int $sourceSiteId): Collection
    {
        if ($sourceSiteId) {
            return SourceSite::query()
                ->whereKey($sourceSiteId)
                ->get();
        }

        return SourceSite::query()
            ->where('active', true)
            ->where('status', '!=', SourceSite::STATUS_PAUSED)
            ->orderByDesc('priority')
            ->orderBy('name')
            ->get();
    }
}
