<?php

namespace App\Services\AiArticles;

use App\Models\AiArticle;
use App\Models\SourcePost;
use App\Services\OpenAI\OpenAIService;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class ArticleGenerationEngine
{
    public const DEFAULT_PROMPT = 'article-generation';

    public function __construct(
        private readonly OpenAIService $openAI,
    ) {}

    /**
     * @param  SourcePost|iterable<int, SourcePost>  $sourcePosts
     * @param  array<string, mixed>  $options
     */
    public function prepare(SourcePost|iterable $sourcePosts, array $options = []): ArticleGenerationResult
    {
        $posts = $this->collectSourcePosts($sourcePosts);

        if ($posts->isEmpty()) {
            throw new InvalidArgumentException('Se requiere al menos una noticia para generar un artículo.');
        }

        $model = (string) ($options['model'] ?? 'gpt-4.1');
        $temperature = (float) ($options['temperature'] ?? 0.7);
        $promptName = (string) ($options['prompt'] ?? self::DEFAULT_PROMPT);
        $variables = $this->variablesFor($posts, $options['variables'] ?? []);
        $prompt = $this->openAI->renderer->render(
            $this->openAI->prompts->get($promptName),
            $variables,
        );

        $request = $this->openAI->responses->create($prompt, [
            'model' => $model,
            'temperature' => $temperature,
            'response_format' => ['type' => 'json_object'],
        ]);

        $article = AiArticle::query()->create([
            'source_post_ids' => $posts->pluck('id')->filter()->values()->all(),
            'prompt_used' => $prompt,
            'model' => $model,
            'temperature' => $temperature,
            'status' => AiArticle::STATUS_PENDING,
        ]);

        return new ArticleGenerationResult($article, $request);
    }

    /**
     * @param  array<string, mixed>  $response
     * @param  array<string, mixed>  $metrics
     */
    public function complete(AiArticle $article, array|string $response, array $metrics = []): AiArticle
    {
        $decoded = is_array($response) ? $response : $this->decodeResponse($response);

        $article->update([
            'title' => $decoded['title'] ?? null,
            'content' => $decoded['content'] ?? null,
            'excerpt' => $decoded['excerpt'] ?? null,
            'meta_description' => $decoded['meta_description'] ?? null,
            'slug' => $decoded['slug'] ?? str($decoded['title'] ?? '')->slug()->toString(),
            'categories' => $this->listValue($decoded['categories'] ?? []),
            'tags' => $this->listValue($decoded['tags'] ?? []),
            'seo_keywords' => $this->listValue($decoded['seo_keywords'] ?? []),
            'faqs' => $decoded['faqs'] ?? [],
            'conclusion' => $decoded['conclusion'] ?? null,
            'full_response' => is_string($response) ? $response : json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'tokens' => $metrics['tokens'] ?? null,
            'cost' => $metrics['cost'] ?? null,
            'duration_ms' => $metrics['duration_ms'] ?? null,
            'model' => $metrics['model'] ?? $article->model,
            'temperature' => $metrics['temperature'] ?? $article->temperature,
            'status' => AiArticle::STATUS_GENERATED,
        ]);

        return $article;
    }

    /**
     * @param  array<string, mixed>  $metrics
     */
    public function fail(AiArticle $article, array|string $response, array $metrics = []): AiArticle
    {
        $article->update([
            'full_response' => is_string($response) ? $response : json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'tokens' => $metrics['tokens'] ?? null,
            'cost' => $metrics['cost'] ?? null,
            'duration_ms' => $metrics['duration_ms'] ?? null,
            'model' => $metrics['model'] ?? $article->model,
            'temperature' => $metrics['temperature'] ?? $article->temperature,
            'status' => AiArticle::STATUS_FAILED,
        ]);

        return $article;
    }

    /**
     * @return Collection<int, SourcePost>
     */
    private function collectSourcePosts(SourcePost|iterable $sourcePosts): Collection
    {
        if ($sourcePosts instanceof SourcePost) {
            return collect([$sourcePosts]);
        }

        return collect($sourcePosts)
            ->filter(fn (mixed $sourcePost) => $sourcePost instanceof SourcePost)
            ->values();
    }

    /**
     * @param  Collection<int, SourcePost>  $posts
     * @param  array<string, mixed>  $extraVariables
     * @return array<string, mixed>
     */
    private function variablesFor(Collection $posts, array $extraVariables = []): array
    {
        $categories = $posts->flatMap(fn (SourcePost $post) => $post->categories ?: [])->filter()->unique()->values()->all();
        $tags = $posts->flatMap(fn (SourcePost $post) => $post->tags ?: [])->filter()->unique()->values()->all();

        return [
            'news_items' => $posts->map(fn (SourcePost $post) => [
                'title' => $post->title,
                'content' => $post->content,
                'summary' => $post->summary,
                'author' => $post->author,
                'date' => $post->published_at?->toIso8601String(),
                'url' => $post->url,
                'categories' => $post->categories ?: [],
                'tags' => $post->tags ?: [],
            ])->values()->all(),
            'categories' => $categories,
            'tags' => $tags,
            'language' => $posts->first()?->language ?: 'es',
            ...$extraVariables,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeResponse(string $response): array
    {
        $decoded = json_decode($response, true);

        if (! is_array($decoded)) {
            throw new InvalidArgumentException('La respuesta de generación no es JSON válido.');
        }

        return $decoded;
    }

    /**
     * @return array<int, string>
     */
    private function listValue(mixed $value): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        if (! is_iterable($value)) {
            return [];
        }

        return collect($value)
            ->map(fn (mixed $item) => trim((string) $item))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
