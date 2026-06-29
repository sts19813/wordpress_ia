<?php

namespace App\Services;

use App\Models\AiArticle;
use App\Models\SourcePost;
use App\Services\AiArticles\ArticleGenerationEngine;
use App\Services\AiArticles\ArticleGenerationResult;

class AiArticleService
{
    public function __construct(
        private readonly ArticleGenerationEngine $engine,
    ) {}

    /**
     * @param  SourcePost|iterable<int, SourcePost>  $sourcePosts
     * @param  array<string, mixed>  $options
     */
    public function prepareGeneration(SourcePost|iterable $sourcePosts, array $options = []): ArticleGenerationResult
    {
        return $this->engine->prepare($sourcePosts, $options);
    }

    /**
     * @param  array<string, mixed>  $metrics
     */
    public function completeGeneration(AiArticle $article, array|string $response, array $metrics = []): AiArticle
    {
        return $this->engine->complete($article, $response, $metrics);
    }

    /**
     * @param  array<string, mixed>  $metrics
     */
    public function failGeneration(AiArticle $article, array|string $response, array $metrics = []): AiArticle
    {
        return $this->engine->fail($article, $response, $metrics);
    }
}
