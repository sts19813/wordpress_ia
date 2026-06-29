<?php

namespace App\Services\OpenAI\Capabilities;

use App\Services\OpenAI\Data\OpenAIRequest;

class ImagesService extends AbstractOpenAICapability
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function generate(string $prompt, array $options = []): OpenAIRequest
    {
        return new OpenAIRequest('images', 'generate', [
            'prompt' => $prompt,
            ...$options,
        ]);
    }

    /**
     * @param  array<string, mixed>  $variables
     * @param  array<string, mixed>  $options
     */
    public function generateFromPrompt(string $prompt, array $variables = [], array $options = []): OpenAIRequest
    {
        return $this->generate($this->renderPrompt($prompt, $variables), $options);
    }
}
