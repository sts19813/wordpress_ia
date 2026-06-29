<?php

namespace App\Repositories;

use App\Models\SourcePost;
use App\Models\SourceSite;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class SourcePostRepository
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateForAdmin(array $filters): LengthAwarePaginator
    {
        $sort = $this->allowedSorts()[$filters['sort'] ?? 'published_at'] ?? 'published_at';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return SourcePost::query()
            ->with('sourceSite:id,name')
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('content', 'like', "%{$search}%")
                        ->orWhere('summary', 'like', "%{$search}%")
                        ->orWhere('author', 'like', "%{$search}%")
                        ->orWhere('url', 'like', "%{$search}%")
                        ->orWhere('hash', 'like', "%{$search}%");
                });
            })
            ->when($filters['source_site_id'] ?? null, fn (Builder $query, string $sourceSiteId) => $query->where('source_site_id', $sourceSiteId))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['language'] ?? null, fn (Builder $query, string $language) => $query->where('language', $language))
            ->when($filters['author'] ?? null, fn (Builder $query, string $author) => $query->where('author', $author))
            ->when($filters['category'] ?? null, fn (Builder $query, string $category) => $query->whereJsonContains('categories', $category))
            ->when($filters['tag'] ?? null, fn (Builder $query, string $tag) => $query->whereJsonContains('tags', $tag))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('published_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('published_at', '<=', $date))
            ->orderBy($sort, $direction)
            ->orderBy('id', 'desc')
            ->paginate(15)
            ->withQueryString();
    }

    /**
     * @return array<string, mixed>
     */
    public function filterOptions(): array
    {
        return [
            'sourceSites' => SourceSite::query()->orderBy('name')->pluck('name', 'id'),
            'languages' => SourcePost::query()->whereNotNull('language')->distinct()->orderBy('language')->pluck('language'),
            'authors' => SourcePost::query()->whereNotNull('author')->distinct()->orderBy('author')->pluck('author'),
            'categories' => SourcePost::query()
                ->whereNotNull('categories')
                ->get(['categories'])
                ->flatMap(fn (SourcePost $sourcePost) => $sourcePost->categories ?: [])
                ->filter()
                ->unique()
                ->sort()
                ->values(),
            'tags' => SourcePost::query()
                ->whereNotNull('tags')
                ->get(['tags'])
                ->flatMap(fn (SourcePost $sourcePost) => $sourcePost->tags ?: [])
                ->filter()
                ->unique()
                ->sort()
                ->values(),
        ];
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
