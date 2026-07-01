<?php

namespace App\Services\AiArticles;

use App\Models\AiArticle;
use App\Models\SourcePost;
use App\Services\OpenAI\OpenAIService;
use App\Support\SafeHtml;
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

        $model = (string) ($options['model'] ?? config('services.openai.text_model', 'gpt-4.1-mini'));
        $temperature = (float) ($options['temperature'] ?? 0.7);
        $promptName = (string) ($options['prompt'] ?? self::DEFAULT_PROMPT);
        $variables = $this->variablesFor($posts, $options['variables'] ?? []);
        $prompt = $this->openAI->renderer->render(
            $this->openAI->prompts->get($promptName),
            $variables,
        );

        $systemPrompt = trim((string) ($options['system_prompt'] ?? ''));
        $maxOutputTokens = (int) ($options['max_output_tokens'] ?? 4000);
        $requestOptions = [
            'model' => $model,
            'instructions' => $systemPrompt !== '' ? $systemPrompt : null,
            'max_output_tokens' => $maxOutputTokens,
            'store' => false,
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => 'draft_article',
                    'strict' => true,
                    'schema' => $this->articleSchema(),
                ],
            ],
        ];

        if ($this->supportsTemperature($model)) {
            $requestOptions['temperature'] = $temperature;
        }

        $request = $this->openAI->responses->create($prompt, array_filter(
            $requestOptions,
            fn (mixed $value) => $value !== null,
        ));

        $article = AiArticle::query()->create([
            'source_post_ids' => $posts->pluck('id')->filter()->values()->all(),
            'user_id' => $options['user_id'] ?? null,
            'ai_prompt_profile_id' => $options['ai_prompt_profile_id'] ?? null,
            'prompt_used' => trim($systemPrompt."\n\n".$prompt),
            'model' => $model,
            'temperature' => $temperature,
            'writing_style' => $options['writing_style'] ?? null,
            'tone' => $options['tone'] ?? null,
            'content_length' => $options['content_length'] ?? null,
            'language' => $options['language'] ?? $posts->first()?->language ?? 'es',
            'audience' => $options['audience'] ?? null,
            'max_output_tokens' => $maxOutputTokens,
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
            'content' => SafeHtml::clean($decoded['content'] ?? null),
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
            'generation_error' => null,
            'generated_at' => now(),
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
            'generation_error' => is_string($response) ? $response : (data_get($response, 'error.message') ?: 'No fue posible generar el artículo.'),
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
            ...$extraVariables,
            'language' => $extraVariables['language'] ?? $posts->first()?->language ?: 'es',
            'writing_style' => $extraVariables['writing_style'] ?? 'periodístico informativo',
            'tone' => $extraVariables['tone'] ?? 'claro, objetivo y profesional',
            'content_length' => $this->contentLengthInstruction((string) ($extraVariables['content_length'] ?? 'medium')),
            'audience' => $extraVariables['audience'] ?? 'público general',
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

    private function contentLengthInstruction(string $length): string
    {
        return match ($length) {
            'short' => 'Entre 400 y 600 palabras.',
            'long' => 'Entre 1,200 y 1,600 palabras.',
            default => 'Entre 700 y 1,000 palabras.',
        };
    }

    private function supportsTemperature(string $model): bool
    {
        return ! str($model)->startsWith(['gpt-5', 'o1', 'o3', 'o4']);
    }

    /**
     * @return array<string, mixed>
     */
    private function articleSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['title', 'content', 'excerpt', 'meta_description', 'slug', 'categories', 'tags', 'seo_keywords', 'faqs', 'conclusion'],
            'properties' => [
                'title' => ['type' => 'string'],
                'content' => ['type' => 'string'],
                'excerpt' => ['type' => 'string'],
                'meta_description' => ['type' => 'string'],
                'slug' => ['type' => 'string'],
                'categories' => ['type' => 'array', 'items' => ['type' => 'string']],
                'tags' => ['type' => 'array', 'items' => ['type' => 'string']],
                'seo_keywords' => ['type' => 'array', 'items' => ['type' => 'string']],
                'faqs' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['question', 'answer'],
                        'properties' => [
                            'question' => ['type' => 'string'],
                            'answer' => ['type' => 'string'],
                        ],
                    ],
                ],
                'conclusion' => ['type' => 'string'],
            ],
        ];
    }
}
