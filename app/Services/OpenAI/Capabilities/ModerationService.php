<?php

namespace App\Services\OpenAI\Capabilities;

use App\Services\OpenAI\Data\OpenAIRequest;

class ModerationService extends AbstractOpenAICapability
{
    /**
     * @param  string|array<int, string>  $input
     * @param  array<string, mixed>  $options
     */
    public function create(string|array $input, array $options = []): OpenAIRequest
    {
        return new OpenAIRequest('moderation', 'create', [
            'input' => $input,
            ...$options,
        ]);
    }

    /**
     * @param  array<string, mixed>  $variables
     * @param  array<string, mixed>  $options
     */
    public function createFromPrompt(string $prompt, array $variables = [], array $options = []): OpenAIRequest
    {
        return $this->create($this->renderPrompt($prompt, $variables), $options);
    }
}
