<?php

namespace App\Repositories;

use App\Models\SourceSite;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class SourceSiteRepository
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateForAdmin(array $filters): LengthAwarePaginator
    {
        $sort = $this->allowedSorts()[$filters['sort'] ?? 'created_at'] ?? 'created_at';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return SourceSite::query()
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('url', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%")
                        ->orWhere('country', 'like', "%{$search}%")
                        ->orWhere('language', 'like', "%{$search}%");
                });
            })
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('type', $type))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when(($filters['active'] ?? '') !== '', fn (Builder $query) => $query->where('active', $filters['active'] === '1'))
            ->when($filters['category'] ?? null, fn (Builder $query, string $category) => $query->where('category', $category))
            ->when($filters['language'] ?? null, fn (Builder $query, string $language) => $query->where('language', $language))
            ->when($filters['country'] ?? null, fn (Builder $query, string $country) => $query->where('country', $country))
            ->orderBy($sort, $direction)
            ->orderBy('id', 'desc')
            ->paginate(15)
            ->withQueryString();
    }

    /**
     * @return array<string, mixed>
     */
    public function distinctFilterOptions(): array
    {
        return [
            'categories' => SourceSite::query()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
            'languages' => SourceSite::query()->whereNotNull('language')->distinct()->orderBy('language')->pluck('language'),
            'countries' => SourceSite::query()->whereNotNull('country')->distinct()->orderBy('country')->pluck('country'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function allowedSorts(): array
    {
        return [
            'name' => 'name',
            'url' => 'url',
            'type' => 'type',
            'status' => 'status',
            'frequency_minutes' => 'frequency_minutes',
            'category' => 'category',
            'language' => 'language',
            'country' => 'country',
            'priority' => 'priority',
            'daily_limit' => 'daily_limit',
            'last_synced_at' => 'last_synced_at',
            'active' => 'active',
            'created_at' => 'created_at',
        ];
    }
}
