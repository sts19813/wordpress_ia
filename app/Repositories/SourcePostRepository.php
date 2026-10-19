<?php

namespace App\Repositories;

use App\Models\SourcePost;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class SourcePostRepository
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function getForAdmin(array $filters): Collection
    {
        return $this->adminQuery($filters)->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateForAdmin(array $filters, int $perPage = 50): LengthAwarePaginator
    {
        return $this->adminQuery($filters)
            ->select([
                'id',
                'source_site_id',
                'origin_type',
                'social_platform',
                'title',
                'author',
                'published_at',
                'url',
                'status',
            ])
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function adminQuery(array $filters): Builder
    {
        $sort = $this->allowedSorts()[$filters['sort'] ?? 'published_at'] ?? 'published_at';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return SourcePost::query()
            ->with('sourceSite:id,name')
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';

                $query->where(function (Builder $query) use ($like): void {
                    $query
                        ->where('title', 'like', $like)
                        ->orWhere('author', 'like', $like)
                        ->orWhere('url', 'like', $like)
                        ->orWhereHas('sourceSite', fn (Builder $siteQuery) => $siteQuery->where('name', 'like', $like));
                });
            })
            ->when($filters['source_site_id'] ?? null, fn (Builder $query, string $sourceSiteId) => $query->where('source_site_id', $sourceSiteId))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when(! filled($filters['status'] ?? null), fn (Builder $query) => $query->where('status', SourcePost::STATUS_FETCHED))
            ->when($filters['language'] ?? null, fn (Builder $query, string $language) => $query->where('language', $language))
            ->when($filters['author'] ?? null, fn (Builder $query, string $author) => $query->where('author', $author))
            ->when($filters['category'] ?? null, fn (Builder $query, string $category) => $query->whereJsonContains('categories', $category))
            ->when($filters['tag'] ?? null, fn (Builder $query, string $tag) => $query->whereJsonContains('tags', $tag))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('published_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('published_at', '<=', $date))
            ->orderBy($sort, $direction)
            ->orderBy('id', 'desc');
    }

    /**
     * @return array<string, string>
     */
    private function allowedSorts(): array
    {
        return [
            'title' => 'title',
            'author' => 'author',
            'published_at' => 'published_at',
            'status' => 'status',
            'language' => 'language',
            'created_at' => 'created_at',
        ];
    }
}
