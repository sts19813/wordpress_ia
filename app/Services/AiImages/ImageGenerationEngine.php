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

        $model = (string) ($options['model'] ?? 'gpt-image-1');
        $resolution = (string) ($options['resolution'] ?? self::DEFAULT_RESOLUTIONS[$type]);
        $seed = $options['seed'] ?? null;
        $prompt = $this->openAI->renderer->render(
            $this->openAI->prompts->get((string) ($options['prompt'] ?? self::PROMPTS[$type])),
            $this->variablesFor($context, $options['variables'] ?? []),
        );

        $request = $this->openAI->images->generate($prompt, array_filter([
            'model' => $model,
            'size' => $resolution,
            'seed' => $seed,
        ], fn (mixed $value) => $value !== null));

        $image = AiImage::query()->create([
            'type' => $type,
            'title' => $context['title'] ?? null,
            'prompt' => $prompt,
            'seed' => $seed,
            'model' => $model,
            'resolution' => $resolution,
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
            'full_response' => is_string($response) ? $response : json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'cost' => $metrics['cost'] ?? null,
            'duration_ms' => $metrics['duration_ms'] ?? null,
            'model' => $metrics['model'] ?? $image->model,
            'resolution' => $metrics['resolution'] ?? $image->resolution,
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
}
