<?php

namespace App\Services\Publications;

use App\Models\AiArticle;
use App\Models\Publication;
use App\Models\WordPressSite;

class CompanyArticleUrlResolver
{
    /**
     * Returns the public WordPress URL for the same company as the social profile.
     * A social profile without a company can never fall back to another company's site.
     */
    public function resolve(WordPressSite $profile, AiArticle $article): ?string
    {
        if (! $profile->company_id) {
            return null;
        }

        return $article->publications()
            ->where('status', Publication::STATUS_PUBLISHED)
            ->whereNotNull('remote_url')
            ->whereHas('wordpressSite', fn ($query) => $query
                ->where('type', WordPressSite::TYPE_WORDPRESS)
                ->where('company_id', $profile->company_id))
            ->latest('published_at')
            ->value('remote_url');
    }
}
