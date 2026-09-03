<?php

namespace App\Services;

use App\Models\AiArticle;
use App\Models\Company;
use App\Models\Publication;
use App\Models\WordPressSite;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AiProductionReportService
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function filters(array $input): array
    {
        $bounds = $this->dateBounds();
        $dateFrom = filled($input['date_from'] ?? null) ? (string) $input['date_from'] : $bounds['from'];
        $dateTo = filled($input['date_to'] ?? null) ? (string) $input['date_to'] : $bounds['to'];

        return [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'start' => Carbon::createFromFormat('Y-m-d', $dateFrom)->startOfDay(),
            'end' => Carbon::createFromFormat('Y-m-d', $dateTo)->addDay()->startOfDay(),
            'company_id' => filled($input['company_id'] ?? null) ? (int) $input['company_id'] : null,
            'publication_status' => in_array($input['publication_status'] ?? null, ['published', 'unpublished'], true)
                ? $input['publication_status']
                : 'all',
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function build(array $filters): array
    {
        $articles = $this->articleQuery($filters)
            ->with([
                'company:id,name',
                'publications' => fn ($query) => $query
                    ->where('status', Publication::STATUS_PUBLISHED)
                    ->with('wordpressSite.company:id,name')
                    ->orderBy('published_at'),
            ])
            ->orderByDesc('generated_at')
            ->get();

        $failures = AiArticle::query()
            ->where('status', AiArticle::STATUS_FAILED)
            ->where('created_at', '>=', $filters['start'])
            ->where('created_at', '<', $filters['end'])
            ->when($filters['company_id'], fn (Builder $query, int $companyId) => $query->where('company_id', $companyId))
            ->orderByDesc('created_at')
            ->get(['id', 'company_id', 'title', 'model', 'generation_error', 'created_at']);

        $articleRows = $articles->map(fn (AiArticle $article): array => $this->articleRow($article));
        $generated = $articleRows->count();
        $published = $articleRows->where('published', true)->count();
        $publicationSends = (int) $articleRows->sum('publication_count');
        $historicalSends = (int) $articleRows->sum('historical_publication_count');

        return [
            'filters' => $filters,
            'summary' => [
                'generated' => $generated,
                'published' => $published,
                'unpublished' => $generated - $published,
                'publication_rate' => $generated > 0 ? round(($published / $generated) * 100, 1) : null,
                'publication_sends' => $publicationSends,
                'current_destination_sends' => $publicationSends - $historicalSends,
                'historical_destination_sends' => $historicalSends,
                'failed_generations' => $failures->count(),
                'last_generated_at' => $articles->max('generated_at'),
                'latest_failure' => $failures->first(),
            ],
            'daily' => $this->daily($articleRows, $failures),
            'destinations' => $this->destinations($articleRows),
            'articles' => $articleRows,
            'failures' => $failures,
            'companies' => Company::query()->orderBy('name')->get(['id', 'name']),
        ];
    }

    /** @param array<string, mixed> $filters */
    private function articleQuery(array $filters): Builder
    {
        return AiArticle::query()
            ->whereNotNull('generated_at')
            ->whereIn('status', [AiArticle::STATUS_DRAFT, AiArticle::STATUS_GENERATED])
            ->where('generated_at', '>=', $filters['start'])
            ->where('generated_at', '<', $filters['end'])
            ->when($filters['company_id'], fn (Builder $query, int $companyId) => $query->where('company_id', $companyId))
            ->when($filters['publication_status'] === 'published', fn (Builder $query) => $query
                ->whereHas('publications', fn (Builder $publicationQuery) => $publicationQuery->where('status', Publication::STATUS_PUBLISHED)))
            ->when($filters['publication_status'] === 'unpublished', fn (Builder $query) => $query
                ->whereDoesntHave('publications', fn (Builder $publicationQuery) => $publicationQuery->where('status', Publication::STATUS_PUBLISHED)));
    }

    /** @return array<string, mixed> */
    private function articleRow(AiArticle $article): array
    {
        $destinations = $article->publications
            ->map(function (Publication $publication): array {
                $site = $publication->wordpressSite;
                $url = $this->safeUrl($publication->remote_url);

                if (! $site) {
                    $host = $this->urlHost($publication->remote_url);

                    return [
                        'key' => 'historical:'.($host ?: 'unknown'),
                        'profile_id' => null,
                        'name' => $host ? 'Perfil eliminado · '.$host : 'Perfil eliminado',
                        'type' => 'historical',
                        'type_label' => 'Histórico',
                        'company' => null,
                        'url' => $url,
                        'published_at' => $publication->published_at,
                        'historical' => true,
                    ];
                }

                return [
                    'key' => 'profile:'.$site->id,
                    'profile_id' => $site->id,
                    'name' => $site->name,
                    'type' => $site->type,
                    'type_label' => WordPressSite::typeOptions()[$site->type] ?? Str::headline($site->type),
                    'company' => $site->company?->name,
                    'url' => $url,
                    'published_at' => $publication->published_at,
                    'historical' => false,
                ];
            })
            ->values();

        return [
            'id' => $article->id,
            'title' => $article->title ?: 'Generación sin título #'.$article->id,
            'company' => $article->company?->name,
            'generated_at' => $article->generated_at,
            'model' => $article->model,
            'cost' => $article->cost !== null ? (float) $article->cost : null,
            'published' => $destinations->isNotEmpty(),
            'publication_count' => $destinations->count(),
            'current_destination_count' => $destinations->where('historical', false)->count(),
            'historical_publication_count' => $destinations->where('historical', true)->count(),
            'destinations' => $destinations,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $articles
     * @param  Collection<int, AiArticle>  $failures
     * @return Collection<int, array<string, mixed>>
     */
    private function daily(Collection $articles, Collection $failures): Collection
    {
        $successfulByDay = $articles->groupBy(fn (array $article) => $article['generated_at']->toDateString());
        $failedByDay = $failures->groupBy(fn (AiArticle $article) => $article->created_at->toDateString());

        return $successfulByDay->keys()
            ->merge($failedByDay->keys())
            ->unique()
            ->sortDesc()
            ->values()
            ->map(function (string $day) use ($successfulByDay, $failedByDay): array {
                $articles = $successfulByDay->get($day, collect());
                $generated = $articles->count();
                $published = $articles->where('published', true)->count();

                return [
                    'date' => Carbon::createFromFormat('Y-m-d', $day),
                    'generated' => $generated,
                    'published' => $published,
                    'unpublished' => $generated - $published,
                    'publication_sends' => (int) $articles->sum('publication_count'),
                    'failed' => $failedByDay->get($day, collect())->count(),
                ];
            });
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $articles
     * @return Collection<int, array<string, mixed>>
     */
    private function destinations(Collection $articles): Collection
    {
        $destinations = [];

        foreach ($articles as $article) {
            foreach ($article['destinations'] as $destination) {
                $key = $destination['key'];
                $destinations[$key] ??= [
                    ...$destination,
                    'publication_count' => 0,
                    'article_ids' => [],
                ];
                $destinations[$key]['publication_count']++;
                $destinations[$key]['article_ids'][$article['id']] = true;
            }
        }

        return collect($destinations)
            ->map(function (array $destination): array {
                $destination['article_count'] = count($destination['article_ids']);
                unset($destination['article_ids'], $destination['published_at']);

                return $destination;
            })
            ->sortByDesc('article_count')
            ->values();
    }

    /** @return array{from: string, to: string} */
    private function dateBounds(): array
    {
        $firstGenerated = AiArticle::query()->whereNotNull('generated_at')->min('generated_at');
        $firstFailure = AiArticle::query()->where('status', AiArticle::STATUS_FAILED)->min('created_at');
        $candidates = collect([$firstGenerated, $firstFailure])->filter()->map(fn ($date) => Carbon::parse($date));
        $from = $candidates->isEmpty() ? now()->startOfMonth() : $candidates->sort()->first();

        return [
            'from' => $from->toDateString(),
            'to' => now()->toDateString(),
        ];
    }

    private function safeUrl(?string $url): ?string
    {
        return filled($url) && Str::startsWith(Str::lower($url), ['https://', 'http://']) ? $url : null;
    }

    private function urlHost(?string $url): ?string
    {
        return $this->safeUrl($url) ? parse_url($url, PHP_URL_HOST) : null;
    }
}
