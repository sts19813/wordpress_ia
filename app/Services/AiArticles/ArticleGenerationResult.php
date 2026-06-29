<?php

namespace App\Services\AiArticles;

use App\Models\AiArticle;
use App\Services\OpenAI\Data\OpenAIRequest;

class ArticleGenerationResult
{
    public function __construct(
        public readonly AiArticle $article,
        public readonly OpenAIRequest $request,
    ) {}
}
