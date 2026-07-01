<?php

namespace App\Services\AiImages;

use App\Models\AiImage;
use App\Services\OpenAI\OpenAIService;
use InvalidArgumentException;

class ImageGenerationEngine
{
    private const PROMPTS = [
        AiImage::TYPE_MAIN => 'image-main',
        AiImage::TYPE_VARIANT => 'image-variant',
        AiImage::TYPE_THUMBNAIL => 'image-thumbnail',
        AiImage::TYPE_BANNER => 'image-banner',
        AiImage::TYPE_OG => 'image-og',
    ];

    private const DEFAULT_RESOLUTIONS = [
        AiImage::TYPE_MAIN => '1024x1024',
        AiImage::TYPE_VARIANT => '1024x1024',
        AiImage::TYPE_THUMBNAIL => '512x512',
        AiImage::TYPE_BANNER => '1792x1024',
        AiImage::TYPE_OG => '1200x630',
    ];

    public function __construct(
        private readonly OpenAIService $openAI,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $options
     */
    public function prepare(string $type, array $context, array $options = []): ImageGenerationResult
    {
        if (! isset(self::PROMPTS[$type])) {
            throw new InvalidArgumentException("Tipo de imagen no soportado [{$type}].");
        }

        $model = (string) ($options['model'] ?? config('services.openai.image_model', 'gpt-image-2'));
        $resolution = (string) ($options['resolution'] ?? self::DEFAULT_RESOLUTIONS[$type]);
        $quality = (string) ($options['quality'] ?? 'medium');
        $seed = $options['seed'] ?? null;
        $promptTemplate = isset($options['prompt_text'])
            ? (string) $options['prompt_text']
            : $this->openAI->prompts->get((string) ($options['prompt'] ?? self::PROMPTS[$type]));
        $prompt = $this->openAI->renderer->render($promptTemplate, $this->variablesFor($context, $options['variables'] ?? []));

        $request = $this->openAI->images->generate($prompt, [
            'model' => $model,
            'size' => $resolution,
            'quality' => $quality,
            'output_format' => 'png',
            'n' => 1,
        ]);

        $image = AiImage::query()->create([
            'ai_article_id' => $options['ai_article_id'] ?? null,
            'type' => $type,
            'title' => $context['title'] ?? null,
            'prompt' => $prompt,
            'seed' => $seed,
            'model' => $model,
            'resolution' => $resolution,
            'quality' => $quality,
            'status' => AiImage::STATUS_PENDING,
            'source_context' => $context,
        ]);

        return new ImageGenerationResult($image, $request);
    }

    /**
     * @param  array<string, mixed>  $metrics
     */
    public function complete(AiImage $image, array|string $response, ?string $imageUrl = null, array $metrics = []): AiImage
    {
        $image->update([
            'image_url' => $imageUrl ?? $this->imageUrlFromResponse($response),
            'full_response' => is_string($response) ? $response : json_encode($this->responseForStorage($response), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'cost' => $metrics['cost'] ?? null,
            'duration_ms' => $metrics['duration_ms'] ?? null,
            'model' => $metrics['model'] ?? $image->model,
            'resolution' => $metrics['resolution'] ?? $image->resolution,
            'quality' => $metrics['quality'] ?? $image->quality,
            'file_path' => $metrics['file_path'] ?? $image->file_path,
            'mime_type' => $metrics['mime_type'] ?? $image->mime_type,
            'generation_error' => null,
            'status' => AiImage::STATUS_GENERATED,
        ]);

        return $image;
    }

    /**
     * @param  array<string, mixed>  $metrics
     */
    public function fail(AiImage $image, array|string $response, array $metrics = []): AiImage
    {
        $image->update([
            'full_response' => is_string($response) ? $response : json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'cost' => $metrics['cost'] ?? null,
            'duration_ms' => $metrics['duration_ms'] ?? null,
            'model' => $metrics['model'] ?? $image->model,
            'resolution' => $metrics['resolution'] ?? $image->resolution,
            'generation_error' => is_string($response) ? $response : (data_get($response, 'error.message') ?: 'No fue posible generar la imagen.'),
            'status' => AiImage::STATUS_FAILED,
        ]);

        return $image;
    }

    public function prepareMain(array $context, array $options = []): ImageGenerationResult
    {
        return $this->prepare(AiImage::TYPE_MAIN, $context, $options);
    }

    public function prepareVariant(array $context, array $options = []): ImageGenerationResult
    {
        return $this->prepare(AiImage::TYPE_VARIANT, $context, $options);
    }

    public function prepareThumbnail(array $context, array $options = []): ImageGenerationResult
    {
        return $this->prepare(AiImage::TYPE_THUMBNAIL, $context, $options);
    }

    public function prepareBanner(array $context, array $options = []): ImageGenerationResult
    {
        return $this->prepare(AiImage::TYPE_BANNER, $context, $options);
    }

    public function prepareOg(array $context, array $options = []): ImageGenerationResult
    {
        return $this->prepare(AiImage::TYPE_OG, $context, $options);
    }

    private function variablesFor(array $context, array $extraVariables = []): array
    {
        return [
            'title' => $context['title'] ?? '',
            'summary' => $context['summary'] ?? $context['excerpt'] ?? '',
            'categories' => $context['categories'] ?? [],
            'style' => $context['style'] ?? 'editorial, moderno, sin texto incrustado',
            'variant' => $context['variant'] ?? 'mantener el tema, cambiar composición y encuadre',
            ...$extraVariables,
        ];
    }

    private function imageUrlFromResponse(array|string $response): ?string
    {
        if (is_string($response)) {
            $response = json_decode($response, true) ?: [];
        }

        return data_get($response, 'data.0.url')
            ?: data_get($response, 'url')
            ?: null;
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    private function responseForStorage(array $response): array
    {
        if (data_get($response, 'data.0.b64_json')) {
            data_set($response, 'data.0.b64_json', '[imagen almacenada localmente]');
        }

        return $response;
    }
}
