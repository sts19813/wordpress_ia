<?php

namespace App\Services;

use App\Models\AiImage;
use App\Services\AiImages\ImageGenerationEngine;
use App\Services\AiImages\ImageGenerationResult;

class AiImageService
{
    public function __construct(
        private readonly ImageGenerationEngine $engine,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $options
     */
    public function prepareGeneration(string $type, array $context, array $options = []): ImageGenerationResult
    {
        return $this->engine->prepare($type, $context, $options);
    }

    public function prepareMain(array $context, array $options = []): ImageGenerationResult
    {
        return $this->engine->prepareMain($context, $options);
    }

    public function prepareVariant(array $context, array $options = []): ImageGenerationResult
    {
        return $this->engine->prepareVariant($context, $options);
    }

    public function prepareThumbnail(array $context, array $options = []): ImageGenerationResult
    {
        return $this->engine->prepareThumbnail($context, $options);
    }

    public function prepareBanner(array $context, array $options = []): ImageGenerationResult
    {
        return $this->engine->prepareBanner($context, $options);
    }

    public function prepareOg(array $context, array $options = []): ImageGenerationResult
    {
        return $this->engine->prepareOg($context, $options);
    }

    /**
     * @param  array<string, mixed>  $metrics
     */
    public function completeGeneration(AiImage $image, array|string $response, ?string $imageUrl = null, array $metrics = []): AiImage
    {
        return $this->engine->complete($image, $response, $imageUrl, $metrics);
    }

    /**
     * @param  array<string, mixed>  $metrics
     */
    public function failGeneration(AiImage $image, array|string $response, array $metrics = []): AiImage
    {
        return $this->engine->fail($image, $response, $metrics);
    }
}
