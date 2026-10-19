<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class HelpCenter
{
    /** @return Collection<int, array<string, mixed>> */
    public static function categories(): Collection
    {
        return collect(config('help.categories', []));
    }

    /** @return Collection<int, array<string, mixed>> */
    public static function articles(): Collection
    {
        return collect(config('help.articles', []))->map(function (array $article): array {
            return array_merge([
                'prerequisites' => [],
                'result' => null,
                'common_problems' => [],
                'screenshots' => [],
            ], $article);
        });
    }

    /** @return Collection<int, array<string, mixed>> */
    public static function workflows(): Collection
    {
        return collect(config('help.workflows', []));
    }

    /** @return array<string, mixed>|null */
    public static function article(string $slug): ?array
    {
        return self::articles()->firstWhere('slug', $slug);
    }

    /** @return Collection<int, array<string, mixed>> */
    public static function search(?string $term = null, ?string $category = null): Collection
    {
        $needle = Str::lower(trim((string) $term));

        return self::articles()
            ->filter(function (array $article) use ($needle, $category): bool {
                if ($category && $article['category'] !== $category) {
                    return false;
                }

                if ($needle === '') {
                    return true;
                }

                $searchable = implode(' ', [
                    $article['title'],
                    $article['summary'],
                    implode(' ', $article['keywords'] ?? []),
                    implode(' ', $article['steps'] ?? []),
                ]);

                return Str::contains(Str::lower($searchable), $needle);
            })
            ->values();
    }

    /** @return array<string, mixed>|null */
    public static function contextualArticleForRoute(?string $routeName): ?array
    {
        foreach (config('help.contextual_routes', []) as $prefix => $slug) {
            if (Str::startsWith((string) $routeName, $prefix)) {
                return self::article($slug);
            }
        }

        return null;
    }
}
