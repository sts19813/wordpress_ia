<?php

namespace App\Services\NewsSources;

use App\Models\SourceScanLog;
use App\Models\SourceSite;
use App\Models\SystemLog;
use App\Services\SourcePostService;
use App\Services\SystemLogService;
use Illuminate\Support\Collection;
use Throwable;

class SourceImportService
{
    public function __construct(
        private readonly SourceManager $sourceManager,
        private readonly SourcePostService $sourcePostService,
        private readonly SourceContentFilter $contentFilter,
        private readonly ArticleContentExtractor $contentExtractor,
        private readonly SystemLogService $systemLogs,
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
            'discarded' => 0,
            'created_post_ids' => [],
            'limits_reached' => [],
            'errors' => [],
        ];

        foreach ($this->sitesFor($sourceSiteId) as $sourceSite) {
            $result['sites']++;

            $siteResult = $this->importSource($sourceSite);

            $result['fetched'] += $siteResult['fetched'];
            $result['created'] += $siteResult['created'];
            $result['duplicates'] += $siteResult['duplicates'];
            $result['discarded'] += $siteResult['discarded'];
            $result['created_post_ids'] = [
                ...$result['created_post_ids'],
                ...$siteResult['created_post_ids'],
            ];

            if ($siteResult['daily_limit_reached']) {
                $result['limits_reached'][] = $sourceSite->name;
            }

            if ($siteResult['error']) {
                $result['errors'][] = $siteResult['error'];
            }
        }

        return $result;
    }

    /**
     * @return array{fetched: int, created: int, duplicates: int, discarded: int, created_post_ids: array<int, int>, consultation_limit: int, error: ?string, daily_limit_reached: bool}
     */
    public function importSource(SourceSite $sourceSite): array
    {
        try {
            $dailyLimit = (int) ($sourceSite->daily_limit ?: 20);
            $maxPerScan = min(
                $dailyLimit,
                max(1, (int) ($sourceSite->max_posts_per_scan ?: 20)),
            );
            $scannedToday = SourceScanLog::query()
                ->where('source_site_id', $sourceSite->id)
                ->whereDate('scanned_at', today())
                ->count();
            $remainingToday = max(0, $dailyLimit - $scannedToday);

            if ($remainingToday === 0) {
                return [
                    'fetched' => 0,
                    'created' => 0,
                    'duplicates' => 0,
                    'discarded' => 0,
                    'created_post_ids' => [],
                    'consultation_limit' => 0,
                    'error' => null,
                    'daily_limit_reached' => true,
                ];
            }

            $fetchSite = $sourceSite->replicate();
            $consultationLimit = min($remainingToday, $maxPerScan);
            $fetchSite->daily_limit = $consultationLimit;
            $items = $this->sourceManager
                ->fetch($fetchSite)
                ->take($consultationLimit)
                ->values();
            $created = 0;
            $duplicates = 0;
            $discarded = 0;
            $createdPostIds = [];

            foreach ($items as $item) {
                if (! $this->sourcePostService->isStorable($item)) {
                    $this->writeLog($sourceSite, $item, null, [
                        'applies' => null,
                        'reason' => 'El elemento no contiene título, URL de nota o apunta a la portada.',
                        'matched_topics' => [],
                        'method' => 'validation',
                    ], SourceScanLog::OUTCOME_INVALID);

                    continue;
                }

                $filter = $this->contentFilter->evaluate($sourceSite, $item);

                if (! $filter['applies']) {
                    $discarded++;
                    $this->writeLog(
                        $sourceSite,
                        $item,
                        null,
                        $filter,
                        SourceScanLog::OUTCOME_DISCARDED,
                    );

                    continue;
                }

                // El contenido completo solo se descarga después de aprobar el filtro.
                $itemToStore = $this->contentExtractor->extract($sourceSite, $item);
                $sourcePost = $this->sourcePostService->storeNormalizedItem($sourceSite, $itemToStore, $filter);

                if ($sourcePost->wasRecentlyCreated) {
                    $created++;
                    $createdPostIds[] = $sourcePost->id;
                    $outcome = SourceScanLog::OUTCOME_ACCEPTED;
                } else {
                    $duplicates++;
                    $outcome = SourceScanLog::OUTCOME_DUPLICATE;
                }

                $this->writeLog($sourceSite, $itemToStore, $sourcePost->id, $filter, $outcome);
            }

            $sourceSite->forceFill([
                'status' => SourceSite::STATUS_ACTIVE,
                'last_synced_at' => now(),
            ])->save();

            return [
                'fetched' => $items->count(),
                'created' => $created,
                'duplicates' => $duplicates,
                'discarded' => $discarded,
                'created_post_ids' => $createdPostIds,
                'consultation_limit' => $consultationLimit,
                'error' => null,
                'daily_limit_reached' => $items->count() >= $remainingToday,
            ];
        } catch (Throwable $exception) {
            $sourceSite->forceFill([
                'status' => SourceSite::STATUS_ERROR,
                'last_synced_at' => now(),
            ])->save();
            $this->systemLogs->recordError(
                SystemLog::EVENT_SOURCE_FAILED,
                'Sitios fuente',
                "{$sourceSite->name}: {$exception->getMessage()}",
                $sourceSite,
                ['source' => $sourceSite->name],
                $sourceSite->automation_user_id,
            );

            return [
                'fetched' => 0,
                'created' => 0,
                'duplicates' => 0,
                'discarded' => 0,
                'created_post_ids' => [],
                'consultation_limit' => 0,
                'error' => "{$sourceSite->name}: {$exception->getMessage()}",
                'daily_limit_reached' => false,
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

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, mixed>  $filter
     */
    private function writeLog(
        SourceSite $sourceSite,
        array $item,
        ?int $sourcePostId,
        array $filter,
        string $outcome,
    ): void {
        SourceScanLog::query()->create([
            'source_site_id' => $sourceSite->id,
            'source_post_id' => $sourcePostId,
            'title' => $item['titulo'] ?? null,
            'url' => $item['url'] ?? null,
            'outcome' => $outcome,
            'applies' => $filter['applies'] ?? null,
            'reason' => $filter['reason'] ?? null,
            'matched_topics' => $filter['matched_topics'] ?? [],
            'filter_method' => $filter['method'] ?? null,
            'metadata' => [
                'categories' => $item['categorias'] ?? [],
                'tags' => $item['tags'] ?? [],
                'published_at' => $item['fecha'] ?? null,
                'connection_type' => ($item['_ai_discovered'] ?? false)
                    ? SourceSite::TYPE_AI_WEB
                    : $sourceSite->type,
                'structure_summary' => $item['_ai_structure_summary'] ?? null,
            ],
            'scanned_at' => now(),
        ]);
    }
}
