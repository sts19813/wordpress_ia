<?php

namespace App\Services;

use App\Models\AiArticle;
use App\Models\AiImage;
use App\Models\AiPromptProfile;
use App\Models\SourcePost;
use App\Models\User;
use App\Services\AiArticles\ArticleGenerationEngine;
use App\Services\AiArticles\ArticleGenerationResult;
use App\Services\OpenAI\OpenAIClient;
use App\Services\OpenAI\OpenAICostCalculator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class AiArticleService
{
    public function __construct(
        private readonly ArticleGenerationEngine $engine,
        private readonly AiImageService $images,
        private readonly OpenAIClient $client,
        private readonly OpenAICostCalculator $costs,
    ) {}

    /**
     * @param  iterable<int, SourcePost>  $sourcePosts
     */
    public function generateDraft(User $user, AiPromptProfile $profile, iterable $sourcePosts): AiArticle
    {
        $article = $this->generateTextDraft($user, $profile, $sourcePosts);

        if ($article->status === AiArticle::STATUS_DRAFT && $profile->generate_image) {
            $this->generateMainImage($article, $profile);
        }

        return $article->fresh('images');
    }

    /**
     * Genera únicamente el texto. La imagen se ejecuta en otra cola para que
     * nunca bloquee ni invalide un borrador que ya quedó listo.
     *
     * @param  iterable<int, SourcePost>  $sourcePosts
     */
    public function generateTextDraft(User $user, AiPromptProfile $profile, iterable $sourcePosts, ?callable $onPrepared = null): AiArticle
    {
        $startedAt = hrtime(true);
        $textModel = AiPromptProfile::normalizeTextModel($profile->model);
        $result = $this->prepareGeneration($sourcePosts, [
            'user_id' => $user->id,
            'ai_prompt_profile_id' => $profile->id,
            'model' => $textModel,
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

        if ($onPrepared) {
            $onPrepared($result->article);
        }

        try {
            $response = $this->client->execute($result->request);
            $usage = $this->client->usage($response);
            $responseModel = (string) data_get($response, 'model', $textModel);
            $article = $this->completeGeneration($result->article, $this->client->outputText($response), [
                'tokens' => $usage,
                'cost' => $this->costs->text($responseModel, $usage),
                'duration_ms' => $this->elapsedMilliseconds($startedAt),
                'model' => $responseModel,
                'temperature' => $profile->temperature,
            ]);
            $article->update(['status' => AiArticle::STATUS_DRAFT]);
        } catch (Throwable $exception) {
            return $this->failGeneration($result->article, $exception->getMessage(), [
                'duration_ms' => $this->elapsedMilliseconds($startedAt),
                'model' => $textModel,
                'temperature' => $profile->temperature,
            ]);
        }

        return $article;
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

    public function generateMainImage(AiArticle $article, AiPromptProfile $profile): AiImage
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
            'output_format' => $profile->image_format ?: 'jpeg',
            'output_compression' => $profile->image_compression ?: 85,
        ]);

        try {
            $response = $this->client->execute($result->request);
            $binary = base64_decode($this->client->imageBase64($response), true);

            if ($binary === false) {
                throw new \RuntimeException('La imagen generada no tiene una codificación válida.');
            }

            $usage = $this->client->usage($response);
            $format = (string) data_get($response, 'output_format', $profile->image_format ?: 'jpeg');
            [$extension, $mimeType] = $this->imageFileType($format);
            $path = 'ai-images/'.$article->id.'/'.Str::uuid().'.'.$extension;
            Storage::disk('local')->put($path, $binary);

            return $this->images->completeGeneration($result->image, $response, metrics: [
                'tokens' => $usage,
                'cost' => $this->costs->image($imageModel, $usage, $profile->image_size, $profile->image_quality),
                'duration_ms' => $this->elapsedMilliseconds($startedAt),
                'model' => $imageModel,
                'resolution' => data_get($response, 'size', $profile->image_size),
                'quality' => data_get($response, 'quality', $profile->image_quality),
                'output_format' => $format,
                'output_compression' => $profile->image_compression ?: 85,
                'file_path' => $path,
                'mime_type' => $mimeType,
            ]);
        } catch (Throwable $exception) {
            return $this->images->failGeneration($result->image, $exception->getMessage(), [
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

    /** @return array{string, string} */
    private function imageFileType(string $format): array
    {
        return match (strtolower($format)) {
            'webp' => ['webp', 'image/webp'],
            'jpeg', 'jpg' => ['jpg', 'image/jpeg'],
            default => ['png', 'image/png'],
        };
    }
}
