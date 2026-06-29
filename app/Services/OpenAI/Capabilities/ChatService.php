<?php

namespace App\Services\OpenAI\Capabilities;

use App\Services\OpenAI\Data\OpenAIRequest;

class ChatService extends AbstractOpenAICapability
{
    /**
     * @param  array<int, array<string, string>>  $messages
     * @param  array<string, mixed>  $options
     */
    public function create(array $messages, array $options = []): OpenAIRequest
    {
        return new OpenAIRequest('chat', 'create', [
            'messages' => $messages,
            ...$options,
        ]);
    }

    /**
     * @param  array<string, mixed>  $variables
     * @param  array<string, mixed>  $options
     */
    public function createFromPrompt(string $prompt, array $variables = [], string $role = 'user', array $options = []): OpenAIRequest
    {
        return $this->create([
            [
                'role' => $role,
                'content' => $this->renderPrompt($prompt, $variables),
            ],
        ], $options);
    }
}
