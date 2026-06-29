<?php

namespace App\Services\AiImages;

use App\Models\AiImage;
use App\Services\OpenAI\Data\OpenAIRequest;

class ImageGenerationResult
{
    public function __construct(
        public readonly AiImage $image,
        public readonly OpenAIRequest $request,
    ) {}
}
