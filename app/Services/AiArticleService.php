<?php

namespace App\Services;

use App\Models\AiArticle;
use App\Models\AiPromptProfile;
use App\Models\SourcePost;
use App\Models\User;
use App\Services\AiArticles\ArticleGenerationEngine;
use App\Services\AiArticles\ArticleGenerationResult;
use App\Services\OpenAI\OpenAIClient;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class AiArticleService
{
    public function __construct(
        private readonly ArticleGenerationEngine $engine,
        private readonly AiImageService $images,
        private readonly OpenAIClient $client,
    ) {}

    /**
     * @param  iterable<int, SourcePost>  $sourcePosts
     */
    public function generateDraft(User $user, AiPromptProfile $profile, iterable $sourcePosts): AiArticle
    {
        $startedAt = hrtime(true);
        $result = $this->prepareGeneration($sourcePosts, [
            'user_id' => $user->id,
            'ai_prompt_profile_id' => $profile->id,
            'model' => $profile->model,
            'temperature' => (float) $profile->temperature,
            'system_prompt' => $profile->system_prompt,
            'max_output_tokens' => $profile->max_output_tokens,
            'writing_style' => $profile->writing_style,
            'tone' => $profile->tone,
            'content_length' => $profile->content_length,
            'language' => $profile->language,
            'audience' => $profile->audience,
            'variables' => [
                'writing_style' => $profile->writing_style,
                'tone' => $profile->tone,
                'content_length' => $profile->content_length,
                'language' => $profile->language,
                'audience' => $profile->audience,
            ],
        ]);

        try {
            $response = $this->client->execute($result->request);
            $article = $this->completeGeneration($result->article, $this->client->outputText($response), [
                'tokens' => $this->client->usage($response),
                'duration_ms' => $this->elapsedMilliseconds($startedAt),
                'model' => data_get($response, 'model', $profile->model),
                'temperature' => $profile->temperature,
            ]);
            $article->update(['status' => AiArticle::STATUS_DRAFT]);
        } catch (Throwable $exception) {
            return $this->failGeneration($result->article, $exception->getMessage(), [
                'duration_ms' => $this->elapsedMilliseconds($startedAt),
                'model' => $profile->model,
                'temperature' => $profile->temperature,
            ]);
        }

        if ($profile->generate_image) {
            $this->generateMainImage($article, $profile);
        }

        return $article->fresh('images');
    }

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

    private function generateMainImage(AiArticle $article, AiPromptProfile $profile): void
    {
        $startedAt = hrtime(true);
        $imageModel = AiPromptProfile::normalizeImageModel($profile->image_model);
        $result = $this->images->prepareMain([
            'title' => $article->title,
            'summary' => $article->excerpt,
            'categories' => $article->categories,
            'style' => $profile->image_style,
        ], [
            'ai_article_id' => $article->id,
            'model' => $imageModel,
            'resolution' => $profile->image_size,
            'quality' => $profile->image_quality,
        ]);

        try {
            $response = $this->client->execute($result->request);
            $binary = base64_decode($this->client->imageBase64($response), true);

            if ($binary === false) {
                throw new \RuntimeException('La imagen generada no tiene una codificación válida.');
            }

            $path = 'ai-images/'.$article->id.'/'.Str::uuid().'.png';
            Storage::disk('local')->put($path, $binary);

            $this->images->completeGeneration($result->image, $response, metrics: [
                'duration_ms' => $this->elapsedMilliseconds($startedAt),
                'model' => $imageModel,
                'resolution' => data_get($response, 'size', $profile->image_size),
                'quality' => data_get($response, 'quality', $profile->image_quality),
                'file_path' => $path,
                'mime_type' => 'image/png',
            ]);
        } catch (Throwable $exception) {
            $this->images->failGeneration($result->image, $exception->getMessage(), [
                'duration_ms' => $this->elapsedMilliseconds($startedAt),
                'model' => $imageModel,
                'resolution' => $profile->image_size,
            ]);
        }
    }

    private function elapsedMilliseconds(int $startedAt): int
    {
        return (int) round((hrtime(true) - $startedAt) / 1_000_000);
    }
}
