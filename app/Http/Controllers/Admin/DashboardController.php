<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiArticle;
use App\Models\Publication;
use App\Models\Scheduler;
use App\Models\SourcePost;
use App\Models\SourceScanLog;
use App\Models\SourceSite;
use App\Models\WordPressSite;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $todayStart = now()->startOfDay();
        $tomorrowStart = $todayStart->copy()->addDay();
        $activityStart = $todayStart->copy()->subDays(6);

        $scansToday = SourceScanLog::query()
            ->where('scanned_at', '>=', $todayStart)
            ->where('scanned_at', '<', $tomorrowStart)
            ->count();
        $acceptedToday = SourceScanLog::query()
            ->where('outcome', SourceScanLog::OUTCOME_ACCEPTED)
            ->where('scanned_at', '>=', $todayStart)
            ->where('scanned_at', '<', $tomorrowStart)
            ->count();
        $generatedToday = AiArticle::query()
            ->where('generated_at', '>=', $todayStart)
            ->where('generated_at', '<', $tomorrowStart)
            ->count();
        $quickPostsToday = SourcePost::query()
            ->where('origin_type', SourcePost::ORIGIN_QUICK_POST)
            ->where('captured_at', '>=', $todayStart)
            ->where('captured_at', '<', $tomorrowStart)
            ->count();
        $publishedToday = Publication::query()
            ->where('status', Publication::STATUS_PUBLISHED)
            ->where('published_at', '>=', $todayStart)
            ->where('published_at', '<', $tomorrowStart)
            ->count();
        $publishedArticlesToday = Publication::query()
            ->where('status', Publication::STATUS_PUBLISHED)
            ->where('published_at', '>=', $todayStart)
            ->where('published_at', '<', $tomorrowStart)
            ->distinct()
            ->count('ai_article_id');

        $schedulerErrorsToday = Scheduler::query()
            ->where('status', Scheduler::STATUS_FAILED)
            ->where('updated_at', '>=', $todayStart)
            ->where('updated_at', '<', $tomorrowStart)
            ->count();
        $publicationErrorsToday = Publication::query()
            ->where('status', Publication::STATUS_FAILED)
            ->where('updated_at', '>=', $todayStart)
            ->where('updated_at', '<', $tomorrowStart)
            ->count();
        $articleErrorsToday = AiArticle::query()
            ->where('status', AiArticle::STATUS_FAILED)
            ->where('updated_at', '>=', $todayStart)
            ->where('updated_at', '<', $tomorrowStart)
            ->count();
        $errorsToday = $schedulerErrorsToday + $publicationErrorsToday + $articleErrorsToday;

        $publicationAttemptsToday = $publishedToday + $publicationErrorsToday;
        $publicationSuccessRate = $publicationAttemptsToday > 0
            ? (int) round(($publishedToday / $publicationAttemptsToday) * 100)
            : null;
        $scanAcceptanceRate = $scansToday > 0
            ? (int) round(($acceptedToday / $scansToday) * 100)
            : null;

        $databaseQueueSize = Schema::hasTable('jobs') ? DB::table('jobs')->count() : 0;
        $failedQueueSize = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;
        $queuedTasks = Scheduler::query()->where('status', Scheduler::STATUS_QUEUED)->count();
        $runningTasks = Scheduler::query()->where('status', Scheduler::STATUS_RUNNING)->count();
        $stalledTasks = Scheduler::query()
            ->where('status', Scheduler::STATUS_QUEUED)
            ->where('created_at', '<=', now()->subMinutes(2))
            ->count();

        $activeSources = SourceSite::query()->where('active', true)->count();
        $sourceErrors = SourceSite::query()
            ->where(fn ($query) => $query
                ->where('status', SourceSite::STATUS_ERROR)
                ->orWhere(fn ($activeQuery) => $activeQuery->where('active', true)->whereNotNull('next_scan_at')->where('next_scan_at', '<', now()->subMinutes(10))))
            ->count();
        $nextScanAt = SourceSite::query()
            ->where('active', true)
            ->whereNotNull('next_scan_at')
            ->min('next_scan_at');
        $activeDestinations = WordPressSite::query()
            ->where('active', true)
            ->where('status', WordPressSite::STATUS_ACTIVE)
            ->count();
        $destinationErrors = WordPressSite::query()
            ->where(fn ($query) => $query
                ->where('status', WordPressSite::STATUS_ERROR)
                ->orWhereNotNull('connection_error'))
            ->count();

        $activity = $this->activitySeries($activityStart, $tomorrowStart);
        $recentErrors = $this->recentErrors();
        $recentTasks = Scheduler::query()
            ->with([
                'article:id,title',
                'sourceSite:id,name',
                'sourcePost:id,title',
                'publication:id,status,remote_url',
            ])
            ->latest('updated_at')
            ->limit(7)
            ->get();

        $publishedArticles = AiArticle::query()
            ->whereHas('publications', fn ($query) => $query->where('status', Publication::STATUS_PUBLISHED))
            ->with([
                'images:id,ai_article_id,type,status',
                'publications' => fn ($query) => $query
                    ->where('status', Publication::STATUS_PUBLISHED)
                    ->with('wordpressSite:id,type,name,rest_api_url,facebook_page_id')
                    ->latest('published_at'),
            ])
            ->withMax([
                'publications as last_published_at' => fn ($query) => $query->where('status', Publication::STATUS_PUBLISHED),
            ], 'published_at')
            ->orderByDesc('last_published_at')
            ->limit(9)
            ->get();

        return view('admin.dashboard.index', [
            'todayLabel' => now()->locale('es')->isoFormat('dddd D [de] MMMM'),
            'scansToday' => $scansToday,
            'acceptedToday' => $acceptedToday,
            'scanAcceptanceRate' => $scanAcceptanceRate,
            'generatedToday' => $generatedToday,
            'quickPostsToday' => $quickPostsToday,
            'publishedToday' => $publishedToday,
            'publishedArticlesToday' => $publishedArticlesToday,
            'publicationSuccessRate' => $publicationSuccessRate,
            'errorsToday' => $errorsToday,
            'activeErrors' => Scheduler::query()->where('status', Scheduler::STATUS_FAILED)->count()
                + Publication::query()->where('status', Publication::STATUS_FAILED)->count(),
            'databaseQueueSize' => $databaseQueueSize,
            'failedQueueSize' => $failedQueueSize,
            'queuedTasks' => $queuedTasks,
            'runningTasks' => $runningTasks,
            'stalledTasks' => $stalledTasks,
            'activeSources' => $activeSources,
            'sourceErrors' => $sourceErrors,
            'nextScanAt' => $nextScanAt ? Carbon::parse($nextScanAt) : null,
            'activeDestinations' => $activeDestinations,
            'destinationErrors' => $destinationErrors,
            'activity' => $activity,
            'recentErrors' => $recentErrors,
            'recentTasks' => $recentTasks,
            'publishedArticles' => $publishedArticles,
        ]);
    }

    /**
     * @return Collection<int, array{
     *     date: Carbon,
     *     scans: int,
     *     generated: int,
     *     published: int,
     *     errors: int,
     *     scan_height: int,
     *     generated_height: int,
     *     published_height: int
     * }>
     */
    private function activitySeries(Carbon $start, Carbon $end): Collection
    {
        $scans = SourceScanLog::query()
            ->selectRaw('DATE(scanned_at) as day, COUNT(*) as aggregate')
            ->where('scanned_at', '>=', $start)
            ->where('scanned_at', '<', $end)
            ->groupBy('day')
            ->pluck('aggregate', 'day');
        $generated = AiArticle::query()
            ->selectRaw('DATE(generated_at) as day, COUNT(*) as aggregate')
            ->where('generated_at', '>=', $start)
            ->where('generated_at', '<', $end)
            ->groupBy('day')
            ->pluck('aggregate', 'day');
        $published = Publication::query()
            ->selectRaw('DATE(published_at) as day, COUNT(*) as aggregate')
            ->where('status', Publication::STATUS_PUBLISHED)
            ->where('published_at', '>=', $start)
            ->where('published_at', '<', $end)
            ->groupBy('day')
            ->pluck('aggregate', 'day');
        $schedulerErrors = Scheduler::query()
            ->selectRaw('DATE(updated_at) as day, COUNT(*) as aggregate')
            ->where('status', Scheduler::STATUS_FAILED)
            ->where('updated_at', '>=', $start)
            ->where('updated_at', '<', $end)
            ->groupBy('day')
            ->pluck('aggregate', 'day');
        $publicationErrors = Publication::query()
            ->selectRaw('DATE(updated_at) as day, COUNT(*) as aggregate')
            ->where('status', Publication::STATUS_FAILED)
            ->where('updated_at', '>=', $start)
            ->where('updated_at', '<', $end)
            ->groupBy('day')
            ->pluck('aggregate', 'day');
        $articleErrors = AiArticle::query()
            ->selectRaw('DATE(updated_at) as day, COUNT(*) as aggregate')
            ->where('status', AiArticle::STATUS_FAILED)
            ->where('updated_at', '>=', $start)
            ->where('updated_at', '<', $end)
            ->groupBy('day')
            ->pluck('aggregate', 'day');

        $days = collect(range(0, 6))->map(function (int $offset) use ($start, $scans, $generated, $published, $schedulerErrors, $publicationErrors, $articleErrors): array {
            $date = $start->copy()->addDays($offset);
            $key = $date->toDateString();

            return [
                'date' => $date,
                'scans' => (int) ($scans[$key] ?? 0),
                'generated' => (int) ($generated[$key] ?? 0),
                'published' => (int) ($published[$key] ?? 0),
                'errors' => (int) ($schedulerErrors[$key] ?? 0)
                    + (int) ($publicationErrors[$key] ?? 0)
                    + (int) ($articleErrors[$key] ?? 0),
            ];
        });
        $maximum = max(1, (int) $days->flatMap(fn (array $day) => [
            $day['scans'],
            $day['generated'],
            $day['published'],
        ])->max());

        return $days->map(function (array $day) use ($maximum): array {
            $day['scan_height'] = max(5, (int) round(($day['scans'] / $maximum) * 94));
            $day['generated_height'] = max(5, (int) round(($day['generated'] / $maximum) * 94));
            $day['published_height'] = max(5, (int) round(($day['published'] / $maximum) * 94));

            return $day;
        });
    }

    /**
     * @return Collection<int, array{title: string, context: string, message: string, occurred_at: Carbon, url: string}>
     */
    private function recentErrors(): Collection
    {
        $taskErrors = Scheduler::query()
            ->where('status', Scheduler::STATUS_FAILED)
            ->latest('updated_at')
            ->limit(6)
            ->get()
            ->map(fn (Scheduler $task): array => [
                'title' => $task->typeLabel(),
                'context' => $task->name ?: 'Trabajo #'.$task->id,
                'message' => $task->last_error ?: 'El trabajo terminó con error.',
                'occurred_at' => $task->updated_at,
                'url' => route('admin.scheduler.index', ['task' => $task->id]),
            ]);
        $publicationErrors = Publication::query()
            ->with(['aiArticle:id,title', 'wordpressSite:id,name'])
            ->where('status', Publication::STATUS_FAILED)
            ->latest('updated_at')
            ->limit(6)
            ->get()
            ->map(fn (Publication $publication): array => [
                'title' => 'Publicación fallida',
                'context' => ($publication->aiArticle?->title ?: 'Artículo eliminado').' · '.($publication->wordpressSite?->name ?: 'Perfil eliminado'),
                'message' => $publication->error_message ?: 'El destino rechazó la publicación.',
                'occurred_at' => $publication->updated_at,
                'url' => route('admin.publications.index'),
            ]);
        $articleErrors = AiArticle::query()
            ->where('status', AiArticle::STATUS_FAILED)
            ->latest('updated_at')
            ->limit(6)
            ->get()
            ->map(fn (AiArticle $article): array => [
                'title' => 'Generación fallida',
                'context' => $article->title ?: 'Artículo #'.$article->id,
                'message' => $article->generation_error ?: 'No fue posible generar el artículo.',
                'occurred_at' => $article->updated_at,
                'url' => route('admin.ai-articles.show', $article),
            ]);

        return $taskErrors
            ->concat($publicationErrors)
            ->concat($articleErrors)
            ->sortByDesc(fn (array $error) => $error['occurred_at']->timestamp)
            ->take(6)
            ->values();
    }
}
